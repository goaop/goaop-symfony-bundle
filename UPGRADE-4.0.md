Upgrading from GoAopBundle 2.x to 4.0
=====================================

GoAopBundle 4.0 is a rewrite for Go! AOP 4.x, PHP 8.4 and Symfony 7.4/8. The major version
follows the framework: bundle 4.x works with goaop/framework 4.x.

Requirements
------------

- PHP 8.4+, Symfony 7.4 or 8.x, goaop/framework 4.x (`minimum-stability: dev` until 4.0 is tagged).
- Aspects declare advices with PHP attributes (`#[Before]`, `#[After]`, `#[Around]`,
  `#[AfterThrowing]`, `#[Pointcut]`, `#[DeclareParents]`); Doctrine annotations are gone.
  Advice methods must be `public`. See the [framework upgrade guide](https://github.com/goaop/framework/blob/master/UPGRADE-4.0.md).

Configuration
-------------

The `options` level is gone, the options live directly below `go_aop`:

```yaml
# 2.x
go_aop:
    cache_warmer: true
    doctrine_support: false
    options:
        debug: '%kernel.debug%'
        app_dir: '%kernel.root_dir%/../src'
        cache_dir: '%kernel.cache_dir%/aspect'
        include_paths: []
        exclude_paths: []
        container_class: ~
        features: []

# 4.0
go_aop:
    debug: '%kernel.debug%'
    app_dir: '%kernel.project_dir%'
    cache_dir: '%kernel.cache_dir%/aop'
    include_paths: ['%kernel.project_dir%/src']
    exclude_paths: []
    features: []            # names of Go\Aop\Features constants: PREBUILT_CACHE, INTERCEPT_INCLUDES, PARTIAL_REFLECTION
    cache_warmer: true
    doctrine: ~             # enabled automatically with DoctrineBundle
```

- `container_class` is removed (the framework container is a PSR-11 container that delegates to the
  Symfony container). Register additional transformers through a custom `kernel_class` instead.
- `doctrine_support` is replaced by `doctrine`, which is enabled automatically when DoctrineBundle is
  registered. It wires the woven entities into the ORM attribute drivers and validates the
  configuration of intercepted entity properties; the old metadata workaround is not needed anymore.
- `app_dir` now defaults to the project directory and `include_paths` to `src/`: the weaver no longer
  touches `vendor/`.
- `INTERCEPT_FUNCTIONS` and `INTERCEPT_INITIALIZATIONS` are not features anymore: function and
  `initialization()` pointcuts are enabled by the pointcuts themselves.
- The default cache directory moved from `%kernel.cache_dir%/aspect` to `%kernel.cache_dir%/aop`.
  Delete the old directory.

Services
--------

| 2.x                           | 4.0                                                   |
|-------------------------------|-------------------------------------------------------|
| `goaop.aspect.kernel`         | `Go\Core\AspectKernel`                                |
| `goaop.aspect.container`      | `Go\Core\AspectContainer`                             |
| `goaop.cache.path.manager`    | removed, `$aspectContainer->getService(CachePathManager::class)` |
| `goaop.cache.warmer`          | `Go\Symfony\GoAopBundle\CacheWarmer\AspectCacheWarmer` |
| `goaop.command.*`             | the framework classes, `Go\Console\Command\*`         |
| `goaop.bridge.doctrine.metadata_load_interceptor` | `Go\Bridge\Doctrine\MetadataLoadInterceptor` |

Aspects are still services tagged `goaop.aspect`, and autoconfiguration still tags every service
implementing `Go\Aop\Aspect`. They are no longer registered as instances: the aspect container knows
them by class and asks the Symfony container for the instance when the first advice runs. An aspect
class can only be registered once.

Bundle registration
-------------------

The bundle must still be the first entry of `config/bundles.php`. The check moved from runtime to the
container build and names the bundle registered before it.

Cache and deployment
--------------------

- The cache warmer is optional now (`cache:warmup`, `cache:clear`); the first request with a cold cache
  no longer weaves the whole application.
- `cache:clear` builds the new AOP cache next to the running one, in the new cache directory, without touching
  the directory the running kernel uses.
- With opcache preloading, require the `_preload.php` of the AOP cache (written by the cache warmer in production
  mode) in `config/preload.php` before the container preload file, so that the woven classes are preloaded (see
  the README).
- For production, enable `features: [PREBUILT_CACHE]` and build the cache on deployment.

Console
-------

The commands of the framework are registered as they are (`cache:warmup:aop`, `debug:aspect`, `debug:advisor`,
and the new `debug:weaving` and `cache:warmup:opcache`): inside the application they use the running kernel, the
`loader` argument is omitted. The bundle no longer ships command classes of its own.
