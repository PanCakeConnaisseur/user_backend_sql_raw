# User Backend SQL Raw
This an app for Nextcloud that allows user management and authentication with arbitrary SQL queries. Only tested with Nextcloud 13. Only PostgreSQL is supported at the moment. This is a first release that only supports password checking.

## Configuration
There is no UI for this app. All configuration is done via Nextcloud's system configuration in *config/config.php*. The config key is `user_backend_sql_raw.`

    'user_backend_sql_raw' => array (
              'dbHost' => 'localhost',
              'dbPort' => '5432',
              'dbName' => '<theNameOfYourUserDatabase>',
              'dbUser' => '<yourUserDatabaseUser>',
              'dbPassword' => '<thePasswordforTheDatabaseUser>',
              'queries' => array (
                      'getPasswordHashForUser' => 'SELECT password FROM virtual_users_fqda WHERE fqda = :username',
                      'userExists' => 'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)',
              )
    
      ),

You need to configure:
1. **connection parameters** for the db
    - *dbHost* is optional and defaults to `localhost`
    - *dbPort* is optional and defaults to `5432`
    - the rest is mandatory
2. **queries** that this app will use to query the db. This will be passed verbatim to the [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object.

The queries use named parameters. You have to use the exact names as shown in the example, e.g. to retrieve a hash for a user `getPasswordHashForUser` will be used. Put your custom SQL query there but use `:username` where you are referring to the username of the user trying to login.

## Queries
- the query `userExists` should return a boolean. See the example on how to do this properly.

## Password Hash
There is no need to specify the hash algorithm. This app uses PHP's [password_verify()](http://php.net/manual/en/function.password-verify.php). It autodetects common hash algorithm in the "crypt" format, i.e. $hashType$salt$hash.
