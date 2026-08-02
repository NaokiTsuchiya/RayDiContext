# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
(while on 0.x, minor releases may include backwards-incompatible changes — see
README's Versioning section).

## [Unreleased]

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
