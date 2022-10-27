============
Installation
============

This page describes how to install the latest version of the driver and client, as well as the bleeding edge one. It also includes the requirements to get the driver and client working, including optional dependencies for improved performance or to gain access to a wider set of features.

Recommended
===========

Add the client as a dependency using Composer:

.. code-block:: bash

    composer require laudis/neo4j-php-client

The recommended way to install the client is with
`Composer <https://getcomposer.org>`_. Composer is a dependency management tool for PHP that allows you to declare the dependencies your project needs and installs them into your project.

You can quickly install the tool by running this command:

.. code-block:: bash

    # Install Composer
    curl -sS https://getcomposer.org/installer | php

Alternatively, you can specify the client as a dependency in your project's existing composer.json file:

.. code-block:: json

    {
      "require": {
         "laudis/neo4j-php-client": "^2.0"
      }
   }

And run the composer install command:

.. code-block:: bash

    composer install

After installing, you need to require Composer's autoloader:

.. code-block:: php

    require 'vendor/autoload.php';

You can find out more on how to install Composer, configure autoloading, and
other best-practices for defining dependencies at `getcomposer.org <https://getcomposer.org>`_.


Bleeding edge
-------------

During your development, you can keep up with the latest changes on the main
branch by setting the version requirement for the PHP Client to ``dev-main``.

.. code-block:: js

   {
      "require": {
         "laudis/neo4j-php-client": "dev-main"
      }
   }


Requirements
============

* PHP >= 7.4
* A Neo4j database (minimum version 3.5)
* ext-bcmath [#f1]_
* ext-json [#f2]_
* ext-sockets [#f3]_
* ext-sysvsem [#f4]_

If you plan on using the HTTP drivers, make sure you have `psr-7 <https://www.php-fig.org/psr/psr-7/>`_, `psr-17 <https://www.php-fig.org/psr/psr-17/>`_ and `psr-18 <https://www.php-fig.org/psr/psr-18/>`_ implementations included into the project. If you don't have any, you can install them via composer:

.. code-block:: bash

    composer require nyholm/psr7 nyholm/psr7-server kriswallsmith/buzz


.. rubric:: Footnotes

.. [#f1] Needed to implement the bolt protocol.
.. [#f2] Needed to implement the http protocol.
.. [#f3] Can be installed for optimal bolt protocol performance.
.. [#f4] Can be installed to implement a connection pool across multiple threads.



