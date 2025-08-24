.. title:: Organizing Sets

Organizing Sets
###############

.. sidebar:: Table of Contents
  .. contents::

Records can be organized in sets. Each set may contain multiple records while each record may be part of any number of
sets. Sets consist of a *setSpec*, a *name*, and optionally a *description*.

The *setSpec* is the primary identifier by which a set is referenced. Colons (`:`) in a *setSpec* have special purpose
as they divide hierarchical sub-sets.

.. hint::

  For example, two sets with *setSpec* `manuscripts:medieval` and `manuscripts:modern` are considered sub-sets of the
  set `manuscripts`. Even if you never associated any record directly with the latter this virtual set can be harvested
  and will return all records of both sub-sets.

Add or Update Sets
==================

Adding and updating sets works in the same way. In fact, `oai:update:set` is just an alias of `oai:add:set`.

.. code-block:: shell

  # Add (or update) a set
  bin/cli oai:add:set <setSpec> [setName] [file]

**The arguments are:**

`<setSpec>` - The setSpec is the primary identifier by which a set is referenced and therefore must be unique.

`[setName]` - The human-readable name of the set. If omitted, by default the `<setSpec>` is also used as name.

`[file]` - The absolute or relative path to an XML file containing a set description. This is shown if the *ListSets*
verb is requested. Set descriptions are optional.

.. caution::

  If you run the command with a `<setSpec>` matching an existing set the corresponding set will be updated, otherwise
  a new set will be added.

Delete Sets
===========

Deleting sets is just as easy as adding them.

.. code-block:: shell

  # Delete a set
  bin/cli oai:delete:set <setSpec>

Obviously, `<setSpec>` has to match an existing set. The corresponding set will be deleted.

.. caution::

  Deleting a set will not delete any records!

Prune Orphaned Sets
===================

Deleting records does not remove their respective sets, even if it is the last record associated with a set. To prune
empty sets you can run the following command.

.. code-block:: shell

  # Prune empty sets
  bin/cli oai:prune:sets
