# User Backend SQL Raw
This is an app for Nextcloud that offers user management and authentication with arbitrary SQL queries. Only tested with Nextcloud 13. Only PostgreSQL is supported at the moment.

## Configuration
This app has no user interface. All configuration is done via Nextcloud's system configuration in *config/config.php*. The config key is `user_backend_sql_raw.`

    	'user_backend_sql_raw' =>
    		array(
    			'db_host' => 'localhost',
    			'db_port' => '5432',
    			'db_name' => 'theNameOfYourUserDatabase',
    			'db_user' => 'yourDatabaseUser',
    			'db_password' => 'thePasswordforTheDatabaseUser',
    			'queries' =>
    				array(
    					'get_password_hash_for_user' => 'SELECT password FROM virtual_users_fqda WHERE fqda = :username',
    					'user_exists' => 'SELECT EXISTS(SELECT 1 FROM virtual_users_fqda WHERE fqda = :username)',
    					'get_users' => 'SELECT fqda FROM virtual_users_fqda WHERE fqda ILIKE :username',
    					'set_password_hash_for_user' => 'UPDATE virtual_users SET password_hash = :new_password_hash WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
    					'delete_user' => 'DELETE FROM virtual_users WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
    					'get_display_name' => 'SELECT display_name FROM virtual_users WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
    					'set_display_name' => 'UPDATE virtual_users SET display_name  = :new_display_name WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
    					'count_users' => 'SELECT COUNT (*) FROM virtual_users',
    				),
    			'hash_algorithm_for_new_passwords' => '',
    		),

There are three types of configuration parameters
1. **connection parameters** for database access
    - *db_host* is optional and defaults to `localhost`
    - *db_port* is optional and defaults to `5432`
    - the rest is mandatory
2. **queries** that this app will use to query the db. These will be passed verbatim to the [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object.
3. **hash algorithm** used for creation of new passwords
    - This one is optional. By default bcrypt ($2y$) will be used. If all systems using your user db understand bcrypt, you don't need to set this parameter. It only sets the hash algorithm used for creation of new passwords. For checking a password the hash algorithm will be [detected automatically](http://php.net/manual/en/function.password-verify.php) and all common crypt format are recognized. Only override this, if you have an older system using your database. Only MD5-CRYPT, SHA-256-CRYPT and SHA-512-CRYPT are supported. The config values are `md5`, `sha256` or `sha512` respectively, e.g. `'hash_algorithm' => 'sha512'`.

### Queries
- The queries use named parameters. You have to use the exact names as shown in the examples. For example to retrieve the hash for a user the query named `get_password_hash_for_user` will be used. Adjust it to your custom SQL query and simply put `:username` where you are referring to the username of the user trying to login.
- All queries must return at most one column (but most queries use only the first anyway)
- `user_exists` should return a boolean. See the example on how to do this properly.
- `get_users` is a query that searches for users and does pattern matching, therefore it should contain a `ILIKE` (`I` for case insensitive)
    - must not already have a `LIMIT` or `OFFSET`. They will be added by the app at the and of the query by the app
    - specify the `LIKE` without `%`, they will be added by the app. This is (unfortunately) necessary because of how prepared statements work.
    
#### Which queries do I need to provide for what?
- For user authentication (i.e. login) you need at least `get_password_hash_for_user` and `user_exists`.