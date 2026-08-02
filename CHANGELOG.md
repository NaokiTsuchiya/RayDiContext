# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
(while on 0.x, minor releases may include backwards-incompatible changes — see
README's Versioning section).

## [Unreleased]

### Changed

- The runtime dependencies are declared one minor at a time — `ray/di` as
  `~2.19.0 || ~2.20.0 || ~2.22.0` and `ray/compiler` as `~1.14.0`, in place of
  `^2.19` and `^1.14`. Every version installable under the old constraints is
  still installable; a minor released from now on is added by a Renovate PR
  once CI has run against it.

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
