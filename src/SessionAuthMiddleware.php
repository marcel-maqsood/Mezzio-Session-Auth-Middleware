<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Session\SessionInterface;         
use Mezzio\Authentication\UserInterface;
use MazeDEV\DatabaseConnector\PersistentPDO;
use Mezzio\Authentication\DefaultUser;
use Mezzio\Router\RouteResult;
use Laminas\Diactoros\Response\RedirectResponse;

class SessionAuthMiddleware implements MiddlewareInterface
{
    private $urlHelper;
    private $userConditions;
    private $repoFields;
    private $securityFields;
    private $username;
    private $persistentPDO;
    private $sessionConfig;
    private $errorMessage;
    private $currentRoute;
    private $messages;

    private $tableConfig;

    private $referer;

    private $authConfig;

    private PermissionManager $permissionManager;

    public function __construct(PersistentPDO $persistentPDO, $urlHelper, array $authConfig, array $sessionConfig, array $messages, array $tableConfig)
    {
        $this->urlHelper = $urlHelper;
        $this->persistentPDO = $persistentPDO;
        $this->authConfig = $authConfig;
        $this->repoFields = $authConfig['repository']['fields'];
        $this->securityFields = $authConfig['security']['fields'];
        $this->sessionConfig = $sessionConfig;
        $this->messages = $messages;
        $this->tableConfig = $tableConfig;
        $this->permissionManager = new PermissionManager($persistentPDO, $tableConfig, $authConfig);
    }

    function isRefererInternal(ServerRequestInterface $request) : bool
    {
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        return str_starts_with($this->referer, $baseUrl);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->referer = $request->getHeaderLine('Referer');
        if(!$this->isRefererInternal($request))
        {
            $this->referer = null;
        }

        $routeResult = $request->getAttribute(RouteResult::class);
        $this->currentRoute = $routeResult->getMatchedRouteName();

        if(isset($this->authConfig['repository']['table_override']))
        {
            foreach($this->authConfig['repository']['table_override'] as $routePrefix => $table)
            {
                if(str_starts_with($this->currentRoute, $routePrefix))
                {
                    $this->authConfig['repository']['table'] = $table;
                    break;
                }
            }
        }
        $this->permissionManager->setTablePrefix($this->authConfig['repository']['table']);
        $this->permissionManager->fetchData();
        
        $redirect = $this->handleAuth($request->getAttribute('session'));

        if($this->errorMessage !== null)
        {
            \setcookie("error", $this->errorMessage, time() + 60, '/');
        }

        $isLoginRoute = false;
        $loginTarget = "";
        switch($this->currentRoute)
        {
            case "managementLogin":
                $loginTarget = 'managementPage';
                $isLoginRoute = true;
            break;

            case "adminLogin":
                $loginTarget = 'adminPage';
                $isLoginRoute = true;
            break;
        }

        if($isLoginRoute)
        {
            if ($redirect === null && $this->permissionManager->userHasPermission($loginTarget))
            {
                return new RedirectResponse($this->urlHelper->generate($loginTarget));
            }
            return $handler->handle($request);
        }
            
        if($redirect !==  null)
        {
            //Always a RedirectResponse towards our login form, as this only happens if the request isn't authorized.
            return $redirect;
        }

        $request = $request->withAttribute('adminName', $this->username);
        return $handler->handle($request);
    }

    private function handleAuth(SessionInterface $session
    ) : ResponseInterface|null
    {
        //If the given route is not defined as a permission within our database, we redirect it to "home".
        $fallback = $this->permissionManager->getFallbackRoute($this->currentRoute) ?? 'home';


        $loginUrl = $this->urlHelper->generate($fallback);
        if (!$session->has(UserInterface::class)) 
        {
            $this->errorMessage = $this->messages['error']['admin-logon-required-error'];

            $session->unset(UserInterface::class);
            return new RedirectResponse($loginUrl);
        }

        $sessionCheckResult = $this->checkAndSetSession($session);
        if($sessionCheckResult instanceof RedirectResponse)
        {
            return $sessionCheckResult;
        }
        else if (!$sessionCheckResult)
        {
            $session->unset(UserInterface::class);
            return new RedirectResponse($loginUrl);
        }

        return null;
    }

    private function checkAndSetSession(SessionInterface $session) : bool|RedirectResponse
    {
        $currentSessionHash = $this->getCurrentSessionHash($session);
        if(!$currentSessionHash)
        {
            $this->errorMessage = $this->messages['error']['session-detail-error'];
            return false;
        }

        $dbRow = $this->persistentPDO->get('*', $this->tableConfig[$this->authConfig['repository']['table']]['tableName'], $this->userConditions);

        $sessionStamp = $dbRow == null ? null : $dbRow->{$this->securityFields['stamp']};

        if($sessionStamp === null)
        {
            $this->errorMessage = $this->messages['error']['session-set-error'];
            //there can't be a session without a session's start time.
            return false;
        }

        $sessionHash = $dbRow == null ? null : $dbRow->{$this->securityFields['session']};

        if($currentSessionHash !== $sessionHash)
        {
            $this->errorMessage = $this->messages['error']['another-device-logon-error'];
            return false;
        }

        $sessionMaxTime = (new \DateTime($sessionStamp))->add(new \DateInterval('PT' . $this->sessionConfig['gc_lifetime'] . 'S'));
        $currentTime = new \DateTime();

        //we must check if the session is still alive by checking if timestamp is inside allowed time window here, as the request's session might have been altered.
        if($sessionMaxTime < $currentTime)
        {
            $this->errorMessage = $this->messages['error']['session-expired-error'];
            return false;
        }

        $this->permissionManager->fetchUserPermissions($this->username);

        if(!$this->permissionManager->userHasPermission($this->currentRoute))
        {
            if($this->referer != null)
            {
                return new RedirectResponse($this->referer);
            }
            //"return false" will redirect towards "/", which happens if the user doesn't have the permission for the requested route.
            //best case, we should redirect them to their respective Dashboard and maybe display a permission error.
            $fallback = $this->permissionManager->getFallbackRoute($this->currentRoute) ?? 'home';
            return new RedirectResponse($this->urlHelper->generate($fallback));
        }

        //the request contains our current session fingerprint so we letting it pass
        $session->set(DefaultUser::class, [
            'username' => $this->username,
            'roles'    => [],
            'details'  => [],
        ]);

        $session->regenerate();
        return true;
    }

    /**
     * Tries to get the username in session
     * 
     * @return bool - Is a username present?
     */
    private function setUsername(SessionInterface $session) : bool
    {
        $user = $session->get(UserInterface::class);
        if($user === null)
        {
            //this isn't a valid user
            return false;
        }

        $this->username = $user['username'];
        $this->userConditions = [
            $this->repoFields['identity'] => [
                'operator' => '=',
                'queue' => $this->username,
            ]
        ];
        return true;
    }

    public function getCurrentSessionHash(SessionInterface $session) : string|bool
    {
        if(!$this->setUsername($session))
        {
            return false;
        }
        if($this->username === null || $this->username === "")
        {
            //this isn't a valid user
            return false;
        }

        $fingerprint = implode(
            [
                \date_default_timezone_get(),
                'IP: ' . $_SERVER['REMOTE_ADDR'] . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '') . (isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : '' ),
                'AGENT: ' . $_SERVER['HTTP_USER_AGENT'],
                $this->authConfig['security']['salt']
            ]
        );

        return hash($this->authConfig['security']['algo'], $fingerprint);
    }
}
