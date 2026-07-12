# Ray.Di Context

Context, meta, and compile management for [Ray.Di](https://github.com/ray-di/Ray.Di) applications.

This package separates two directories that runtime frameworks often weld together:

| Directory    | Role                                   | Lifecycle                                              |
|--------------|----------------------------------------|--------------------------------------------------------|
| `compileDir` | Pre-compiled DI scripts                | Baked into the container image, **read-only** at runtime |
| `tmpDir`     | Runtime scratch area (e.g. `/tmp`)     | **Writable** at runtime, never baked into the image      |

## Why

Deploying a PHP application with `readOnlyRootFilesystem` (Kubernetes) requires that
everything written at runtime goes to a mounted writable volume, while everything else
is immutable. A DI compile step fits this model perfectly — *if* the compiled scripts
live in their own directory that can be baked into the image. Frameworks that derive
the script directory from the runtime tmp directory (`scriptDir = tmpDir . '/di'`)
make that impossible.

`AppMeta` keeps the two independent:

- `compileDir` defaults to `{appDir}/var/di/{context}`, overridable with `APP_COMPILE_DIR`
- `tmpDir` defaults to `{appDir}/var/tmp/{context}`, overridable with `APP_TMP_DIR`

A baked `compileDir` is safe under one invariant: **the compile step must run at the
same path the runtime uses** (the same `APP_COMPILE_DIR` at image build and at run).
Ray.Compiler loads scripts relative to the runtime-resolved script dir, but bindings
that inject `#[ScriptDir]` or `InjectorInterface` freeze the build-time `compileDir`
string into the scripts, so moving the scripts to a different path after compiling
(multi-stage `COPY`, env drift between Dockerfile and manifest) breaks them.

Paths bound with `toInstance()`, however, are never safe: they are frozen into the
compiled scripts — including every path held by an object bound with `toInstance()`.
That is why:

- **Never bind `AppMeta` with `toInstance()`.** Bind it with a provider that builds it
  at runtime, or inject the individual values you need.
- **`BakedPathGuard` fails the compile when a script contains an `appDir` or `tmpDir`
  literal**, so the mistake is caught in CI, not in production.

## Installation

```
composer require naokitsuchiya/ray-di-context
```

## Usage

Define one context per environment. A compiled (production) context composes
`DiCompileModule` and reads from the read-only `compileDir`; a development context
uses the regular runtime injector with the writable `tmpDir`. The `tmpDir` must
exist — `Ray\Di\Injector` silently falls back to the system temp dir otherwise.

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

Compile ahead of time (`bin/compile.php`), typically during the image build:

```php
use NaokiTsuchiya\RayDiContext\AppMeta;
use NaokiTsuchiya\RayDiContext\CompileRunner;
use NaokiTsuchiya\RayDiContext\MapContextProvider;

require dirname(__DIR__) . '/vendor/autoload.php';

$provider = new MapContextProvider(['prod' => ProdContext::class, 'dev' => DevContext::class]);
$meta = AppMeta::fromAppDir('my-app', dirname(__DIR__), 'prod');

exit((new CompileRunner($provider))->run('prod', $meta));
```

`CompileRunner::run()` recreates the compile dir (`Cleaner`), compiles the context
module, and verifies the output (`BakedPathGuard`). Stale scripts from renamed classes
or changed bindings never survive a recompile.

Bootstrap at runtime:

```php
$meta = AppMeta::fromAppDir('my-app', dirname(__DIR__), 'prod');
$context = $provider->get(getenv('APP_ENV') ?: 'prod', $meta);
$injector = $context->getInjectorInstance();

foreach ($context->getSavedSingleton() as $class) {
    $injector->getInstance($class); // instantiated once per process, never unserialized
}
```

`getSavedSingleton()` lists classes that are instantiated once at process start under
the real environment. Because they are freshly constructed — not unserialized from a
compile-time snapshot — they may hold runtime resources such as database connections.
Note that singleton scope is per injector instance: they are shared through the
bootstrap injector, not with any separate injector a class obtains by injecting
`InjectorInterface`.

### Container deployment

```dockerfile
ENV APP_COMPILE_DIR=/var/di
RUN php bin/compile.php        # bake the DI scripts into the image
```

```yaml
# Kubernetes
securityContext:
  readOnlyRootFilesystem: true
env:
  - name: APP_COMPILE_DIR
    value: /var/di
  - name: APP_TMP_DIR
    value: /tmp/app            # emptyDir volume
```

## Requirements

- PHP 8.2+
- ray/di ^2.19, ray/compiler ^1.13

## License

MIT
