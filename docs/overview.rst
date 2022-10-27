========
Overview
========

This page provides a quick introduction to the driver and introductory examples.
If you have not already installed it, head over to the :doc:`installation <installation>`
page.

Quickstart
==========

.. _creating a driver:

Creating a Driver
-----------------

A driver manages connections and spawns session you can run queries on. Please refer to the :ref:`client section<creating a client>` if you want to use a streamlined interface to run queries using different drivers automatically.

.. code-block:: php

    use Laudis\Neo4j\Basic\Driver;

    // Create a driver with a neo4j connection
    $driver = Driver::create('neo4j://test:test@localhost');

    // Create a session to run queries on.
    $session = $driver->createSession();

.. _creating a client:

Creating a client
-----------------

A client is a collection of drivers that automatically manages sessions for you to run queries on. Please refer to the :ref:`driver section<creating a driver>` for how to create a driver and session.

Create a client object to run queries on:

.. code-block:: php

    use Laudis\Neo4j\Authentication\Authenticate;
    use Laudis\Neo4j\ClientBuilder;

    $client = ClientBuilder::create()
        ->withDriver('bolt', 'bolt+s://user:password@localhost') // creates a bolt driver
        ->withDriver('https', 'https://test.com', Authenticate::basic('user', 'password')) // creates an http driver
        ->withDriver('neo4j', 'neo4j://neo4j.test.com?database=my-database', Authenticate::oidc('token')) // creates an auto routed driver with an OpenID Connect token
        ->withDefaultDriver('bolt')
        ->build();

This example creates a client with **bolt, HTTPS and neo4j drivers**. The default driver that the client will use is **bolt**.

Read more about the URLs and how to use them to configure drivers at the :doc:`configuration <configuration>` page.


Running Queries
---------------

Queries can be run either on a session or a client.

The api for running queries and transactions is the same for both, with the exception that the client always provides an extra optional parameter at the end to provide a different alias to determine the driver to use.

.. code-block:: php

    $result = $client->run('MERGE (x {y: "z"}:X) return x');

    echo $result->first()->get('x')['y']; // echos 'z'

Or use them in an automatically managed transaction:

.. code-block:: php

    use Laudis\Neo4j\Contracts\TransactionInterface;

    $result = $client->writeTransaction(static function (TransactionInterface $tsx) {
        $result = $tsx->run('MERGE (x {y: "z"}:X) return x');
        return $result->first()->get('x')['y'];
    });

    echo $result; // echos 'z'

Naturally, an unmanaged transaction is possible as well:

.. code-block:: php

    $tsx = $client->beginTransaction();

    $result = $tsx->run('MERGE (x {y: "z"}:X) return x');

    echo $result->first()->get('x')['y']; // echos 'z'

    $tsx->commit();

Please refer to the :doc:`querying <querying>` page for more information to discover about the differences between the three methods.

Basic Concepts
==============

Driver types
------------

There are three types of drivers: neo4j, bolt and http, each coming with their own advantages and tradeoffs. The default is neo4j as it covers most use cases.

- **neo4j** is the default driver and is the most versatile. It understands clusters and cloud applications and automatically routes the queries to correct server using the bolt network protocol.
- **bolt** is a driver that uses the bolt network protocol to communicate with the database. It is the fastest driver only connects to a single server making it unsuitable for most cloud and cluster deployments unless a custom load balancer is used.
- **http** is a driver that uses the http protocol to communicate with the database. It is the slowest driver but it can be useful if you are in a situation where you cannot use the bolt protocol. It can also run multiple queries in a single round trip.

Core concepts
-------------

The core concepts of the package can be divided into three main categories: the protocol that is being used, the schema handling the concepts, and the Main classes providing a uniform API to developer for interacting with the database.

Protocol
~~~~~~~~

A protocol refers to the application layer network protocol used by the driver.

This protocol can be either Bolt or HTTP. Bolt is by far the fastest and most versatile providing a binary protocol that efficiently manages the connections for queries. The HTTP protocol is a lot slower but it can be handy in situations where bolt is not available.

These protocols are managed by the drivers and are not directly accessible to the developer but can be configured using the :doc:`configuration <configuration>` page.

The driver will use the same uniform API to communicate with the database regardless of the protocol used. But being aware of the underlying protocol can help you understand potential performance limitations and optimisations.

Schemes
~~~~~~~

The scheme determines which driver and network protocol should be used, including the variations. It can be configured using the scheme part of the URI when creating a driver or client and has the biggest impact on performance, capabilities and protocol selection.

Main Classes
~~~~~~~~~~~~


Supported Versions and Features
===============================

Refer to these tables to get an overview of the supported versions and features.

Version Support
---------------

.. csv-table:: Supported Versions Neo4j
   :widths: 70 30
   :file: _static/version-support.csv
   :header-rows: 1

.. csv-table:: Supported Versions PHP
   :widths: 70 30
   :file: _static/version-support-php.csv
   :header-rows: 1

Feature Support
---------------

.. csv-table:: Supported Features
   :file: _static/feature-support.csv
   :widths: 80 20
   :header-rows: 1
