<?php


namespace MazeDEV\SessionAuth;

use MazeDEV\DatabaseConnector\PersistentPDO;

class PermissionManager
{
    private array $mergedPermissions = [];

    private array $allPermissions;

    private PersistentPDO $persistentPDO;
    private array $tableConfig;

    public function __construct(PersistentPDO $persistentPDO, array $tableConfig)
    {
        $this->persistentPDO = $persistentPDO;
        $this->tableConfig = $tableConfig;

        $allPermissions = $persistentPDO->getAll($this->tableConfig['permissions']['tableName']);

        if($allPermissions == null)
        {
            return;
        }

        foreach ($allPermissions as $permission)
        {
            if(isset($allPermissions[$permission[$this->tableConfig['permissions']['noPermFallback']]]))
            {
                $this->allPermissions[$permission[$this->tableConfig['permissions']['value']]] = [
                    $this->tableConfig['permissions']['value'] =>
                    $allPermissions[$permission[$this->tableConfig['permissions']['noPermFallback']]][$this->tableConfig['permissions']['value']],
                ];
            }
            else
            {
                $this->allPermissions[$permission[$this->tableConfig['permissions']['value']]] = [
                    $this->tableConfig['permissions']['value'] => null
                ];
            }

            $this->allPermissions[$permission[$this->tableConfig['permissions']['value']]] += [
                $this->tableConfig['permissions']['allowBypass'] =>
                    $allPermissions[$permission[$tableConfig['permissions']['identifier']]][$this->tableConfig['permissions']['allowBypass']]
            ];
        }
    }

    public function fetchUserPermissions($username): void
    {
        $userId = $this->getUserId($username);

        $groupJoins = [
            [
                'table' => $this->tableConfig['login_group_relation']['tableName'],
                'on' => $this->tableConfig['login']['tableName'] . '.' . $this->tableConfig['login']['identifier'] . ' = ' . $this->tableConfig['login_group_relation']['tableName'] . '.' . $this->tableConfig['login_group_relation']['login_identifier']
            ],
            [
                'table' => $this->tableConfig['groups']['tableName'],
                'on' => $this->tableConfig['login_group_relation']['tableName'] . '.' . $this->tableConfig['login_group_relation']['group_identifier'] . ' = ' . $this->tableConfig['groups']['tableName'] . '.' . $this->tableConfig['groups']['identifier']
            ],
            [
                'table' => $this->tableConfig['group_permission_relation']['tableName'],
                'on' => $this->tableConfig['groups']['tableName'] . '.' . $this->tableConfig['groups']['identifier'] . ' = ' . $this->tableConfig['group_permission_relation']['tableName'] . '.' . $this->tableConfig['group_permission_relation']['group_identifier']
            ]
        ];

        $groupDetails = [
            'groups' => [
                [
                    'for' => $this->tableConfig['group_permission_relation']['permission_identifier'],
                    'as' => 'permissions',
                ]
            ],
            'identifier' => $this->tableConfig['groups']['tableName'] . '.' . $this->tableConfig['groups']['identifier']
        ];

        $permissions = $this->persistentPDO->getAll(
            $this->tableConfig['login']['tableName'], // Tabelle
            $this->tableConfig['login']['identifier'] . ' = ' . $userId,
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
                $allIdsWhereStatement .= $this->tableConfig['permissions']['identifier'] . ' = ' . $permissionId;
                continue;
            }

            $allIdsWhereStatement .= ' OR ' . $this->tableConfig['permissions']['identifier'] . ' = ' . $permissionId;
        }

        $groups = $this->persistentPDO->getAll(
            $this->tableConfig['permissions']['tableName'],
            $allIdsWhereStatement,
            [],
            [],
            '',
            [],
            false
        );

        foreach ($groups as $group)
        {
            $this->mergedPermissions[] = $group[$this->tableConfig['permissions']['value']];
        }
    }

    public function userHasPermission(string $permission): bool
    {
        if($this->isBypassable($permission))
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
            return (bool) $this->allPermissions[$permission][$this->tableConfig['permissions']['allowBypass']];
        }
        return false;
    }

    public function getFallbackRoute(string $permission)
    {

        if(isset($this->allPermissions[$permission]))
        {
            return $this->allPermissions[$permission][$this->tableConfig['permissions']['value']];
        }

        return null;
    }

    public function getUserId($loginName) : string
    {
        //We could retrieve this id after successful login and store it as a cookie but there is no guarantee that this cookie is present and untouched.
        return $this->persistentPDO->get(
            $this->tableConfig['login']['identifier'],
            $this->tableConfig['login']['tableName'],
            [
                $this->tableConfig['login']['loginName'] =>
                    [
                        'operator' => '=',
                        'queue' => $loginName,
                    ]
            ]
        );
    }

}
