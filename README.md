# User Backend SQL Raw
[![Latest Release by Semantic Version)](https://img.shields.io/github/v/release/PanCakeConnaisseur/user_backend_sql_raw?sort=semver)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases)
[![Total Downloads](https://img.shields.io/github/downloads/PanCakeConnaisseur/user_backend_sql_raw/total)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases)
[![License](https://img.shields.io/github/license/PanCakeConnaisseur/user_backend_sql_raw)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/blob/master/LICENSE)
[![Test Status](https://img.shields.io/github/workflow/status/PanCakeConnaisseur/user_backend_sql_raw/tests/master)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/actions?query=workflow%3Atests)

This is an app for Nextcloud that offers user management and authentication with arbitrary SQL queries. 

You can authenticate, create, delete users, change their password or display name, basically do (almost) everything that Nextcloud can do with users.

In contrast to the app *SQL user backend*, you write the SQL queries yourself. You are not limited by assumptions that app authors made about how your db is structured.

The app uses prepared statements and is written to be secure by default to prevent SQL injections. It understands the most popular standards for password hash formats: MD5-CRYPT, SHA256-CRYPT, SHA512-CRYPT, BCrypt, Argon2i and Argon2id. Because the various formats are recognized on-the-fly your db can can have differing hash string formats at the same time, which eases migration to newer formats.

This app supports PostgreSQL and MariaDB/MySQL.

See [CHANGELOG.md](CHANGELOG.md) for changes in newer versions. This app follows semantic versioning and there should not be any breaking changes unless the major version has changed. 

## Installation
You can find *User Backend SQL Raw* in the *Security* category of the Nextcloud app store inside your Nextcloud instance.

## Configuration
This app has no user interface. All configuration is done via Nextcloud's system configuration in
 *config/config.php*. This app uses the config key `user_backend_sql_raw`. The following code shows a 
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


There are three types of configuration parameters:
### 1. Database
that *User Backend SQL Raw* will connect to
- *db_type* is optional and defaults to `postgresql`. The only other valid non-empty value is `mariadb`, which can be used for MySQL, too.
- *db_host* is optional and defaults to `localhost`
- *db_port* is optional and defaults to `5432`
- *db_name*, *db_user* and *db_password* are mandatory
- *mariadb_charset* sets the charset for mariadb connections, is optional and defaults to `utf8mb4`

### 2. SQL Queries
that will be used to read/write data
- queries use named parameters. You have to use the exact names as shown in the examples. For
 example, to retrieve the hash for a user, the query named `get_password_hash_for_user` will be 
 used. Write your custom SQL query and simply put `:username` where you are referring to 
 the username (aka uid) of the user trying to login.
- You don't need to supply all queries. For example, if you use the default user home simply 
 leave the query `get_home` commented. This app will recognize 
 this and [communicate](https://docs.nextcloud.com/server/13/developer_manual/api/OCP/UserInterface.html#OCP\UserInterface::implementsActions) to Nextcloud that this feature is not available.
    - `user_exists` and `get_users` are required, the rest is optional.
    -  For user authentication (i.e. login) you need at least `get_password_hash_for_user`, 
	`user_exists` and `get_users`.
    
 - For all queries that read data, only the first column is interpreted.
 - Two queries require a little bit of attention:
    1. `user_exists` should return a boolean. See the example on how to do this properly.
    2. `get_users` is a query that searches for usernames (e.g. *bob*) and display names (e.g. *Bob Bobson*) and returns usernames
        - make sure the query looks through both usernames **and** display names, see example config
        - do case insensitive pattern matching, i.e. `ILIKE` (`ILIKE` only available in PostgreSQL)
        - query must not already contain a `LIMIT` or `OFFSET`. They will be added to the end of your query by
          this app
        - specify the `LIKE` without `%`, they will be added by the app. This is due to how prepared
          statements work. Again, see the example.
 - Technical Info: Queries are passed verbatim to the
    [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object.
	
### 3. Hash Algorithm For New Passwords
used for the creation of new passwords
- is optional and, if you leave it empty, defaults to `bcrypt` ($2y$).
- Other supported hash algorithms are MD5-CRYPT, SHA-256-CRYPT, SHA-512-CRYPT, Argon2i and Argon2id. 
The config values are `md5`, `sha256`, `sha512`, `argon2i`, `argon2id` respectively, e.g. 
  `'hash_algorithm_for_new_passwords' => 'argon2id',`. Or you can explicitly set `bcrypt`.
- This parameter only sets the hash algorithm for the creation of new passwords. For
 checking an existing password the hash algorithm will be [detected automatically](http://php.net/manual/en/function.password-verify.php)
 and all common crypt formats are recognized.
    - This means, that your db can have different hash formats simultaneously. Whenever a 
    user's password is changed, it will be updated to the configured hash algorithm. This eases 
     migration to more modern algorithms.
- Argon2i is only supported by PHP 7.2.0 and higher.
- Argon2id is only supported by PHP 7.3.0 and higher.


## Security
- Password length is limited to 100 characters to prevent denial of service attacks against the 
web server. Without a limit, malicious users could feed your Nextcloud instance with passwords that have a length of tens of thousands of characters, which could cause a very
 high load due to expensive password hashing operations.
- The username during user creation (`create_user`) and the display name (`set_display_name`) are
 not limited in length. You should limit this on the db layer.
 
## Troubleshooting
- **TL;DR**: check the log file
- This app has no UI, therefore all error output (exceptions and explicit logs) is written to [Nextcloud's log](https://docs.nextcloud.com/server/20/admin_manual/configuration_server/logging_configuration.html), 
by default  */var/www/nextcloud/data/nextcloud.log* or */var/log/syslog*. Log level 3 is sufficient for all non-debug output.
- There are no semantic checks for the SQL queries. As soon as a query string
  is not empty the app assumes that it is a query and executes it. It's likely that you will 
  have typos in your SQL queries. Check the log to find out if and why SQL queries fail.
- **Pro Tip**: use *jq* to parse and format Nextcloud's JSON logfile
    * if not installed: `apt install jq`
    * watch logfile starting at the bottom:
        ```bash
        jq -C . nextcloud.log  | less -R +G
        ```
        
        * `-C` enables colored output, later `-R` keeps it
        * `+G` jumps to end of file
        * don't forget the single period `.` after `-C` it's not a typo and means "don't filter anything"
        * *less* does not auto-update, you need to quit using <kbd>q</kbd> and start again 
- This app also logs non-SQL configuration errors, e.g. missing db name.
