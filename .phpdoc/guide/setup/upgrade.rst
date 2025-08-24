.. title:: Upgrading

Upgrading
#########

.. sidebar:: Table of Contents
  .. contents::

The intended and recommended way of upgrading this application is by using the provided self-upgrade tool. This will
upgrade the application to the latest stable release and run all necessary migrations. **Remember to always keep a
backup of your settings and database before upgrading!**

.. caution::

  All commands should be run as your unprivileged Apache/Nginx system user or the PHP-FPM/PHP-CGI user (depending on
  your environment)! This is important in order to avoid later issues due to insufficient access permissions.

  Commands intended to be run as `root` are always prepended with `sudo` throughout this documentation.

Step by Step
============

#. Backup your database and settings (`./config/config.yml`)!

#. Upgrading this application is as easy as running a single command.

   .. code-block:: shell

     # Upgrade to the latest stable release
     bin/cli app:upgrade

   .. hint::

     If you want to upgrade to a specific release you can do so by appending the desired version to the command. Even a
     downgrade or a reinstallation of the current version is supported, but requires the additional `--force` flag to
     prevent accidental downgrades.

     .. code-block:: shell

       # Upgrade to release 1.1.0
       bin/cli app:upgrade v1.1.0

     The following command lists all available releases.

     .. code-block:: shell

       # List all available releases
       bin/cli app:upgrade --list

     If you want to install or list development branches instead of stable releases you can do so by adding the `--dev`
     flag to the respective commands above. But be aware that those are most likely unstable and not supposed to be
     used in production!

#. Congratulations, now you have a new release of the OAI-PMH2 Data Provider up and running!

.. caution::

  It's highly recommended to have look at the :doc:`configuration <configuration>` for any new settings and to
  carefully read the :doc:`release notes <../changelog>` for any hints on further maintenance tasks.
