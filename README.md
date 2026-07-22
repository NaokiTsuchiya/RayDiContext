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
composer require naokitsuchiya/ray-di-context
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

Compile ahead of time (`bin/compile.php`):

```php
$provider = new MapContextProvider(['prod' => ProdContext::class, 'dev' => DevContext::class]);
$meta = AppMeta::fromAppDir('my-app', dirname(__DIR__), 'prod');

exit((new CompileRunner($provider))->run('prod', $meta));
```

Bootstrap at runtime:

```php
$meta = AppMeta::fromAppDir('my-app', dirname(__DIR__), 'prod');
$context = $provider->get(getenv('APP_ENV') ?: 'prod', $meta);
$injector = $context->getInjectorInstance();

foreach ($context->getSavedSingleton() as $class) {
    $injector->getInstance($class);
}
```

## Requirements

PHP 8.2+, ray/di ^2.19, ray/compiler ^1.14

## License

MIT
