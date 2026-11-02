# Go! AOP Symfony bundle

[![CI](https://github.com/goaop/goaop-symfony-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/goaop/goaop-symfony-bundle/actions/workflows/ci.yml)
![PHPStan Badge](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg?style=flat&link=https%3A%2F%2Fphpstan.org%2Fuser-guide%2Frule-levels)
[![GitHub release](https://img.shields.io/github/release/goaop/goaop-symfony-bundle.svg)](https://github.com/goaop/goaop-symfony-bundle/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/goaop-symfony-bundle.svg)](https://packagist.org/packages/goaop/goaop-symfony-bundle)
[![Monthly Downloads](https://img.shields.io/packagist/dm/goaop/goaop-symfony-bundle.svg)](https://packagist.org/packages/goaop/goaop-symfony-bundle)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.4-8892BF.svg)](https://www.php.net/supported-versions.php)
[![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.x-000000.svg?logo=symfony)](https://symfony.com)
[![License](https://img.shields.io/packagist/l/goaop/goaop-symfony-bundle.svg)](https://packagist.org/packages/goaop/goaop-symfony-bundle)
[![Sponsor](https://img.shields.io/badge/Sponsor-❤️-lightgray?style=flat&logo=github)](https://github.com/sponsors/lisachenko)

Aspect-Oriented Programming for Symfony applications, powered by the [Go! AOP framework](https://github.com/goaop/framework) 4.x. Write an aspect, give it a pointcut, and the bundle weaves it into your services as they are loaded — no base classes, no decorators to register, no proxies to maintain, no `eval()`, no PHP extension.

```php
namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Psr\Log\LoggerInterface;

final class TimingAspect implements Aspect
{
    public function __construct(private readonly LoggerInterface $logger) {}

    #[Around('execution(public App\Service\**->*(*))')]
    public function measure(MethodInvocation $invocation): mixed
    {
        $start = hrtime(true);
        try {
            return $invocation->proceed();
        } finally {
            $this->logger->info('{method} took {ms} ms', [
                'method' => (string) $invocation,
                'ms'     => (hrtime(true) - $start) / 1e6,
            ]);
        }
    }
}
```

That is the whole integration: the class implements `Go\Aop\Aspect`, so autoconfiguration registers it as an aspect, autowiring gives it its logger, and every public method of every class below `App\Service` is timed from the next request on.

## Why Aspect-Oriented Programming in Symfony?

Some logic belongs to no single class and still ends up in hundreds of them: logging, metrics, caching, transactions, authorization checks, retries, rate limits, audit trails. Symfony offers event listeners for the HTTP lifecycle and decorators for single services; AOP generalizes both to **any method of your own code**. You describe *where* with a pointcut expression and *what* with an advice, and the engine applies it, so your services stay business logic.

### ✨ Highlights

- 🪡 **Transparent weaving.** Classes are transformed at load time into a proxy backed by the original code as a trait. Constructor injection, autowiring, `new`, `instanceof`, `final`, `readonly`, enums, static and private methods, property hooks: everything keeps working, and the woven code is plain PHP you can read in the cache directory.
- 🏷️ **PHP 8 attributes.** `#[Before]`, `#[After]`, `#[Around]`, `#[AfterThrowing]`, `#[Pointcut]` and `#[DeclareParents]`, with a rich pointcut language: `execution(public App\Service\**->*(*))`, `@execution(App\Attribute\Cached)`, `access(App\Entity\*->email)`, `initialization(App\Model\*)`, `within(...)`, `&&`, `||`, `!`.
- 🧩 **Symfony native.** Aspects are ordinary autowired services, discovered by autoconfiguration (or the `goaop.aspect` tag). The aspect container delegates to the Symfony container, the aspect kernel and container are autowirable services, and the bundle configures Symfony's container dumper so that nothing bypasses the weaver.
- 🚀 **Production ready.** `cache:warmup` and `cache:clear` weave the whole application ahead of time, also when `cache:clear` builds the new cache next to the running one. With `PREBUILT_CACHE` the runtime trusts the deployed cache completely: no file system checks, works on read-only file systems, friendly to opcache and preloading.
- 🔬 **Observable.** A **web profiler panel** lists the aspects and their advisors, the woven classes used by the request and the classes that were loaded before weaving was possible. `debug:aspect`, `debug:advisor` and `debug:weaving` give the same answers on the command line.
- 🗄️ **Doctrine ORM aware.** Entities are discovered through their woven classes, so an aspect on an entity method works, and intercepted entity properties are validated against the ORM configuration at metadata load time.
- ✅ **Tested and typed.** PHPStan at level max, and an end-to-end test suite that boots a real Symfony application in debug and production mode, runs `cache:clear`, renders the profiler panel and maps a Doctrine entity.

## Requirements

- PHP >= 8.4
- Symfony 7.4 or 8.x
- goaop/framework 4.x (attribute-based aspects)

## Installation

```bash
composer require goaop/goaop-symfony-bundle
```

> **Note**
> Until goaop/framework 4.0 is tagged, the bundle depends on the unreleased `4.0-dev` line, so your application's `composer.json` needs:
>
> ```json
> "minimum-stability": "dev",
> "prefer-stable": true
> ```

Register the bundle as the **first** entry of `config/bundles.php`. Weaving only applies to classes loaded after the aspect kernel is initialized, and bundles boot in registration order, so this is enforced: the container build fails with a clear message otherwise.

> **Symfony Flex** registers new bundles at the *end* of `config/bundles.php`, so the `cache:clear` that runs at the end of `composer require` fails with "GoAopBundle must be the first bundle". Move the line to the top as shown below and run `bin/console cache:clear` again; this is needed once.

```php
// config/bundles.php
return [
    Go\Symfony\GoAopBundle\GoAopBundle::class => ['all' => true],
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    // ...
];
```

Optionally create `config/packages/go_aop.yaml`; the defaults below weave the `src/` directory in the mode of the kernel, and enable the prebuilt cache in production:

```yaml
go_aop:
    include_paths: ['%kernel.project_dir%/src']

when@prod:
    go_aop:
        features: [PREBUILT_CACHE]
```

## Quick start

1. **Write an aspect.** A service implementing `Go\Aop\Aspect` whose public methods carry advice attributes (see the example at the top). Dependencies are injected by the container as usual.
2. **Check what it matches.**
   ```bash
   bin/console debug:aspect                                              # aspects with their pointcuts and advisors
   bin/console debug:advisor --advisor='App\Aspect\TimingAspect->measure' # the joinpoints an advisor matches
   ```
3. **Use your services.** Classes below the include paths are woven on first load in debug mode (and re-woven when their source or an aspect changes). Open the **Go! AOP** panel of the web profiler to see the woven classes of a request.
4. **Deploy.** `cache:clear` or `cache:warmup` weaves everything ahead of time; enable `PREBUILT_CACHE` in production (see [Deployment](#deployment)).

The pointcut language, the advice types and the joinpoint API are documented in the framework: [pointcuts](https://github.com/goaop/framework/blob/master/docs/pointcuts.md), [advices](https://github.com/goaop/framework/blob/master/docs/advices.md), [configuration](https://github.com/goaop/framework/blob/master/docs/configuration.md).

## How it works

1. **Boot.** `GoAopBundle::boot()` runs before any other bundle boots and creates the `goaop.aspect_kernel` service. The kernel hooks the Go! AOP class loader into Composer's autoloader (lifting Symfony's `DebugClassLoader` for a moment and putting it back on top) and registers the aspects.
2. **Aspects by class.** A compiler pass collects every service tagged `goaop.aspect` into a service locator keyed by the aspect class. The weaver only needs the classes: an aspect is instantiated by the Symfony container when one of its advices runs for the first time, never during weaving or cache warm-up. The aspect container also delegates to the Symfony container, so `$aspectContainer->get(LoggerInterface::class)` works where the framework documentation uses it.
3. **Weaving.** Every class loaded from an include path goes through the weaver. In debug mode a stream filter transforms the source on the fly and caches the result; in production the woven class map is handed to Composer, so woven classes resolve natively to their cached files.
4. **No shortcuts around the class loader.** Symfony's container dumper can `include_once` the source files of services directly (`.container.dumper.inline_class_loader`, on by default in debug mode). The bundle turns it off unless your configuration sets it, because such an include bypasses the class loader and therefore the weaving.

The process that compiles the container has to load the service classes before the bundle boots, so the request (or command) that rebuilds the container runs those classes unwoven once. The profiler panel lists them as *classes loaded before weaving*; the next request is fully woven. Deployments are not affected: `cache:clear` and `cache:warmup` build the container and the AOP cache together.

## Configuration

`bin/console config:dump-reference go_aop` prints the reference. All options:

```yaml
go_aop:
    # Development mode: changed sources and aspects are woven again on the next request,
    # breakpoints work in the original files. Keep it off in production.
    debug: '%kernel.debug%'

    # Application root: only files below it can be woven, cache paths mirror source paths relative to it.
    app_dir: '%kernel.project_dir%'

    # Directory for woven sources, proxies and advisor caches. Anchor it on %kernel.cache_dir%
    # or %kernel.build_dir% so that cache:clear and cache:warmup manage it.
    cache_dir: '%kernel.cache_dir%/aop'

    # Permissions of the cache files: an octal literal (0o770), an octal string ("0770") or an integer.
    # Defaults to 0770 minus the umask.
    cache_file_mode: ~

    # Directories that are woven. Keep the list narrow: weaving vendor code is slow and rarely needed.
    include_paths: ['%kernel.project_dir%/src']

    # Directories below the include paths that are never woven (the cache directory and the
    # framework itself are always excluded).
    exclude_paths: []

    # Framework features, by the name of the Go\Aop\Features constant:
    #   PREBUILT_CACHE     trust the deployed cache completely, never check or write it at runtime
    #   INTERCEPT_INCLUDES route include/require in woven files through the weaver (legacy code)
    features: []

    # Aspect kernel class; extend Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel to customize
    # the transformation pipeline.
    kernel_class: Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel

    # Weave every file below the include paths during cache:warmup and cache:clear.
    cache_warmer: true

    # Doctrine ORM integration, enabled automatically when DoctrineBundle is registered.
    doctrine: ~
```

Environment variables work as everywhere in Symfony, e.g. `debug: '%env(bool:GOAOP_DEBUG)%'`. Function and `initialization()` pointcuts need no feature flag: the pointcuts enable their machinery themselves.

## Console commands

| Command | Purpose |
|---|---|
| `cache:warmup:aop [--fail-fast] [--cache-dir=DIR]` | Weaves every file below the include paths into the cache. Fails when a file cannot be woven. The same warm-up runs as a cache warmer of `cache:warmup` and `cache:clear`. |
| `debug:aspect [--aspect=FQCN]` | Lists the aspects with their pointcuts and advisors. |
| `debug:advisor [--advisor=ID]` | Lists the advisors; with `--advisor` the joinpoints that advisor matches. The quickest way to check a pointcut expression. |
| `debug:weaving [-v]` | Weaves the application twice and reports proxies that differ, which points to a non-deterministic aspect. |

These are the commands of the framework itself, registered in the console of the application: they work on the running aspect kernel, so the loader argument of `vendor/bin/aspect` is omitted. `cache:warmup:aop --cache-dir=DIR` builds the cache in another directory, from scratch, which is what the cache warmer does during `cache:clear`.

## Deployment

### Warm up the cache

`cache:clear` and `cache:warmup` weave the application through an optional cache warmer. `cache:clear` builds the new cache next to the running one and renames it afterwards; the warmer builds the AOP cache in that new directory, from scratch, while the running kernel keeps its own, so the usual deployment steps need no change:

```bash
composer install --no-dev --optimize-autoloader   # runs cache:clear through the auto-scripts
# or explicitly
bin/console cache:clear
bin/console cache:warmup
```

The warm-up fails when a file cannot be woven, so a deployment stops instead of shipping an incomplete cache. Use `cache:warmup:aop --fail-fast -vvv` to see the file and the error.

### Trust the cache in production

With `features: [PREBUILT_CACHE]` the runtime skips every cache-related file system check (directory probes, `filemtime` comparisons, aspect freshness) and never writes to the cache directory. Rebuild the cache on every deployment. This is the recommended production setting, and it is what makes read-only file systems and immutable images work:

```yaml
when@prod:
    go_aop:
        features: [PREBUILT_CACHE]
```

If your `kernel.build_dir` differs from `kernel.cache_dir` (read-only build directory, writable cache directory), anchor the AOP cache on the directory you build at deploy time: `cache_dir: '%kernel.build_dir%/aop'`.

### Opcache preloading

Symfony's preload file loads the classes of your services through the autoloader, so without the aspect kernel it would preload the original classes, and a preloaded class can not be woven anymore. Boot the kernel in `config/preload.php` before requiring the container preload file: booting initializes the aspect kernel, and the woven classes are preloaded instead.

```php
// config/preload.php
use App\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php')) {
    (new Kernel('prod', false))->boot();
    require dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php';
}
```

## Doctrine ORM

When DoctrineBundle is registered (or `doctrine: true` is set), the bundle:

- replaces the directory scan of every ORM attribute driver with `Go\Bridge\Doctrine\WovenEntityClassLocator`. Doctrine's own locator includes every entity file directly, which bypasses the class loader: an entity included that way loses its aspects for the rest of the request and, once loaded from the cache, disappears from `orm:validate-schema` and the migrations. The locator reads the class names from the sources and loads them through the class loader instead;
- registers `Go\Bridge\Doctrine\MetadataLoadInterceptor` as a `loadClassMetadata` listener. An intercepted entity property (`access(...)` pointcut) is implemented with native property hooks, which the ORM supports with native lazy objects only; the listener reports such a configuration when the metadata loads instead of failing on the first lazy proxy.

Entities must lie below an include path to be woven, and the aspects apply to them whether they are created by your code or hydrated by the ORM.

## Web profiler

With `symfony/web-profiler-bundle` installed, the toolbar shows the number of aspects, and the **Go! AOP** panel lists:

- the aspects with their advisors, advice types and pointcut expressions;
- the woven classes loaded during the request, with their proxy files;
- the classes below the include paths that were loaded *before* the aspect kernel was initialized — the diagnostic for a bundle booting before GoAopBundle or for the request that rebuilt the container (the panel turns yellow);
- the effective configuration of the kernel.

## Extending

- **Custom transformers.** Point `kernel_class` at a subclass of `Go\Symfony\GoAopBundle\Kernel\AspectSymfonyKernel` and register your `SourceTransformer` or `NodeRewriter` services in `configureAop()` (see the [framework documentation](https://github.com/goaop/framework/blob/master/docs/configuration.md#extending-the-transformation-pipeline)).
- **Autowirable services.** `Go\Core\AspectKernel` and `Go\Core\AspectContainer` are services, `goaop.aspect_kernel` is public. The aspect container is a PSR-11 container that falls back to the Symfony container for unknown ids.
- **Aspects without autoconfiguration.** Tag the service with `goaop.aspect`:
  ```yaml
  services:
      Acme\Audit\AuditAspect:
          tags: [goaop.aspect]
  ```
  An aspect class is a singleton: registering it as two services is a compile error.

## Troubleshooting

- **"GoAopBundle must be the first bundle in config/bundles.php"** — move its line above every other bundle (the internal component bundles of Symfony 8.1 are fine before it). Symfony Flex appends new bundles at the end, so this happens once right after `composer require`.
- **An aspect does not apply.** Check `debug:advisor --advisor=...` first: it lists the joinpoints a pointcut matches. Then make sure the class lies below an include path and is not loaded before the bundle boots (profiler panel, *classes loaded before weaving*). Vendor code is only woven when its directory is an include path.
- **Everything works in dev but not in prod.** The production cache is built at deploy time: run `cache:clear` or `cache:warmup`, and keep `PREBUILT_CACHE` on only when the deployment rebuilds the cache. Check that nothing defines `.container.dumper.inline_class_loader: true`, and that `config/preload.php` boots the kernel before the container preload file when preloading is enabled.
- **Circular reference when an aspect depends on a service it advises.** The aspect is created when the first advice runs, which may happen while the Symfony container is still building that very service. Inject a service locator or mark the woven service `lazy`.
- **Exception handlers.** Exclude the classes that must be loadable when everything else failed (error handlers, the kernel) through `exclude_paths`.

## Upgrading from 2.x

See [UPGRADE-4.0.md](UPGRADE-4.0.md): configuration keys, service ids, attributes instead of annotations.

## License

MIT — see [LICENSE](LICENSE).
