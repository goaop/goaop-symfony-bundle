# Agent guidance for the test suite

This file is the canonical guide to the tests of goaop/goaop-symfony-bundle, for AI coding agents and humans alike.
The repository-wide rules are in the [root AGENTS.md](../AGENTS.md).

## Commands

```bash
composer test               # vendor/bin/phpunit, the whole suite
vendor/bin/phpunit --filter DebugClassLoaderTest
composer analyse            # phpstan, level max (src/ only)
```

## Layout

- `tests/*Test.php`, `tests/DependencyInjection/` — unit tests of the bundle class, the configuration, the options resolver and the compiler passes. They never boot a kernel.
- `tests/Functional/` — end-to-end tests that boot the fixture application. They extend `FunctionalTestCase` (kernel boot, console runs in a subprocess, cache cleanup).
- `tests/Fixtures/App/` — the fixture Symfony application: `Kernel.php`, `bin/console`, `config/`, and the weaving fixtures in `src/` (aspects, services, a Doctrine entity). `Admin/` and `BaseClassGenerator.php` hold the fixtures of the `generated` environments, outside `src/` so that no other environment weaves them.
- `tests/bootstrap.php` — gives every test process a cache directory of its own (`APP_CACHE_DIR`) and removes it at exit.

## Fixture environments

`tests/Fixtures/App/Kernel.php` selects bundles and services by environment:

| Environment | Mode | Purpose |
|---|---|---|
| `test` | debug | weaving on demand, the default |
| `prod` | production, `PREBUILT_CACHE` | the cache must be warmed up before booting |
| `doctrine` | debug | DoctrineBundle with an attribute-mapped entity |
| `profiler` | debug | the web profiler and a `/greet` route |
| `misplaced` | — | GoAopBundle registered after FrameworkBundle, for the ordering check |
| `signatures` | debug | the woven classes of `src/Signature` and their aspect, for the DebugClassLoader checks (#23) |
| `generated` | debug | also weaves `Admin/`, whose class extends a class generated into the cache directory that Composer can not locate (`BaseClassGenerator`, #17) |
| `generated_excluded` | debug | `generated` with `Admin/` in `exclude_paths` |

Adding an environment:

- Keep its fixtures in a directory of their own and exclude that directory from the default `services->load()` of `src/`, so the aspect and advisor counts of the other environments do not change.
- Document it in the docblock of `Kernel.php` and in the table above.

## Rules that keep the suite reliable

- `Go\Core\AspectKernel` is a process-wide singleton that wraps the Composer class loader. Every test booting a kernel runs with `#[RunTestsInSeparateProcesses]` and `#[PreserveGlobalState(false)]`.
- The process that compiles the container loads the service classes before the bundle boots, so they are not woven in that process. A test that needs woven services builds the container first in a separate process: `self::runConsole(['cache:warmup', '--no-optional-warmers'])`.
- Weaving fixtures (`tests/Fixtures/App/src`) must never be autoloaded before the kernel boots. Refer to them with `::class` only: no `new`, static calls, constants or `class_exists()` in the test process before booting.
- A test that changes a fixture file (content or mtime) restores it in a `finally` block or in `tearDown()`.
- Symfony regenerates `tests/Fixtures/App/config/reference.php` on every container build, for the environment that was built last, so a test run usually leaves it modified. Do not commit those changes: restore it with `git checkout -- tests/Fixtures/App/config/reference.php`.
- When collecting deprecations or errors (e.g. under the DebugClassLoader), assert on the messages that concern the fixture classes only: dependencies may report their own deprecations.

## Testing against a local framework checkout

Until goaop/framework 4.0 is tagged, the suite runs against the `4.0-dev` line (`minimum-stability: dev`, `prefer-stable: true`). To test a change of the framework, add a path repository pointing at your checkout to your local `composer.json` and run `composer update goaop/framework`. Do not commit it.
