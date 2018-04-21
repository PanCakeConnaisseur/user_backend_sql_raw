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
    					'get_home' => 'SELECT home_folder FROM users WHERE username = :username',
    					'create_user' => 'INSERT INTO virtual_users (local, domain, password_hash) VALUES (split_part(:username, \'@\', 1), split_part(:username, \'@\', 2), :password_hash)',
    				),
    			'hash_algorithm_for_new_passwords' => '',
    		),

There are three types of configuration parameters
1. **connection parameters** for database access
    - *db_host* is optional and defaults to `localhost`
    - *db_port* is optional and defaults to `5432`
    - the rest is mandatory
2. **queries** that this app will use to query the db.
    - Queries will be passed verbatim to the
 [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object. You don't need to 
 configure queries for all user attributes. For example if you use the default user home simply 
 leave the query `get_home` empty, i.e. `'get_home' => '',`. User Backend SQl Raw will recognize 
 this and [communicate](https://docs.nextcloud.com/server/13/developer_manual/api/OCP/UserInterface.html#OCP\UserInterface::implementsActions) to Nextcloud that this feature is not available.
        - For user authentication (i.e. login) you need at least `get_password_hash_for_user` and 
    `user_exists`. All other queries are optional.
3. **hash algorithm** (`hash_algorithm_for_new_passwords`) used for creation of new passwords
    - This one is optional and, if you leave it empty, defaults to `bcrypt` ($2y$).
    - The other supported hash algorithms are MD5-CRYPT, SHA-256-CRYPT, SHA-512-CRYPT and Argon2i. 
    The config values are `md5`, `sha256`, `sha512`, `argon2i` respectively, e.g. 
      `'hash_algorithm_for_new_passwords' => 'sha512',`. Or you can explicitly set `bcrypt`.
    - This parameter only sets the hash algorithm used for the creation of new passwords. For
     checking a password the hash algorithm will be [detected automatically](http://php.net/manual/en/function.password-verify.php)
     and all common crypt formats are recognized.
    - Argon2i is only supported by PHP 7.2.0 and higher.
    

### Queries
- The queries use named parameters. You have to use the exact names as shown in the examples. For example to retrieve the hash for a user the query named `get_password_hash_for_user` will be used. Adjust it to your custom SQL query and simply put `:username` where you are referring to the username of the user trying to login.
- All queries must return at most one column (but most queries use only the first anyway)
- `user_exists` should return a boolean. See the example on how to do this properly.
- `get_users` is a query that searches for users and does pattern matching, therefore it should contain a `ILIKE` (`I` for case insensitive)
    - must not already have a `LIMIT` or `OFFSET`. They will be added by the app at the and of the query by the app
    - specify the `LIKE` without `%`, they will be added by the app. This is (unfortunately) necessary because of how prepared statements work.

## Security
- Password length is limited to 100 characters to prevent denial of service attacks against the 
webserver. Otherwise users can supply passwords with 10000 or more characters which can cause a very
 high load for the server when they are run through hashing functions.
- The username during user creation (`create_user`) and the display name (`set_display_name`) are
 not limited in length. You should limit this on the db layer.