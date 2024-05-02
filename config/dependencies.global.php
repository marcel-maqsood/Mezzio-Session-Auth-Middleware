<?php

declare(strict_types=1);

use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepository\PdoDatabase;
use Mezzio\Authentication\UserRepository\PdoDatabaseFactory;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserRepositoryInterface;

use MazeDEV\DatabaseConnector\PersistentPDO;
use MazeDEV\DatabaseConnector\PersistentPDOFactory;
use MazeDEV\SessionAuth\Repository\PDORepository;
use MazeDEV\SessionAuth\Repository\PDORepositoryFactory;

return [
    'dependencies' => 
    [
        'aliases' => 
        [
            AuthenticationInterface::class => PhpSession::class,
            UserRepositoryInterface::class => PDORepository::class,
        ],
        'invokables' => [],
        'factories' => 
        [
            PersistentPDO::class => PersistentPDOFactory::class,
            PDORepository::class => PDORepositoryFactory::class,
            Mezzio\Session\SessionMiddleware::class => Mezzio\Session\SessionMiddlewareFactory::class
        ],
    ],
];
