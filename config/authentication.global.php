<?php

declare(strict_types=1);

return [
    'docs' => 'https://github.com/marcel-maqsood/Mezzio-Session-Auth-Middleware',
    'persistentpdo' => [
        'dsn' => 'mysql:host=localhost;dbname=report_portal;port=3306', //- Der String, mit dem die Verbindung zur Datenbank hergestellt wird.
        'username' => 'root', //- Der Username für die Verbindung mit der Datenbank.
        'password' => 'root' //- Das Passwort für die Verbindung mit der Datenbank.
    ],
    'authentication' => [
        'redirect' => '/', //- The Link at which unauthorized request get redirect (As of PHPSession), however, the SessionAuthMiddleware won't use it.
        'allowWildcard' => true,
        'username' => 'username', //- The key in which the username is within $_POST. default: 'username'
        'password' => 'password', //- The key in which the password is within $_POST. default: 'password'
        'repository' => [ //- An array, in which the details for our database-table are.
            'table' => 'login', //- The table, in which we look for the user.  default: 'login'
            'fields' => [ //- An array in which the fields of that table are to authenticate a user.
                'identity' => 'username', //- The key, with which we look in our table for the username given in $_POST. default: 'username'
                'password' => 'password' //- The key, with which we check if the password in $_POST is equal.
            ]
        ],
        'security' => [ //- An array for our security features.
            'algo' => 'sha-256', //- The algorithm used for generating the SessionHash stored in the database. default: 'sha-256'
            'salt' => 'anySalt', // - The string which we use to harden our hashes be appending it.
            'fields' => [ //- An array, in which we define session related fields within our 'login' table to be used to check if the session is valid.
                'session' => 'sessionhash', //- The key which we use to get the users current session-hash and check if it matches the request. default: 'sessionhash'
                'stamp' => 'sessionstart' //- The key which we use to get the session-start of the current session to check if it is still valid. default: 'sessionstart'
            ],
            'table_override' => [ // - An array, in which we define routes and their database-table prefix that the system will use tot check if they start with the key of any entry.
                'user'  => 'user', // Routename starts with 'user' => use table prefix 'user' : user - for base table, user_permissions for all permissions that only user_groups can have, etc.
                'admin' => 'admin',
            ],
        ]
    ],
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
    'session' => [
        'config' => [
            'cookie_lifetime' => 60 * 60 * 1, //- Time in seconds which the cookie is valid. default: '1h'
            'gc_lifetime' => 60 * 60 * 24 //- Time in seconds which the created session is valid. default: '24h'
        ]
    ],
    'tables' => [
        'user' => [
            'tableName' => 'users',
            'identifier' => 'loginId',
            'loginName' => 'username',
            'display' => 'hidden'
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
            'display' => 'hidden'
        ],
        'user_permissions' => [
            'tableName' => 'user_permissions',
            'identifier' => 'permissionId',
            'name' => 'name',
            'value' => 'value',
            'noPermFallback' => 'noPermFallback',
            'allowBypass' => 'allowBypass',
            'display' => 'hidden'
        ],
        'user_group_permission_relation' => [
            'tableName' => 'user_group_has_permissions',
            'identifier' => 'ghpId',
            'permission_identifier' => 'permissionId',
            'group_identifier' => 'groupId',
        ],
    ],
    'messages' => [
        'error' => [
            'session-detail-error' => 'Ihre Sitzung scheint fehlerhaft zu sein, bitte melden Sie sich erneut an.',
            'session-set-error' => 'Ihre Sitzung konnte nicht eingetragen werden, bitte probieren Sie es erneut.',
            'session-expired-error' => 'Ihre Sitzung ist ausgelaufen, bitte melden Sie sich erneut an.',
            'another-device-logon-error' => 'Ein anderes Gerät hat sich angemeldet.',
            'admin-logon-required-error' => 'Für diesen Inhalt müssen Sie angemeldet sein.',

        ],
    ]   
];