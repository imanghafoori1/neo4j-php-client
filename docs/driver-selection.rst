================
Driver Or Client Selection
================

Driver or Client
================

This package was loosely based on the now abandoned neo4j php client project of GraphAware. Completely rebuilt from the ground up and with a strong focus on feature parity with the official drivers, it is now called the php client and driver package.

To make things easy and make the migration as simple as possible between the packages, we focused on keeping the client object API as close as possible to the original.

On the other hand, the package had to be reimagined to correctly implement the concept of drivers, session and transactions and bring them in line with the other languages so they behave the same.

This means the client changed a lot under the hood. While the drivers work the same as described :ref:`todo<todo>`_, the client is now reduced to a much more simple object managing drivers and session based on their configured aliases configured during construction.

As a rule of thumb, all new projects should use the driver and session pattern, while existing projects migrating from the legacy client should use the new client.

Other reasons for including the client is to use the built in driver fallback functionality or situations using the location pattern instead of dependency injection to quickly and efficiently switch between drivers.


Driver Selection
================

Selecting the drivers is another topic. This project provides three under the hood: Neo4j, Bolt and HTTP. Neo4j is the recommended driver as it works in most situations, while bolt and HTTP require a deeper understanding of how Neo4j works and will and have situational advantages.

It is important to realise all drivers have a uniform API and almost complete feature parity. This library took great care in ensuring that this parity is as close as possible. Yet, there are a few limitation in the HTTP driver which cannot be solved. We will provide a warning if this is the case.

Neo4j Driver
------------

The Neo4j Driver is essentially an extended version of the bolt driver and is able to understand complex cloud and clustering setups out of the box.

It uses the read and write hints provided by the end user to efficiently route and load balance from the client side. The load balancer also uses the routing table provided by the preconfigured server to determine which neo4j instance to route the queries towards.

This means the driver will work in virtually all situations that have enabled the bolt protocol, which is almost every instance of Neo4j.

It also means the end users gives up direct control on which specific server the queries are routed towards.

The only overhead of the Neo4j driver is that of the routing step. The routing table is essentially fetched once per PHP session, resulting in one extra round trip communication. It can be cached using any psr simple cache, potentially persisting the table between sessions to optimise performance.

The lifetime of the routing table in the driver is inherited by the response TTL of the server. You can configure this here :ref:`todo<todo>`_.

Bolt Driver
-----------

The bolt driver is built on top of the bolt protocol and is essentially the fastest. It directly connects to the preconfigured server and only communicates to it.

Using this driver essentially trades the loss of convenience and flexibility for a minor speed improvement (no routing step), easier debugging (it only ever talks to the same neo4j instance) and no need for bookmarks (yet they can still be provided for feature parity).

HTTP Driver
-----------

The HTTP driver is an odd instance that exists in case the bolt protocol is not enabled on the neo4j server.

It does not provide auto routing functionality, does not understand bookmarks and cannot optimise result set consumption.

The only things is has going for it is that it can run multiple queries in a single round trip and that it is easier to debug as the protocol is text based in stead of binary.


