<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Chubbyphp\Container\MinimalContainer;
use DI\Container as PHPDIContainer;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\ServiceManager\ServiceManager;
use MazeDEV\SessionAuth\SessionAuthMiddleware;
use Mezzio\LaminasView\LaminasViewRenderer;
use Mezzio\Plates\PlatesRenderer;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\LaminasRouter;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplateRendererInterface;
use Mezzio\Twig\TwigRenderer;
use Pimple\Psr11\Container as PimpleContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Uri;                      
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Session\SessionInterface;         
use Mezzio\Authentication\UserInterface;     
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Mezzio\Helper\UrlHelper;
use MazeDEV\DatabaseConnector\PersistentPDO;
use Mezzio\Authentication\DefaultUser;

class GlobalLoginHandler implements RequestHandlerInterface
{
    private const REDIRECT_ATTRIBUTE = 'authentication:redirect';

    /** @var PhpSession */
    private $session;

    /** @var TemplateRendererInterface */
    private $renderer;

    private $urlHelper;

    private $config;
    private $loginHandlingConfig;
    private $tableConfig;
    private $repoFields;
    private $securityFields;
    private $sessionAuth;
    private $persistentPDO;
    private $currentRoute;

    private $loginTitleName;

    private $loginUrl;
	private $resetTarget;

    public function __construct(TemplateRendererInterface $renderer, PhpSession $session, UrlHelper $urlHelper, $config, $loginHandlingConfig, $tableConfig, $sessionAuth, PersistentPDO $persistentPDO)
    {
        $this->renderer = $renderer;
        $this->session = $session;
        $this->urlHelper = $urlHelper;
        $this->config = $config;
        $this->loginHandlingConfig = $loginHandlingConfig;
        $this->repoFields = $config['repository']['fields'];
        $this->securityFields = $config['security']['fields'];
        $this->sessionAuth = $sessionAuth;
        $this->persistentPDO = $persistentPDO;
        $this->tableConfig = $tableConfig;
    }
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $session  = $request->getAttribute('session');
        $session->unset(UserInterface::class);

        $routeResult = $request->getAttribute(RouteResult::class);
        $this->currentRoute = $routeResult->getMatchedRouteName();

        foreach($this->loginHandlingConfig as $key => $data)
        {
            if($this->currentRoute == $key)
            {
                $this->loginTitleName = $data['name'];
                $this->loginUrl = $this->urlHelper->generate($data['destination']);
				$this->resetTarget = $data['resetDestination'];
                break;
            }
        }   

        // Handle submitted credentials
        if ($request->getMethod() === 'POST') 
        {
            return $this->handleLogin($request, $session);
        }

        // Display initial login form
        $error = null;
        if(isset($_COOKIE['error']))
        {
            $error = $_COOKIE['error'];
            setcookie('error', '', time() - 3600, '/');
        }

        return new HtmlResponse($this->renderer->render(
            'app::Login',
            $error != null ?
				[ 'error' => $error, 'handler' => $this->loginTitleName, 'resetDestination' => $this->resetTarget] :
				['handler' => $this->loginTitleName, 'resetDestination' => $this->resetTarget]
        ));
    }
 
    private function handleLogin(
        ServerRequestInterface $request,
        SessionInterface $session
    ) : ResponseInterface 
    {
        $user = $this->session->authenticate($request);
        if ($user) 
        {
            //This request now has a valid auth and thus, we gonna handle it.
            $updates = [
                $this->securityFields['session'] => $this->sessionAuth->getCurrentSessionHash($session),
                $this->securityFields['stamp'] => date("Y-m-d H:i:s")
            ];

            //Update sessionstamp and sessionhash in db to logout any other device that was logged in
			$userConditions = [];

			foreach ($this->repoFields['identities'] as $identityField)
			{
				$userConditions[$identityField] =
				[
					'operator' => '=',
					'queue' => $user->getIdentity(),
					'logicalOperator' => 'OR'
				];
			}

            $table = SessionAuthMiddleware::$tableOverride;
            if($table == "" || $table == null)
            {
                $table = $this->reporsitoryConfig['table'];
            }

            if(!$this->persistentPDO->update($this->tableConfig[$table]['tableName'], $updates, $userConditions, false))
            {
                //There was an issue with our db communication, thus we won't auth this request, it will be send back to our login form.
                $session->unset(UserInterface::class);
                return new HtmlResponse($this->renderer->render(
                    'app::Login',
                    ['error' => 'Looks like we ran into some issues; please try again.', 'handler' => $this->loginTitleName, 'resetDestination' => $this->resetTarget]
                ));
            }

            return new RedirectResponse($this->loginUrl);
        }
 
        // Login failed
        return new HtmlResponse($this->renderer->render(
            'app::Login',
            ['error' => 'Invalid credentials; please try again', 'handler' => $this->loginTitleName, 'resetDestination' => $this->resetTarget]
        ));
    }
}
