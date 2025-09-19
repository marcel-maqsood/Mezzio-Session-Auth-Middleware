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

	private bool $fetched = false;
	private bool $userDataFetched = false;

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

		$userUpdate = $this->persistentPDO->update($this->tableConfig[self::$prefix . "_settings"]['tableName'], $updates,
			$this->tableConfig[self::$prefix . "_settings"]['user_identifier'] . ' = ' . self::$user->{$this->tableConfig[self::$prefix . "_settings"]['user_identifier']},
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

	public function __construct(PersistentPDO $persistentPDO, array $tableConfig, array $authConfig)
	{
		$this->persistentPDO = $persistentPDO;
		$this->tableConfig = $tableConfig;
		$this->authConfig = $authConfig;
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

	public function fetchData()
	{
		if($this->fetched)
		{
			return;
		}
		$permCfg = $this->tableConfig[$this->getTablePrefix() . 'permissions'];
		$allPermissions = $this->persistentPDO->getAll($permCfg['tableName']);

		if ($allPermissions === null) {
			return;
		}

		foreach ($allPermissions as $permission) {
			$permValueKey = $permCfg['value'];
			$permIdKey = $permCfg['identifier'];
			$fallbackKey = $permCfg['noPermFallback'];
			$bypassKey = $permCfg['allowBypass'];

			$permName = $permission[$permValueKey];
			$fallback = $permission[$fallbackKey];

			$value = isset($allPermissions[$fallback])
				? $allPermissions[$fallback][$permValueKey]
				: null;

			$this->allPermissions[$permName] = [
				$permValueKey => $value,
				$bypassKey => $allPermissions[$permission[$permIdKey]][$bypassKey]
			];
		}

		$this->fetched = true;
	}

	public function dataFetched()
	{
		return $this->fetched;
	}

	public function fetchUserData($username): void
	{
		if($this->userDataFetched)
		{
			return;
		}
		$sql = "
			SELECT 
				u.*,
		
				us.{$this->tableConfig[$this->getTablePrefix() . 'settings']['language']}     AS language,
				us.{$this->tableConfig[$this->getTablePrefix() . 'settings']['icon_path']}    AS settingsIcon,
		
				g.{$this->tableConfig[$this->getTablePrefix() . 'groups']['identifier']}      AS groupId,
				g.{$this->tableConfig[$this->getTablePrefix() . 'groups']['name']}            AS groupName,
				g.{$this->tableConfig[$this->getTablePrefix() . 'groups']['hidden']}          AS groupHidden,
				g.{$this->tableConfig[$this->getTablePrefix() . 'groups']['creator']}         AS groupCreator,
				g.{$this->tableConfig[$this->getTablePrefix() . 'groups']['isLecturer']}      AS isLecturer,
				g.{$this->tableConfig[$this->getTablePrefix() . 'groups']['isParticipant']}   AS isParticipant,
		
				p.{$this->tableConfig[$this->getTablePrefix() . 'permissions']['identifier']} AS permissionId,
				p.{$this->tableConfig[$this->getTablePrefix() . 'permissions']['name']}       AS permissionName,
				p.{$this->tableConfig[$this->getTablePrefix() . 'permissions']['value']}      AS permissionValue,
				p.{$this->tableConfig[$this->getTablePrefix() . 'permissions']['noPermFallback']} AS noPermFallback,
				p.{$this->tableConfig[$this->getTablePrefix() . 'permissions']['allowBypass']}    AS allowBypass,
				p.{$this->tableConfig[$this->getTablePrefix() . 'permissions']['hidden']}         AS permissionHidden
		
			FROM {$this->tableConfig[self::$prefix]['tableName']} u
		
			LEFT JOIN {$this->tableConfig[$this->getTablePrefix() . 'settings']['tableName']} us 
				ON us.{$this->tableConfig[$this->getTablePrefix() . 'settings']['user_identifier']} = u.{$this->tableConfig[self::$prefix]['identifier']}
		
			LEFT JOIN {$this->tableConfig[$this->getTablePrefix() .'group_relation']['tableName']} ugr 
				ON ugr.{$this->tableConfig[$this->getTablePrefix() .'group_relation']['login_identifier']} = u.{$this->tableConfig[self::$prefix]['identifier']}
		
			LEFT JOIN {$this->tableConfig[$this->getTablePrefix() .'groups']['tableName']} g 
				ON g.{$this->tableConfig[$this->getTablePrefix() .'groups']['identifier']} = ugr.{$this->tableConfig[$this->getTablePrefix() .'group_relation']['group_identifier']}
		
			LEFT JOIN {$this->tableConfig[$this->getTablePrefix() .'group_permission_relation']['tableName']} gpr 
				ON gpr.{$this->tableConfig[$this->getTablePrefix() .'group_permission_relation']['group_identifier']} = g.{$this->tableConfig[$this->getTablePrefix() .'groups']['identifier']}
		
			LEFT JOIN {$this->tableConfig[$this->getTablePrefix() .'permissions']['tableName']} p 
				ON p.{$this->tableConfig[$this->getTablePrefix() .'permissions']['identifier']} = gpr.{$this->tableConfig[$this->getTablePrefix() .'group_permission_relation']['permission_identifier']}
		
			WHERE u.{$this->tableConfig[self::$prefix]['loginName']} = :username OR u.{$this->tableConfig[self::$prefix]['loginMail']} = :username
		";

		$dataset          = $this->persistentPDO->getAllBase($sql, false, ['username' => $username]);
		if($dataset == null)
		{
			return;
		}

		$dataset = array_values($dataset)[0];

		self::$user = json_decode(json_encode($dataset));

		$this->allGroups  = explode(",", strtolower(str_replace(" ", "", $dataset["groupName"] ?? "")));
		$this->mergedPermissions = explode(",", $dataset["permissionValue"] ?? "");
		$this->userDataFetched = true;
	}

	public static function getUser()
	{
		return self::$user;
	}

	public static function getUserSettings()
	{
		return self::$user;
	}

	public static function getGroups()
	{
		return $this->allGroups;
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
}
