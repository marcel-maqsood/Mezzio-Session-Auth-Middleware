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
    public static $currentRoute;
    private $messages;

    private $tableConfig;

    private $referer;

    private $authConfig;

    private $loginHandlingConfig;

    public static PermissionManager $permissionManager;

    private $fallbackRoute;

    public static $tableOverride = "";
	public static $noAuthRoutes;

    public function __construct(PersistentPDO $persistentPDO, $urlHelper, array $authConfig, array $sessionConfig, array $messages, array $tableConfig, array $loginHandlingConfig, array $noAuthRoutes )
    {
        $this->urlHelper = $urlHelper;
        $this->persistentPDO = $persistentPDO;
        $this->authConfig = &$authConfig;
        $this->repoFields = $authConfig['repository']['fields'];
        $this->securityFields = $authConfig['security']['fields'];
        $this->sessionConfig = $sessionConfig;
        $this->messages = $messages;
        $this->tableConfig = $tableConfig;
        $this->loginHandlingConfig = $loginHandlingConfig;
		self::$noAuthRoutes = $noAuthRoutes;
        self::$permissionManager = new PermissionManager($persistentPDO, $tableConfig, $authConfig);
    }

    function isRefererInternal(ServerRequestInterface $request) : bool
    {
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        return str_starts_with($this->referer, $baseUrl);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->referer = $request->getHeaderLine('Referer');

        if(isset($this->authConfig['username-forwarding']) && $this->authConfig['username-forwarding'] == true)
        {
            $session = $request->getAttribute('session');
            if($session->has(UserInterface::class))
            {
                $request = $request->withAttribute('adminName', $session->get(UserInterface::class)['username']);
                $request = $request->withAttribute('userPath', $session->get(UserInterface::class)['path']);
            }
        }

        if(!$this->isRefererInternal($request))
        {
            $this->referer = null;
        }

        $routeResult = $request->getAttribute(RouteResult::class);
        self::$currentRoute = $routeResult->getMatchedRouteName();

        if(!self::$currentRoute)
		{
			return $handler->handle($request);
		}

		self::$tableOverride = $this->authConfig['repository']['table'];

        if(isset($this->authConfig['repository']['table_override']))
        {
            foreach($this->authConfig['repository']['table_override'] as $routePrefix => $table)
            {
                if(str_starts_with(self::$currentRoute, $routePrefix))
                {
                    self::$tableOverride = $table['tableKey'];
                    break;
                }
            }
        }

        self::$permissionManager->setTablePrefix(self::$tableOverride);
        self::$permissionManager->fetchData();

		foreach (self::$noAuthRoutes as $route => $data)
		{
			if(self::$currentRoute == $route)
			{
				return $handler->handle($request);
			}
		}

        $this->fallbackRoute = self::$permissionManager->getFallbackRoute(self::$currentRoute);

        $isLoginRoute = false;
        $loginTarget = "";
		$resetTarget = "";

        foreach($this->loginHandlingConfig as $key => $data)
		{
			if (self::$currentRoute == $key)
			{
				$loginTarget = $data['destination'];
				$isLoginRoute = true;
				break;
			}
		}

		$redirect = $this->handleAuth($request->getAttribute('session'), $isLoginRoute);

		if($this->errorMessage !== null)
		{
			\setcookie("error", $this->errorMessage, time() + 60, '/');
		}
        if($isLoginRoute)
        {
            if ($redirect === null && self::$permissionManager->userHasPermission($loginTarget))
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

	public static function generateRandomSalt($length = 255)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$randomString = '';
		for ($i = 0; $i < $length; $i++)
		{
			$randomString .= $characters[rand(0, strlen($characters) - 1)];
		}
		return $randomString;
	}

    private function handleAuth(SessionInterface $session, $isLoginRoute = false) : ResponseInterface|null
    {

        //If the given route is not defined as a permission within our database, we redirect it to "home".
        $loginUrl = $this->urlHelper->generate($this->fallbackRoute);
        if (!$session->has(UserInterface::class)) 
        {
			if(!$isLoginRoute)
			{
				$this->errorMessage = $this->messages['error']['logon-required-error'];
			}
            $session->unset(UserInterface::class);
            return new RedirectResponse($this->urlHelper->generate($this->fallbackRoute));
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

		self::$permissionManager->fetchUserPermissions($this->username);

        $user = self::$permissionManager::getUser();

		if($user == null)
		{
			$this->errorMessage = $this->messages['error']['logon-required-error'];
			return false;
		}

		$this->username = $user->{$this->tableConfig[self::$tableOverride]['loginName']};

        $sessionStamp = $user->{$this->securityFields['stamp']};

        if($sessionStamp === null)
        {
            $this->errorMessage = $this->messages['error']['session-set-error'];
            return false;
        }

        $sessionHash = $user->{$this->securityFields['session']};

        if($currentSessionHash !== $sessionHash)
        {
            $this->errorMessage = $this->messages['error']['another-device-logon-error'];
            return false;
        }

        $sessionMaxTime = (new \DateTime($sessionStamp))->add(new \DateInterval('PT' . $this->sessionConfig['gc_lifetime'] . 'S'))->getTimestamp();
        $currentTime = (new \DateTime())->getTimestamp();

        //we must check if the session is still alive by checking if timestamp is inside allowed time window here, as the request's session might have been altered.
        if($sessionMaxTime < $currentTime)
        {
            $this->errorMessage = $this->messages['error']['session-expired-error'];
            return false;
        }

		if($sessionMaxTime - $currentTime < 1800)
		{
			$this->persistentPDO->update(
				$this->tableConfig[self::$tableOverride]['tableName'],
				[
					$this->securityFields['stamp'] => date("Y-m-d H:i:s", $currentTime)
				],
				$this->tableConfig[self::$tableOverride]['identifier'] . " = '" . $user->{$this->tableConfig[self::$tableOverride]['identifier']} . "'",
				false
			);
		}

		$session->set(DefaultUser::class, [
			'username' => $user->{$this->tableConfig[self::$tableOverride]['loginName']},
            'path' => self::$tableOverride,
			'roles'    => [],
			'details'  => [],
		]);

		$session->regenerate();

        if(!self::$permissionManager->userHasPermission(self::$currentRoute))
        {
            if($this->referer != null)
            {
                return new RedirectResponse($this->referer);
            }
            //"return false" will redirect towards "/", which happens if the user doesn't have the permission for the requested route.
            //best case, we should redirect them to their respective Dashboard and maybe display a permission error.
            return new RedirectResponse($this->urlHelper->generate($this->fallbackRoute));
        }

        //the request contains our current session fingerprint so we letting it pass
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

		foreach ($this->repoFields['identities'] as $identityField)
		{
			$this->userConditions[] =
			[
                'field' => $identityField,
				'operator' => '=',
				'queue' => $this->username,
				'logicalOperator' => 'OR'
			];
		}
        return true;
    }

	public static function getUserName()
	{
		return self::$username;
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
