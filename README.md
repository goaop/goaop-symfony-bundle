GoAopBundle
==============

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/goaop/goaop-symfony-bundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/goaop/goaop-symfony-bundle/?branch=master)
[![GitHub release](https://img.shields.io/github/release/goaop/goaop-symfony-bundle.svg)](https://github.com/goaop/goaop-symfony-bundle/releases/latest)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.5-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/goaop/goaop-symfony-bundle.svg)](https://packagist.org/packages/goaop/goaop-symfony-bundle)

The GoAopBundle adds support for Aspect-Oriented Programming via Go! AOP Framework for Symfony2 applications.

Overview
--------

Aspect-Oriented Paradigm allows to extend the standard Object-Oriented Paradigm with special instruments for effective solving of cross-cutting concerns in your application. This code is typically present everywhere in your application (for example, logging, caching, monitoring, etc) and there is no easy way to fix this without AOP.

AOP defines new instruments for developers, they are:

 * Joinpoint - place in your code that can be used for interception, for example, execution of single public method or accessing of single object property.
 * Pointcut is a list of joinpoints defined with a special regexp-like expression for your source code, for example, all public and protected methods in the concrete class or namespace.
 * Advice is an additional callback that will be called before, after or around concrete joinpoint. For PHP each advice is represented as a `\Closure` instance, wrapped into the interceptor object.
 * Aspect is a special class that combines pointcuts and advices together, each pointcut is defined as an annotation and each advice is a method inside this aspect.
 
 You can read more about AOP in different sources, there are good articles for Java language and they can be applied for PHP too, because it's general paradigm.

Installation
------------

GoAopBundle can be easily installed with composer. Just ask a composer to download the bundle with dependencies by running the command:

```bash
$ composer require goaop/goaop-symfony-bundle
```

Versions 1.x are for Symfony >=2.0,<2.7
Versions 2.x(master) are for Symfony >= 2.7 and 3.0

Then enable the bundle in the kernel:
```php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        new Go\Symfony\GoAopBundle\GoAopBundle(),
        // ...
    );
}
```
Make sure that bundle is the first item in this list. This is required for the AOP engine to work correctly.

Configuration
-------------

Configuration for bundle is required for additional tuning of AOP kernel and source code whitelistsing/blacklisting.

```yaml
# app/config/config.yml
go_aop:
    # This setting enables or disables an automatic AOP cache warming in the application.
    # By default, cache_warmer is enabled (true), disable it only if you have serious issues with 
    # cache warming process.
    cache_warmer: true
    
    # Additional settings for the Go! AOP kernel initialization
    options:
        # Debug mode for the AOP, enable it for debugging and switch off for production mode to have a
        # better runtime performance for your application
        debug: %kernel.debug%
        
        # Application root directory, AOP will be applied ONLY to the files in this directory, by default it's
        # src/ directory of your application.
        app_dir: "%kernel.root_dir%/../src"

        # AOP cache directory where all transformed files will be stored.
        cache_dir: %kernel.cache_dir%/aspect

        # Whitelist is array of directories where AOP should be enabled, leave it empty to process all files
        include_paths: []
        
        # Exclude list is array of directories where AOP should NOT be enabled, leave it empty to process all files
        exclude_paths: []
        
        # AOP container class name can be used for extending AOP engine or services adjustment
        container_class: ~
        
        # List of enabled features for AOP kernel, this allows to enable function interception, support for
        # read-only file systems, etc. Each item should be a name of constant from the `Go\Aop\Features` class.
        features: []
      
```

Defining new aspects
--------------------

Aspects are services in the Symfony2 apllications and loaded into the AOP container with the help of compiler pass that collects all services tagged with `goaop.aspect` tag. Here is an example how to implement a logging aspect that will log information about public method invocations in the src/ directory.


Definition of aspect class with pointuct and logging advice
```php
<?php

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Before;
use Psr\Log\LoggerInterface;

/**
 * Application logging aspect
 */
class LoggingAspect implements Aspect
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Writes a log info before method execution
     *
     * @param MethodInvocation $invocation
     * @Before("execution(public **->*(*))")
     */
    public function beforeMethod(MethodInvocation $invocation)
    {
        $this->logger->info($invocation, $invocation->getArguments());
    }
}
```

Registration of aspect in the container:

```yaml
services:
    logging.aspect:
        class: App\Aspect\LoggingAspect
        arguments: ["@logger"]
        tags:
            - { name: goaop.aspect }
```

License
-------

This bundle is under the MIT license. See the complete license in the bundle:

    Resources/meta/LICENSE
