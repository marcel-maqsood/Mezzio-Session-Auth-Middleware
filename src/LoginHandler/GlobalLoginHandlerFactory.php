<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\LoginHandler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Helper\UrlHelper;
use MazeDEV\DatabaseConnector\PersistentPDO;
use MazeDEV\SessionAuth\SessionAuthMiddleware;
use Mezzio\Authentication\Exception;

class GlobalLoginHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {

        $config = $container->get('config');
        $loginHandlingConfig = $config['loginHandling'] ?? null;

        if ($loginHandlingConfig === null) 
        {
            throw new Exception\InvalidConfigException(
                "'loginHandling'-Config is missing in Config, please check our docs: " . $config['authdocs'] . '#user-content-loginHandling'
            );
        }

        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;

        return new GlobalLoginHandler($template, $container->get(PhpSession::class), $container->get(UrlHelper::class), $config['authentication'], $loginHandlingConfig, $container->get(SessionAuthMiddleware::class), $container->get(PersistentPDO::class));
    }
}
