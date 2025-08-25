.. title:: Configuration

Configuration
#############

.. sidebar:: Table of Contents
  .. contents::

All configuration happens in `./config/config.yml`. After initial :doc:`installation <installation>` this file will
just be a copy of the default settings and should be changed according to your requirements and preferences. See below
for a documentation of all available options.

.. hint::

  The default configuration can be found in `./config/config.dist.yml`. You should leave this file untouched because it
  may be overwritten with new defaults when :doc:`upgrading the application <upgrade>`. However, your custom settings
  in `./config/config.yml` are preserved and always take precedence over the defaults.

Repository Name and Contact
===========================

You should set an unique name for the repository and an administrative email address for contacting the repository
owner. Those are shown along some technical information when the *Identify* verb is requested.

.. code-block:: yaml

  # Default settings
  repositoryName: 'OAI-PMH 2.0 Data Provider'
  adminEmail: 'admin@example.org'

Database Connection
===================

This has to be a valid data source name (DSN) URI. The *scheme* is used to specify a driver, the *user* and *password*
in the URI encode user and password for the connection, followed by the *host* and *port* parts. The *path* after the
authority part represents the name of the database (the leading slash is removed so add an extra slash to specify an
absolute file path for SQLite). Any optional *query* parameters are used as additional connection parameters.

.. admonition::

  `%DRIVER%://[[%USER%[:%PASSWORD%]@]%HOST%[:%PORT%]]/%DBNAME%[?%OPTIONS%]`

Since the scheme determines the database driver it also specifies if the PDO abstraction (`mariadb`, `mssql`, `mysql`,
`oracle`, `postgresql`, `sqlite`) or native drivers (`ibm-db2`, `mysqli`, `oci8`, `pgsql`, `sqlite3`, `sqlsrv`) should
be used to handle the connection. Make sure the corresponding PHP extension is installed.

The placeholder `%BASEDIR%` may be used to represent the application's base directory.

Additional connection parameters are database-specific. A list of available options can be found in the `Doctrine DBAL
documentation <https://www.doctrine-project.org/projects/doctrine-dbal/en/4.3/reference/configuration.html#connection-details>`_.

.. code-block:: yaml

  # Default setting
  database: 'sqlite3:///%BASEDIR%/data/sqlite3.db'

  # More examples
  database: 'mssql://user:secret@127.0.0.1/oaipmh'
  database: 'mysql://user@localhost/oai?charset=utf8mb4'
  database: 'pgsql://oaipmh:password@localhost:5432/oai_data_provider'
  database: 'sqlite:////opt/oaipmh/database.db'

.. caution::

  Using the default installation, the OAI-PMH2 Data Provider initializes a new SQLite database in `./data/sqlite3.db`.
  While you can work with the defaults perfectly fine, you may want to move the database file to another location or
  even use any other SQL database of your choice. This is especially recommended if your OAI-PMH2 Data Provider is
  supposed to serve more than a few thousand records or is expected to handle a lot of traffic.

  Run the CLI commands `./bin/cli app:install:db` and `./bin/cli oai:update:formats` after switching to a new database
  to test the settings and initialize the schema!

Metadata Formats
================

The default format is `oai_dc` which is also required by the `OAI-PMH specification <https://www.openarchives.org/pmh/>`_,
but technically you can provide any XML based data formats you want. Just add another entry with the metadata prefix as
key and namespace/schema URIs as array values or replace the default entry (although not recommended).

You do not have to provide every record in each metadata format, but if you have the same record in multiple formats it
is highly recommended to use the same identifier for all versions of the record.

.. code-block:: yaml

  # Default setting
  metadataPrefix: {
    oai_dc: {
      namespace: 'http://www.openarchives.org/OAI/2.0/oai_dc/',
      schema: 'https://www.openarchives.org/OAI/2.0/oai_dc.xsd'
    }
  }

.. caution::

  Run the command `./bin/cli oai:update:formats` after changing metadata prefixes to update the database accordingly!

Deletion Policy
===============

This states if and how the repository keeps track of deleted records. You can delete records by importing empty records
with the same identifier and metadata prefix or by using the command `./bin/cli oai:delete:record`. Depending on the
deleted records policy those records will be either marked as deleted or completely removed from the database.

Valid options are:

`no` - The repository does not provide any information about deletions and deleted records are completely removed from
the database.

`persistent` - The repository provides consistent information about deletions and placeholders for deleted records are
kept in the database.

`transient` - The repository may provide information about deletions. This is handled exactly the same as `persistent`,
but you are allowed to manually prune deleted records from the database (see below).

.. code-block:: yaml

  # Default setting
  deletedRecords: 'transient'

.. hint::

  Run the command `./bin/cli oai:prune:records` after changing the deleted records policy to `no` to remove all deleted
  records from the database.

  If your policy is `transient` and you want to clean up deleted records from the database anyway run the command with
  the `--force` flag.

Harvesting Settings
===================

For larger result sets resumption tokens are provided repeatedly which allow requesting more batches of records until
the set is complete. Here you can configure how many records each batch should contain and how long a resumption token
should be considered valid.

Valid options for records per batch are any number between `1` and `100`.

The expiration time for resumption tokens can be between `300` and `86400` seconds (i. e. from 5 minutes to 24 hours).

.. code-block:: yaml

  # Default settings
  maxRecords: 50
  tokenValid: 1800 # 30 minutes

.. hint::

  Expired resumption tokens can be deleted from database by running the command `./bin/cli oai:prune:tokens`. For good
  :doc:`maintenance <maintenance>` it is recommended to run this command regularly as a cronjob.
