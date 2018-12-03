# User Backend SQL Raw
This is an app for Nextcloud that offers user management and authentication with arbitrary SQL 
queries. 

You can authenticate, create, delete users, change their password or display name, basically do (almost) everything that Nextcloud can do with users.

In contrast to the app *SQL user backend*, you write the SQL queries yourself. You are not limited by assumptions that app authors made about how your db is structured.

The app uses prepared statements and is written to be secure by default to prevent SQL injections. It understands the most popular standards for password hash formats: MD5-CRYPT, SHA256-CRYPT, SHA512-CRYPT, BCrypt and the state-of-the-art Argon2i. Because the various formats are recognized on-the-fly your db can can have differing hash string formats at the same time, which eases migration to newer formats.

This app supports PostgreSQL and MariaDB/MySQL.

See [release notes](#release-notes) below for (possibly breaking) changes in new versions.

## Configuration
This app has no user interface. All configuration is done via Nextcloud's system configuration in
 *config/config.php*. This app uses the config key `user_backend_sql_raw.`. The following code shows a 
 complete configuration with all optional parameters commented out.

	'user_backend_sql_raw' => array(
		//'db_type' => 'postgresql',
		//'db_host' => 'localhost',
		//'db_port' => '5432',
		'db_name' => 'theNameOfYourUserDatabase',
		'db_user' => 'yourDatabaseUser',
		'db_password' => 'thePasswordforTheDatabaseUser',
		//'mariadb_charset' => 'utf8mb4',
		'queries' => array(
			'get_password_hash_for_user' => 'SELECT password_hash FROM users_fqda WHERE fqda = :username',
			'user_exists' => 'SELECT EXISTS(SELECT 1 FROM users_fqda WHERE fqda = :username)',
			'get_users' => 'SELECT fqda FROM users_fqda WHERE (fqda ILIKE :search) OR (display_name ILIKE :search)',
			//'set_password_hash_for_user' => 'UPDATE users SET password_hash = :new_password_hash WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
			//'delete_user' => 'DELETE FROM users WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
			//'get_display_name' => 'SELECT display_name FROM users WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
			//'set_display_name' => 'UPDATE users SET display_name = :new_display_name WHERE local = split_part(:username, \'@\', 1) AND domain = split_part(:username, \'@\', 2)',
			//'count_users' => 'SELECT COUNT (*) FROM users',
			//'get_home' => '',
			//'create_user' => 'INSERT INTO users (local, domain, password_hash) VALUES (split_part(:username, \'@\', 1), split_part(:username, \'@\', 2), :password_hash)',
		),
		//'hash_algorithm_for_new_passwords' => 'bcrypt',
	),


There are three types of configuration parameters
1. **connection parameters** for database access
	- *db_type* is optional and defaults to `postgresql`
	- *db_host* is optional and defaults to `localhost`
	- *db_port* is optional and defaults to `5432`
	- *db_name*, *db_user* and *db_password* are mandatory
	- *mariadb_charset* sets the charset for mariadb connections, is optional and defaults to `utf8mb4`
2. **queries** this app will use to query the db.
	- The queries use named parameters. You have to use the exact names as shown in the examples. For
 example to retrieve the hash for a user the query named `get_password_hash_for_user` will be 
 used. Write your custom SQL query and simply put `:username` where you are referring to 
 the username (aka uid) of the user trying to login.
	- You don't need to supply all queries. For example if you use the default user home simply 
 leave the query `get_home` commented. This app will recognize 
 this and [communicate](https://docs.nextcloud.com/server/13/developer_manual/api/OCP/UserInterface.html#OCP\UserInterface::implementsActions) to Nextcloud that this feature is not available.
		- `user_exists` and `get_users` are required, the rest is optional.
		-  For user authentication (i.e. login) you need at least `get_password_hash_for_user`, 
	`user_exists` and `get_users`.
3. **hash algorithm** (`hash_algorithm_for_new_passwords`) used for creation of new passwords
	- This one is optional and, if you leave it empty, defaults to `bcrypt` ($2y$).
	- The other supported hash algorithms are MD5-CRYPT, SHA-256-CRYPT, SHA-512-CRYPT and Argon2i. 
	The config values are `md5`, `sha256`, `sha512`, `argon2i` respectively, e.g. 
	  `'hash_algorithm_for_new_passwords' => 'sha512',`. Or you can explicitly set `bcrypt`.
	- This parameter only sets the hash algorithm used for the creation of new passwords. For
	 checking a password the hash algorithm will be [detected automatically](http://php.net/manual/en/function.password-verify.php)
	 and all common crypt formats are recognized.
		- This means, that your db can have different hash formats simultaneously. Whenever a 
		user's password is changed, it will be updated to the configured hash algorithm. This eases 
		 migration to more modern algorithms.
	- Argon2i is only supported by PHP 7.2.0 and higher.

### Queries
- For all queries that read data, only the first column is interpreted.
- Two queries need a little bit of attention:
	1. `user_exists` should return a boolean. See the example on how to do this properly.
	2. `get_users` is a query that search for usernames (e.g. *bob*) and display names (e.g. *Bob Bobson*) and returns usernames
		- make sure the query looks through both usernames **and** display names, see example config
		- do case insensitive pattern matching, i.e. `ILIKE` (`ILIKE` only available in PostgreSQL)
		- query must not already contain a `LIMIT` or `OFFSET`. They will be added to the end of your query by
	      this app
		- specify the `LIKE` without `%`, they will be added by the app. This is due to how prepared
		  statements work. Again, see the example.
- Queries are passed verbatim to the
   [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object.

## Security
- Password length is limited to 100 characters to prevent denial of service attacks against the 
web server. Otherwise users can supply passwords with 10000 or more characters which can cause a very
 high load for the server when they are run through hashing functions.
- The username during user creation (`create_user`) and the display name (`set_display_name`) are
 not limited in length. You should limit this on the db layer.
 
## Troubleshooting
- **TL;DR**: check the log file
- This app has no UI, therefore all error output (exceptions and explicit logs) is written to [Nextcloud's log](https://docs.nextcloud.com/server/13/admin_manual/configuration_server/logging_configuration.html), 
by default  */var/www/nextcloud/data/nextcloud.log* or */var/log/syslog*.
- There are no semantic checks for the SQL queries. As soon as a query string
  is not empty the app assumes that it is a query and executes it. It's likely that you will 
  have typos in your SQL queries. Check the log to find out if and why SQL queries fail.
- This app also logs non-SQL configuration errors, e.g. missing db name.

# Release Notes
## 1.0.4
- Fixed code integrity check issues.
## 1.0.3
- Compatibility with Nextcloud 14
## 1.0.2
- Fixed a typo bug introduced in 1.0.1
## 1.0.1
- Fixed a bug where for some (non security related) operations, SQL errors would prevent Nextcloud
from realizing that that operation failed.
## 1.0.0
- Named parameter in query `get_users` was `:username`, is now `:search` because you search for
user names and display names.