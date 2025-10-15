.. title:: Changelog

Changelog
#########

.. sidebar:: Table of Contents
  .. contents::

All available versions as well as further information about :doc:`requirements and dependencies <setup/requirements>`
can be found in the `Packagist repository <https://packagist.org/packages/opencultureconsulting/oai-pmh2>`_ and on the
`GitHub releases page <https://github.com/opencultureconsulting/oai-pmh2/releases>`_.

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
