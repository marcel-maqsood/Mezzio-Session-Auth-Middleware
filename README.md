# Mezzio Session Auth Middleware

Die SessionAuthMiddleware (SAM) ist eine PSR-15-Middleware für Laminas/Mezzio-Anwendungen. Sie verbindet Session-basierte Authentifizierung, Login-Routen, Datenbank-gestützte Session-Fingerprints und ein gruppenbasiertes Berechtigungssystem.

SAM prüft nicht nur, ob ein Benutzer angemeldet ist. Sie stellt auch sicher, dass die Session noch gültig ist, dass kein neueres Login auf einem anderen Gerät existiert und dass der angemeldete Benutzer die Permission der aktuell angefragten Route besitzt.

## Installation

```bash
composer require marcel-maqsood/session-auth
```

Das Paket registriert seinen `ConfigProvider` automatisch über Laminas/Mezzio. Die projektbezogenen Konfigurationen müssen trotzdem in die Anwendung übernommen und angepasst werden.

## Was SAM macht

- schützt einzelne Routen oder optional die gesamte Pipeline
- nutzt Mezzio Sessions und `mezzio/mezzio-authentication-session`
- authentifiziert Benutzer über `MazeDEV\SessionAuth\Repository\PDORepository`
- schreibt pro Login einen Session-Hash und einen Session-Zeitstempel in die Login-Tabelle
- verhindert parallele Logins mit demselben Account, indem ein neuer Login ältere Sessions ungültig macht
- prüft die Session gegen Browser/IP/User-Agent/Salt-Fingerprint
- verlängert den Session-Zeitstempel während aktiver Nutzung
- leitet nicht angemeldete Benutzer auf die passende Fallback- oder Login-Route
- prüft Permissions über Gruppen und Gruppen-Permission-Zuordnungen
- unterstützt mehrere Auth-Bereiche, z. B. `user` und `admin`, über Table Overrides
- kann Benutzernamen und Benutzer-/Permission-Daten an Requests weiterreichen
- stellt Handler für Login, Logout, Passwort-Reset und Account-Erstellung bereit
- legt Fehlertexte kurzzeitig im Cookie `error` ab, damit Login-Templates sie anzeigen können

## Grundkonfiguration

Die mitgelieferten Dateien in `config/` sind als Vorlage für `config/autoload/` einer Mezzio-Anwendung gedacht:

- `config/dependencies.global.php`
- `config/authentication.global.php`
- `config/messages.global.php`

Die Datenbankstruktur liegt in `db/base.sql`. Zusätzlich gibt es ein MySQL-Workbench-Modell unter `db/SQL-model.mwb`.

### Dependencies

SAM benötigt `PersistentPDO`, den eigenen `PDORepository` und die Mezzio `SessionMiddleware`.

```php
use MazeDEV\DatabaseConnector\PersistentPDO;
use MazeDEV\DatabaseConnector\PersistentPDOFactory;
use MazeDEV\SessionAuth\Repository\PDORepository;
use MazeDEV\SessionAuth\Repository\PDORepositoryFactory;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepositoryInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Session\SessionMiddlewareFactory;

return [
    'dependencies' => [
        'aliases' => [
            AuthenticationInterface::class => PhpSession::class,
            UserRepositoryInterface::class => PDORepository::class,
        ],
        'factories' => [
            PersistentPDO::class => PersistentPDOFactory::class,
            PDORepository::class => PDORepositoryFactory::class,
            SessionMiddleware::class => SessionMiddlewareFactory::class,
        ],
    ],
];
```

## Pipeline

Die Mezzio `SessionMiddleware` muss früh in der Pipeline laufen, bevor SAM oder Login-Handler auf die Session zugreifen.

```php
$app->pipe(ErrorHandler::class);
$app->pipe(ServerUrlMiddleware::class);
$app->pipe(SessionMiddleware::class);
```

SAM kann pro Route verwendet werden. Das ist die empfohlene Variante, wenn nur bestimmte Bereiche geschützt werden sollen.

```php
$app->route('/admin/dashboard[/]', [
    MazeDEV\SessionAuth\SessionAuthMiddleware::class,
    App\Handler\AdminDashboardHandler::class,
], ['GET'], 'adminDashboard');
```

Alternativ kann SAM global in die Pipeline gesetzt werden. Dann müssen öffentliche Routen in `no-auth-routes` eingetragen werden.

Wichtig: Wenn SAM global gepiped wird, muss sie nach `Mezzio\Helper\UrlHelperMiddleware` laufen, weil Redirects über den `UrlHelper` erzeugt werden.

```php
$app->pipe(UrlHelperMiddleware::class);
$app->pipe(SessionAuthMiddleware::class);
```

## Login-Routen

Login-Routen werden über `loginHandling` definiert. Der Schlüssel ist der Routename der Login-Route. `destination` ist die Route, auf die nach erfolgreichem Login weitergeleitet wird. `resetDestination` ist die Passwort-Reset-Route, die im Login-Template verfügbar gemacht wird.

```php
'loginHandling' => [
    'adminLogin' => [
        'name' => 'Admin',
        'destination' => 'adminDashboard',
        'resetDestination' => 'adminPasswordReset',
    ],
    'userLogin' => [
        'name' => 'Benutzer',
        'destination' => 'userDashboard',
        'resetDestination' => 'userPasswordReset',
    ],
],
```

Beispielrouten:

```php
$app->route('/admin/login[/]', [
    MazeDEV\SessionAuth\SessionAuthMiddleware::class,
    MazeDEV\SessionAuth\Handler\GlobalLoginHandler::class,
], ['GET', 'POST'], 'adminLogin');

$app->route('/admin/dashboard[/]', [
    MazeDEV\SessionAuth\SessionAuthMiddleware::class,
    App\Handler\AdminDashboardHandler::class,
], ['GET'], 'adminDashboard');
```

Der `GlobalLoginHandler` rendert das Template `app::Login`. Die mitgelieferte Vorlage liegt unter `src/Templating/Login.html.twig`.

Bei einem Login:

- wird eine bestehende User-Session zuerst entfernt
- werden Benutzername und Passwort über `PhpSession` und `PDORepository` geprüft
- wird das Passwort mit `password_verify($password . $salt, $hash)` validiert
- werden Session-Hash und Session-Start in der passenden Login-Tabelle gespeichert
- wird anschließend auf die konfigurierte `destination` weitergeleitet

## Logout

Der `LogoutHandler` entfernt den User aus der Session und leitet auf die Route `home` weiter. Die Anwendung muss diese Route bereitstellen.

```php
$app->route('/logout[/]', [
    MazeDEV\SessionAuth\Handler\LogoutHandler::class,
], ['GET', 'POST'], 'logout');
```

## Öffentliche Routen

Wenn SAM global gepiped wird, werden Routen aus `no-auth-routes` ohne Auth-Prüfung durchgelassen. Das ist besonders für Passwort-Reset-Routen wichtig.

```php
'no-auth-routes' => [
    'adminPasswordReset' => 'adminLogin',
    'userPasswordReset' => 'userLogin',
],
```

Der Wert wird vom Passwort-Reset-Handler auch als Login-Ziel genutzt, wenn ein Reset-Link ungültig oder abgelaufen ist.

## Authentication Config

Die `authentication`-Config steuert Login-Felder, Repository-Zugriff, Session-Sicherheit, Passwort-Reset und optionale Weitergabe von Daten an Requests.

```php
'authentication' => [
    'redirect' => '/',
    'username-forwarding' => true,
    'permission-forwarding' => false,
    'passwordResetOffset' => 2592000,
    'allowWildcard' => true,

    'username' => 'username',
    'password' => 'password',

    'repository' => [
        'table' => 'user',
        'fields' => [
            'identities' => [
                'username',
                'email',
            ],
            'password' => 'passwordhash',
        ],
        'disable-check' => true,

        'table_override' => [
            'user' => [
                'tableKey' => 'user',
                'display' => 'Benutzer',
                'loginAt' => 'userLogin',
            ],
            'admin' => [
                'tableKey' => 'admin',
                'display' => 'Admin',
                'loginAt' => 'adminLogin',
            ],
        ],
    ],

    'security' => [
        'algo' => 'sha256',
        'salt' => 'change-this-salt',
        'fields' => [
            'session' => 'sessionhash',
            'stamp' => 'sessionstart',
        ],
    ],
],
```

Konfigurationsfelder:

- `username` und `password`: POST-Feldnamen für Login-Formulare.
- `repository.table`: Standard-Tabellenprefix, wenn keine Route per `table_override` erkannt wird.
- `repository.fields.identities`: Datenbankfelder, über die ein Login gesucht werden darf, z. B. Benutzername oder E-Mail.
- `repository.fields.password`: Feld mit dem Passwort-Hash.
- `repository.disable-check`: aktiviert die Prüfung gegen das konfigurierte Disabled-Feld der Login-Tabelle.
- `repository.table_override`: ordnet Routenpräfixe einem Tabellenprefix zu. Beginnt eine Route mit `admin`, nutzt SAM z. B. `admin`, `admin_groups`, `admin_permissions` usw.
- `security.algo`: Hash-Algorithmus für Session- und Reset-Hashes.
- `security.salt`: Salt für Passwortprüfung und Session-Fingerprint.
- `security.fields.session`: Datenbankfeld für den aktuellen Session-Hash.
- `security.fields.stamp`: Datenbankfeld für den Session-Zeitstempel.
- `username-forwarding`: setzt den aktuellen Benutzernamen als Request-Attribut `adminName`.
- `permission-forwarding`: lädt Benutzerdaten und Permissions früh im Request über den `PermissionManager`.
- `allowWildcard`: erlaubt die Permission `*` als globale Berechtigung, wenn die angefragte Permission in der Datenbank existiert.
- `passwordResetOffset`: Gültigkeit neuer Passwort-Reset-Hashes in Sekunden.

Hinweis: Die aktuelle `SessionAuthMiddleware` liest `table_override` unter `authentication.repository.table_override`.

## Session Config

```php
'session' => [
    'config' => [
        'cookie_lifetime' => 60 * 60,
        'gc_lifetime' => 60 * 60 * 24,
    ],
],
```

`gc_lifetime` ist für SAM besonders wichtig: Der Wert definiert, wie lange der in der Datenbank gespeicherte Session-Zeitstempel gültig bleibt. Läuft diese Zeit ab, wird die Session verworfen und der Benutzer muss sich neu anmelden.

Während aktiver Nutzung aktualisiert SAM den Datenbank-Zeitstempel höchstens einmal pro Minute.

## PermissionManager

Der `PermissionManager` lädt Berechtigungsdaten für den aktuellen Tabellenprefix und prüft anschließend die Route als Permission.

Das Prinzip ist bewusst einfach: Der Routename ist die Permission.

Beispiel: Eine Route mit dem Namen `adminDashboard` benötigt eine Permission mit dem Wert `adminDashboard`. Benutzer erhalten Permissions nicht direkt, sondern über Gruppen.

Der PermissionManager kann:

- alle Permissions eines Bereichs laden
- den aktuellen Tabellenprefix setzen, z. B. `user` oder `admin`
- Benutzerdaten inklusive Settings, Gruppen und Permissions laden
- prüfen, ob der Benutzer in einer Gruppe ist
- prüfen, ob der Benutzer eine Permission besitzt
- Bypass-Permissions berücksichtigen
- Wildcard-Permissions berücksichtigen
- eine Fallback-Route für fehlende Permissions ermitteln
- User-Daten und User-Settings aktualisieren

### Permission-Fallbacks

Permissions können eine Fallback-Permission referenzieren. Hat ein Benutzer keinen Zugriff auf die angefragte Route und gibt es keinen internen Referer, leitet SAM auf die Fallback-Route um.

Wenn keine Permission zur Route existiert, verwendet der `PermissionManager` `home` als Fallback.

### Bypass und Wildcard

Eine Permission mit `allowBypass = 1` gilt immer als erlaubt. Das ist nützlich für Fallback- oder Basisrouten, die technisch geschützt sind, aber allen angemeldeten Benutzern offenstehen sollen.

Wenn `authentication.allowWildcard` auf `true` steht, kann eine Gruppe mit der Permission `*` Zugriff auf alle in der Datenbank definierten Permissions erhalten.

## Tabellenkonfiguration

SAM verwendet Tabellenprefixe. Für den Prefix `user` werden z. B. diese Config-Keys erwartet:

- `user`
- `user_settings`
- `user_group_relation`
- `user_groups`
- `user_permissions`
- `user_group_permission_relation`

Für `admin` entsprechend:

- `admin`
- `admin_settings`
- `admin_group_relation`
- `admin_groups`
- `admin_permissions`
- `admin_group_permission_relation`

Beispiel für `user`:

```php
'tables' => [
    'user' => [
        'tableName' => 'users',
        'identifier' => 'loginId',
        'loginName' => 'username',
        'loginMail' => 'email',
        'disabled' => 'disabled',
        'hidden' => 'hidden',
        'resetHash' => 'forgothash',
        'resetValid' => 'forgotvalid',
    ],
    'user_settings' => [
        'tableName' => 'user_settings',
        'identifier' => 'settingId',
        'user_identifier' => 'loginId',
        'icon_path' => 'icon_path',
        'language' => 'language',
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
        'hidden' => 'hidden',
    ],
    'user_permissions' => [
        'tableName' => 'user_permissions',
        'identifier' => 'permissionId',
        'name' => 'name',
        'value' => 'value',
        'noPermFallback' => 'noPermFallback',
        'allowBypass' => 'allowBypass',
        'hidden' => 'hidden',
    ],
    'user_group_permission_relation' => [
        'tableName' => 'user_group_has_permissions',
        'identifier' => 'ghpId',
        'permission_identifier' => 'permissionId',
        'group_identifier' => 'groupId',
    ],
],
```

Die Spaltennamen sind frei konfigurierbar, solange die Config die tatsächlichen Datenbankfelder korrekt abbildet.

## Message Config

SAM und die Handler lesen Fehlermeldungen aus `messages.error`. Wenn SAM während einer Auth-Prüfung einen Fehler erkennt, wird der Text für 60 Sekunden als Cookie `error` gesetzt.

```php
'messages' => [
    'error' => [
        'session-detail-error' => 'Ihre Sitzung scheint fehlerhaft zu sein, bitte melden Sie sich erneut an.',
        'session-set-error' => 'Ihre Sitzung konnte nicht eingetragen werden, bitte probieren Sie es erneut.',
        'session-expired-error' => 'Ihre Sitzung ist ausgelaufen, bitte melden Sie sich erneut an.',
        'another-device-logon-error' => 'Ein anderes Gerät hat sich angemeldet.',
        'logon-required-error' => 'Für diesen Inhalt müssen Sie angemeldet sein.',
        'user-create-error' => 'Der Zugang konnte nicht angelegt werden.',
        'user-repo-error' => 'Sie müssen sich mit einem Zugang für diesen Bereich anmelden.',
        'credential-error' => 'Fehlerhafte Zugangsdaten',
        'session-path-swap-error' => 'Sie wurden abgemeldet, da Ihre Sitzung für diesen Bereich ungültig ist.',
    ],
],
```

Verwendete Keys:

- `session-detail-error`: Sessiondaten fehlen oder sind nicht lesbar.
- `session-set-error`: Session-Zeitstempel konnte nicht sauber gesetzt oder gelesen werden.
- `session-expired-error`: Session ist nach `gc_lifetime` abgelaufen.
- `another-device-logon-error`: Der Datenbank-Sessionhash passt nicht mehr zur aktuellen Session.
- `logon-required-error`: Die Route benötigt ein Login.
- `credential-error`: Benutzername oder Passwort sind falsch.
- `user-create-error`: Account-Erstellung konnte nicht gespeichert werden.
- `user-repo-error`: Login passt nicht zum aktuellen Auth-Bereich.
- `session-path-swap-error`: Eine Session aus einem Auth-Bereich wird für einen anderen Bereich benutzt, z. B. Admin-Session auf User-Route.

Im Login-Handler wird das Cookie gelesen, gelöscht und als Template-Variable `error` an `app::Login` übergeben.

## Passwort-Reset

Der `ForgotPasswordHandler` unterstützt zwei POST-Aktionen:

- `request`: Reset-Link anfordern
- `submit`: neues Passwort speichern

Für den Reset werden diese Felder in der Login-Tabelle benötigt:

- `resetHash`
- `resetValid`
- `loginMail`

Die Gültigkeit wird über `authentication.passwordResetOffset` gesteuert. Standard im Code ist `2592000` Sekunden, also 30 Tage.

Für den Mailversand erwartet der Handler:

- `requestPasswordAdapter`
- `submitPasswordAdapter`
- das optionale Paket `MazeDEV\FormularHandlerMiddleware\Adapter\SmtpMail`

Die mitgelieferten Templates liegen unter:

- `src/Templating/SetPasswordForm.html.twig`
- `src/Templating/emailing/ForgotPassword.html.twig`
- `src/Templating/emailing/PasswordSet.html.twig`
- `src/Templating/emailing/DefaultMail.html.twig`

## Account-Erstellung

Der `CreateAccountHandler` verarbeitet POST-Requests und nutzt die Tabellenkonfiguration des aktuell erkannten Auth-Bereichs. Die eigentliche Feldabbildung wird über den `AbstractRequestHandler` erzeugt.

Fehlschläge werden mit `messages.error.user-create-error` gemeldet.

## Session- und Sicherheitslogik

SAM erzeugt den Session-Fingerprint aus:

- PHP-Zeitzone
- Remote-IP plus Proxy-Header, wenn vorhanden
- User-Agent
- `authentication.security.salt`

Der Hash wird mit `authentication.security.algo` erzeugt und beim Login in der Datenbank gespeichert. Bei jedem geschützten Request wird der aktuelle Hash mit dem Datenbankwert verglichen.

Wenn ein anderer Login denselben Account verwendet, überschreibt dieser Login den Datenbankhash. Die ältere Session erkennt das beim nächsten Request und wird abgemeldet.

## Zugriff auf User und Permissions im Code

Die Middleware hält den `PermissionManager` statisch vor:

```php
$user = MazeDEV\SessionAuth\SessionAuthMiddleware::$permissionManager::getUser();
$groups = MazeDEV\SessionAuth\SessionAuthMiddleware::$permissionManager->getGroups();
$hasPermission = MazeDEV\SessionAuth\SessionAuthMiddleware::$permissionManager->userHasPermission('adminDashboard');
```

Zusätzlich kann `username-forwarding` den Benutzernamen als Request-Attribut `adminName` setzen. Bei Sessions mit gespeichertem Auth-Bereich wird außerdem `userPath` gesetzt.

## Credits

Entwickelt von MazeDEV / Marcel Maqsood.

## License

MIT. Siehe [LICENSE.md](LICENSE.md).
