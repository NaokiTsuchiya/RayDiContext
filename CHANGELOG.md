# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
(while on 0.x, minor releases may include backwards-incompatible changes — see
README's Versioning section).

## [Unreleased]

### Added

- `WarmInjectorInterface`, an injector that can instantiate its singletons before anything asks for
  one, and `CompiledWarmInjector`, its implementation over `ray/compiler`'s `CompiledInjector`.
  `warmup()` instantiates every singleton the compile recorded in `singletons.json`, so a coroutine
  runtime cannot race two requests into building the same singleton twice. A compile dir holding no
  such metadata raises the new `Exception\WarmupNotCompiled` rather than silently doing nothing;
  `ray/compiler`'s own `SingletonsFileNotFound` stays retrievable via `getPrevious()`.
- `AbstractWarmCompiledContext`, the ahead-of-time compiled base context whose
  `getInjectorInstance()` returns a `CompiledWarmInjector`. It supersedes `AbstractCompiledContext`
  for new code; that class, `AbstractContext` and `ContextInterface` — including
  `getSavedSingleton()`, the hand-written predecessor of `warmup()` — are unchanged, so an existing
  context keeps working untouched and moves over by changing which base class it extends.
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
