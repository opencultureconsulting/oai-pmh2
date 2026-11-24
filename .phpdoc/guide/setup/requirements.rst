.. title:: Requirements

Requirements
############

.. sidebar:: Table of Contents
  .. contents::

Environment
===========

This application requires at least **PHP 8.2**. In addition, you will need the database API extension of your
preferred DBMS (e. g. `mysqli` or `pdo_mysql`) if you don't want to use the default SQLite database. For performance
reasons SQLite is only recommended if you have no more than a few thousand records to serve.

For easy :doc:`deployment <installation>`, :doc:`upgrades <upgrade>` and dependency management you need
`Composer <https://getcomposer.org/>`_ 2.3 or newer up and running, too. Composer in turn needs either PHP extension
`zip` or the `unzip` command to extract downloaded packages.

Dependencies
============

Although all application-level dependencies are handled by `Composer <https://getcomposer.org/>`_ (or are bundled with
the installation package) we would like to make some honorable mentions here:

Without great frameworks like `Symfony <https://symfony.com/>`_ and `Doctrine <https://www.doctrine-project.org/>`_
this application wouldn't be feasible. For HTTP request handling it uses the PSR-15 queue-based request handler of the
`opencultureconsulting/psr15 <https://packagist.org/packages/opencultureconsulting/psr15>`_ package.
