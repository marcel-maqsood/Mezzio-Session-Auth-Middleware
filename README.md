# Session-Auth-Middleware


You can install this package with the following command:
```composer require marcel-maqsood/session-auth-middleware```

## Configuration

### Additional Notes: ###
As our Middleware can run on any request, it is meant to be injected within your applications ```config\autoload\dependencies.global.php``` file, as seen in ```dependencies.global.php```:
```
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
```
This fullfils multiple purposes:
- You dont have to configure each ConfigProvide within your modules
- Any request will always be capabale of SessionAuth Handling (But this will only be used if the route contains our SessionAuthMiddleware)
- You cant forget to add our base config in every new module that you supply; which could be a hustle otherwise.

You can find our default configuration in ```config\autoload\authentication.global.php``` and drop it into your applications ```config\autoload\``` folder.
It contains every configuration needed to run our SessionAuthMiddleware and can easily be copied and adjusted..


Also, you have to add the ```Mezzio\Session\SessionMiddleware``` to your pipeline (```config\pipeline.php```), it must be included in the very top of the Pipeline:
``` 
$app->pipe(ErrorHandler::class);
$app->pipe(ServerUrlMiddleware::class);
$app->pipe(SessionMiddleware::class); // <<<<<-----
```


For ease of use, we also include a basic database-sql file that contains every table and field that this middleware needs (built like the defaults described in this doc).
You find it in ```db\base.sql```, we also included a MySQLWorkbench file ```db\SQL-model.mwb```so that you can adjust it to fit your needs without having to reconstruct it.


Also: our SessionAuthMiddleware doesn't allow for multi-logons per account, we implemented features that prevent that on purpose as we think its the safest approach to logoff any other device and telling them they have been logged out.
You just have to use the variable "error" (as iterable) within your template to display any error that occoured.

#### LoginHandlers ####
To provide you with a working LoginHandler, we included one that is capable of all features that this doc meantions, you can find it in
```src\LoginHandler\GlobalLoginHandler.php```

To use it, you just have to define a route for it, as it is already included in our ConfigProvider:
```

$app->route('/authorized[/]',
    [
        MazeDEV\SessionAuth\SessionAuthMiddleware::class,
        MazeDEV\SessionAuth\LoginHandler\GlobalLoginHandler::class
    ],
    [
        'GET',
        'POST'
    ],
    'login1'
);

$app->route('/authorized/landing[/]',
    [
        MazeDEV\SessionAuth\SessionAuthMiddleware::class,
        App\Handler\YourLandingHandler::class
    ],
    [
        'GET',
        'POST'
    ],
    'authorizedPage'
);

```

Each route that begins with our SessionAuthMiddleware will be fully secured and requires a valid login with permissions set corrently.
Our GlobalLoginHandler is capable of redirecting logins from different login-forms to different destinations, you just have to set a configuration for it:
```
'loginHandling' => [
    'login1' => [
        'name' => 'Base Login',
        'destination' => 'authorizedPage',
    ],
    'login2' => [
        'name' => 'Base Login2',
        'destination' => 'authorizedPage2',
    ],
    //...
],
```
Our LoginHandler needs exactly that syntax to work properly.

#### Keep in mind: ####
The 'routename' (like: 'authorizedPage') of each route is also its permission; so for every route that you define, you have to add a permission inside the database and connect it to the desired groups.
However this only applies to routes that our Middleware is invloved, any other route doesn't have to be added within the database.

Also, you have to provide a template named "Login.html.twig" within your  '```src\App\src\templates\app```' folder, it is used by our GlobalLoginHandler to render the login form.

We provide you a basic login form, named ```Login.html.twig``` within ```src\Templating\```.



#### Logout Handlers ####
We provide you a default LogoutHandler which just removes the UserInterface from the request's session and redirect the request towards your home route.
As this is currently hard-coded, you have to provide your application with one route named "home", best case: your main landingpage.
This is how to add the LogoutHandler in one of your routes:
```
$app->route('/logout[/]',
    [
        MazeDEV\SessionAuth\LogoutHandler\LogoutHandler::class,
    ],
    [
        'GET',
        'POST'
    ],
    'logout'
);
```
From within your applcation, you just have to add it as a link or redirect so that users can logout.


##### <a id="pdo">persistentpdo - An array, in which we define our database-connection rules:</a>
See MazeDEV/Marcel-Maqsood(https://github.com/marcel-maqsood/DatabaseConnector) for additional informations and documentation.
Our SessionAuthMiddleware uses this DatabaseConnector and therefore requires its configuration set.
Within our default config, we already supply these settings and you just have to adjust them.
Also, PersistentPDO must be included within your applications ```config\autoload\dependencies.global.php``` as it is required for our SessionAuthMiddleware.
We already included it within our ```config\dependencies.global.php```.



##### <a id="auth">Within the 'authentication' entry, we define specific attribites for our Session-Auth Middleware:</a>
```
'authentication' => [
    'redirect' => '/', //- The Link at which unauthorized request get redirect (As of PHPSession), however, the SessionAuthMiddleware won't use it.
    'username' => 'username', //- The key in which the username is within $_POST. default: 'username'
    'password' => 'password', //- The key in which the password is within $_POST. default: 'password'
    'repository' => [ //- An array, in which the details for our database-table are.
        'table' => 'login', //- The table, in which we look for the user.  default: 'logins'
        'fields' => [ //- An array in which the fields of that table are to authenticate a user.
            'identity' => 'username', //- The key, with which we look in our table for the username given in $_POST. default: 'username'
            'password' => 'anyPass' //- The key, with which we check if the password in $_POST is equal.
        ],
        'table_override' => [ // - An array, in which we define routes and their database-table prefix that the system will use tot check if they start with the key of any entry.
            'user'  => 'user', // Routename starts with 'user' => use table prefix 'user' : user - for base table, user_permissions for all permissions that only user-groups can have, etc.
            'admin' => 'admin',
        ],
    ],
    'security' => [ //- An array for our security features.
        'algo' => 'sha-256', //- The algorithm used for generating the SessionHash stored in the database. default: 'sha-256'
        'salt' => 'anySalt', // - The string which we use to harden our hashes be appending it.
        'fields' => [ //- An array, in which we define session related fields within our 'logins' table to be used to check if the session is valid.
            'session' => 'sessionhash', //- The key which we use to get the users current session-hash and check if it matches the request. default: 'sessionhash'
            'stamp' => 'sessionstart' //- The key which we use to get the session-start of the current session to check if it is still valid. default: 'sessionstart'
        ]
    ]
]
```

if the key ```'table_override'``` is not set within ```'repository'```, the system will only use the ```'table'``` value set in ```'repository'``` to map to a table.

Our SessionAuthMiddleware also requires this config entry:
```
'session' => [
    'config' => [
        'cookie_lifetime' => 60 * 60 * 1, //- Time in seconds which the cookie is valid. default: '1h'
        'gc_lifetime' => 60 * 60 * 24 //- Time in seconds which the created session is valid. default: '24h'
    ]
]
```

##### <a id="permissions">Permission Management</a>
As this is a authentication handler, we also want to check if a user has the permission to see its requested content.
- Check if the request's user has permissions on the current route.
- Redirecting towards the referring page, if the user does not have permissions to see its requested content.
- Redirecting towards login-forms if the user directly requested a page without permission and without beeing on the page before.
- Redirecting from login-form towards a page if the user has permissions to that page.
- Permissions can be marked as "allowBypass" which grants the user the same right as having the permission, like for routes that should always be accessabile but defined to use as fallback.
- Definition of a fallback permission (route) if the user does not have permission on its current route and should be redirected towards another route.


Default table definition within any global or local config.php (located in ```config\autoload\```):
```
return [
    'tables' => [
        'user' => [
            'tableName' => 'users',
            'identifier' => 'loginId',
            'loginName' => 'username'
        ],
        'user_group_relation' => [
            'tableName' => 'user_has_groups',
            'identifier' => 'lhgId',
            'group_identifier' => 'groupId',
            'login_identifier' => 'loginId',
        ],
        'user_groups' => [
            'tableName' => 'user_groups',
            'identifier' => 'groupId',
            'name' => 'name',
        ],
        'user_permissions' => [
            'tableName' => 'user_permissions',
            'identifier' => 'permissionId',
            'name' => 'name',
            'value' => 'value',
            'noPermFallback' => 'noPermFallback',
            'allowBypass' => 'allowBypass'
        ],
        'user_group_permission_relation' => [
            'tableName' => 'user_group_has_permissions',
            'identifier' => 'ghpId',
            'permission_identifier' => 'permissionId',
            'group_identifier' => 'groupId',
        ],
    ]
]
```

As stated before, you can define permission fallbacks if a given permission is not granted and should redirect towards somewhere else.

Permissions cannot be granted to certain users but instead to a group which can be granted to users.
users may have as much groups as you want and groups may have as much permissions as you want.


##### <a id="messages">Error Messages</a>
Our Session-Auth-Middleware will store a cookie that is valid for 60 seconds if it encounters any issues:
```
setcookie("error", $this->errorMessage, time() + 60, '/');
```
You can use that cookie to receive the error message and display it to the user.


## Credits

This Software has been developed by MazeDEV/Marcel-Maqsood(https://github.com/marcel-maqsood).


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
