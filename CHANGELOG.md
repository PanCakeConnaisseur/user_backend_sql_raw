# Changelog

All notable changes to this project will be documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2024-05-05

### Fixed

* Update app description in appinfo.xml to reflect new features in 2.0.0.

## [2.0.0] - 2024-05-05

### Addded

* DSN-based database connection mechanism. This enables support for socket-based database connections and also connections to Firebird, MS SQL, Oracle DB, ODBC, DB2, SQLite, Informix and IBM databases - basically whatever the [PHP PDO-driver](https://www.php.net/manual/en/pdo.drivers.php) supports. But PostgreSQL remains the only tested database and MySQL/MariaDB to some degree. The other databaes should "just work", but this has not been tested.
* `dsn` configuration key
* dependancy on PHP >=8.0

### Removed

* **Breaking**: removed configuration keys `db_type`, `db_host`, `db_port`, `db_name`, `mariadb_charset`. These settings must now be included in the DSN string. See [README.md](README.md#1-database) on how to do this.
* removed support for Nextcloud <26, because Nextcloud 26 is the first to require PHP 8.0, which this app now also requires


## [1.5.1] - 2024-05-01

### Fixed

* Remove .git files from release archive which were kept by accident.
* Add `db_password_file` example and hint about mandatory db keys for socket configuration to README.md.

## [1.5.0] - 2024-04-30

### Added

* Support reading passwords from a file specified in the `db_password_file`
config key. This is useful for systems where configuration files might be(come)
public or world readable and thus secrets must be kept separately in files.
Examples are NixOS or Docker (secrets).

## [1.4.0] - 2024-04-28

### Added

* check and increase supported version to Nextcloud 29

## [1.3.0] - 2022-05-29

### Added

* [check](https://github.com/nextcloud/server/issues/29914) and increase
  supported version to Nextcloud 24

## [1.2.0] - 2022-05-29

### Added

* check and increase supported version to Nextcloud 23

## [1.1.1] - 2021-06-24

### Added

* check and enable compatibility with Nextcloud 22

## [1.1.0] - 2021-06-07

### Added

* compatibility with Nextcloud 21

### Changed

* app now requires PHP >=7.2
* internal migrations to current Nextcloud APIs

### Removed

* support for Nextcloud < 20, due to migration to newer Nextcloud APIs. Older
  versions of the app will remain in the [Nextcloud app
  store](https://apps.nextcloud.com/apps/user_backend_sql_raw) and support older
  versions of Nextcloud. Alternatively you can find all versions in the
  [releases
  section](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/releases).

### Fixed

* Order of XML elements in appinfo/info.xml now conforms to XSD

## [1.0.12] - 2020-10-07

### Added

* Compatibility with Nextcloud 20

## [1.0.11] - 2020-09-17

### Added

* Compatibility with Nextcloud 19

### Changed

* Migrated changelog from README.md to CHANGELOG.md

## [1.0.10] - 2020-01-15

### Added

* Compatibility with Nextcloud 18
  [728f0fc](https://github.com/PanCakeConnaisseur/user_backend_sql_raw/commit/728f0fc13f4d2ecdc48dde2685d5962f1713fef5)

## [1.0.9] - 2019-12-18

### Added

* Compatibility with Nextcloud 17

## [1.0.8] - 2019-05-31

### Added

* Add support for Argon2id password hashing

## [1.0.7] - 2019-05-30

### Added

* Compatibility with Nextcloud 16

## [1.0.6] - 2018-12-14

### Added

* Compatibility with Nextcloud 15

## [1.0.5] - 2018-12-04

### Fixed

* Fix an issue where the MariaDB/MySQL driver would not handle a LIMIT or OFFSET
  parameter properly.

## [1.0.4] - 2018-12-03

### Fixed

* Fixed code integrity check issues.

## [1.0.3] - 2018-10-14

### Added

* Compatibility with Nextcloud 14

## [1.0.2] - 2018-06-26

### Fixed

* Fixed a typo bug introduced in 1.0.1

## [1.0.1] - 2018-06-26

### Fixed

* Fixed a bug where for some (non security related) operations, SQL errors would
prevent Nextcloud from realizing that that operation failed.

## [1.0.0] - 2018-04-29

### Changed

* Named parameter in query `get_users` was `:username`, is now `:search` because
you search for user names and display names.
