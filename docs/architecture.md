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
   aborts `run()` before step 2 starts. Swapping the two lines still passes every other assertion in
   `CompileRunnerTest`; `CompileRunnerTest::resolvesTheContextBeforeEmptyingTheCompileDir` pins the
   order directly for that reason.
2. **`Cleaner`** empties `compileDir` (or creates it) before every compile, so stale scripts from
   renamed or removed classes never survive a recompile. It asks a `CompileDirGuardInterface`
   first — this is what stops an `APP_COMPILE_DIR` typo from recursively deleting the app directory
   or the filesystem root. An app that knows more about its own layout can inject a stricter guard.
3. **`ScriptCompilerInterface`** (default `RayScriptCompiler`) compiles the context's module into
   `compileDir`. `RayScriptCompiler` is a one-line delegate to `ray/compiler`; the seam exists so
   the pipeline's *ordering* can be asserted directly
   (`tests/CompileRunnerTest.php::resolvesTheContextBeforeEmptyingTheCompileDir`) instead of
   inferred from a real compile. `CompileRunner::run()` wraps anything `compile()` throws
   in `Exception\CompileFailed` (original retrievable via `getPrevious()`); an app calling
   `compile()` directly through the interface still sees the implementation's own exception type,
   unwrapped, per its docblock.
4. **`BakedPathGuardInterface`** (default `BakedPathGuard`, using `BakedPathScanner`) scans every
   compiled `*.php` for a literal `$meta->appDir` or `$meta->tmpDir`. The scanner matches on
   path-segment boundaries (so `/app` does not false-positive inside `/appdata`) and exempts
   occurrences lying entirely inside a `compileDir` literal, which *is* meant to be baked in. Only
   those two paths are known here — an app passes `$extraNeedles` for anything else that must not
   ship, such as a secret. A rejection never echoes an extra needle's value, only the script.

   **The scanner unescapes `\\` and `\'` before it scans.** Ray.Compiler emits a `toInstance()`
   object or array as `unserialize(var_export(serialize($value), true))` and a plain value as
   `var_export($value)`, so every needle byte arrives escaped for a single-quoted PHP literal. A
   raw byte comparison therefore missed any path or secret containing `'` or `\` — the guard failed
   open on exactly the values most likely to hold them, and symmetrically failed closed when the
   `compileDir` held one, because its allowed range went unrecognised. Those two sequences are the
   only escapes a single-quoted literal has, so unescaping them is exact rather than a heuristic.
   It happens once in the constructor, which keeps every offset the class computes — needle hits,
   `compileDir` ranges, segment-boundary lookups — in one coordinate space; unescaping per lookup
   instead would need an index map to keep those three in agreement.

   **`\` counts as a segment byte, `'` cannot.** Once unescaped, both are ordinary path bytes, and
   `SEGMENT_CHAR` includes `\` for the same reason `CONTEXT_PATTERN` does: the OS does not resolve a
   backslash inside a segment, so `/app\cache` is a sibling of `/app`, not a child, and must not
   match — exactly like `/appdata`. `'` gets no such treatment, because unescaping erases the one
   thing that distinguished a `\'` inside a literal from the `'` that delimits it. Adding `'` to
   `SEGMENT_CHAR` therefore reads every closing delimiter as segment-continuation and stops the
   guard seeing any path baked as a literal at all — measured as 17 failing cases, `appDir` itself
   among them. So a needle abutting a quote is reported, and `/app` over-reports on a sibling named
   `/app'cache`. That is the safe direction for a guard whose failure mode is shipping a secret, and
   the bakedCases entry named for the delimiter pins it against a well-meant "fix".
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
through its own `ContextProviderInterface`, and hands both to `InjectorBuilder`. The builder reads
one thing — whether the context carries `CompiledContextInterface` — and returns a read-only
`Ray\Compiler\CompiledInjector($meta->compileDir)` for a marked context or a runtime
`Ray\Di\Injector($context(), $meta->tmpDir)` otherwise, rethrowing ray/compiler's own
directory-check failure as `Exception\CompileDirUnavailable` on the marked path. No `Ray\Compiler`
class name reaches application code. Getting the two directories out of sync between compile time and
runtime is the main way to misuse this package.

The compile side never wraps the module in `DiCompileModule`: measured against `ray/compiler`
1.15.0, the wrapped and unwrapped compiles of the same module produce byte-identical script trees
and the same `singletons.json` (only the `_bindings.log` debug text differs), because
`Compiler::compile()` installs its own `CompilerModule`, which binds the `Compile` flag itself. A
context therefore returns its bare application module, for every environment.

What the builder returns is a `WarmableInjectorInterface`, and the branch it took travels with the
result as its concrete class: `CompiledWarmableInjector` wraps the `CompiledInjector` and its
`warmup()` instantiates every dependency `ray/compiler` recorded in `singletons.json` at compile
time — every singleton it can build without a caller, which excludes an injection-point-dependent
provider (rejected at compile time) and the AOP `MethodInvocation` binding — rethrowing
`ray/compiler`'s missing-metadata failure as `Exception\WarmupNotCompiled`;
`RuntimeWarmableInjector` wraps the runtime injector, whose `warmup()` has nothing to do and
returns quietly. Warming at boot is what keeps two concurrent requests under a coroutine runtime
from each building the same singleton, and it stays a call the bootstrap makes by hand because
whether to warm belongs to the runtime model (worker vs per-request), not to the context.
Resolving `InjectorInterface` through the container returns the underlying injector, not the
wrapper — warm the instance the builder returned. Build once per process and reuse the result:
`CompiledInjector` caches singletons in an instance property, so warming one injector and serving
requests from another warms nothing.

## Extension points applications implement

- **`ContextInterface`** — one per environment, and one method: `__invoke(): AbstractModule`.
  Extend `AbstractContext`, whose constructor is `final` so `MapContextProvider`'s
  `new $class($meta)` stays valid for every subclass. For the ahead-of-time compiled production
  shape, additionally implement the `CompiledContextInterface` marker — that `implements` clause
  is the entire difference between a dev context and a prod context.
- **`ContextProviderInterface`** — maps an `AppMeta` to a `ContextInterface`. `MapContextProvider`
  is the bundled name→class-string implementation, and a bootstrap file returns one of these; it
  validates the whole map at construction (see its docblock). A context whose constructor takes
  more than `AppMeta` does not fit that map — the application implements this interface directly
  (a `match` over `$meta->context`), keeping every construction a statically checked call.
- **`CompileDirGuardInterface`** — only needed when the bundled guard's two checks (filesystem root,
  compile dir containing the app dir) are not strict enough for an app's layout.
- **`BakedPathGuardInterface`** — the same idea on the verification side. For the common case pass
  `new BakedPathGuard([...$needles])` rather than reimplementing; replace the whole thing only if
  the scanning strategy itself must change.
- **`ScriptCompilerInterface`** — rarely implemented by an app; see step 3 above for why the seam
  exists.
