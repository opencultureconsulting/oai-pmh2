.. title:: Maintenance

Maintenance
###########

When serving large amounts of records the OAI-PMH2 Data Provider splits the result set in batches issuing resumption
tokens for flow control. Those are kept in the database, but expire after a certain time. It is therefore advisable to
set up a cronjob to regularly prune expired tokens. The job should run daily and call the following CLI command.

.. code-block:: shell

  # Make sure to use the right path for your environment
  php /var/www/oai-pmh2/bin/cli oai:prune:tokens --quiet

The `--quiet` flag suppresses all output except error messages which should be redirected and handled properly.

.. hint::

  By default resumption tokens are valid for 30 minutes, but you can :doc:`change that <configuration>` according to
  your requirements.
