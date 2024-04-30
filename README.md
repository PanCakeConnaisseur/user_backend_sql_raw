# User Backend SQL Raw

[![Latest Release by Semantic Version)](https://img.shields.io/github/v/release/PanCakeConnaisseur/user_backend_sql_raw?sort=semver)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases)
[![Total Downloads](https://img.shields.io/github/downloads/PanCakeConnaisseur/user_backend_sql_raw/total)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases)
[![License](https://img.shields.io/github/license/PanCakeConnaisseur/user_backend_sql_raw)](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/blob/master/LICENSE)
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

This app supports PostgreSQL and MariaDB/MySQL.

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
 //'validation_password_class' => '\Namespace\Of\Your\Class', // You can create a class that implements `\OCA\UserBackendSqlRaw\IHashPassword`
 ),
 ```

There are three types of configuration parameters:

### 1. Database

that *User Backend SQL Raw* will connect to.

| key                | value                                                                                                                    | default value |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------ | ------------- |
| `db_type`          | `postgresql` or `mariadb`                                                                                                | `postgresql`  |
| `db_host`          | your db host such as `localhost` or `db.example.com` or (only for PostgreSQL) path to socket, e.g. `/var/run/postgresql` | `localhost`   |
| `db_port`          | your db port                                                                                                             | `5432`        |
| `db_name`          | your db name                                                                                                             |               |
| `db_user`          | your db user                                                                                                             |               |
| `db_password`      | your db password                                                                                                         |               |
| `db_password_file` | path to file containing the db password                                                                                  |               |
| `mariadb_charset`  | the charset for mariadb connections                                                                                      | `utf8mb4`     |

* Values without a default value are mandatory, except that
  * only one of `db_password` or `db_passowrd_file` must be set.
* Only the first line of the file specified by `db_passowrd_file` is read.
  * Not more than 100 characters of the first line are read.
  * Whitespace-like characters are [stripped](https://www.php.net/manual/en/function.trim.php) from
    the beginning and end of the read password.

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
* Argon2i is only supported by PHP 7.2.0 and higher.
* Argon2id is only supported by PHP 7.3.0 and higher.

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
