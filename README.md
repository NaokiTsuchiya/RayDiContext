# Ray.Di Context

Context, meta, and compile management for [Ray.Di](https://github.com/ray-di/Ray.Di) applications.

| Directory    | Role                    | Lifecycle                                      |
|--------------|-------------------------|-------------------------------------------------|
| `compileDir` | Pre-compiled DI scripts | Baked into the image, **read-only** at runtime   |
| `tmpDir`     | Runtime scratch area    | **Writable** at runtime, never baked             |

`AppMeta` keeps the two independent, so `compileDir` can be baked into a
`readOnlyRootFilesystem` container while `tmpDir` stays a writable volume.

- `compileDir`/`tmpDir` default to `{appDir}/var/di/{context}` / `{appDir}/var/tmp/{context}`
- `appDir` must exist — `AppMeta::fromAppDir()` resolves it with `realpath()`, so a relative
  path is never baked into the compiled scripts, and rejects it otherwise
- Neither `AppMeta::fromAppDir()` nor the bundled CLI reads the environment — pass
  overrides in explicitly (e.g. as CLI arguments, sourced from env vars by your shell
  or Dockerfile). Compile-time and runtime code must agree on the same values, or the
  compiled scripts and the running app will look in different places

**Never bind `AppMeta` with `toInstance()`** — Ray.Compiler freezes bound objects into
the compiled scripts. `BakedPathGuard` fails the compile if `appDir`/`tmpDir` leaks in.

## Install

```
composer require naoki-tsuchiya/ray-di-context
```

## Usage

```php
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
paths, and normalizes the permissions of what it wrote. Under the hood it is:

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
| `1`  | The compile failed, or `appDir` does not exist. Every exception of this package (`UnknownContext`, `BakedPathFound`, `CompileDirNotWritable`, `InvalidAppMeta`, …) is caught and its message written to STDERR as a single line — no stack trace, so the CI log stays readable |
| `2`  | Usage error: wrong number of arguments, bootstrap file not found, or a bootstrap that does not return a `ContextProviderInterface` |

Bootstrap at runtime. Resolve `compileDir`/`tmpDir` to the **same** values you passed
to the CLI above — a mismatch means the running app looks for compiled scripts in a
different place than they were baked into:

```php
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

## Requirements

PHP 8.2+, ray/di ^2.19, ray/compiler ^1.14

## License

MIT
