.. title:: Installation

Installation
############

.. sidebar:: Table of Contents
  .. contents::

The intended and recommended way of installing this application is via `Composer <https://getcomposer.org/>`_. First,
make sure all :doc:`requirements <requirements>` are met before trying to install the application.

.. caution::

  All commands should be run as your unprivileged Apache/Nginx system user or the PHP-FPM/PHP-CGI user (depending on
  your environment)! This is important in order to avoid later issues due to insufficient access permissions.

  Commands intended to be run as `root` are always prepended with `sudo` throughout this documentation.

Step by Step
============

#. The following command will get you the latest stable version and all its dependencies, and set up the database with
   some sensible defaults.

   .. code-block:: shell

     # Install the latest stable version suitable for your environment
     composer create-project opencultureconsulting/oai-pmh2 --ask --no-dev

   .. hint::

     If you want to use a version other than the latest available for your environment you can do so by appending the
     desired `version constraint <https://getcomposer.org/doc/articles/versions.md#writing-version-constraints>`_.

     .. code-block:: shell

       # Install the latest patch level version of 1.0 (i. e. >=1.0.0 & <1.1.0)
       composer create-project opencultureconsulting/oai-pmh2 "1.0.*" --ask --no-dev

     Available versions can be found on `Packagist <https://packagist.org/packages/opencultureconsulting/oai-pmh2>`_.

#. To finish installation configure your web server to use the `./public/` directory as document root and `index.php`
   as default entry point.

   .. admonition::

     Visit `http(s)://your-domain.com/?verb=Identify` to verify everything is working as expected.

#. Congratulations, now you have a new OAI-PMH2 Data Provider up and running!

.. caution::

  It's highly recommended to adjust the :doc:`configuration <configuration>` next according to your preferences before
  taking a few steps to ease :doc:`maintenance <maintenance>`.
