<?php


namespace MazeDEV\SessionAuth;

use MazeDEV\DatabaseConnector\PersistentPDO;

class PermissionManager
{
	private array $mergedPermissions = [];

	private array $allGroups = [];

	private array $allPermissions;

	private static string $prefix = "";

	private PersistentPDO $persistentPDO;
	private array $tableConfig;
	private array $authConfig;

	private static $user;

	private static $userSettings;

	private bool $fetched = false;

	public function updateUser(array $updates)
	{
		if (self::$user === null) {
			return false;
		}
		$userUpdate = $this->persistentPDO->update($this->tableConfig[self::$prefix]['tableName'], $updates,
			$this->tableConfig[self::$prefix]['identifier'] . ' = ' . self::$user->{$this->tableConfig[self::$prefix]['identifier']},
			false);

		if (!$userUpdate) {
			return false;
		}

		foreach ($updates as $key => $value)
		{
			self::$user->{$key} = $value;
		}

		return true;
	}

	public function updateUserSettings(array $updates)
	{
		if (self::$user === null) {
			return false;
		}

		if (self::$userSettings === null)
		{
			return false;
		}

		$userUpdate = $this->persistentPDO->update($this->tableConfig[self::$prefix . "_settings"]['tableName'], $updates,
			$this->tableConfig[self::$prefix . "_settings"]['user_identifier'] . ' = ' . self::$user->{$this->tableConfig[self::$prefix . "_settings"]['user_identifier']},
			false);

		if (!$userUpdate) {
			return false;
		}

		foreach ($updates as $key => $value)
		{
			self::$userSettings->{$key} = $value;
		}
		return true;
	}

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
		$this->fetched = true;
	}

	public function dataFetched()
	{
		return $this->fetched;
	}

	public function setTablePrefix(string $prefix)
	{
		self::$prefix = $prefix;
	}

	public function getTablePrefix()
	{
		if(self::$prefix == "")
		{
			return "";
		}
		return self::$prefix . "_";
	}

	public function fetchUserPermissions($username): void
	{
		$userId = $this->getUserId($username);

		$groupJoins = [
			[
				'table' => $this->tableConfig[$this->getTablePrefix() . 'group_relation']['tableName'],
				'on' => $this->tableConfig[self::$prefix ]['tableName'] . '.' . $this->tableConfig[self::$prefix ]['identifier'] . ' = ' . $this->tableConfig[$this->getTablePrefix() . 'group_relation']['tableName'] . '.' . $this->tableConfig[$this->getTablePrefix() . 'group_relation']['login_identifier']
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
				],
				[
					'for' => $this->tableConfig[$this->getTablePrefix() . 'groups']['name'],
					'as' => 'group_name',
				]
			],
			'identifier' => $this->tableConfig[self::$prefix]['tableName'] . '.' . $this->tableConfig[self::$prefix]['identifier']
		];

		$permissions = $this->persistentPDO->getAll(
			$this->tableConfig[self::$prefix]['tableName'], // Tabelle
			$this->tableConfig[self::$prefix]['identifier'] . ' = ' . $userId,
			$groupJoins,
			$groupDetails,
			'',
			[],
			false
		);

		if($permissions == null)
		{
			return;
		}

		if(!empty($permissions[$userId]["group_name"]))
		{
			$groups = explode(",", $permissions[$userId]['group_name']) ?? [];
			foreach($groups as $group)
			{
				$this->allGroups[] = strtolower(str_replace(" ", "", $group));
			}
		}

		if($permissions[$userId]['permissions'] == null)
		{
			return;
		}

		$permissionIds = explode(",", $permissions[$userId]['permissions'])  ?? [];

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

	public function userHasGroup(string $groupName)
	{
		$name = strtolower(str_replace(" ", "", $groupName));
		return in_array($name, $this->allGroups);
	}

	public function userHasPermission(string $permission): bool
	{
		if($this->isBypassable($permission)
			||  (
				isset($this->allPermissions[$permission])
				&& isset($this->authConfig['allowWildcard'])
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

		return "home";
	}

	public function getUserId($loginName) : string|null
	{

		$conditions = [];
		foreach ($this->authConfig['repository']['fields']['identities'] as $identityField)
		{
			$conditions[] =
				[
					'field' => $identityField,
					'operator' => '=',
					'queue' => $loginName,
					'logicalOperator' => 'OR'
				];
		}

		self::$user = $this->persistentPDO->get(
			'*',
			$this->tableConfig[self::$prefix]['tableName'],
			$conditions
		);

		if(self::$user == null)
		{
			return null;
		}
		self::$userSettings = $this->persistentPDO->get(
			'*',
			$this->tableConfig[self::$prefix . "_settings"]['tableName'],
			$this->tableConfig[self::$prefix . "_settings"]['user_identifier'] . " = " . self::$user->{$this->tableConfig[self::$prefix]['identifier']},
			[], [], false
		);

		return self::$user->{$this->tableConfig[self::$prefix]['identifier']};
	}

	public static function getUser()
	{
		return self::$user;
	}

	public static function getUserSettings()
	{
		return self::$userSettings;
	}

}
