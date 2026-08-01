# CLAUDE.md

`naoki-tsuchiya/ray-di-context` compiles Ray.Di ahead of time into a read-only `compileDir` baked
into a container image, keeping it separate from a runtime-writable `tmpDir`. Build as root, `COPY`
the scripts in, run as non-root on a read-only root filesystem. No framework, no HTTP, no database.

What does not belong here lives in two places. The `architecture` skill has the compile pipeline,
the extension points, and why a step sits where it does. `docs/decisions.md` has the approaches
already tried and rejected, with the evidence — read it before proposing a mago or CI rule, adding a
dependency, or asking "why doesn't this just do X", and add to it whenever a change is abandoned for
a reason worth not rediscovering. This file is only what you would get wrong without them.

## Commands

```bash
composer tests   # cs + sa + test + dist — the full gate, mirrors CI's `ci` job
composer cs      # mago lint + fmt --check
composer sa      # mago analyze + guard
composer test    # phpunit
composer dist    # installs the package into a real fixture consumer and runs the real binary
vendor/bin/mago fmt   # auto-fix; CI only checks
```

`mago` is the only style/analysis tool, with every strictness flag on and `note` failing the build.
Run `composer cs && composer sa` before calling a change to `src/` done.

**Run the suite as a non-root user.** Nine tests set a directory unreadable and assert the package
reports it; root ignores permission bits, so they `markTestSkipped()` via `Fake\PermissionBits`,
which measures the capability rather than reading the uid. Root gives
`143 tests, 9 skipped`; non-root gives `143 tests, 0 skipped`. CI is non-root on purpose — don't
move the `test` job into a `container:`.

`CompileRunnerTest::resolvesFromReadOnlyCompileDir` is the opposite case and must **not** skip: its
assertion is a per-file `sha256` snapshot that never depended on the mode.

`composer dist` is the only test that exercises the package as a consumer does, through Composer's
autoloader and bin-linking. A change to `composer.json`'s `bin`/`autoload` has to be checked there,
not just against the unit suite.

## Decisions that look like bugs

- **`fromAppDir()` does not call `realpath()`** — it rejects a relative `$appDir` instead (#53).
  `BakedPathScanner` compares strings verbatim, so resolving symlinks here would let the guard fail
  open when the resolved spelling differs from the one the running app binds. Don't "fix" this.
- **`compileDir === tmpDir` is refused** because it is the one shape `BakedPathGuard` cannot see: the
  guard allows a needle inside a `compileDir` literal, so when the strings are equal every `tmpDir`
  hit is also a `compileDir` hit. A `tmpDir` merely *nested* under `compileDir` extends past the
  literal and is still caught, so that one is left to the guard.
- **`CONTEXT_PATTERN` includes `\`** so a `::class`-shaped context (`App\ProdContext`) passes
  through. Unlike `/` and `.`, the OS does not resolve a backslash inside a path segment.
- **`AbstractContext::__construct()` is `final`** so `MapContextProvider`'s `new $class($meta)` stays
  valid for every subclass. Nothing catches a subclass widening it: `mago guard`'s `target` takes
  only class-like kinds, so `must-be-final` cannot reach a constructor.
- **Neither this package nor `bin/ray-di-compile` reads environment variables.** A deployment that
  sets `APP_COMPILE_DIR`/`APP_TMP_DIR` must pass them through as arguments; compile time and runtime
  have to resolve to the same values.
- **The CLI logic lives in `src/Cli.php`, not the bin script.** A bin script has no `.php`
  extension, so mago's source glob never finds it — adding `bin` to `[source] paths` does nothing.
- **`PermissionNormalizer` exists for one upstream quirk**: `ray/compiler` writes every script via
  `tempnam()`, always `0600` regardless of umask, which breaks build-as-root/run-as-non-root.

The footgun this package exists to catch: **binding `AppMeta`, or anything holding `appDir`/`tmpDir`,
with `toInstance()` freezes that path into the compiled script**, where it silently diverges from the
path that exists at runtime.

## `bin/ray-di-compile` exit-status contract

Public — the README says to gate CI on it. Preserve it if you touch the CLI.

| Code | Meaning |
|---|---|
| `0` | Compiled successfully |
| `1` | Compile failed — **anything** thrown while requiring the bootstrap or compiling, not just this package's exceptions. One line to STDERR, never a stack trace. Also covers "autoloader not found" |
| `2` | Usage error — wrong arg count, missing bootstrap, `appDir` doesn't exist, or a bootstrap not returning a `ContextProviderInterface` |

## Comments

**A docblock documents an interface. Nothing else gets a comment.**

Prose belongs in a docblock when a caller needs it to use the thing: the contract on an `@api`
interface, the exit statuses above, a `@param` constraint the type cannot carry. A private method, a
concrete implementation and a test have no such caller — `missing-docs` still demands a docblock, so
they get one line or only their tags (`/** {@inheritDoc} */`, `/** @throws ExceptionInterface */`).
Inline `//`, rationale paragraphs and summaries that are the method name in English are deleted; the
one exception is the justification `@codeCoverageIgnore` requires.

*Why* the code is the way it is goes in this file or the `architecture` skill, never beside the code
— a second copy is a copy that drifts. Better still, express it as a `mago.toml` rule.

Be suspicious of your own output here: the failure mode is writing for whoever reads the diff right
now, and it has been caught twice in review (PR #69, seventeen times; PR #71, six more after a pass
that had already halved the volume). Nothing enforces it — see `decisions` for why not.

## Other conventions

- Tests mirror `src/` one-to-one, tagged `#[CoversClass(...)]`, using `#[Test]` attributes. A test's
  docblock is `@throws ExceptionInterface` alone unless it has something to say. `src/` keeps its
  precise `@throws` enumeration (34f6a95); `BinCompileTest` keeps `RuntimeException` because
  `Fake\Cli` really throws one.
- Coverage is effectively 100%. A new `src/` class needs its own `#[CoversClass(...)]` test even if
  existing tests already execute every line — PHPUnit only credits declared classes.
- Exceptions are `final`, one per file, extending `AbstractRuntimeException`. `mago.toml`'s
  `[[guard.structural.rules]]` enforces this; no test needed.
- `@api` marks the public surface — everything in `src/` except `PermissionNormalizer` and `Cli`,
  which are `@internal`.
