.. title:: Maintenance Tasks

Maintenance Tasks
#################

.. sidebar:: Table of Contents
  .. contents::

Additional commands are provided for common maintenance tasks like upgrading the application, migrating the database
and pruning expired resumption tokens.

Pruning Expired Tokens
======================

When serving large amounts of records the OAI-PMH2 Data Provider splits the result set in batches issuing resumption
tokens for flow control. Those are kept in the database, but expire after a certain time. It is therefore advisable to
regularly remove expired tokens.

.. code-block:: shell

  # Delete expired tokens
  bin/cli oai:prune:tokens

Instead of running this command manually over and over again it is recommended to :doc:`set up a maintenance cronjob
<../setup/maintenance>`.

Performing Upgrades
===================

Upgrading the OAI-PMH2 Data Provider is as easy as running a single command.

.. code-block:: shell

  # Upgrade to the latest stable release
  bin/cli app:upgrade

.. hint::

  For more information and step-by-step instructions visit the :doc:`documentation on upgrading <../setup/upgrade>`.

Initializing a new database or migrating an existing database after a manual upgrade can be performed by running the
following command. (`app:install:db` and `app:upgrade:db` are aliases of the same command.)

.. code-block:: shell

  # Migrate (or initialize) the database
  bin/cli app:upgrade:db

.. caution::

  Be aware that migrations can potentially lead to data loss. **Always keep a backup when performing upgrades and
  migrations!**
