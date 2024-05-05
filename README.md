# User Backend SQL Raw

[![Latest Release by Semantic Version)](https://img.shields.io/github/v/release/PanCakeConnaisseur/user_backend_sql_raw?sort=semver)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases)
[![Total Downloads](https://img.shields.io/github/downloads/PanCakeConnaisseur/user_backend_sql_raw/total)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases)
![Tests Status](https://img.shields.io/github/actions/workflow/status/PanCakeConnaisseur/user_backend_sql_raw/tests.yml?branch=master)

This is an app for Nextcloud that offers user management and authentication with
arbitrary SQL queries.

You can authenticate, create, delete users, change their password or display
name, basically do (almost) everything that Nextcloud can do with users.

In contrast to the app *SQL user backend*, you write the SQL queries yourself.
You are not limited by assumptions that app authors made about how your db is
structured.

The app uses prepared statements and is written to be secure by default to
prevent SQL injections. It understands the most popular standards for password
hash formats: MD5-CRYPT, SHA256-CRYPT, SHA512-CRYPT, BCrypt, Argon2i and
Argon2id. Because the various formats are recognized on-the-fly your db can can
have differing hash string formats at the same time, which eases migration to
newer formats.

This app primarily supports PostgreSQL and MariaDB/MySQL but the underlying PHP
[mechanism](https://www.php.net/manual/en/pdo.drivers.php) also supports
Firebird, MS SQL, Oracle DB, ODBC, DB2, SQLite, Informix and IBM databases. By
using an appropriate DSN you should be able to connect to these databases. This
has not been tested, though.

See [CHANGELOG.md](CHANGELOG.md) for changes in newer versions. This app follows
semantic versioning and there should not be any breaking changes unless the
major version has changed.

## Installation

You can find *User Backend SQL Raw* in the *Security* category of the Nextcloud
app store inside your Nextcloud instance.

## Configuration

This app has no user interface. All configuration is done via Nextcloud's system
 configuration in *config/config.php*. This app uses the config key
 `user_backend_sql_raw`. The following code shows a complete configuration with
 all optional parameters commented out.

```php
 'user_backend_sql_raw' => array(
   'dsn' => 'pgsql:host=/var/run/postgresql;dbname=theNameOfYourUserDb',
   //'db_user' => 'yourDatabaseUser',
   //'db_password' => 'thePasswordForTheDatabaseUser',
   //'db_password_file' => '/path/to/file/ContainingThePasswordForTheDatabaseUser',
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
 ```

There are three types of configuration parameters:

### 1. Database

that *User Backend SQL Raw* will connect to.

* `dsn`: check how to construct DSNs for [PostgreSQL](https://www.php.net/manual/en/ref.pdo-pgsql.connection.php) and [MySQL](https://www.php.net/manual/en/ref.pdo-mysql.connection.php).
* `db_user`: user that will be used to connect to the database
* `db_password`: password for the user that will be used to connect to the database
* `db_password_file`: Can be set to read the password from a file
  * Only the first line of the file specified by `db_password_file` is read.
  * Not more than 100 characters of the first line are read.
  * Whitespace-like characters are [trimmed](https://www.php.net/manual/en/function.trim.php) from
    the beginning and end of the read password.

There are two methods to configure the database connection:

1. Set `dsn` to a DSN that contains the entire db connnection configuration including the db user and db password
2. Set `dsn` to a DSN that contains everything **but** the db user and db password and then set `db_user` and `db_password`/`db_password_file`

PostgreSQL works with method 1 and 2. MySQL works only with method 2. If you use `db_password_file` also set `db_user` (even for PostgreSQL) and don't put the username in the DSN. This is because, the underlying PDO classes have some quirks and diverge from the documented behaviour. So, better don't mix both methods. `db_password_file` has higher priority than `db_password`, but lower priority than password in DSN. But it's better to only set one source for the password, for the same reasons.

#### Examples

* connect to PostgreSQL via a socket with ident authentication which requires no user or password at all:

  ```php
  'dsn' => 'pgsql:host=/var/run/postgresql;dbname=theNameOfYourUserDb',
  ```

* connect to PostgreSQL via TCP and user/password authentication:
  ```php
  'dsn' => 'pgsql:host=localhost;port=5432;dbname=theNameOfYourUserDb;user=theNameOfYourDbUser;password=thePasswordForTheDbUser',
  ```
* connect to PostgreSQL via TCP and user/password authentication and use password file:

  ```php
  'dsn' => 'pgsql:host=localhost;port=5432;dbname=theNameOfYourUserDb',
  'db_user' => 'theNameOfYourDbUser',
  'db_password_file' => '/path/to/password_file',
  ```

* connect to MySQL via socket which requires no user or password at all:

  ```php
  'dsn' => 'mysql:unix_socket=/var/run/mysql/mysql.sock;dbname=theNameOfYourUserDb',
  ```
  
* connect to MySQL via TCP and user/password authentication:

  ```php
  'dsn' => 'mysql:host=localhost;port=3306;dbname=testdb',
  'db_user' => 'theNameOfYourDbUser',
  'db_password' => 'thePasswordForTheDbUser', // or db_password_file instead
  ```

For other databases check their [PDO driver documentation pages](https://www.php.net/manual/en/pdo.drivers.php) which in-turn link to their respective DSN references. They either use method 1 or method 2 AFAICS.

### 2. SQL Queries

that will be used to read/write data.

* queries use named parameters. You have to use the exact names as shown in the examples. For
 example, to retrieve the hash for a user, the query named `get_password_hash_for_user` will be
 used. Write your custom SQL query and simply put `:username` where you are referring to the
 username (aka uid) of the user trying to login.
* You don't need to supply all queries. For example, if you use the default user home simply leave
 the query `get_home` commented. This app will recognize this and
 [communicate](https://github.com/nextcloud/server/blob/316acc3cc313f4333fe29d136f9124f163b40dec/lib/public/UserInterface.php#L47)
 to Nextcloud that this feature is not available.
  * `user_exists` and `get_users` are required, the rest is optional.
  * For user authentication (i.e. login) you need at least `get_password_hash_for_user`,
    `user_exists` and `get_users`.
* For all queries that read data, only the first column is interpreted.
* Two queries require a little bit of attention:
    1. `user_exists` should return a boolean. See the example on how to do this properly.
    2. `get_users` is a query that searches for usernames (e.g. *bob*) and display names (e.g. *Bob
       Bobson*) and returns usernames
        * make sure the query looks through both usernames **and** display names, see example config
        * do case insensitive pattern matching, i.e. `ILIKE` (`ILIKE` only available in PostgreSQL)
        * query must not already contain a `LIMIT` or `OFFSET`. They will be added to the end of
          your query by this app
        * specify the `LIKE` without `%`, they will be added by the app. This is due to how prepared
          statements work. Again, see the example.
* Technical Info: Queries are passed verbatim to the
    [prepare()](http://php.net/manual/en/pdo.prepare.php) method of a PDO object.

### 3. Hash Algorithm For New Passwords

used for the creation of new passwords.

* is optional and, if you leave it empty, defaults to `bcrypt` ($2y$).
* Other supported hash algorithms are MD5-CRYPT, SHA-256-CRYPT, SHA-512-CRYPT, Argon2i and Argon2id.
The config values are `md5`, `sha256`, `sha512`, `argon2i`, `argon2id` respectively, e.g.
  `'hash_algorithm_for_new_passwords' => 'argon2id',`. Or you can explicitly set `bcrypt`.
* This parameter only sets the hash algorithm for the creation of new passwords. For
 checking an existing password the hash algorithm will be [detected automatically](http://php.net/manual/en/function.password-verify.php)
 and all common crypt formats are recognized.
  * This means, that your db can have different hash formats simultaneously. Whenever a
    user's password is changed, it will be updated to the configured hash algorithm. This eases
     migration to more modern algorithms.

## Security

* Password length is limited to 100 characters to prevent denial of service attacks against the
web server. Without a limit, malicious users could feed your Nextcloud instance with passwords that have a length of tens of thousands of characters, which could cause a very
 high load due to expensive password hashing operations.
* The username during user creation (`create_user`) and the display name (`set_display_name`) are
 not limited in length. You should limit this on the db layer.

## Troubleshooting

* **TL;DR**: check the log file
* This app has no UI, therefore all error output (exceptions and explicit logs) is written to [Nextcloud's log](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/logging_configuration.html),
by default  */var/www/nextcloud/data/nextcloud.log* or */var/log/syslog*. Log level 3 is sufficient for all non-debug output.
* There are no semantic checks for the SQL queries. As soon as a query string
  is not empty the app assumes that it is a query and executes it. It's likely that you will
  have typos in your SQL queries. Check the log to find out if and why SQL queries fail.
* **Pro Tip**: use *jq* to parse and format Nextcloud's JSON logfile
  * if not installed: `apt install jq`
  * watch logfile starting at the bottom:

    ```bash
    jq -C 'select (.app=="user_backend_sql_raw")' /var/www/nextcloud/data/nextcloud.log  | less -R +G
    ```

    * `-C` enables colored output, later for *less* `-R` keeps it
    * the `select` defines a filter to only show entries where the key `app` is set to this app's name
    * `+G` jumps to end of file
    * *less* does not auto-update, you need to quit using <kbd>q</kbd> and start again
* This app also logs non-SQL configuration errors, e.g. missing db name.
