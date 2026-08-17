# Ray.Di Context

[![CI](https://github.com/NaokiTsuchiya/RayDiContext/actions/workflows/ci.yml/badge.svg)](https://github.com/NaokiTsuchiya/RayDiContext/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/NaokiTsuchiya/RayDiContext/graph/badge.svg)](https://codecov.io/gh/NaokiTsuchiya/RayDiContext)
[![PHP Version](https://img.shields.io/badge/php-8.2%20--%208.5-777BB4)](composer.json)
[![License](https://img.shields.io/github/license/NaokiTsuchiya/RayDiContext)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/naoki-tsuchiya/ray-di-context)](https://packagist.org/packages/naoki-tsuchiya/ray-di-context)

Context, meta, and compile management for [Ray.Di](https://github.com/ray-di/Ray.Di) applications.

This README covers installing and using the package. For how the compile pipeline and runtime
resolution work internally, see [docs/architecture.md](docs/architecture.md); for approaches already
tried and rejected, see [docs/decisions.md](docs/decisions.md).

## Why

A container running with a read-only root filesystem (Docker `--read-only`, Kubernetes
`securityContext.readOnlyRootFilesystem: true`) has nowhere writable for Ray.Di's first
request-time compile to land. Compiling ahead of time with `Ray\Compiler` solves the write, but the
compiled scripts and the running app still have to resolve `compileDir` to the exact same string,
and nothing stops a runtime-only path from getting frozen into a script by accident.
`AppMeta`, `ContextInterface`, and `BakedPathGuard` exist to make that separation safe — see
[docs/architecture.md](docs/architecture.md) for how.

If you're on [BEAR.Sunday](https://bearsunday.github.io/), you already have this —
`BEAR\AppMeta\Meta` and `AbstractAppContext` solve the same problem, and this package's vocabulary
deliberately echoes theirs. You don't need both.

| Directory    | Role                    | Lifecycle                                      |
|--------------|-------------------------|-------------------------------------------------|
| `compileDir` | Pre-compiled DI scripts | Baked into the image, **read-only** at runtime   |
| `tmpDir`     | Runtime scratch area    | **Writable** at runtime, never baked             |

- `compileDir`/`tmpDir` default to `{appDir}/var/di/{context}` / `{appDir}/var/tmp/{context}`
- `appDir` must be an absolute path — `AppMeta::fromAppDir()` rejects a relative one outright rather
  than resolving it, so the spelling baked into compiled scripts always matches what `BakedPathGuard`
  compares it against
- Neither `AppMeta::fromAppDir()` nor the bundled CLI reads the environment — pass overrides in
  explicitly. Compile-time and runtime code must agree on the same values
- This package creates `compileDir` but never `tmpDir` — `mkdir` it yourself before runtime, or Ray.Di
  silently falls back to `sys_get_temp_dir()`. Add `var/di/` and `var/tmp/` to your `.gitignore`

**Never bind a runtime-determined value or secret with `toInstance()`** — Ray.Compiler freezes
whatever you pass into the compiled scripts, and `compileDir` ships inside your image.
`BakedPathGuard` catches `appDir`/`tmpDir` leaking this way by default, but nothing else: pass your
own secrets as extra needles and it will fail the compile the same way, naming the script but never
repeating the value:

```php
use NaokiTsuchiya\RayDiContext\BakedPathGuard;

$dbPassword = getenv('DB_PASSWORD');
$needles = $dbPassword === false || $dbPassword === '' ? [] : [$dbPassword];

(new CompileRunner($provider, bakedPathGuard: new BakedPathGuard($needles)))->run($meta);
```

Better still, bind secrets and other runtime-determined values through a provider — `get()` runs
each time the compiled injector resolves the binding, not once at compile time, so nothing gets
frozen into the script:

```php
use Ray\Di\ProviderInterface;

/** @implements ProviderInterface<string> */
final class TmpDirProvider implements ProviderInterface
{
    public function get(): string
    {
        return getenv('APP_TMP_DIR') ?: sys_get_temp_dir();
    }
}
```

```php
$this->bind()->annotatedWith('tmp_dir')->toProvider(TmpDirProvider::class);
```

## Install

```
composer require naoki-tsuchiya/ray-di-context
```

## Usage

```php
use NaokiTsuchiya\RayDiContext\AbstractContext;
use NaokiTsuchiya\RayDiContext\CompiledContextInterface;
use Ray\Di\AbstractModule;

final class ProdContext extends AbstractContext implements CompiledContextInterface
{
    public function __invoke(): AbstractModule
    {
        return new AppModule();
    }
}

final class DevContext extends AbstractContext
{
    public function __invoke(): AbstractModule
    {
        return new AppModule();
    }
}
```

A context supplies its module, and nothing else. `CompiledContextInterface` is a marker: it is the
single place where "this environment resolves from the ahead-of-time compiled scripts" is stated.

Compile ahead of time with the bundled `bin/ray-di-compile` CLI, pointed at a *bootstrap* file that
returns your `ContextProviderInterface`:

```php
// bootstrap.php — see examples/bootstrap.php
use NaokiTsuchiya\RayDiContext\MapContextProvider;

return new MapContextProvider(['prod' => ProdContext::class, 'dev' => DevContext::class]);
```

`MapContextProvider` validates the whole map at construction and builds each context as
`new $class($meta)`. A context needing constructor dependencies beyond `AppMeta` (a secrets loader, a
clock) implements `ContextInterface` directly, with the bootstrap implementing
`ContextProviderInterface` itself instead of using the map; see
[docs/architecture.md](docs/architecture.md#extension-points-applications-implement) for the pattern.

```
php vendor/bin/ray-di-compile bootstrap.php "$(pwd)" prod
```

The CLI never reads the environment; pass `APP_COMPILE_DIR`/`APP_TMP_DIR` through explicitly if your
deployment sets them:

```
php vendor/bin/ray-di-compile bootstrap.php "$(pwd)" prod "$APP_COMPILE_DIR" "$APP_TMP_DIR"
```

It cleans the compile dir, compiles, guards the result against baked paths, and normalizes
permissions (Ray.Compiler writes `0600`; this rewrites to `0644`/`0755` so a root-built image stays
readable after a `USER` switch). **A compile that fails the guard leaves `compileDir` empty.** See
[docs/architecture.md](docs/architecture.md) for the full pipeline.

### Exit status

The exit status is a public contract — gate your CI on it.

| Code | Meaning |
|------|---------|
| `0`  | The context compiled successfully |
| `1`  | The compile failed — this package's own exceptions, a wrapped `ray/di`/`ray/compiler` error (`Exception\CompileFailed`), or anything the bootstrap itself throws. The message goes to STDERR as one line, no stack trace |
| `2`  | Usage error: wrong argument count, `appDir` missing, bootstrap not found, or a bootstrap not returning `ContextProviderInterface` |

Bootstrap at runtime, resolving `compileDir` to the **same** value passed to the CLI above
(`tmpDir` doesn't need to match — see [docs/deploying.md](docs/deploying.md)):

```php
use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\InjectorBuilder;

$provider = require 'bootstrap.php';
$meta = AppMeta::fromAppDir(
    dirname(__DIR__),
    getenv('APP_ENV') ?: 'prod',
    getenv('APP_COMPILE_DIR') ?: null,
    getenv('APP_TMP_DIR') ?: null,
);
$context = $provider->get($meta);
$injector = (new InjectorBuilder())($context, $meta);

$injector->warmup();
```

`InjectorBuilder` returns a `WarmableInjectorInterface` wrapping a read-only `CompiledInjector` for a
`CompiledContextInterface` context, a runtime `Injector` otherwise — one bootstrap serves every
environment. Call `warmup()` at worker
start under Swoole and friends to instantiate every compiled singleton up front; **skip it in a
PHP-FPM or short-lived-CLI bootstrap**, where warming everything costs more than resolving lazily.
Build once per process and reuse the result — singletons are cached per injector instance. See
[docs/architecture.md](docs/architecture.md#runtime-resolution-no-compile-step) for the full
mechanics.

## Deploying to Docker / Kubernetes

Build the compiled scripts in a build stage, `COPY` the application and the compiled scripts into
the runtime image, and run as a non-root user with a read-only root filesystem. Compiling only
proves the binding graph is internally consistent — it never calls any of it — so
[`examples/docker/bin/build-check`](examples/docker/bin/build-check) also resolves the compiled
result for real in that same build stage, catching a broken `Provider::get()` or an incomplete
`COPY` before the image ships instead of at the first request.
[`examples/docker/Dockerfile`](https://github.com/NaokiTsuchiya/RayDiContext/blob/main/examples/docker/Dockerfile)
is the full, buildable version; run `bash tests/docker-check.sh` from the repo root to reproduce it.

See [docs/deploying.md](docs/deploying.md) for the runtime bootstrap path, the Kubernetes `emptyDir`
tmp mount, and the `APP_COMPILE_DIR`/`APP_TMP_DIR` per-context override rules; see
[docs/architecture.md](docs/architecture.md#verifying-the-compile-examplesdockerbinbuild-check) for
how build-check also collects AOP proxy classes into `preload.php`.

## Requirements

PHP 8.2 – 8.5, ray/di ^2.19, ray/compiler ^1.15

## Development

See [docs/development.md](docs/development.md) for CI quirks and the release process.

## Versioning

While on 0.x, minor releases may include backwards-incompatible changes. v1.0.0 will be tagged once
the package has run in a real production application. From v1.0.0 on, semantic versioning applies
strictly.

## Upgrading

Breaking changes are listed under a version's `### Removed` heading in
[`CHANGELOG.md`](CHANGELOG.md), each entry documenting how to migrate off it.

## License

MIT
