Neo4j PHP Client and Driver
###########################

|license| |maintainability| |version-support|

|workflow-status| |stable-version| |commits-since-stable|

|total-downloads| |monthly-downloads| |discord|


Control to worlds' most powerful graph database
===============================================

This package make its trivial to connect, query and control Neo4j:

- Pick and choose your drivers with easy configuration
- Intuitive API
- Extensible
- Designed, built and tested under close supervision with the official neo4j driver team
- Validated with `testkit <https://github.com/neo4j-drivers/testkit>`_
- Fully typed with `psalm <https://psalm.dev/>`_
- Bolt, HTTP and auto routed drivers available

.. code-block:: php

    // Create a Neo4j Driver connecting to neo4j.test.com
    $driver = \Laudis\Neo4j\Basic\Driver::create('neo4j://neo4j.test.com');
    // Create a session to run queries on
    $session = $driver->createSession();

    // Send a parametrised query to the database
    $result = $session->run(<<<'CYPHER'
    MATCH (p:Person) - [:Loves] -> (db:Database {name: $name})
    RETURN p.name AS name
    LIMIT 100
    CYPHER, ['name' => 'neo4j']);

    // Pluck the names from the results as an array.
    $result->pluck('name')->toArray();

Refer to the :doc:`overview page<overview>` for a more comprehensive quickstart.

User Guide
==========

.. toctree::
    :maxdepth: 2

    installation
    overview
    driver-selection
    querying
    results
    configuration
    architecture
    high-availability
    testing
    faq-common-issues
    license
    reporting-a-security-vulnerability
    contributing


.. |license| image:: https://img.shields.io/github/license/neo4j-php/neo4j-php-client
    :alt: License
    :target: https://github.com/laudis-technologies/neo4j-php-client/blob/main/LICENSE

.. |maintainability| image:: https://img.shields.io/codeclimate/maintainability/laudis-technologies/neo4j-php-client
    :alt: Code Climate maintainability
    :target: https://codeclimate.com/github/laudis-technologies/neo4j-php-client/maintainability

.. |version-support| image:: https://img.shields.io/packagist/php-v/laudis/neo4j-php-client
    :alt: Packagist PHP Version Support
    :target: https://packagist.org/packages/laudis/neo4j-php-client

.. |workflow-status| image:: https://img.shields.io/github/workflow/status/neo4j-php/neo4j-php-client/Full%20Test/main
    :alt: Github Workflow Status Main Branch
    :target: https://github.com/neo4j-php/neo4j-php-client/actions

.. |stable-version| image:: https://poser.pugx.org/laudis/neo4j-php-client/v
    :alt: Latest Stable Version
    :target: https://packagist.org/packages/laudis/neo4j-php-client

.. |commits-since-stable| image:: https://img.shields.io/github/commits-since/neo4j-php/neo4j-php-client/latest
    :alt: Commits Since Latest stable version
    :target: https://github.com/neo4j-php/neo4j-php-client/commits/main

.. |total-downloads| image:: https://img.shields.io/packagist/dt/laudis/neo4j-php-client
    :alt: Total Downloads
    :target: https://packagist.org/packages/laudis/neo4j-php-client/stats

.. |monthly-downloads| image:: https://img.shields.io/packagist/dm/laudis/neo4j-php-client
    :alt: Monthly Downloads
    :target: https://packagist.org/packages/laudis/neo4j-php-client/stats

.. |discord| image:: https://img.shields.io/discord/787399249741479977
    :alt: Chat On Discord
    :target: https://discord.com/channels/787399249741479977
