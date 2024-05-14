<?php

declare(strict_types=1);

namespace MazeDEV\SessionAuth\Repository;

use MazeDEV\SessionAuth\SessionAuthMiddleware;
use Mezzio\Authentication\Exception;
use Mezzio\Authentication\UserInterface;
use Mezzio\Authentication\UserRepositoryInterface;
use PDO;
use PDOException;
use Webmozart\Assert\Assert;
use MazeDEV\DatabaseConnector\PersistentPDO;

use function password_verify;
use function sprintf;
use function strpos;


class PDORepository implements UserRepositoryInterface
{
    private PDO $pdo;
    private PersistentPDO $persistentPDO;

    private $authConfig;
    private $config;

    private $tableConfig;

    private $userFactory;

    public function __construct(
        PersistentPDO $persistentPDO,
        array $authConfig,
        array $tableConfig,
        callable $userFactory
    ) 
    {
        $this->persistentPDO = $persistentPDO;
        $this->pdo = $persistentPDO->getPDO();
        $this->reporsitoryConfig = $authConfig['repository'];
        $this->authConfig = $authConfig;
        $this->tableConfig = $tableConfig;

        // Provide type safety for the composed user factory.
        $this->userFactory = static function (
            string $identity,
            array $roles = [],
            array $details = []
        ) use ($userFactory): UserInterface 
        {
            return $userFactory($identity, $roles, $details);
        };
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(string $username, ?string $password = null, ?string $currentRoute = ""): ?UserInterface
    {

		$conditions = [];

		foreach ($this->reporsitoryConfig['fields']['identities'] as $identityField)
		{
			$conditions[$identityField] =
			[
				'operator' => '=',
				'queue' => $username,
				'logicalOperator' => 'OR'
			];
		}

        $table = SessionAuthMiddleware::$tableOverride;
        if($table == "" || $table == null)
        {
            $table = $this->reporsitoryConfig['table'];
        }

        $passwordHash = $this->persistentPDO->get(
            $this->reporsitoryConfig['fields']['password'],
            $this->tableConfig[$table]['tableName'],
            $conditions,
            [],
            [],
            false
        );

        if($passwordHash === null || $passwordHash === "")
        {
            //Username not in our db
            return null;
        }

        if (password_verify(($password ?? '') . $this->authConfig['security']['salt'], $passwordHash)) 
        {
            return ($this->userFactory)(
                $username,
                $this->getUserRoles($username),
                $this->getUserDetails($username)
            );
        }
        return null;
    }

    /**
     * Get the user roles if present.
     *
     * @psalm-return list<string>
     */
    protected function getUserRoles(string $identity): array
    {
        if (! isset($this->reporsitoryConfig['sql_get_roles'])) 
        {
            return [];
        }

        if (false === strpos($this->reporsitoryConfig['sql_get_roles'], ':identity')) 
        {
            throw new Exception\InvalidConfigException(
                'The sql_get_roles configuration setting must include an :identity parameter'
            );
        }

        try 
        {
            $stmt = $this->pdo->prepare($this->reporsitoryConfig['sql_get_roles']);
        } 
        catch (PDOException $e) 
        {
            throw new Exception\RuntimeException(sprintf(
                'Error preparing retrieval of user roles: %s',
                $e->getMessage()
            ));
        }
        if (false === $stmt) 
        {
            throw new Exception\RuntimeException(sprintf(
                'Error preparing retrieval of user roles: unknown error'
            ));
        }
        $stmt->bindParam(':identity', $identity);

        if (! $stmt->execute()) 
        {
            return [];
        }

        $roles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $role) 
        {
            $roles[] = (string) $role[0];
        }
        return $roles;
    }

    protected function getUserDetails(string $identity): array
    {
        if (! isset($this->reporsitoryConfig['sql_get_details'])) 
        {
            return [];
        }

        Assert::string($this->reporsitoryConfig['sql_get_details']);

        if (false === strpos($this->reporsitoryConfig['sql_get_details'], ':identity')) 
        {
            throw new Exception\InvalidConfigException(
                'The sql_get_details configuration setting must include a :identity parameter'
            );
        }

        try 
        {
            $stmt = $this->pdo->prepare($this->reporsitoryConfig['sql_get_details']);
        } 
        catch (PDOException $e) 
        {
            throw new Exception\RuntimeException(sprintf(
                'Error preparing retrieval of user details: %s',
                $e->getMessage()
            ));
        }
        if (false === $stmt) 
        {
            throw new Exception\RuntimeException(sprintf(
                'Error preparing retrieval of user details: unknown error'
            ));
        }
        $stmt->bindParam(':identity', $identity);

        if (! $stmt->execute()) 
        {
            return [];
        }

        $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        return $userDetails;
    }
}
