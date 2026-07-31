# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`naoki-tsuchiya/ray-di-context` is a small PHP library for [Ray.Di](https://github.com/ray-di/Ray.Di)
applications that keeps ahead-of-time DI compilation cleanly separated from runtime:

- **`compileDir`** — pre-compiled DI scripts, baked into the container image, read-only at runtime.
- **`tmpDir`** — a runtime-writable scratch area, never baked into the image.

The core problem it solves: build the image as root, `COPY` the compiled scripts in, then run the
container as a non-root user with a read-only root filesystem. Everything in `src/` exists to make
that deployment safe (permissions, guarding against accidentally-baked absolute paths, refusing to
empty the wrong directory).

There is no framework, no HTTP layer, no database — this is a compile-time/bootstrap utility plus
one CLI binary (`bin/ray-di-compile`).

## Commands

```bash
composer test      # phpunit
composer lint       # mago lint --minimum-fail-level note
composer analyze    # mago analyze
composer guard      # mago guard (structural rules, see mago.toml)
composer fmt        # mago fmt --check
composer cs          # lint + fmt
composer sa          # analyze + guard
composer dist        # tests/dist-check.sh (installs the package into a real fixture consumer app)
composer tests       # cs + sa + test + dist — the full local gate, mirrors CI's `ci` job
```

Single test / single file:

```bash
vendor/bin/phpunit --filter runCleansAndCompiles
vendor/bin/phpunit tests/CompileRunnerTest.php
```

Auto-fix formatting (not run in CI, which only checks):

```bash
vendor/bin/mago fmt
```

`mago` is the single source of truth for style/static analysis — it's stricter than PHPStan/PHPCS
defaults (every strictness flag on, fails on `note`-level issues). Run `composer cs` and `composer sa`
before considering any change to `src/` done. `mago.toml` also declares `require-api-or-internal`,
so every new public class/method needs an `@api` or `@internal` doc tag or analysis fails.

## Architecture

### The compile-time flow (`bin/ray-di-compile` → `CompileRunner`)

```
bootstrap.php  →  ContextProviderInterface
                         │
AppMeta::fromAppDir(appDir, context, compileDir?, tmpDir?)
                         │
              Cli → CompileRunner::run($meta)
                         │
        ┌────────────────┼─────────────────────────────┐
        │                │                              │
  provider->get($meta)  Cleaner($meta)         ScriptCompilerInterface
  → ContextInterface    (guarded, empties/     (default RayScriptCompiler
                         creates compileDir)    → Ray\Compiler\Compiler)
                                                         │
                                                  BakedPathGuardInterface($meta)
                                                  (default BakedPathGuard: scans
                                                   every *.php via BakedPathScanner)
                                                         │
                                                  PermissionNormalizer($compileDir)
                                                  (0600 → 0644 files / 0755 dirs)
```

`CompileRunner::run()` (`src/CompileRunner.php`) is the whole pipeline — read it first when tracing
behavior. Each step's guard runs *before* its destructive/expensive work, in this order:

1. **`Cleaner`** (`src/Cleaner.php`) empties `compileDir` (or creates it) before every compile, so
   stale scripts from renamed/removed classes never survive a recompile. It asks a
   `CompileDirGuardInterface` (default `CompileDirGuard`) first — this is what stops an
   `APP_COMPILE_DIR` typo from recursively deleting the app directory or the filesystem root.
   An app that knows more about its own layout can inject a stricter guard.
2. **`ScriptCompilerInterface`** (`src/ScriptCompilerInterface.php`, default `RayScriptCompiler`)
   does the actual compilation of the context's module into `compileDir`. `RayScriptCompiler` is a
   one-line delegate to `ray/compiler`; the seam exists so the pipeline's *ordering* can be asserted
   directly (see `tests/CompileRunnerOrderingTest.php`) instead of inferred from a real compile.
3. **`BakedPathGuardInterface`** (`src/BakedPathGuardInterface.php`, default `BakedPathGuard`, using
   `src/BakedPathScanner.php`) scans every compiled `*.php` script for a literal occurrence of
   `$meta->appDir` or `$meta->tmpDir`. This is the guard against the one real footgun in Ray.Di:
   **binding `AppMeta` (or anything holding one of these paths) with `toInstance()` freezes that
   value into the compiled script.** A path baked in at build time silently diverges from the path
   that exists at runtime. The scanner matches on path-segment boundaries (so `/app` doesn't
   false-positive inside `/appdata`) and exempts occurrences that lie entirely inside a `compileDir`
   literal (which *is* meant to be baked in). Only those two paths are known here — an app passes
   `$extraNeedles` (or its own implementation) for anything else that must not ship, e.g. a secret.
   A rejection never echoes an extra needle's value, only the script it was found in.
4. **`PermissionNormalizer`** (`src/PermissionNormalizer.php`, `@internal`) fixes a `ray/compiler`
   quirk: it writes every script via `tempnam()`, which is always `0600` regardless of umask. That
   leaves the whole compile dir owner-only, which breaks the build-as-root/run-as-non-root pattern.
   This step normalizes files to `0644` and directories to `0755`, skipping symlinks and anything
   that already grants the needed world-bit (so it doesn't fight a pre-configured volume). Only runs
   after the guard passes.

Only `Cleaner`'s guard runs *before* compilation; the baked-path guard and `PermissionNormalizer`
necessarily run *after*, since they inspect what was just compiled.

**A failed compile leaves `compileDir` empty.** Steps 2–3 run inside a `try`/`finally` that re-runs
the `Cleaner` unless the guard passed, so scripts the guard refused never survive for the next
`COPY` to bake into an image. (Before this, the only thing making them unusable was that step 4
hadn't run, leaving them `0600` — a property of how `ray/compiler` happens to write, and the very
dependency `PermissionNormalizer` exists to shed.) The cleanup is done with a flag and `finally`
rather than `catch`/rethrow, because a rethrown `$e` types as `ExceptionInterface` and would widen
`run()`'s precise `@throws` list back to "anything this package throws".

### Runtime resolution (no compile step)

At runtime the application never touches `Cleaner`/`BakedPathGuard`/`Compiler` — it just builds an
`AppMeta` with the *same* `compileDir`/`tmpDir` values used at compile time, looks up the
`ContextInterface` via its own `ContextProviderInterface`, and calls `getInjectorInstance()`. A
production context returns `Ray\Compiler\CompiledInjector($meta->compileDir)` (reads only); a dev
context returns a plain `Ray\Di\Injector($module, $meta->tmpDir)`. Getting `compileDir`/`tmpDir`
out of sync between compile time and runtime is the main way to misuse this package — see the
README's exit-status/environment-variable discussion if you're touching anything path-related.

### Extension points applications implement

- **`ContextInterface`** (`src/ContextInterface.php`) — one per environment (prod/dev/...). Extend
  `AbstractContext` (`src/AbstractContext.php`), whose constructor is `final` (it takes only
  `AppMeta`) so `MapContextProvider`'s `new $class($meta)` stays valid for every subclass.
  `getSavedSingleton()` defaults to `[]` — override to name classes instantiated once at process
  start (these get freshly constructed, never unserialized, so they may hold live resources like DB
  connections; the singleton scope is per-injector-instance).
- **`ContextProviderInterface`** (`src/ContextProviderInterface.php`) — maps an `AppMeta` to a
  `ContextInterface`. `MapContextProvider` (`src/MapContextProvider.php`) is the bundled
  name→class-string implementation; a bootstrap file returns one of these.
- **`CompileDirGuardInterface`** (`src/CompileDirGuardInterface.php`) — only needed if the bundled
  `CompileDirGuard`'s two checks (filesystem root, compile dir containing the app dir) aren't
  strict enough for an app's layout.
- **`BakedPathGuardInterface`** (`src/BakedPathGuardInterface.php`) — the same idea on the
  verification side. The bundled guard only knows `appDir`/`tmpDir`; an app knows its own secrets
  and host names. For the common case pass `new BakedPathGuard([...$needles])` rather than
  reimplementing; replace the whole thing only if the scanning strategy itself needs to change.
- **`ScriptCompilerInterface`** (`src/ScriptCompilerInterface.php`) — rarely implemented by an app.
  It exists mainly so tests can substitute a fake and observe the pipeline mid-run
  (`tests/Fake/FakeRecordingCompiler.php`).

### `AppMeta` (`src/AppMeta.php`)

`final readonly class` — the value object threaded through everything above. Two constructors:

- The public constructor enforces every invariant that holds regardless of entry point: all four
  fields non-empty, `$appDir` absolute, trailing slashes trimmed, and **`compileDir` != `tmpDir`**.
  `$context` has no path-safety restriction here since it's just a lookup key.
- `AppMeta::fromAppDir()` adds only what it needs for the paths it builds: it rejects a `$context`
  that isn't a safe path segment (it *does* get interpolated into a path here) and defaults
  `compileDir`/`tmpDir` to `{appDir}/var/di/{context}` / `{appDir}/var/tmp/{context}`.

**`fromAppDir()` does not call `realpath()`** — it rejects a relative `$appDir` outright instead.
That's deliberate (#53): `BakedPathScanner` compares strings verbatim, so resolving symlinks here
would let the guard fail open when the resolved spelling differs from the one the running app binds.
Don't "fix" this by resolving the path.

`compileDir === tmpDir` is refused because it's the one shape `BakedPathGuard` cannot see: the guard
allows a needle occurrence inside a `compileDir` literal, so when the two strings are equal, every
`tmpDir` hit is also a `compileDir` hit and the check silently passes. A `tmpDir` merely *nested*
under `compileDir` extends past the allowed literal and is still caught, so that one is left to the
guard rather than refused here.

**Neither this package nor `bin/ray-di-compile` reads environment variables.** If a deployment sets
`APP_COMPILE_DIR`/`APP_TMP_DIR`, the caller (Dockerfile, bootstrap script) must read them and pass
them through explicitly as arguments — compile time and runtime must resolve to the *same* values.

### Exception hierarchy — enforced structurally, not just by convention

Every exception lives in `src/Exception/`, is one-per-file, `final`, and extends
`AbstractRuntimeException` (which extends SPL `RuntimeException` and implements the `ExceptionInterface`
marker — so applications can `catch (ExceptionInterface)` to catch anything this package throws).
This isn't just a style convention — `mago.toml`'s `[[guard.structural.rules]]` block fails the build
if a new exception class isn't `final` and doesn't extend `AbstractRuntimeException`, or if the
abstract base itself stops being abstract/implementing the marker. When adding a new exception, follow
the existing one-liner pattern (see e.g. `src/Exception/UnsafeCompileDir.php`) — no need to add a test
asserting the hierarchy, `mago guard` already does.

### `bin/ray-di-compile` exit-status contract

This is a public contract (README says "gate your CI on it") — preserve it if you touch the CLI:

| Code | Meaning |
|---|---|
| `0` | Compiled successfully |
| `1` | Compile failed — **anything** thrown while requiring the bootstrap or compiling, not just this package's exceptions. A missing binding surfaces as `Ray\Di\Exception\Unbound`; a foreign throwable is prefixed with its class name. One line to STDERR, never a stack trace. Also covers "autoloader not found" |
| `2` | Usage error — wrong arg count, missing bootstrap file, `appDir` doesn't exist, or a bootstrap that doesn't return a `ContextProviderInterface` |

The logic lives in **`src/Cli.php`** (`@internal`), not in the bin script. That's not cosmetic: a
bin script has no `.php` extension, so mago's source glob never discovers it — **adding `bin` to
`[source] paths` does nothing**. Pointed at the file explicitly, the old script reported 26 analyzer
and 6 lint issues. `bin/ray-di-compile` now holds only the autoloader lookup, which can't move
(it has to run before `Cli` is loadable). Two rules make that remainder permanently unfixable rather
than merely unfixed — `no-inline` fires on the shebang every executable PHP script needs, and
`no-global` on the `$GLOBALS['_composer_autoload_path']` lookup — so only `mago fmt`, which handles
both, is wired into `composer fmt` for it.

## Tests

- `tests/*.php` mirror `src/*.php` one-to-one, each tagged `#[CoversClass(...)]`, using PHPUnit 11's
  `#[Test]` attribute (not `test`-prefixed method names — `mago`'s `prefer-test-attribute` rule
  requires this).
- `tests/Fake/` holds shared test doubles (`FakeCar`/`FakeCarInterface`/`FakeModule` for a minimal
  bindable DI graph, `FakeProdContext`/`FakeDevContext`/`FakeBakedContext` for context scenarios,
  `Fs` for recursive directory helpers used in setUp/tearDown).
- `tests/Fixture/` holds standalone PHP fixtures (invalid/valid bootstraps) plus
  `tests/Fixture/consumer/` — a *complete separate Composer project* used only by
  `tests/dist-check.sh` (`composer dist`): it copies `src`/`bin`/`composer.json` into a scratch dir,
  `composer install`s the fixture consumer against it, and runs the real `vendor/bin/ray-di-compile`
  binary end-to-end. This is the only test that exercises the package the way a real consumer would
  (via Composer's autoloader/bin-linking), so a change to `composer.json`'s `bin`/`autoload` section
  should be checked against this script, not just the unit suite.
- `phpunit.xml.dist` forces `APP_COMPILE_DIR`/`APP_TMP_DIR` to empty strings and fails on risky/warning/
  deprecation/notice — tests must not rely on ambient env vars.
- Coverage is effectively required at 100%: uncoverable lines (unreachable except by a race, or by a
  filesystem permission state that can't be reproduced portably) are marked
  `// @codeCoverageIgnoreStart/End` with a comment explaining *why*, not just that it's excluded.
  **A new `src/` class needs its own test tagged `#[CoversClass(...)]` even if existing tests already
  execute every line of it** — PHPUnit attributes coverage only to the classes a test declares, so
  otherwise those lines count for nothing and the floor breaks.
- **Run the suite as a non-root user.** Nine tests set a directory unreadable and assert the package
  reports it; root ignores permission bits (`CAP_DAC_OVERRIDE`), so they can't hold. They now
  `markTestSkipped()` when a probe finds the bits aren't enforced — `tests/Fake/PermissionBits.php`,
  which measures the capability rather than reading the uid (that would need `ext-posix`, declined in
  #14, and would still miss a non-root process holding the capability). As root you get
  `143 tests, 9 skipped, OK`; as a non-root user, `143 tests, 0 skipped`. CI is non-root on purpose —
  don't move the `test` job into a `container:`.
- `CompileRunnerTest::resolvesFromReadOnlyCompileDir` is the *opposite* case and deliberately does
  **not** skip: its real assertion is a per-file `sha256` snapshot that never depended on the mode.
  The `chmod 0555` is belt over braces. (It used to snapshot size + mtime, which missed a same-length
  rewrite inside one second, since mtime is second-granular.)

## CI (`.github/workflows/ci.yml`)

Jobs: `lint` (mago, single PHP version — mago's target version is fixed in `mago.toml`, not the
matrix), `test` (PHP 8.2–8.5 matrix, **deliberately non-root** — `CompileRunnerTest` chmods the
compile dir to `0555` to prove nothing is written at runtime, which only means something if the
process isn't root), `lowest` (`--prefer-lowest` applied only to `ray/di`/`ray/compiler`, on the two
PHP-version corners), `dist` (runs `composer dist`). A final `ci` job fans in on all four and is the
single required status check — the matrix can grow/shrink without touching branch protection.

## Conventions worth preserving when editing `src/`

- `final readonly class` for value objects (`AppMeta`); plain `final class` for services; exceptions
  are `final` and one-per-file as described above.
- Every builtin function used is explicitly `use function`-imported (no fully-qualified `\strlen(...)`
  calls) — `mago`'s `no-fully-qualified-global-function` rule enforces this.
- Doc comments explain the *why* (a rejected shape, a workaround for an upstream quirk, a security
  boundary) rather than restating the method name — follow that tone rather than writing
  what-it-does comments.
- `@api` marks the public surface — everything under `src/` except two. `PermissionNormalizer` is
  `@internal` (a workaround for a `ray/compiler` quirk, not something to build on) and so is `Cli`
  (the *exit-status contract* is public; the class carrying it is not, so it stays free to change).
