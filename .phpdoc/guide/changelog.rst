.. title:: Changelog

Changelog
#########

.. sidebar:: Table of Contents
  .. contents::

All available versions as well as further information about :doc:`requirements and dependencies <setup/requirements>`
can be found in the `Packagist repository <https://packagist.org/packages/opencultureconsulting/oai-pmh2>`_ and on the
`GitHub releases page <https://github.com/opencultureconsulting/oai-pmh2/releases>`_.

Unreleased
==========

**Minor Changes:**

* Fixed `setSpec` elements in record headers containing the set's name instead of its spec
* Fixed selective harvesting by set returning all records instead of only the records in that set (and its subsets)
* Fixed set specs containing `_` matching unrelated sets in selective harvesting
* Fixed incomplete lists and repeated records when harvesting with resumption tokens by ordering records and sets
  consistently
* Fixed day granularity `until` arguments excluding records changed during that day
* Fixed invalid dates (e.g. `2024-02-30`) or datestamps with wrong syntax being accepted as `from` and `until` arguments
* Fixed datestamps depending on the server's timezone setting; all datestamps are now stored and returned in UTC, and
  dates with a timezone offset (e.g. from CSV imports) are converted accordingly
* Fixed repeated arguments (e.g. `verb=Identify&verb=Identify`) not resulting in a `badVerb` or `badArgument` error
* Fixed `POST` requests being rejected when the `Content-Type` header contains a `charset` parameter
* Fixed `<baseURL>`, `<request>` and the stylesheet URL missing a non-default port
* Fixed double-escaped special characters in the `<request>` element's attributes
* Fixed updating or deleting records with sets failing with a Doctrine error, especially when new sets were created
  with `--createSets`
* Fixed removing metadata formats, records or sets leaving orphaned data in SQLite databases, because foreign key
  constraints were not enforced
* Fixed stale cached set lists after adding sets or pruning orphaned sets
* Fixed a deprecation warning on PHP 8.4 for an implicitly nullable parameter
* Resumption tokens are now generated with a cryptographically secure random number generator, which makes them
  unpredictable and prevents collisions of tokens issued at the same time
* An invalid or expired resumption token for `ListIdentifiers` and `ListRecords` now only results in a
  `badResumptionToken` error instead of an additional `badArgument` error
* Empty record identifiers, set specs and metadata prefixes are no longer accepted (e.g. from an empty CSV column)
* Invalid XML no longer causes PHP warnings in addition to the validation error
* `oai:add:record` now fails with an error for unsupported metadata formats instead of reporting success

**Maintenance:**

* Added a test suite for PHPUnit (run with `composer phpunit:test`), which is run for every supported PHP version (8.2
  to 8.5) on GitHub
* The documentation is no longer part of the repository, but built and deployed to
  `GitHub Pages <https://code.opencultureconsulting.com/oai-pmh2/>`_ automatically for every release. To read it
  offline, build it locally with `composer phpdoc:build`
* Updated Psalm to version 7 and PHP Mess Detector to version 3

v1.1.3
======

**Minor Changes:**

* Updated `Guzzle PSR-7 <https://github.com/guzzle/psr7>`_ to version 2.13 to mitigate a vulnerability in `guzzlehttp/psr7`
* Updated `Symfony components <https://symfony.com/>`_ to version 7.4.15 to mitigate a vulnerability in `symfony/cache`

v1.1.2
======

**Minor Changes:**

* Updated `Composer <https://getcomposer.org/>`_ to version 2.9.7 to mitigate a vulnerability in `composer/composer`

v1.1.1
======

**Minor Changes:**

* Updated `Symfony components <https://symfony.com/>`_ to version 7.4 to mitigate a vulnerability in `symfony/process`

v1.1.0
======

**New Features:**

* Added a :doc:`maintenance mode <usage/tasks>` for CLI operations to prevent web requests during long-running tasks
* Dropped support for PHP 8.1 since it is no longer maintained by the PHP development team

**Maintenance:**

* Updated dependencies

v1.0.4
======

**Minor Changes:**

* Fixed some edge cases returning the wrong error codes according to `Cornell University's OAI-PMH validation service
  <https://www.openarchives.org/Register/ValidateSite>`_

v1.0.3
======

**Minor Changes:**

* Fixed a bug in the *Identify* response when DQL returns a `string` instead of a `DateTime` object
* Fixed a bug in the self-update command which failed to initialize the filesystem handler

v1.0.2
======

**Minor Changes:**

* Fixed date formatting for earliest datestamp

v1.0.1
======

**Minor Changes:**

* Reworked upgrade process and database migrations

**Maintencance:**

* Updated dependencies

v1.0.0
======

**Initial Release**
