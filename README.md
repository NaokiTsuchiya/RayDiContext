# Ray.Di Context

Context, meta, and compile management for [Ray.Di](https://github.com/ray-di/Ray.Di) applications.

| Directory    | Role                    | Lifecycle                                      |
|--------------|-------------------------|-------------------------------------------------|
| `compileDir` | Pre-compiled DI scripts | Baked into the image, **read-only** at runtime   |
| `tmpDir`     | Runtime scratch area    | **Writable** at runtime, never baked             |

`AppMeta` keeps the two independent, so `compileDir` can be baked into a
`readOnlyRootFilesystem` container while `tmpDir` stays a writable volume.

- `compileDir` defaults to `{appDir}/var/di/{context}`; the bundled CLI lets you
  override it with `APP_COMPILE_DIR`
- `tmpDir` defaults to `{appDir}/var/tmp/{context}`; the bundled CLI lets you override
  it with `APP_TMP_DIR`
- `AppMeta::fromAppDir()` never reads the environment itself — pass overrides in
  explicitly. Compile-time and runtime code must read the **same** env vars, or the
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

Compile ahead of time with the bundled `bin/compile.php` CLI. It takes a
*bootstrap* file that returns your `ContextProviderInterface`, plus the app
dir and context:

```php
// bootstrap.php — see examples/bootstrap.php
use NaokiTsuchiya\RayDiContext\MapContextProvider;

return new MapContextProvider(['prod' => ProdContext::class, 'dev' => DevContext::class]);
```

```
php vendor/bin/compile.php bootstrap.php "$(pwd)" prod
```

The CLI cleans the compile dir, compiles the context, and guards the
result against baked paths, exiting `0` on success. Under the hood it is:

```php
$provider = require 'bootstrap.php';
$meta = AppMeta::fromAppDir(
    getcwd(),
    'prod',
    getenv('APP_COMPILE_DIR') ?: null,
    getenv('APP_TMP_DIR') ?: null,
);

exit((new CompileRunner($provider))->run($meta));
```

Bootstrap at runtime. Read the **same** `APP_COMPILE_DIR`/`APP_TMP_DIR` as the compile
step above — `fromAppDir()` won't do it for you, and a mismatch means the running app
looks for compiled scripts in a different place than they were baked into:

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
