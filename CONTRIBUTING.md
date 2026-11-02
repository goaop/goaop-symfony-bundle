# Contributing

Thanks for helping improve the Go! AOP Symfony bundle!

## Workflow

1. Fork/branch off `master`.
2. Name your branch using **conventional branch names** (see below).
3. Make your changes with **conventional commits**.
4. Ensure the suite is green locally: `composer validate --strict && composer test && composer analyse`.
5. Open a pull request. The PR title must be a valid conventional commit subject — CI checks it.

## Conventional commits

Every commit message follows [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/):

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Allowed types:

| Type | Use for |
|---|---|
| `feat` | new user-facing functionality |
| `fix` | bug fixes |
| `chore` | maintenance that touches neither src behavior nor CI |
| `ci` | CI workflows and build tooling |
| `docs` | documentation only |
| `test` | adding or fixing tests only |
| `refactor` | behavior-preserving code changes |
| `build` | composer/dependency/packaging changes |

Breaking changes: append `!` after the type/scope (e.g. `feat!: drop Symfony 7.4 support`) and add a `BREAKING CHANGE:` footer explaining the migration.

Examples:

```
feat: add a profiler panel listing the woven classes of a request
fix(warmer): rebase the cache directory on the warmup directory of cache:clear
ci: add PHP 8.5 experimental job to the matrix
```

## Conventional branches

Branch names mirror commit types, kebab-case after the slash:

```
feat/profiler-panel
fix/cache-clear-warmup-dir
chore/update-dependabot
ci/php85-experimental
docs/readme-preload-section
```

## Pull requests

- Keep PRs focused on one concern; stack PRs rather than mixing streams.
- Fill in the PR template; link the issue the PR addresses (`Closes #N`).
- Squash-merge is preferred; the squash commit message must itself be a valid conventional commit (the PR title is used, hence the title check).

## Testing notes

- The suite boots a real Symfony application: `tests/Fixtures/App` (environments `test`, `prod`, `doctrine`, `profiler`, `misplaced` and `signatures`).
- `Go\Core\AspectKernel` is a process-wide singleton that wraps the Composer class loader, so every test booting a kernel runs with `#[RunTestsInSeparateProcesses]` and `#[PreserveGlobalState(false)]`. `tests/bootstrap.php` gives every process a cache directory of its own (`APP_CACHE_DIR`).
- The process that compiles the container loads the service classes before the bundle boots, so a test that needs woven services builds the container first in a separate process: `self::runConsole(['cache:warmup', '--no-optional-warmers'])`.
- Weaving fixtures (`tests/Fixtures/App/src`) must never be autoloaded before the kernel boots: refer to them with `::class` only.
- Until goaop/framework 4.0 is tagged, `composer.json` needs `minimum-stability: dev` with `prefer-stable: true`. To test against a local checkout of the framework, add a path repository to your local `composer.json` (do not commit it).
