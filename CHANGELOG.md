# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
(while on 0.x, minor releases may include backwards-incompatible changes — see
README's Versioning section).

## [Unreleased]

### Fixed

- `BakedPathGuard` no longer misses a baked path or configured literal that holds `'` or `\`.
  Ray.Compiler writes a `toInstance()` object or array into its script as
  `unserialize(var_export(serialize($value), true))`, and a plain value as `var_export($value)`, so
  both bytes arrive escaped for a single-quoted PHP literal — `\\` and `\'`. `BakedPathScanner`
  compared raw bytes against that escaped text, so a path such as `/srv/it's/app` or a password
  such as `p@ss'w\rd` passed the guard and shipped inside the image. The scanner now unescapes
  those two sequences before scanning, which also stops the mirror-image false positive: a
  `compileDir` spelled with either byte was not recognised as an allowed range, so any needle
  inside it was reported as baked.

## [0.3.0] - 2026-08-15

### Added

- `InjectorBuilder`, which turns a `(ContextInterface, AppMeta)` pair into the
  `WarmableInjectorInterface` serving this process: a context carrying the new
  `CompiledContextInterface` marker gets a `CompiledWarmableInjector` over the read-only
  `compileDir`, any other context gets a `RuntimeWarmableInjector` compiling into `tmpDir` as it
  resolves. A missing or unreadable compile dir surfaces as `Exception\CompileDirUnavailable` for
  every compiled context — previously only contexts extending `AbstractCompiledContext` got that
  wrapping.
- `CompiledContextInterface`, a marker with no methods. Implementing it is the single place an
  environment states "resolved from the ahead-of-time compiled scripts"; a dev context and a prod
  context can differ by that one `implements` clause alone.
- `WarmableInjectorInterface`, `CompiledWarmableInjector` and `RuntimeWarmableInjector`. `warmup()`
  instantiates every singleton the compile recorded in `singletons.json` before anything resolves
  one, so a coroutine runtime cannot race two requests into building the same singleton twice. Call
  it at worker start under Swoole and friends; skip it under PHP-FPM, where the injector lives for
  one request and eager warming costs more than lazy resolution. The branch the builder took
  travels as the concrete class, so no runtime check is needed anywhere: the runtime face has
  nothing to warm and returns quietly, while the compiled face raises the new
  `Exception\WarmupNotCompiled` for scripts carrying no singleton metadata rather than silently
  doing nothing — `ray/compiler`'s own `SingletonsFileNotFound` stays retrievable via
  `getPrevious()`.
- `Exception\CompileFailed`, thrown by `CompileRunner::run()` when the injected
  `ScriptCompilerInterface` throws while compiling the context module. Wraps the compiler's own
  exception (e.g. a missing binding surfacing as `ray/compiler`'s or `ray/di`'s own `Unbound`),
  retrievable via `getPrevious()`, so a `CompileRunner::run()` caller no longer needs to catch a
  `ray/compiler`/`ray/di` exception type directly.

### Changed

- `ray/compiler` is now required at `^1.15`, the release that writes `singletons.json` and adds
  `CompiledInjector::warmup()`.
- `bin/ray-di-compile`'s STDERR message for a compile-step failure (e.g. a missing binding) now
  reads through the wrapped `Exception\CompileFailed` instead of the raw exception's
  `{class}: {message}` passthrough; the message still carries the original exception's class and
  text, and the exit status (`1`) is unchanged.

### Removed

- **`ContextInterface::getInjectorInstance()`** — the context no longer carries the injector.
  Migrate by replacing `$context->getInjectorInstance()` with
  `(new InjectorBuilder())($context, $meta)` in the bootstrap and deleting the method from each
  context; a compiled context implements `CompiledContextInterface` instead of constructing a
  `CompiledInjector`.
- **`ContextInterface::getSavedSingleton()`** — superseded by `warmup()` on the injector the
  builder returns, which reads the compiler-recorded list and cannot miss a singleton the way a
  hand-written one can. Migrate by deleting the override and calling `$injector->warmup()` at
  process start.
- **`AbstractCompiledContext`** — with the injector gone and the `DiCompileModule` wrap measured
  inert (`Compiler::compile()` binds the `Compile` flag itself; wrapped and bare compiles of the
  same module are byte-identical), nothing remained for it to do. A former subclass extends
  `AbstractContext`, implements `CompiledContextInterface`, and renames `appModule()` to
  `__invoke()`.

## [0.2.0] - 2026-08-05

### Added

- `AbstractCompiledContext`, a base class for the ahead-of-time compiled production shape
  that composes `DiCompileModule`/`CompiledInjector` so consumer code no longer imports
  `Ray\Compiler` class names. Its `getInjectorInstance()` catches `ray/compiler`'s
  `ScriptDirNotReadable` and rethrows the new `Exception\CompileDirUnavailable`, so a
  missing or unreadable `compileDir` is observable through this package's own
  `ExceptionInterface` without naming a `Ray\Compiler` exception either; the original stays
  retrievable via `getPrevious()`.

### Changed

- `AppMeta::__construct()` now rejects a relative `compileDir` or `tmpDir`, matching the
  existing requirement that `appDir` be an absolute path.
- `BakedPathGuard::__construct()` now rejects an empty string or a non-string value among
  `$extraNeedles`, throwing the new `Exception\InvalidExtraNeedle` immediately instead of
  letting it silently fail every compile via a rejection that cannot name the cause.
- `UnsafeCompileDir`'s messages now end with `Pass a compile dir that holds nothing but
  compiled scripts.`, replacing the `Point APP_COMPILE_DIR at...` hint that named an
  environment variable nothing in this package reads.

## [0.1.0] - 2026-08-02

### Added

- Ahead-of-time Ray.Di compilation that separates a read-only `compileDir` from a
  writable `tmpDir`, so a compiled injector can run under a read-only root
  filesystem.
- `BakedPathGuard`, which scans compiled scripts for baked-in `appDir`/`tmpDir`
  paths (or custom needles) and fails the compile before anything unsafe reaches
  `compileDir`.
- The `bin/ray-di-compile` CLI for compiling a context ahead of time; see the
  README's *Exit status* table for its exit codes.
