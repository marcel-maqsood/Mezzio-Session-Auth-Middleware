<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Repository;

use Mezzio\Authentication\Exception;
use Mezzio\Authentication\UserInterface;
use Psr\Container\ContainerInterface;
use MazeDEV\DatabaseConnector\PersistentPDO;

class PDORepositoryFactory
{
    public function __invoke(ContainerInterface $container): PDORepository
    {
        $config = $container->get('config');
        $authConfig = $config['authentication'] ?? null;
        $repositoryConfig = $config['authentication']['repository'] ?? null;
        
        if ($authConfig === null) 
        {
            throw new Exception\InvalidConfigException(
                "'authentication' Config is missing, please check our docs: " . $config['docs'] . '#auth'
            );
        }

        if ($repositoryConfig === null) 
        {
            throw new Exception\InvalidConfigException(
                "'repository' Config is missing, please check our docs: " . $config['docs'] . '#auth'
            );
        }
        if (! isset($repositoryConfig['table'])) 
        {
            throw new Exception\InvalidConfigException(
                "'table' value not set in 'repository'-Config, please check our docs: " . $config['docs'] . '#auth'
            );
        }
        if ($repositoryConfig['fields'] === null) 
        {
            throw new Exception\InvalidConfigException(
                "'fields'-Config is missing in 'repository'-Config, please check our docs: " . $config['docs'] . '#auth'
            );
        }
        if (! isset($repositoryConfig['fields']['identities']))
        {
            throw new Exception\InvalidConfigException(
                "'identities'-value not set in 'repository'-'fields'-Config, please check our docs: " . $config['docs'] . '#auth'
            );
        }
        if (! isset($repositoryConfig['fields']['password'])) 
        {
            throw new Exception\InvalidConfigException(
                "'password'-value not set in 'repository'-'fields'-Config, please check our docs: " . $config['docs'] . '#auth'
            );
        }

        $tableConfig = $config['tables'] ?? null;
        if($tableConfig === null)
        {
            throw new Exception\InvalidConfigException(
                "'tables' Config is missing, please check our docs: " . $config['docs'] . '#user-content-tables'
            );
        }

        $user = $container->get(UserInterface::class);

        return new PDORepository($container->get(PersistentPDO::class), $authConfig, $tableConfig, $user);
    }
}
