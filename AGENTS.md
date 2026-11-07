# Agent guidance for goaop/goaop-symfony-bundle

This file is the canonical guidance for AI coding agents (and a quick orientation for humans) working on this repository.

## What this package is

The Symfony bundle of the [Go! AOP framework](https://github.com/goaop/framework) 4.x. It initializes the aspect kernel when the bundle boots, so the Go! AOP class loader weaves aspects into application classes transparently, and it integrates the framework with the Symfony container, cache warm-up, console, Doctrine ORM and the web profiler.

- Target runtime: PHP >= 8.4, Symfony 7.4 / 8.x, goaop/framework 4.x (attribute-based aspects only).
- Package type: `symfony-bundle`, modern layout (`src/`, `config/`, `templates/`, `tests/`), PSR-4 namespace `Go\Symfony\GoAopBundle\`.

## Architecture map

- `src/GoAopBundle.php` — `AbstractBundle`: configuration tree (`configure()`), extension (`loadExtension()`: `goaop.options` parameter, kernel service, aspect autoconfiguration, optional Doctrine and profiler services), compiler passes (`build()`), and `boot()`, which creates the aspect kernel. `build()` also sets `.container.dumper.inline_class_loader` to `false` (unless the application defines it) and checks that the bundle is registered first.
- `src/Kernel/AspectSymfonyKernel.php` — the aspect kernel. `create()` is the factory of the `Go\Core\AspectKernel` service: `init()` with the options, aspects registered **by class** as deferred services whose factories take the instance from a Symfony service locator, the Symfony container attached as PSR-11 delegate. An aspect whose creation runs one of its own advices (it depends on a service whose construction it advises) is stopped by the framework (`Go\Aop\Exception\AspectCircularReferenceException`, thrown by the interceptor before the advice runs) and reported by the factory as `Go\Symfony\GoAopBundle\Exception\AspectCircularReferenceException`, which adds the Symfony ways to inject the dependency lazily. `create()` lifts Symfony's `DebugClassLoader` wrapping while the Go! AOP loader hooks into Composer and the aspects are registered (the framework resolves the files of the aspects through the Composer loaders in debug mode, and autoloads them when it finds none), `init()` replaces the source locator of the framework with `src/Kernel/DebugClassLoaderAwareLocator.php` (the framework one finds no Composer loader behind the `DebugClassLoader`, so weaving a class with undeclared ancestors failed in debug mode) and records the classes declared before initialization (debug mode).
- `src/DependencyInjection/Compiler/AspectCollectorPass.php` — collects `goaop.aspect` tagged services (autoconfigured for `Go\Aop\Aspect`) into a service locator keyed by aspect class, injected as the second argument of the kernel service.
- `src/DependencyInjection/Compiler/DoctrineIntegrationPass.php` — wraps the directories of every ORM `AttributeDriver` into `Go\Bridge\Doctrine\WovenEntityClassLocator`.
- `src/DependencyInjection/KernelOptionsResolver.php` — processed config → kernel options, file mode normalization, cache dir anchor (`%kernel.cache_dir%` or `%kernel.build_dir%`).
- `src/DependencyInjection/Compiler/ClassSignatureResourcePass.php` + `src/Config/ClassSignatureResource.php` + `src/Config/SourceSignature.php` — the last compiler pass (after removing, priority -4096), debug mode only: replaces the `ReflectionClassResource` of every class whose hierarchy has a file below the include paths (or a woven file of the AOP cache directory) with a `ClassSignatureResource`. The kernel checks the container freshness before the bundles boot; Symfony's resource loads the class to compute its signature when a file changed, which declared it unwoven, on every request when only a method body changed (issue #24). The replacement compares token signatures of the files (bodies of non-static methods, comments and whitespace ignored) and never loads a class.
- `src/CacheWarmer/AspectCacheWarmer.php` — optional `kernel.cache_warmer`. Rebases the configured cache directory on the directory Symfony asks to warm up (`cache:clear` builds the new cache next to the current one) and calls `new CacheWarmer($kernel, cachedFiles: <parsedFilesLimit>, cacheDir: $targetDir)` of the framework, which builds another directory while the running kernel keeps its own (the framework defaults `cachedFiles` to 100, not to the kernel option, so the warmer passes the option).
- Console: the framework commands (`Go\Console\Command\*`, `cache:warmup:opcache` included) are registered as they are; their `loader` argument is optional and they use the initialized kernel of the process (`AspectKernel::isInitialized()`). `--workers` has no effect (no loader file: `getWorkerCommandLine()` returns `[]`). `src/Console/FrameworkCommandSubscriber.php` lifts the `DebugClassLoader` while they run (`AopComposerLoader::ofProcess()`, used by `--preload` and `cache:warmup:opcache`, does not see through it) and clears the last error after `cache:warmup:opcache` (its ignored compile errors of already included files would be reported by Symfony's error handler at shutdown). `src/Kernel/DebugClassLoaderGuard.php` is the helper for lifting the `DebugClassLoader`.
- `src/DataCollector/AspectDataCollector.php` + `templates/Collector/aspect.html.twig` — profiler panel: aspects and advisors, woven classes of the request, classes loaded before weaving, configuration. Registered only when `WebProfilerBundle` is present.
- `config/services.php` — service definitions.

Load-bearing constraints (do not "simplify" these away):

- The aspect kernel must be initialized in `GoAopBundle::boot()` and the bundle must be the first one in `config/bundles.php`: weaving only applies to classes loaded after `init()`, and bundles boot in registration order. Internal component bundles of Symfony 8.1+ (`ServicesBundle`, ...) are allowed before it.
- `.container.dumper.inline_class_loader` must stay off: with it the compiled container `include_once`s the original source files of services (Symfony's default in debug mode), bypassing the class loader and thereby the weaving; and a woven proxy file can not be included on its own.
- Aspects are registered by class only. Weaving and warm-up never instantiate an aspect; the Symfony container creates it when the first advice runs. Never register aspect instances eagerly.
- `Go\Core\AspectKernel` is a process-global singleton bound to the cache directory of its first `init()`. Another directory is built with `new CacheWarmer(..., cacheDir: $dir)` (`CachePathManager::withCacheDir()` in the framework), never by re-initializing the kernel. Tests booting a kernel run in separate processes.
- Woven files refer to `AOP_CACHE_DIR`, a constant defined by `init()`; cache files are location independent (`__DIR__`, relative record keys), which `cache:clear`'s rename of the warm-up directory relies on.
- The freshness check of the debug container runs before `boot()` and must never load a class below the include paths: keep `ClassSignatureResourcePass` the last pass, keep `ClassSignatureResource` free of reflection and autoloading, and keep it in the bundle (it is unserialized from the container meta file before the aspect kernel exists).
- The container compiler loads service classes: the process that builds the container can not weave them (shown in the profiler panel as "classes loaded before weaving"). Tests needing woven services build the container in a subprocess first.
- Doctrine-style annotations are not supported anywhere; advices are PHP attributes (`Go\Lang\Attribute\Before`, `After`, `Around`, `AfterThrowing`, `Pointcut`, `DeclareParents`).

## Development commands

```bash
composer install            # needs minimum-stability: dev (goaop/framework 4.x-dev)
composer validate --strict
composer test               # phpunit (boots the fixture app in tests/Fixtures/App)
composer analyse            # phpstan, level max
```

The test suite (fixture application, environments, process isolation rules) is described in [tests/AGENTS.md](tests/AGENTS.md).

## Conventions

- **Conventional Commits 1.0.0** for every commit message: `type(optional-scope): description`. Allowed types: `feat`, `fix`, `chore`, `ci`, `docs`, `test`, `refactor`, `build`. Breaking changes: `!` after the type/scope and a `BREAKING CHANGE:` footer.
- **Conventional branch names**: `feat/…`, `fix/…`, `chore/…`, `ci/…`, `docs/…`, `test/…`, `refactor/…` (kebab-case after the slash).
- PR titles must themselves be valid conventional commit subjects — CI enforces this (`.github/workflows/lint-pr.yml`).
- New classes are `final` unless they are meant to be extended (`AspectSymfonyKernel` is extendable through `go_aop.kernel_class`).
- See `CONTRIBUTING.md` for the full contributor workflow.

## Things agents get wrong here

- Do not initialize the kernel at compile time (in the extension or a compiler pass) or lazily on first use.
- Do not re-enable `inline_class_loader`, and do not replace the service locator of aspects with eager instances.
- Do not subclass the framework commands or dump bootstrap files to run the kernel outside of Symfony: the framework commands discover the running kernel, and the preload recipe boots the Symfony kernel.
- Do not add `containerClass` to the configuration: the framework default (`Go\Core\Container`) is correct and `Go\Core\GoAspectContainer` no longer exists.
- Do not make the cache warmer non-optional: the cache is built on demand at runtime, the warm-up is for deployments.
- The upstream 4.x line is unreleased: composer requires `minimum-stability: dev` + `prefer-stable: true`. Do not "fix" that to stable until goaop/framework 4.0 is tagged.
