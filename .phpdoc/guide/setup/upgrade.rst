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
     composer app:upgrade

   .. hint::

     If you want to upgrade to a specific release you can do so by appending the desired version to the command. Even a
     downgrade or a reinstallation of the current version is supported, but requires the additional `--force` flag to
     prevent accidental downgrades.

     .. code-block:: shell

       # Upgrade to release 1.2.1
       composer app:upgrade v1.2.1

     The following command lists all available releases.

     .. code-block:: shell

       # List all available releases
       composer app:upgrade --list

     If you want to install or list development branches instead of stable releases you can do so by adding the `--dev`
     flag to the respective commands above. But be aware that those are most likely unstable and not supposed to be
     used in production!

   .. note::

     The repository is put into maintenance mode for the duration of the upgrade and taken out of it again once the
     database migrations have completed successfully. If the upgrade fails, maintenance mode stays on until you fix the
     problem and turn it off manually with `bin/cli app:maintenance:mode off`. Also be aware that maintenance mode is
     always turned off after a successful upgrade, even if it was already on before.

     Before replacing any files, the vendor directory is backed up to `.composer-updater-backup/`. If installing the
     new release or its dependencies fails, the previous version is restored automatically. Database migrations are
     run afterwards and are *not* rolled back, so keep your own backup nevertheless! For more details have a look at
     the documentation of the `updater <https://code.opencultureconsulting.com/composer-updater/>`_.

#. Congratulations, now you have a new release of the OAI-PMH2 Data Provider up and running!

.. hint::

  Releases up to v1.2.0 came with their own upgrade command `bin/cli app:upgrade`. If you are upgrading from one of
  those, install the new upgrade tool first and then upgrade as described above.

  .. code-block:: shell

    composer config allow-plugins.opencultureconsulting/updater true
    composer require opencultureconsulting/updater
    composer app:upgrade

  The current version is shown as "unknown" during this first upgrade, which is no cause for concern. Alternatively,
  you can use the old `bin/cli app:upgrade` one last time instead.

.. caution::

  It's highly recommended to have look at the :doc:`configuration <configuration>` for any new settings and to
  carefully read the :doc:`release notes <../changelog>` for any hints on further maintenance tasks.
