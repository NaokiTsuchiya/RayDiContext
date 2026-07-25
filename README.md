# Ray.Di Context

Context, meta, and compile management for [Ray.Di](https://github.com/ray-di/Ray.Di) applications.

| Directory    | Role                    | Lifecycle                                      |
|--------------|-------------------------|-------------------------------------------------|
| `compileDir` | Pre-compiled DI scripts | Baked into the image, **read-only** at runtime   |
| `tmpDir`     | Runtime scratch area    | **Writable** at runtime, never baked             |

`AppMeta` keeps the two independent, so `compileDir` can be baked into a
`readOnlyRootFilesystem` container while `tmpDir` stays a writable volume.

- `compileDir` defaults to `{appDir}/var/di/{context}`, overridable with `APP_COMPILE_DIR`
- `tmpDir` defaults to `{appDir}/var/tmp/{context}`, overridable with `APP_TMP_DIR`

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
$meta = AppMeta::fromAppDir(getcwd(), 'prod');

exit((new CompileRunner($provider))->run($meta));
```

Bootstrap at runtime:

```php
$meta = AppMeta::fromAppDir(dirname(__DIR__), getenv('APP_ENV') ?: 'prod');
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
