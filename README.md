[![Build Status](https://secure.travis-ci.org/snowcap/SnowcapCoreBundle.png?branch=master)](http://travis-ci.org/snowcap/SnowcapCoreBundle)

Introduction
============

This bundle is used to share common features through all Snowcap project made with Symfony 2

Installation
------------

1. Add this bundle to your ``vendor/`` dir:

    Add the following lines in your ``deps`` file::
  
        [SnowcapCoreBundle]
            git=git://github.com/snowcap/SnowcapCoreBundle.git
            target=/bundles/Snowcap/CoreBundle
        
    Run the vendors script:
  
        ./bin/vendors install

2. Add the Snowcap namespace to your autoloader:

        // app/autoload.php
        $loader->registerNamespaces(array(
              'Snowcap' => __DIR__.'/../vendor/bundles',
              // your other namespaces
        ));

3. Add this bundle to your application's kernel:

        // app/ApplicationKernel.php
        public function registerBundles()
        {
            return array(
                // ...
                new Snowcap\CoreBundle\SnowcapCoreBundle(),
                // ...
            );
        }
          
Running the tests
-----------------

Before running the tests, you will need to install the bundle dependencies. Do it using composer :

    curl -s http://getcomposer.org/installer | php
    php composer.phar --dev install
    
Then you can simply launch phpunit

    phpunit