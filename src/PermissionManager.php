<?php


namespace MazeDEV\SessionAuth;

use MazeDEV\DatabaseConnector\PersistentPDO;

class PermissionManager
{
    private array $mergedPermissions = [];

    private array $allPermissions;

    private string $prefix = "";

    private PersistentPDO $persistentPDO;
    private array $tableConfig;
    private array $authConfig;

    public function __construct(PersistentPDO $persistentPDO, array $tableConfig, array $authConfig)
    {
        $this->persistentPDO = $persistentPDO;
        $this->tableConfig = $tableConfig;
        $this->authConfig = $authConfig;
    }

    public function fetchData()
    {
        $allPermissions = $this->persistentPDO->getAll($this->tableConfig[$this->getTablePrefix() . 'permissions']['tableName']);

        if($allPermissions == null)
        {
            return;
        }

        foreach ($allPermissions as $permission)
        {
            if(isset($allPermissions[$permission[$this->tableConfig[$this->getTablePrefix() . 'permissions']['noPermFallback']]]))
            {
                $this->allPermissions[$permission[$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']]] = [
                    $this->tableConfig[$this->getTablePrefix() . 'permissions']['value'] =>
                    $allPermissions[$permission[$this->tableConfig[$this->getTablePrefix() . 'permissions']['noPermFallback']]][$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']],
                ];
            }
            else
            {
                $this->allPermissions[$permission[$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']]] = [
                    $this->tableConfig[$this->getTablePrefix() . 'permissions']['value'] => null
                ];
            }

            $this->allPermissions[$permission[$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']]] += [
                $this->tableConfig[$this->getTablePrefix() . 'permissions']['allowBypass'] =>
                    $allPermissions[$permission[$this->tableConfig[$this->getTablePrefix() . 'permissions']['identifier']]][$this->tableConfig[$this->getTablePrefix() . 'permissions']['allowBypass']]
            ];
        }
    }

    public function setTablePrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    public function getTablePrefix()
    {
        if($this->prefix == "")
        {
            return "";
        }
        return $this->prefix . "_";
    }

    public function fetchUserPermissions($username): void
    {
        $userId = $this->getUserId($username);

        $groupJoins = [
            [
                'table' => $this->tableConfig[$this->getTablePrefix() . 'group_relation']['tableName'],
                'on' => $this->tableConfig[$this->prefix ]['tableName'] . '.' . $this->tableConfig[$this->prefix ]['identifier'] . ' = ' . $this->tableConfig[$this->getTablePrefix() . 'group_relation']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'group_relation']['login_identifier']
            ],
            [
                'table' => $this->tableConfig[$this->getTablePrefix() . 'groups']['tableName'],
                'on' => $this->tableConfig[$this->getTablePrefix() . 'group_relation']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'group_relation']['group_identifier'] . ' = ' . $this->tableConfig[$this->getTablePrefix() . 'groups']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'groups']['identifier']
            ],
            [
                'table' => $this->tableConfig[$this->getTablePrefix() . 'group_permission_relation']['tableName'],
                'on' => $this->tableConfig[$this->getTablePrefix() . 'groups']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'groups']['identifier'] . ' = ' . $this->tableConfig[$this->getTablePrefix() . 'group_permission_relation']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'group_permission_relation']['group_identifier']
            ]
        ];

        $groupDetails = [
            'groups' => [
                [
                    'for' => $this->tableConfig[$this->getTablePrefix() . 'group_permission_relation']['permission_identifier'],
                    'as' => 'permissions',
                ]
            ],
            'identifier' => $this->tableConfig[$this->getTablePrefix() . 'groups']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'groups']['identifier']
        ];

        $permissions = $this->persistentPDO->getAll(
            $this->tableConfig[$this->prefix]['tableName'], // Tabelle
            $this->tableConfig[$this->prefix]['identifier'] . ' = ' . $userId,
            $groupJoins,
            $groupDetails,
            '',
            [],
            false
        );

        if($permissions == null || $permissions[$userId]['permissions'] == null)
        {
            return;
        }

        $permissionIds = explode(",", $permissions[$userId]['permissions']);

        $allIdsWhereStatement = "";
        foreach ($permissionIds as $permissionId)
        {
            if($permissionId == null || $permissionId == "")
            {
                continue;
            }

            if($allIdsWhereStatement == "")
            {
                $allIdsWhereStatement .= $this->tableConfig[$this->getTablePrefix() . 'permissions']['identifier'] . ' = ' . $permissionId;
                continue;
            }

            $allIdsWhereStatement .= ' OR ' . $this->tableConfig[$this->getTablePrefix() . 'permissions']['identifier'] . ' = ' . $permissionId;
        }

        $groups = $this->persistentPDO->getAll(
            $this->tableConfig[$this->getTablePrefix() . 'permissions']['tableName'],
            $allIdsWhereStatement,
            [],
            [],
            '',
            [],
            false
        );

        foreach ($groups as $group)
        {
            $this->mergedPermissions[] = $group[$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']];
        }
    }

    public function userHasPermission(string $permission): bool
    {
        if($this->isBypassable($permission) 
        ||  (
                isset($this->authConfig['allowWildcard']) 
                && $this->authConfig['allowWildcard'] === true 
                && in_array('*', $this->mergedPermissions)
            )
        )
        {
            return true;
        }
        return in_array($permission, $this->mergedPermissions);
    }

    /**
     * Returns if a permission is bypassable: if the permission is not defined, we handle it as not bypassable.
     * @param string $permission
     * @return false|mixed
     */
    public function isBypassable(string $permission)
    {
        if(isset($this->allPermissions[$permission]))
        {
            return (bool) $this->allPermissions[$permission][$this->tableConfig[$this->getTablePrefix() . 'permissions']['allowBypass']];
        }
        return false;
    }

    public function getFallbackRoute(string $permission)
    {

        if(isset($this->allPermissions[$permission]))
        {
            return $this->allPermissions[$permission][$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']];
        }

        return null;
    }

    public function getUserId($loginName) : string
    {
        //We could retrieve this id after successful login and store it as a cookie but there is no guarantee that this cookie is present and untouched.
        return $this->persistentPDO->get(
            $this->tableConfig[$this->prefix]['identifier'],
            $this->tableConfig[$this->prefix]['tableName'],
            [
                $this->tableConfig[$this->prefix]['loginName'] =>
                    [
                        'operator' => '=',
                        'queue' => $loginName,
                    ]
            ]
        );
    }

}
