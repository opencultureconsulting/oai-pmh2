.. title:: Metadata Formats

Metadata Formats
################

For each metadata format you want to serve you need to define a *metadata prefix*, provide the corresponding *XML
namespace* and link its *validation schema*. This is done in the :doc:`configuration file <../setup/configuration>`
(`./config/config.yml`).

To synchronize the configuration with the database run the following command each time you changed your metadata
configuration.

.. code-block:: shell

  # Synchronize metadata formats in database with configuration
  bin/cli oai:update:formats

.. caution::

  When removing a metadata format all associated records will be purged as well!

The command can also be used to list all currently available metadata formats.

.. code-block:: shell

  # List all available metadata formats
  bin/cli oai:update:formats --list
