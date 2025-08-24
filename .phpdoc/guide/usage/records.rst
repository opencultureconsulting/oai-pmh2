.. title:: Managing Records

Managing Records
################

.. sidebar:: Table of Contents
  .. contents::

The purpose of an OAI-PMH2 Data Provider is to serve records via its :abbr:`API (application programming interface)`,
so having a toolset for managing those is essential. There are commands for adding, updating, batch-importing, and
deleting records.

Add or Update Records
=====================

Adding and updating records works in the same way. In fact, `oai:update:record` is just an alias of `oai:add:record`.

.. code-block:: shell

  # Add (or update) a record
  bin/cli oai:add:record <identifier> <format> <file> [sets]

**Required arguments are:**

`<identifier>` - This is the identifier by which the record is referenced throughout the OAI-PMH2 Data Provider. This
must be unique, but if you are providing the same record in multiple formats it should use the same identifier across
all formats.

`<format>` - The metadata format in which the record is provided. This has to be a valid *metadata prefix* from your
:doc:`configuration <../setup/configuration>`.

`<file>` - The absolute or relativ path to an XML file containing the record.

.. caution::

  If you run the command with a pair of `<identifier>` and `<format>` matching an existing record the corresponding
  record will be updated, otherwise a new record will be added.

**Optional arguments are:**

`[sets]` - One or more sets the record should be added to. Each set has to be referenced by its *setSpec*.

.. hint::

  By default non-existing sets are ignored when adding or updating records. If you want to auto-create new sets instead
  you need to use the `--createSets` flag.

  Automatically created sets will have the same *name* as *setSpec* and an empty *description* but you can :doc:`change
  the details <sets>` later.

Batch-Import Records
====================

Instead of adding or updating records one by one you can also import a :abbr:`CSV (comma-separated values)` file with
multiple records at once. The first line must contain the column names, every line after that is interpreted as one
record, empty lines are ignored.

.. code-block:: shell

  # Import records from CSV file
  bin/cli oai:import:csv <format> <file> [--idColumn|--i] [--contentColumn|--c] [--dateColumn|--d] [--setColumn|--s]

**Required arguments are:**

`<format>` - The metadata format in which the records are provided. This has to be a valid *metadata prefix* from your
:doc:`configuration <../setup/configuration>`.

`<file>` - The absolute or relativ path to the CSV file containing the records.

**Important options are:**

`--idColumn|-i` - The name of the column containing the records' identifier; defaults to `identifier`. Identifiers must
be unique, but if you are providing the same record in multiple formats it should use the same identifier across all
formats.

`--contentColumn|-c` - The name of the column containing the records' XML content; defaults to `content`. If this
column is empty the record is deleted (according to your :doc:`deletion policy <../setup/configuration>`).

`--dateColumn|-d` - The name of the column containing the records' date of last change; defaults to `lastChanged`.
Column entries must be valid `ISO 8601 values <https://en.wikipedia.org/wiki/ISO_8601>`_. If no date column is found or
if it is empty the current date and time are assumed as date of last change.

`--setColumn|-d` - The name of the column containing a comma-separated list of the records' sets; defaults to `sets`.
Each set has to be referenced by its *setSpec*. This column is optional and may be omitted or empty.

.. hint::

  By default non-existing sets are ignored when importing records. If you want to auto-create new sets instead you
  need to use the `--createSets` flag.

  Automatically created sets will have the same *name* as *setSpec* and an empty *description* but you can
  :doc:`change the details <sets>` later.

**Optional parameters are:**

`--noValidation` - Disable XML validation of the records' content. This massively speeds up importing, but obviously
comes with the caveat of potentially importing malformed XML which would break the application.

`--purge` - Delete all existing records of the given `<format>` before importing the CSV file. This is an alternative
to providing empty records for deletions. Of course the :doc:`deletion policy <../setup/configuration>` is respected.

.. caution::

  When importing large amounts of records, PHP memory consumption becomes a concern. By default batches are dynamically
  allocated by monitoring memory usage and flushing records to database before reaching the available limit. This is
  the most efficient mode because it limits database operations to a minimum and will work fine in most environments
  but it can lead to fatal errors due to memory exhaustion if estimation fails.

  If you encounter those errors you can set a safe hard limit for the batch size by adding the `--batchSize=X` flag.
  Any number bigger than `0` for `X` tells the application how many records should be kept in memory before flushing to
  database and starting the next batch.

Delete Records
==============

Deleting records is just as easy as adding them.

.. code-block:: shell

  # Delete a record
  bin/cli oai:delete:record <identifier> <format>

Obviously, `<identifier>` and `<format>` have to match an existing record. Depending on your :doc:`deletion policy
<../setup/configuration>` the corresponding record will be either removed or marked as deleted.

Prune Deleted Records
=====================

If your :doc:`deletion policy <../setup/configuration>` was `persistent` or `transient` and you changed it to `no` you
can purge all remaining placeholders for deleted records by running the following command.

.. code-block:: shell

  # Prune deleted records
  bin/cli oai:prune:records

.. hint::

  If your policy is `transient` and you want to clean up deleted records from the database anyway run the command with
  the `--force` flag.
