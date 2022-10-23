============
Installation
============

Recommended
===========

You can add the client as a dependency using Composer:

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

#. PHP 7.4.0
#. To use the PHP stream handler, ``allow_url_fopen`` must be enabled in your
   system's php.ini.
#. To use the cURL handler, you must have a recent version of cURL >= 7.19.4
   compiled with OpenSSL and zlib.

.. note::

    Guzzle no longer requires cURL in order to send HTTP requests. Guzzle will
    use the PHP stream wrapper to send HTTP requests if cURL is not installed.
    Alternatively, you can provide your own HTTP handler used to send requests.
    Keep in mind that cURL is still required for sending concurrent requests.

