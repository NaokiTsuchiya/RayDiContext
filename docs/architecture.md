# Architecture

How a compile flows through the package, what happens at runtime, and which interfaces an
application implements. `README.md` covers using the package; this covers its insides.

For approaches that were tried and rejected, see [decisions.md](decisions.md).

## The compile-time flow (`bin/ray-di-compile` → `CompileRunner`)

```
bootstrap.php  →  ContextProviderInterface
                         │
AppMeta::fromAppDir(appDir, context, compileDir?, tmpDir?)
                         │
              Cli → CompileRunner::run($meta)
                         │
                provider->get($meta)
                → ContextInterface
                         │
                    Cleaner($meta)
          (guarded, empties/creates compileDir)
                         │
                ScriptCompilerInterface
             (default RayScriptCompiler
              → Ray\Compiler\Compiler)
                         │
               BakedPathGuardInterface($meta)
               (default BakedPathGuard: scans
                every *.php via BakedPathScanner)
                         │
               PermissionNormalizer($compileDir)
               (0600 → 0644 files / 0755 dirs)
```

`CompileRunner::run()` is the whole pipeline — read it first when tracing behavior.

1. **`provider->get($meta)`** resolves the context strictly before `Cleaner` runs, not alongside it.
   `Cleaner` deletes `compileDir`'s contents without inspecting them, so this order is the only
   thing standing between an unknown context and an emptied `compileDir` — an `UnknownContext`
   aborts `run()` before step 2 starts. Swapping the two lines still passes every assertion in
   `CompileRunnerTest`; `CompileRunnerOrderingTest` pins the order directly for that reason.
2. **`Cleaner`** empties `compileDir` (or creates it) before every compile, so stale scripts from
   renamed or removed classes never survive a recompile. It asks a `CompileDirGuardInterface`
   first — this is what stops an `APP_COMPILE_DIR` typo from recursively deleting the app directory
   or the filesystem root. An app that knows more about its own layout can inject a stricter guard.
3. **`ScriptCompilerInterface`** (default `RayScriptCompiler`) compiles the context's module into
   `compileDir`. `RayScriptCompiler` is a one-line delegate to `ray/compiler`; the seam exists so
   the pipeline's *ordering* can be asserted directly (`tests/CompileRunnerOrderingTest.php`)
   instead of inferred from a real compile. `CompileRunner::run()` wraps anything `compile()` throws
   in `Exception\CompileFailed` (original retrievable via `getPrevious()`); an app calling
   `compile()` directly through the interface still sees the implementation's own exception type,
   unwrapped, per its docblock.
4. **`BakedPathGuardInterface`** (default `BakedPathGuard`, using `BakedPathScanner`) scans every
   compiled `*.php` for a literal `$meta->appDir` or `$meta->tmpDir`. The scanner matches on
   path-segment boundaries (so `/app` does not false-positive inside `/appdata`) and exempts
   occurrences lying entirely inside a `compileDir` literal, which *is* meant to be baked in. Only
   those two paths are known here — an app passes `$extraNeedles` for anything else that must not
   ship, such as a secret. A rejection never echoes an extra needle's value, only the script.
5. **`PermissionNormalizer`** (`@internal`) normalizes files to `0644` and directories to `0755`,
   skipping symlinks and anything that already grants the needed world-bit, so it does not fight a
   pre-configured volume. Runs only after the guard passes.

Only `Cleaner`'s guard runs before compilation; the baked-path guard and `PermissionNormalizer`
necessarily run after, since they inspect what was just compiled.

**A failed compile leaves `compileDir` empty.** Steps 3–4 run inside a `try`/`finally` that re-runs
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
extending `AbstractCompiledContext` returns `Ray\Compiler\CompiledInjector($meta->compileDir)`,
which only reads; that call lives in the base class, not application code. Getting the two
directories out of sync between compile time and runtime is the main way to misuse this package.

## Extension points applications implement

- **`ContextInterface`** — one per environment. Extend `AbstractContext`, whose constructor is
  `final` so `MapContextProvider`'s `new $class($meta)` stays valid for every subclass. See the
  interface's docblocks for the injector and `getSavedSingleton()` contracts.
  For the ahead-of-time compiled production shape, extend `AbstractCompiledContext` instead and
  implement only `appModule()` — it composes `DiCompileModule`/`CompiledInjector` so the consumer
  never imports those class names.
- **`ContextProviderInterface`** — maps an `AppMeta` to a `ContextInterface`. `MapContextProvider`
  is the bundled name→class-string implementation, and a bootstrap file returns one of these; it
  validates the whole map at construction (see its docblock).
- **`CompileDirGuardInterface`** — only needed when the bundled guard's two checks (filesystem root,
  compile dir containing the app dir) are not strict enough for an app's layout.
- **`BakedPathGuardInterface`** — the same idea on the verification side. For the common case pass
  `new BakedPathGuard([...$needles])` rather than reimplementing; replace the whole thing only if
  the scanning strategy itself must change.
- **`ScriptCompilerInterface`** — rarely implemented by an app; see step 3 above for why the seam
  exists.
