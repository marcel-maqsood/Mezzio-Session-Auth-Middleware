<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\LogoutHandler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use Mezzio\Helper\UrlHelper;

class LogoutHandlerFactory
{
    public function __invoke(ContainerInterface $container) : LogoutHandler
    {
        return new LogoutHandler($container->get(UrlHelper::class));
    }
}
