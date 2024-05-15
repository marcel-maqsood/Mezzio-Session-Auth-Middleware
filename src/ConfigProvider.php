<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth;

class ConfigProvider
{
    /**
     * Return the configuration array.
     */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * Returns the container dependencies.
     */
    public function getDependencies() : array
    {
        return [
            'aliases' => [

            ],
            'factories' => [
                SessionAuthMiddleware::class => SessionAuthMiddlewareFactory::class,
                Handler\GlobalLoginHandler::class => Handler\GlobalLoginHandlerFactory::class,
                Handler\LogoutHandler::class => Handler\LogoutHandlerFactory::class,
				Handler\ForgotPasswordHandler::class => Handler\ForgotPasswordHandlerFactory::class,
				Handler\CreateAccountHandler::class => Handler\CreateAccountHandlerFactory::class,
            ],
        ];
    }
}
