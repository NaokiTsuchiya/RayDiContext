---
name: architecture
description: The compile pipeline, runtime resolution, and extension points of ray-di-context. Use when tracing how a compile flows through CompileRunner, when changing the order or contents of a pipeline step, when implementing or replacing one of the package's interfaces, or when a change to src/ needs to know why a step sits where it does.
---

# ray-di-context architecture

## The compile-time flow (`bin/ray-di-compile` → `CompileRunner`)

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

`CompileRunner::run()` is the whole pipeline — read it first when tracing behavior. Each step's
guard runs *before* its destructive or expensive work:

1. **`Cleaner`** empties `compileDir` (or creates it) before every compile, so stale scripts from
   renamed or removed classes never survive a recompile. It asks a `CompileDirGuardInterface`
   first — this is what stops an `APP_COMPILE_DIR` typo from recursively deleting the app directory
   or the filesystem root. An app that knows more about its own layout can inject a stricter guard.
2. **`ScriptCompilerInterface`** (default `RayScriptCompiler`) compiles the context's module into
   `compileDir`. `RayScriptCompiler` is a one-line delegate to `ray/compiler`; the seam exists so
   the pipeline's *ordering* can be asserted directly (`tests/CompileRunnerOrderingTest.php`)
   instead of inferred from a real compile.
3. **`BakedPathGuardInterface`** (default `BakedPathGuard`, using `BakedPathScanner`) scans every
   compiled `*.php` for a literal `$meta->appDir` or `$meta->tmpDir`. The scanner matches on
   path-segment boundaries (so `/app` does not false-positive inside `/appdata`) and exempts
   occurrences lying entirely inside a `compileDir` literal, which *is* meant to be baked in. Only
   those two paths are known here — an app passes `$extraNeedles` for anything else that must not
   ship, such as a secret. A rejection never echoes an extra needle's value, only the script.
4. **`PermissionNormalizer`** (`@internal`) normalizes files to `0644` and directories to `0755`,
   skipping symlinks and anything that already grants the needed world-bit, so it does not fight a
   pre-configured volume. Runs only after the guard passes.

Only `Cleaner`'s guard runs before compilation; the baked-path guard and `PermissionNormalizer`
necessarily run after, since they inspect what was just compiled.

**A failed compile leaves `compileDir` empty.** Steps 2–3 run inside a `try`/`finally` that re-runs
the `Cleaner` unless the guard passed, so scripts the guard refused never survive for the next
`COPY` to bake into an image. The cleanup uses a flag and `finally` rather than `catch`/rethrow: a
rethrown `$e` types as `ExceptionInterface` and would widen `run()`'s precise `@throws` list back to
"anything this package throws".

Two private `openDir()` methods (`Cleaner`, `PermissionNormalizer`) refuse a directory this process
cannot reach. A mode whose owner class is narrower than its other class — `0005`, `0405`, `0605` —
passes a world-bit check and still denies the owner, because POSIX resolves the owner class first.
Without read, `FilesystemIterator`'s constructor throws a bare `UnexpectedValueException`; without
execute it opens fine and every per-entry `stat()` fails instead, leaking one warning per entry.

## Runtime resolution (no compile step)

At runtime the application never touches `Cleaner`, `BakedPathGuard` or the compiler. It builds an
`AppMeta` with the *same* `compileDir`/`tmpDir` used at compile time, looks up the `ContextInterface`
through its own `ContextProviderInterface`, and calls `getInjectorInstance()`. A production context
returns `Ray\Compiler\CompiledInjector($meta->compileDir)` (reads only); a dev context returns a
plain `Ray\Di\Injector($module, $meta->tmpDir)`. Getting the two directories out of sync between
compile time and runtime is the main way to misuse this package.

## Extension points applications implement

- **`ContextInterface`** — one per environment. Extend `AbstractContext`, whose constructor is
  `final` (it takes only `AppMeta`) so `MapContextProvider`'s `new $class($meta)` stays valid for
  every subclass; nothing in `mago guard` can pin that, since `target` takes only class-like kinds.
  `getSavedSingleton()` defaults to `[]` — override it to name classes instantiated once at process
  start. Those are freshly constructed, never unserialized, so they may hold live resources such as
  DB connections; the singleton scope is per injector instance.
- **`ContextProviderInterface`** — maps an `AppMeta` to a `ContextInterface`. `MapContextProvider`
  is the bundled name→class-string implementation, and a bootstrap file returns one of these. It
  validates the whole map in its constructor rather than lazily in `get()`, so a typo for a context
  nobody has requested yet fails when the map is wired up.
- **`CompileDirGuardInterface`** — only needed when the bundled guard's two checks (filesystem root,
  compile dir containing the app dir) are not strict enough for an app's layout.
- **`BakedPathGuardInterface`** — the same idea on the verification side. For the common case pass
  `new BakedPathGuard([...$needles])` rather than reimplementing; replace the whole thing only if
  the scanning strategy itself must change.
- **`ScriptCompilerInterface`** — rarely implemented by an app. It exists mainly so tests can
  substitute a fake and observe the pipeline mid-run (`tests/Fake/FakeRecordingCompiler.php`).
