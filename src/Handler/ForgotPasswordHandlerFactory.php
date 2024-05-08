<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Handler;

use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use MazeDEV\DatabaseConnector\PersistentPDO;

class ForgotPasswordHandlerFactory
{
    public function __invoke(ContainerInterface $container) : ForgotPasswordHandler
    {
        return new ForgotPasswordHandler($container->get(PersistentPDO::class));
    }
}
