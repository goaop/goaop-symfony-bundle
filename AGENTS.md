# Agent guidance for goaop/goaop-symfony-bundle

This file is the canonical guidance for AI coding agents (and a quick orientation for humans) working on this repository.

## What this package is

The Symfony bundle of the [Go! AOP framework](https://github.com/goaop/framework) 4.x. It initializes the aspect kernel when the bundle boots, so the Go! AOP class loader weaves aspects into application classes transparently, and it integrates the framework with the Symfony container, cache warm-up, console, Doctrine ORM and the web profiler.

- Target runtime: PHP >= 8.4, Symfony 7.4 / 8.x, goaop/framework 4.x (attribute-based aspects only).
- Package type: `symfony-bundle`, modern layout (`src/`, `config/`, `templates/`, `tests/`), PSR-4 namespace `Go\Symfony\GoAopBundle\`.

## Architecture map

- `src/GoAopBundle.php` — `AbstractBundle`: configuration tree (`configure()`), extension (`loadExtension()`: `goaop.options` parameter, kernel service, aspect autoconfiguration, optional Doctrine and profiler services), compiler passes (`build()`), and `boot()`, which creates the aspect kernel. `build()` also sets `.container.dumper.inline_class_loader` to `false` (unless the application defines it) and checks that the bundle is registered first.
- `src/Kernel/AspectSymfonyKernel.php` — the aspect kernel. `create()` is the factory of the `goaop.aspect_kernel` service: `init()` with the options, aspects registered **by class** as deferred services whose factories take the instance from a Symfony service locator, the Symfony container attached as PSR-11 delegate. `bootstrap()` does the same outside of Symfony for the dumped bootstrap file. `init()` lifts Symfony's `DebugClassLoader` wrapping while the Go! AOP loader hooks into Composer and records the classes declared before initialization (debug mode).
- `src/DependencyInjection/Compiler/AspectCollectorPass.php` — collects `goaop.aspect` tagged services (autoconfigured for `Go\Aop\Aspect`) into a service locator keyed by aspect class, aliased `goaop.aspect_locator`.
- `src/DependencyInjection/Compiler/DoctrineIntegrationPass.php` — wraps the directories of every ORM `AttributeDriver` into `Go\Bridge\Doctrine\WovenEntityClassLocator`.
- `src/DependencyInjection/KernelOptionsResolver.php` — processed config → kernel options, file mode normalization, cache dir anchor (`%kernel.cache_dir%` or `%kernel.build_dir%`).
- `src/CacheWarmer/AspectCacheWarmer.php` — optional `kernel.cache_warmer`. Warms in-process when the target directory is the one of the running kernel, otherwise in a fresh process (`bin/cache-warmup.php` + the bootstrap file): `cache:clear` builds the new cache next to the current one.
- `src/CacheWarmer/BootstrapDumper.php` — dumps `<aop cache dir>/bootstrap.php`: initializes the same kernel outside of Symfony (loader for `vendor/bin/aspect`, the fresh-process warm-up, `config/preload.php`). Paths are relative to the file where possible.
- `src/Command/*` — `cache:warmup:aop`, `debug:aspect`, `debug:advisor`, `debug:weaving`: the framework commands bound to the running kernel (`RunningKernelCommandTrait` drops the `loader` argument).
- `src/DataCollector/AspectDataCollector.php` + `templates/Collector/aspect.html.twig` — profiler panel: aspects and advisors, woven classes of the request, classes loaded before weaving, configuration. Registered only when `WebProfilerBundle` is present.
- `config/services.php` — service definitions.

Load-bearing constraints (do not "simplify" these away):

- The aspect kernel must be initialized in `GoAopBundle::boot()` and the bundle must be the first one in `config/bundles.php`: weaving only applies to classes loaded after `init()`, and bundles boot in registration order. Internal component bundles of Symfony 8.1+ (`ServicesBundle`, ...) are allowed before it.
- `.container.dumper.inline_class_loader` must stay off: with it the compiled container `include_once`s the original source files of services (Symfony's default in debug mode), bypassing the class loader and thereby the weaving; and a woven proxy file can not be included on its own.
- Aspects are registered by class only. Weaving, warm-up and the bootstrap file never instantiate an aspect; the Symfony container creates it when the first advice runs. Never register aspect instances eagerly.
- `Go\Core\AspectKernel` is a process-global singleton bound to the cache directory of its first `init()`. Warming another directory needs another process (the warmer handles it). Tests booting a kernel run in separate processes.
- Woven files refer to `AOP_CACHE_DIR`, a constant defined by `init()`; cache files are location independent (`__DIR__`, relative record keys), which `cache:clear`'s rename of the warm-up directory relies on.
- The container compiler loads service classes: the process that builds the container can not weave them (shown in the profiler panel as "classes loaded before weaving"). Tests needing woven services build the container in a subprocess first.
- Doctrine-style annotations are not supported anywhere; advices are PHP attributes (`Go\Lang\Attribute\Before`, `After`, `Around`, `AfterThrowing`, `Pointcut`, `DeclareParents`).

## Development commands

```bash
composer install            # needs minimum-stability: dev (goaop/framework 4.x-dev)
composer validate --strict
composer test               # phpunit (boots the fixture app in tests/Fixtures/App)
composer analyse            # phpstan, level max
```

## Conventions

- **Conventional Commits 1.0.0** for every commit message: `type(optional-scope): description`. Allowed types: `feat`, `fix`, `chore`, `ci`, `docs`, `test`, `refactor`, `build`. Breaking changes: `!` after the type/scope and a `BREAKING CHANGE:` footer.
- **Conventional branch names**: `feat/…`, `fix/…`, `chore/…`, `ci/…`, `docs/…`, `test/…`, `refactor/…` (kebab-case after the slash).
- PR titles must themselves be valid conventional commit subjects — CI enforces this (`.github/workflows/lint-pr.yml`).
- New classes are `final` unless they are meant to be extended (`AspectSymfonyKernel` is extendable through `go_aop.kernel_class`).
- See `CONTRIBUTING.md` for the full contributor workflow.

## Things agents get wrong here

- Do not initialize the kernel at compile time (in the extension or a compiler pass) or lazily on first use.
- Do not re-enable `inline_class_loader`, and do not replace the service locator of aspects with eager instances.
- Do not add `containerClass` to the configuration: the framework default (`Go\Core\Container`) is correct and `Go\Core\GoAspectContainer` no longer exists.
- Do not make the cache warmer non-optional: the cache is built on demand at runtime, the warm-up is for deployments.
- The upstream 4.x line is unreleased: composer requires `minimum-stability: dev` + `prefer-stable: true`. Do not "fix" that to stable until goaop/framework 4.0 is tagged.
