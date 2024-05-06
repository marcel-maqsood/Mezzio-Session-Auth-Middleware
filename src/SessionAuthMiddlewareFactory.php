<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth;

use Psr\Container\ContainerInterface;
use Mezzio\Authentication\Session\PhpSession;
use MazeDEV\DatabaseConnector\PersistentPDO;
use Mezzio\Helper\UrlHelper;
use Mezzio\Authentication\Exception;

class SessionAuthMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : SessionAuthMiddleware
    {

        $config = $container->get('config');

        $authenticationConfig = $config['authentication'] ?? null;

        if($authenticationConfig === null)
        {
            throw new Exception\InvalidConfigException(
                "'authentication' Config is missing, please check our docs: " . $config['authdocs'] . '#user-content-auth'
            );
        }

        $securityConfig = $config['authentication']['security'] ?? null;

        if ($securityConfig === null) 
        {
            throw new Exception\InvalidConfigException(
                "'security'-Config is missing in 'authentication'-Config, please check our docs: " . $config['authdocs'] . '#user-content-auth'
            );
        }

        $messages = $config['messages'] ?? null;

        if ($messages === null) 
        {
            throw new Exception\InvalidConfigException(
                "'messages'-Config is missing in Config, please check our docs: " . $config['authdocs'] . '#user-content-messages'
            );
        }

        $tableConfig = $config['tables'] ?? null;
        if ($tableConfig === null) 
        {
            throw new Exception\InvalidConfigException(
                "'tables'-Config is missing in Config, please check our docs: " . $config['authdocs'] . '#user-content-tables'
            );
        }

        $loginHandlingConfig = $config['loginHandling'] ?? null;

        if ($loginHandlingConfig === null)
        {
            throw new Exception\InvalidConfigException(
                "'loginHandling'-Config is missing in Config, please check our docs: " . $config['authdocs'] . '#user-content-loginHandling'
            );
        }

        return new SessionAuthMiddleware($container->get(PersistentPDO::class), $container->get(UrlHelper::class), $authenticationConfig, $config['session']['config'], $messages, $tableConfig, $loginHandlingConfig);
    }
}
