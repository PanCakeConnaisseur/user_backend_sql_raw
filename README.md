# User Backend SQL Raw
This is an app for Nextcloud that offers user management and authentication with arbitrary SQL queries. Only tested with Nextcloud 13. Only PostgreSQL is supported at the moment. This is a first release that only supports password checking.

## Configuration
This app has no user interface. All configuration is done via Nextcloud's system configuration in *config/config.php*. The config key is `user_backend_sql_raw.`

    'user_backend_sql_raw' => array (
        'dbHost' => 'localhost',
        'dbPort' => '5432',
        'dbName' => '<theNameOfYourUserDatabase>',
        'dbUser' => '<yourUserDatabaseUser>',
        'dbPassword' => '<thePasswordforTheDatabaseUser>',
        'queries' => array (
            'getPasswordHashForUser' => 'SELECT password FROM virtual_users_fqda WHERE fqda = :username',
            'userExists' => 'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)',
            'getUsers' => 'SELECT fqda FROM virtual_users_fqda WHERE fqda ILIKE :username',
        )
    ),

You need to configure:
1. **connection parameters** for the db
    - *dbHost* is optional and defaults to `localhost`
    - *dbPort* is optional and defaults to `5432`
    - the rest is mandatory
2. **queries** that this app will use to query the db. These will be passed verbatim to the [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object.

## Queries
- The queries use named parameters. You have to use the exact names as shown in the examples. For example to retrieve the hash for a user the query named `getPasswordHashForUser` will be used. Adjust it to your cusotm SQL query and simply put `:username` where you are referring to the username of the user trying to login.
- All queries must return at most one column
- `userExists` should return a boolean. See the example on how to do this properly.
- `getUsers` is a query that searches for users and does pattern matching, therefore it should contain a `ILIKE` (`I` for case insensitive)
    - must not already have a `LIMIT` or `OFFSET`. They will be added by the app at the and of the query by the app
    - specify the `LIKE` without `%`, they will be added by the app. This is (unfortunately) necessary because of how prepared statements work. 

## Password Hash
There is no need to specify the hash algorithm. This app uses PHP's [password_verify()](http://php.net/manual/en/function.password-verify.php). It autodetects common hash algorithm in the "crypt" format, i.e. $hashType$salt$hash.