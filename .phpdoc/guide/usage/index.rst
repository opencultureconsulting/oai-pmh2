.. title:: User Guide

User Guide
##########

Here you will find information about all the :abbr:`CLI (command line interface)` commands to manage records, sets, and
formats in the OAI-PMH2 Data Provider. All commands are self-documenting, i. e. you can call `./bin/cli` for a list of
all available commands and `./bin/cli <command> --help` for more information on a specific command.

.. admonition::

  Other common flags usable with every command are:

  `--verbose` - Sets the verbosity level (e. g. `1`, `2` and `3`, or you can use shortcuts `-v`, `-vv` and `-vvv`).

  `--quiet|-q` - Disables output and interaction (assuming "yes"), except for errors.

  `--no-interaction|-n` - Disables interaction (assuming "yes"), but still produces output.

  `--version|-V` - Outputs the current version of the OAI-PMH2 Data Provider.

  `--ansi|--no-ansi` - Whether to force or disable coloring the output.

Table of Contents
=================

.. toctree::
  :maxdepth: 2

  formats
  records
  sets
  tasks
