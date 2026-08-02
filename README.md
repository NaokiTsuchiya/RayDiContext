# Ray.Di Context

[![CI](https://github.com/NaokiTsuchiya/RayDiContext/actions/workflows/ci.yml/badge.svg)](https://github.com/NaokiTsuchiya/RayDiContext/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/NaokiTsuchiya/RayDiContext/graph/badge.svg)](https://codecov.io/gh/NaokiTsuchiya/RayDiContext)
[![PHP Version](https://img.shields.io/badge/php-8.2%20--%208.5-777BB4)](composer.json)
[![License](https://img.shields.io/github/license/NaokiTsuchiya/RayDiContext)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/naoki-tsuchiya/ray-di-context)](https://packagist.org/packages/naoki-tsuchiya/ray-di-context)

Context, meta, and compile management for [Ray.Di](https://github.com/ray-di/Ray.Di) applications.

## Why

Ray.Di's plain `Injector` compiles each binding into a PHP script written to
`tmpDir` the first time it's resolved — at request time. A container running
with a read-only root filesystem (Docker `--read-only`, Kubernetes
`securityContext.readOnlyRootFilesystem: true`) has nowhere writable for that
first write to land, so the first request fails.

`Ray\Compiler` solves the *write*: compile ahead of time, ship the compiled
scripts, run `CompiledInjector` against them at runtime — no writes needed. It
doesn't solve the *path*: the compiled scripts and the app that reads them have
to resolve `compileDir`/`tmpDir` to the exact same strings, and nothing stops a
runtime-only path from getting frozen into a script by accident. `AppMeta`,
`ContextInterface`, and `BakedPathGuard` exist to make that separation safe —
`compileDir` stays read-only and gets baked into the image, `tmpDir` stays
writable and never does, and CI can verify the split before the image is even
built.

If you're on [BEAR.Sunday](https://bear.sunday.dev/), you already have this —
`BEAR\AppMeta\Meta` and `AbstractAppContext` solve the same problem, and this
package's vocabulary (`AppMeta`, context, compile) deliberately echoes theirs.
You don't need both.

| Directory    | Role                    | Lifecycle                                      |
|--------------|-------------------------|-------------------------------------------------|
| `compileDir` | Pre-compiled DI scripts | Baked into the image, **read-only** at runtime   |
| `tmpDir`     | Runtime scratch area    | **Writable** at runtime, never baked             |

`AppMeta` keeps the two independent, so `compileDir` can be baked into a
`readOnlyRootFilesystem` container while `tmpDir` stays a writable volume.

- `compileDir`/`tmpDir` default to `{appDir}/var/di/{context}` / `{appDir}/var/tmp/{context}`
- `appDir` must be an absolute path — `AppMeta::fromAppDir()` rejects a relative one
  outright rather than resolving it, so the spelling baked into compiled scripts is
  always the same spelling the running app binds. `BakedPathGuard` compares those
  strings verbatim, so resolving symlinks here would make the guard fail open
- Neither `AppMeta::fromAppDir()` nor the bundled CLI reads the environment — pass
  overrides in explicitly (e.g. as CLI arguments, sourced from env vars by your shell
  or Dockerfile). Compile-time and runtime code must agree on the same values, or the
  compiled scripts and the running app will look in different places
- This package creates `compileDir` for you but never creates `tmpDir` — `mkdir` it
  yourself before runtime (e.g. in your Dockerfile or bootstrap script). Ray.Di's
  `Injector` silently falls back to `sys_get_temp_dir()` when `tmpDir` doesn't exist,
  so a missing directory doesn't throw — writes just land somewhere you didn't expect.
  Add `var/di/` and `var/tmp/` (or wherever your `compileDir`/`tmpDir` point) to your
  application's `.gitignore`

**Never bind a runtime-determined value or secret with `toInstance()`** —
Ray.Compiler freezes whatever you pass into the compiled scripts, and `compileDir`
ships inside your image. Binding `AppMeta` this way leaks `appDir`/`tmpDir`, which
`BakedPathGuard` catches and fails the compile on — but the guard only scans for
those two path strings. It won't catch anything else:
`$this->bind()->annotatedWith('db_password')->toInstance('s3cr3t-P@ssw0rd')` writes
the password in plaintext into a compiled script under `compileDir`, and nothing
stops it *by default*. If you know what your own secrets look like, hand them to the
guard and it will fail the compile the same way it does for a baked path:

```php
use NaokiTsuchiya\RayDiContext\BakedPathGuard;

$dbPassword = getenv('DB_PASSWORD');
$needles = $dbPassword === false || $dbPassword === '' ? [] : [$dbPassword];

(new CompileRunner($provider, guard: new BakedPathGuard($needles)))->run($meta);
```

A rejection names the script but never repeats the value — these are supplied precisely
because they must not ship, and quoting one would move it out of the image and into your
CI log. For anything the bundled scanner can't express, implement `BakedPathGuardInterface`
yourself and pass that instead.

Better still, bind secrets and other runtime-determined values through a provider
— a provider's `get()` runs each time the compiled injector resolves the
binding, not once at compile time, so nothing gets frozen into the script:

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

`TmpDirProvider` reads the environment itself, at the moment its value is asked
for — the same rule this package follows in `AppMeta::fromAppDir()` and the CLI:
resolve runtime-determined values at runtime, never freeze them into a compiled
script.

## Install

```
composer require naoki-tsuchiya/ray-di-context
```

## Usage

```php
use NaokiTsuchiya\RayDiContext\AbstractContext;
use Ray\Compiler\CompiledInjector;
use Ray\Compiler\DiCompileModule;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\Di\InjectorInterface;

final class ProdContext extends AbstractContext
{
    public function __invoke(): AbstractModule
    {
        return new DiCompileModule(true, new AppModule());
    }

    public function getInjectorInstance(): InjectorInterface
    {
        return new CompiledInjector($this->meta->compileDir);
    }
}

final class DevContext extends AbstractContext
{
    public function __invoke(): AbstractModule
    {
        return new AppModule();
    }

    public function getInjectorInstance(): InjectorInterface
    {
        return new Injector($this(), $this->meta->tmpDir);
    }
}
```

Compile ahead of time with the bundled `bin/ray-di-compile` CLI. It takes a
*bootstrap* file that returns your `ContextProviderInterface`, the app dir, the
context, and optionally `compileDir`/`tmpDir` overrides:

```php
// bootstrap.php — see examples/bootstrap.php
use NaokiTsuchiya\RayDiContext\MapContextProvider;

return new MapContextProvider(['prod' => ProdContext::class, 'dev' => DevContext::class]);
```

```
php vendor/bin/ray-di-compile bootstrap.php "$(pwd)" prod
```

The CLI itself never reads the environment; if your deployment sets
`APP_COMPILE_DIR`/`APP_TMP_DIR`, pass them through explicitly (e.g. in a Dockerfile
`RUN` step):

```
php vendor/bin/ray-di-compile bootstrap.php "$(pwd)" prod "$APP_COMPILE_DIR" "$APP_TMP_DIR"
```

Ray.Compiler writes every script `0600`, so a compile dir built as `root` would be
unreadable to a non-root runtime user; the compiled scripts are normalized to `0644`
(their directories to `0755`) so the image stays readable after a `USER` switch.

The CLI cleans the compile dir, compiles the context, guards the result against baked
paths, and normalizes the permissions of what it wrote. **A compile that fails the guard
leaves `compileDir` empty**, so scripts it refused can never be `COPY`-ed into an image by a
later build step. Under the hood it is:

```php
$provider = require 'bootstrap.php';
$meta = AppMeta::fromAppDir(getcwd(), 'prod', $compileDir, $tmpDir); // args 4/5, or null

(new CompileRunner($provider))->run($meta); // returns void, throws on failure
```

### Exit status

The exit status is a public contract — gate your CI on it.

| Code | Meaning |
|------|---------|
| `0`  | The context compiled successfully |
| `1`  | The compile failed. **Anything** thrown while loading the bootstrap or compiling is caught and its message written to STDERR as a single line — no stack trace, so the CI log stays readable. That covers this package's own exceptions (`UnknownContext`, `BakedPathFound`, `CompileDirNotWritable`, `InvalidAppMeta` — e.g. a relative `appDir` — …) and equally the failures that come from your module: a missing binding surfaces as `Ray\Di\Exception\Unbound`, and anything foreign is prefixed with its class name so you can tell where it came from. The autoloader also being unfindable reports here |
| `2`  | Usage error: wrong number of arguments, `appDir` does not exist, bootstrap file not found, or a bootstrap that does not return a `ContextProviderInterface` |

Bootstrap at runtime. Resolve `compileDir`/`tmpDir` to the **same** values you passed
to the CLI above — a mismatch means the running app looks for compiled scripts in a
different place than they were baked into:

```php
$provider = require 'bootstrap.php';
$meta = AppMeta::fromAppDir(
    dirname(__DIR__),
    getenv('APP_ENV') ?: 'prod',
    getenv('APP_COMPILE_DIR') ?: null,
    getenv('APP_TMP_DIR') ?: null,
);
$context = $provider->get($meta);
$injector = $context->getInjectorInstance();

foreach ($context->getSavedSingleton() as $class) {
    $injector->getInstance($class);
}
```

`getInjectorInstance()` is called once and the result reused above — see the
docblock on `ContextInterface::getInjectorInstance()` for why that matters.
`getSavedSingleton()` names classes to instantiate once, right after boot: a
compiled injector never unserializes instances, so anything holding a runtime
resource (a database connection, for example) needs this explicit warmup, or it
won't exist until something happens to request it first.

## Deploying to Docker / Kubernetes

Build the compiled scripts in a build stage, `COPY` only the result into the
runtime image, and run as a non-root user with a read-only root filesystem:

```dockerfile
# syntax=docker/dockerfile:1
FROM php:8.3-cli AS build
WORKDIR /build
COPY . .
RUN composer install --no-dev --optimize-autoloader \
 && php bin/ray-di-compile bootstrap.php /build prod /app/var/di/prod

FROM php:8.3-cli
WORKDIR /app
COPY --from=build /build /app
RUN useradd --system appuser
USER appuser
CMD ["php", "bin/console"]
```

The build stage compiles at `/build`; the runtime image runs at `/app`. Left to
its defaults, `AppMeta::fromAppDir()` would derive `compileDir` from `appDir`, so
the scripts above would be compiled for `/build/var/di/prod` while the running
app looks for them at `/app/var/di/prod` — a mismatch that `CompiledInjector`
reports as `ScriptDirNotReadable` the moment the first class is resolved. That's
why the `RUN` step above passes `APP_COMPILE_DIR` explicitly, resolved to the
*runtime* path, not the build path — and the app's own bootstrap must resolve
`APP_COMPILE_DIR` to that same runtime path:

```php
$meta = AppMeta::fromAppDir(dirname(__DIR__), 'prod', '/app/var/di/prod');
```

`APP_TMP_DIR` doesn't fail this loudly at compile time — a `tmpDir` that doesn't
exist yet only surfaces once something tries to write to it, silently, in
`sys_get_temp_dir()` (see above) — but resolve it to the runtime path for the
same reason.

`APP_COMPILE_DIR`/`APP_TMP_DIR` override the whole directory, not just the
`appDir` they're derived from, so they ignore `context` entirely. One override
can only ever serve one context: baking `prod-cli` and `prod-html` into the same
image means compiling both to their conventional, context-suffixed paths
(`{appDir}/var/di/prod-cli`, `{appDir}/var/di/prod-html`) and `COPY`-ing the
whole `appDir`, rather than pointing `APP_COMPILE_DIR` at one shared path.

In Kubernetes, mount `tmpDir` as an `emptyDir` (`medium: Memory` for tmpfs) and
leave everything else read-only:

```yaml
securityContext:
  readOnlyRootFilesystem: true
  runAsNonRoot: true
volumeMounts:
  - name: tmp
    mountPath: /app/var/tmp
volumes:
  - name: tmp
    emptyDir: {}
```

Ray.Compiler writes two housekeeping files into `compileDir` alongside the
compiled scripts: `compile.lock` (held only during the compile) and
`_bindings.log` (a human-readable dump of the binding graph, including the
compile-time `compileDir` path). Both get baked into the image along with the
rest of `compileDir` — neither is meant to hold secrets, but they're not
scripts `BakedPathGuard` scans, either.

## Requirements

PHP 8.2 – 8.5, ray/di ^2.19, ray/compiler ^1.14

Both are left open on purpose. Your context class names `CompiledInjector` and
`DiCompileModule` as well as `AbstractModule`, so both packages are yours to
upgrade; a narrower constraint here would govern that upgrade instead.

## Development

A new `ray/di` or `ray/compiler` minor satisfies the declared range and reaches
you without passing through here, so Renovate has nothing to widen. CI runs
weekly on a schedule instead (`workflow_dispatch` runs it on demand), which
re-tests the ranges against whatever upstream has released since. A red one
means the newest release broke something, not that anyone changed this
repository; `composer show --direct` in the job log records which versions it
resolved.

`phpunit.xml.dist` sets `failOnDeprecation="true"`. Combined with the PHP 8.5
job in the CI matrix, a deprecation raised by `ray/di` or `ray/aop` under a
newer PHP version can turn CI red with zero changes in this repository — if a
Renovate PR or a new PHP minor suddenly fails for no apparent reason, check
here first. If it happens, either add `#[WithoutErrorHandler]` to the
affected test or relax `failOnDeprecation` for that PHP version only.

### Releasing

Before tagging, move the entries from `CHANGELOG.md`'s `## [Unreleased]`
section into a new `## [x.y.z] - <date>` section. Keep an empty
`## [Unreleased]` section at the top. The tag is never re-pointed, so the
changelog entry has to be right the first time.

Tags carry no `v` prefix — `0.1.0`, not `v0.1.0`. Composer accepts either, so
the only thing that matters is not mixing the two.

```bash
git tag -a 0.1.0 -m 'Initial release'
git push origin 0.1.0
gh release create 0.1.0 --generate-notes
```

Packagist builds the version from the git tag, not from the GitHub release —
the release is for humans. A published tag is never re-pointed: the tag ruleset
restricts updates and deletions, and anything wrong in a tag is fixed by the
next patch version.

## Versioning

While on 0.x, minor releases may include backwards-incompatible changes.
v1.0.0 will be tagged once the package has run in a real production
application. From v1.0.0 on, semantic versioning applies strictly.

## License

MIT
