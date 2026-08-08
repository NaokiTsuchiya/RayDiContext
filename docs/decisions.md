# Decisions already made

Approaches this repository tried and rejected. Each entry records what was attempted, what actually
happened, and what would have to change for the answer to be different — so that a proposal to do it
again starts from the evidence rather than from scratch.

An entry with a live premise is worth re-testing; one without is settled.

Add to this file whenever a change is abandoned for a reason a future reader would not rediscover on
their own. A verdict without its evidence gets re-litigated.

## Cannot be done with the tooling

### Enforce the comment rule with a `mago` lint rule — [#70][70]

`mago`'s only comment rules are `no-empty-comment`, `no-hash-comment`, `valid-docblock` and
`missing-docs`. None looks at length, audience or redundancy, which is the whole of the rule. There
is nothing to configure.

**Would change it:** a `mago` release adding a comment-length or duplicate-prose rule.

### Pin a `final` constructor with `mago guard` — [#71][71]

`[[guard.structural.rules]]` accepts `target` values `class-like`, `class`, `interface`, `trait`,
`enum`, `constant`, `function`. Passing `method` fails at config-parse time:

```
ERROR unknown variant `method`, expected one of `class-like`, `class`, `interface`, `trait`, `enum`, `constant`, `function`
```

`must-be-final` on the class does not reach `AbstractContext::__construct()`, so a subclass widening
it is caught by nothing. That is why the constraint is one of the few lines left in CLAUDE.md.

**Would change it:** `mago guard` gaining a method target.

### Enforce test method naming with `mago guard` — [#83][83]

Same limitation as above: `[[guard.structural.rules]]`'s `target` has no `method` variant, so a rule
requiring `#[Test]` method names to be predicate sentences cannot be expressed. `mago`'s
`method-name` lint rule only checks PSR casing, not sentence shape. The convention (see CLAUDE.md's
"Other conventions") is enforced by review only.

**Would change it:** `mago guard` gaining a method target, same as [#71][71].

### Add `bin` to mago's `[source] paths`

Does nothing. A bin script has no `.php` extension, so the source glob never discovers it. Pointed
at the file explicitly, the old `bin/ray-di-compile` reported 26 analyzer and 6 lint issues. The fix
was to move the logic into `src/Cli.php` and leave only the autoloader lookup in the script, which
cannot move — it has to run before `Cli` is loadable. Two rules make that remainder permanently
unfixable rather than merely unfixed: `no-inline` fires on the shebang every executable PHP script
needs, and `no-global` on the `$GLOBALS['_composer_autoload_path']` lookup. Only `mago fmt`, which
handles both, is wired into `composer fmt` for it.

**Would change it:** mago's source discovery growing a way to name a file without a `.php`
extension, which would let the script be linted where it sits.

### Collapse `BinCompileTest`'s `@throws RuntimeException` to `ExceptionInterface` — [#71][71]

`ExceptionInterface` does not cover a plain `RuntimeException`, and `BinCompileTest` never calls
package code in-process: it goes through `Support\PhpProcess::run()`, which throws a bare
`RuntimeException` when `proc_open()` fails. Swapping the tag produces 8 `unhandled-thrown-type`
errors from `mago analyze`. The eight tags stay, for the same reason the one in
`Support\PhpProcess` does.

**Would change it:** `Support\PhpProcess` throwing a package exception instead, which would mean
giving a test helper a dependency on the hierarchy it exists to stay outside of.

### An abstract base `TestCase` to share test setup — [#79][79]

Test classes live in the root namespace, which is exactly what `mago.toml`'s `must-be-final` rule
covers, and its `not-on` names `AbstractContext` and `AbstractCompiledContext` — no other abstract
class. An abstract class placed there anyway fails:

```text
tests/ProbeRoot.php:8:16: error[must-be-final]: Structural flaw in `NaokiTsuchiya\RayDiContext\AbstractProbeRoot`
 = Every concrete class in the root namespace is final except AbstractContext and AbstractCompiledContext, which exist to be extended
```

The same probe under `NaokiTsuchiya\RayDiContext\Support` is not reported — `on =
'NaokiTsuchiya\RayDiContext\*'` matches one namespace segment, not a subtree. That is a way past the
rule, not a licence to use it. Shared setup goes through the final helpers in `tests/Support/`:
static utilities (`Fs`, `PermissionBits`, `PhpProcess`) and per-test objects (`CliFixture`,
`AppDirFixture`, `CompileDirFixture`, `SeparatedDirFixture`).

A second `not-on` entry landed in [#119][119]: `not-on = 'NaokiTsuchiya\RayDiContext\Abstract{Context,CompiledContext}'`
(the brace form, since `mago.toml` rejects a TOML array here). The exemption still names exactly two
classes — `Abstract*` was rejected because it would also exempt any concrete class spelled `Abstract…`
and the abstract `TestCase` this entry exists to keep out. An abstract `TestCase` placed in the root
namespace still fails the same way; only `AbstractContext` and `AbstractCompiledContext` were exempt.
[#148][148] deleted `AbstractCompiledContext`, so the `not-on` names `AbstractContext` alone now —
the reasoning against `Abstract*` is unchanged.

## Deliberate direction — do not reverse

### `fromAppDir()` calling `realpath()` — [#53][53]

Rejected. `BakedPathScanner` compares strings verbatim, so resolving symlinks at construction would
let the guard fail open whenever the resolved spelling differs from the one the running app binds —
a Capistrano-style `current -> release` layout is the ordinary case. A relative `$appDir` is
rejected outright instead, and the factory never touches the filesystem.

### Collapsing `src/`'s `@throws` to `ExceptionInterface` — [34f6a95][34f6a95], reaffirmed in [#70][70]

[34f6a95][34f6a95] moved deliberately in the other direction: "Declare precise exception types
instead of the generic `RuntimeException`". Tests may collapse to the marker interface, because a
test that calls one method and lets everything escape gains nothing from ten tags. `src/` keeps the
enumeration.

### Running the `test` CI job in a `container:`

Rejected without trying it, on an outcome already observed locally: running the suite as root skips
tests that an ordinary user runs, with the same total either way. Containers run as root,
`Support\PermissionBits` finds the bits unenforced, and the tests that exist to assert the package
reports a directory it cannot read skip silently. A green matrix would mean less than it appears.

### Dropping the `chmod()` that follows `mkdir()` in the test fixtures — [#84][84]

`mkdir()` masks its mode argument with the process umask; `chmod()` does not. Probed on PHP 8.5 with
`umask(0o277)`, first on a leaf inside a directory that already exists, then on a path `mkdir()` has
to create recursively:

```
leaf mkdir ok=1 mode=500
after chmod mode=700
recursive ok=0 base=500 di_exists=0
```

The permission tests compare against exact modes — `assertSame(0o700, ...)` on the compile dir they
start from — so `Support\CompileDirFixture` calls `chmod()` after creating a directory. The third
line is why it creates each level itself instead of passing `recursive: true` and fixing only the
leaf: an intermediate directory narrowed to `0o500` is not writable, so the child below it is never
created at all.

Under the usual `umask 022` the `chmod()` changes nothing and reads as a redundant line. It is not:
without it the suite depends on the umask of whoever runs it.

### Folding `BakedPathGuardRejectionTest` into `Support\SeparatedDirFixture` — [#86][86]

Its `setUp()` looks close to the other `BakedPathGuard` test classes' at a glance, which is why the
fold keeps being proposed — measured, it isn't. Of the other five, three (`BakedPathGuardTest`,
`BakedPathGuardBoundaryTest`, `BakedPathGuardDirectoryEntryTest`) share the same three lines
(`SeparatedDirFixture` + `$this->meta` + `$this->guard`) up to the `uniqid()` prefix;
`BakedPathGuardExtraNeedleTest`'s is two lines (no `$this->guard` assignment); and
`BakedPathGuardInvalidNeedleTest` has no `setUp()` at all. It does not fit: the fixture's
defining act is creating the compile dir, and that class exists to assert what the guard does when
the compile dir is *missing*, is a *file*, or carries a mode it cannot list — so it puts the compile
dir at `{baseDir}/di` and creates nothing. Sharing the fixture would mean two more constructor
arguments (layout, and create-or-not) whose only non-default caller is that one file.

**Would change it:** a second class needing the same non-default shape.

### Staging removals through a `src-deprecated/` directory — built in [#148][148], then dropped for a one-shot break

`ray/compiler`'s convention: a second PSR-4 path under the same namespace, so what is on its way
out is visible from the file tree and removal is deleting a directory. It was fully built for
`getSavedSingleton()` — extracted as a `SavedSingletonInterface` that `ContextInterface` extended,
with `AbstractContext`'s `[]` default following as a trait — and then dropped in the same PR: on
0.x, where the README's versioning policy lets a minor break compatibility, staging bought a second
migration for consumers (adopt the deprecation, then survive the removal) and bought this package
nothing. `getSavedSingleton()` and `getInjectorInstance()` were removed outright instead.

The mechanics are recorded because they worked and are non-obvious, should a post-1.0 removal ever
need them:

- A method on a living interface cannot move as a file; it moves by extracting a parent interface
  the living one extends. Invisible to implementers. A concrete default on a living class moves
  only as a trait.
- The `@deprecated` tag's placement took three attempts, each reported by `mago analyze`: on the
  extracted interface it flags the living interface's `extends` clause; on the trait's method too
  it flags every existing caller, including tests that must keep exercising the method; mentioned
  in prose inside a docblock it is parsed as the tag anyway. It works only on the extracted
  interface's *method*.
- Four places enumerate shipped directories, and the PR missed the last: `composer.json`,
  `mago.toml`'s `[source] paths`, `tests/gitattributes-check.sh`'s `top_level_dirs`, and
  `tests/docker-check.sh`'s `cp -R`, which assembles the package the docker example installs. A
  PSR-4 path with nothing behind it fails only where that assembly happens — `docker build`, not
  the unit suite.

**Would change it:** a deprecation on a >=1.0 release line, where a consumer contract makes the
two-step migration the point rather than overhead.

### Reaching `warmup()` through a standalone `SingletonWarmer`, not the type system — [#148][148]

`ray/compiler` 1.15.0 added `CompiledInjector::warmup()`, which instantiates every singleton the
compile recorded in `singletons.json`. That supersedes `ContextInterface::getSavedSingleton()`
outright: the hand-written list can omit a singleton, the compiler's cannot. Four ways to reach it
were built or weighed, in order of how much of this package's surface they commit.

Narrowing `ContextInterface::getInjectorInstance()` to a `WarmInjectorInterface` reads best at the
call site — `$injector->warmup()`, no `instanceof` — and breaks every implementer at once. Changing
`AbstractCompiledContext::getInjectorInstance()` to return a warmable decorator keeps the declared
return type but silently changes the concrete class an existing context hands back. A third,
`AbstractWarmCompiledContext` extending `AbstractCompiledContext` and overriding only
`getInjectorInstance()`, was written and then removed: it breaks nobody, but it answers "a new kind
of injector" with "a new base class to extend", so the next one costs another, and consumers migrate
by editing a class declaration.

The fourth commits nothing. `SingletonWarmer` is a `final` class on no interface and in no
inheritance chain, shaped like `Cleaner` and `PermissionNormalizer` — `__invoke()` taking the
injector. It cannot break an implementer because it has none, and an existing context warms up by
adding a line to its bootstrap rather than changing what it extends. The three interface-and-
inheritance options above all stay open on top of it, which is the point.

Two consequences are deliberate. `instanceof CompiledInjector` moves inside `src/`, which already
names `ray/compiler` classes; what [#119][119] was protecting is *consumer* code, and this removes an
`instanceof` from there rather than adding one. And a runtime `Ray\Di\Injector` is a no-op, not a
throw: it compiles as it resolves, so there is genuinely nothing to warm and no race to lose —
unlike compiled scripts with no metadata, where `ray/compiler` throws for the same reason this
package rethrows as `Exception\WarmupNotCompiled` (a silent success would claim protection that is
not there).

The warmer also survived the [#148][148] break that removed `getInjectorInstance()` unchanged, for
a reason found while designing that break: whether to warm is a property of the runtime model, not
of the context. `CompiledInjector` caches singletons per *instance*, so a worker runtime (Swoole)
warms once per process and every request benefits, while PHP-FPM rebuilds the injector per request
and warming everything up front costs more than resolving lazily. Any design that welds warmup to
the context or to injector construction — the decorator above, or `InjectorBuilder` warming
automatically — gets prod-under-FPM wrong. Only a call the bootstrap makes by hand can sit on the
correct axis.

**Would change it:** a second collaborator wanting the same "can this injector be warmed" question
answered — two `instanceof CompiledInjector` sites is when the type belongs in the type system.

### The context stops carrying the injector — a marker interface and `InjectorBuilder`, not an enum or a factory method — [#148][148]

`ContextInterface` fused two lifecycles: `__invoke()` feeds the compile and never runs at runtime;
`getInjectorInstance()` runs at runtime and never at compile. The package separates compile time
from runtime everywhere else, and the fusion had a documented failure mode — `AbstractCompiledContext`
warned that a subclass overriding one method but not the other loses the guarantee the two agree.
The break removes `getInjectorInstance()`; a context supplies its module and declares one bit,
"resolved from the compiled scripts", by implementing the `CompiledContextInterface` marker.
`InjectorBuilder` — a standalone collaborator like `SingletonWarmer` — turns `(context, meta)` into
the injector and owns the `CompileDirUnavailable` bracketing, which previously protected only
contexts extending `AbstractCompiledContext`, not hand-written ones.

Two shapes for declaring the bit were rejected first. An `injectorKind(): InjectorKind` enum method
adds a method every implementer must answer and closes the set of strategies in a package that
deliberately keeps `ScriptCompilerInterface` open — and once the enum grows an escape hatch it
degenerates into factories. A `(context class, factory class)` pair map keeps strategies open but
mints an `@api` factory interface whose implementers then constrain every future change. The marker
adds no method, no hierarchy, and costs one `instanceof` inside `src/`; an application needing an
injector the builder cannot make simply does not call the builder.

The measurement that let `AbstractCompiledContext` be deleted rather than slimmed: compiling the
same module bare and wrapped in `DiCompileModule(true, ...)` against `ray/compiler` 1.15.0 produces
byte-identical script trees and identical `singletons.json` — only the `_bindings.log` debug text
differs — because `Compiler::compile()` installs `CompilerModule`, which binds the `Compile` flag
itself, and `DiCompileModule::configure()` binds nothing else. With the wrap inert and the injector
gone, nothing remained for a compiled base class to do: a dev context and a prod context now differ
by one `implements` clause. This also retired the `TrueValue` equivalent-mutant ignore that the
wrap's unread flag used to need.

`CallableContextProvider` rides the same release for the gap `MapContextProvider` cannot close: a
context whose constructor takes more than `AppMeta` cannot extend `AbstractContext` (whose
constructor is `final` for `new $class($meta)`), so it implements `ContextInterface` directly and
is mapped as a factory. The two providers stay separate because their guarantees conflict —
class-strings can be proven instantiable at map construction, while invoking a factory for an
environment nobody requested would run constructors whose dependencies may have side effects, so a
factory is only provable callable. Merging them into one map would quietly demote every entry to
the weaker guarantee.

**Would change it:** nothing identified; the open questions are additive (a second bundled injector
strategy would extend `InjectorBuilder`, not reshape the context).

### Producing a non-flat compile output with a fake compiler instead of a qualifier — [#148][148]

`PermissionNormalizer` and `Support\CompiledTree` both recurse, and one test covered that recursion:
`FakeQualifiedModule` bound `annotatedWith('a.php/b')`, so `ray/compiler` wrote a script into a
directory named `a.php` under the compile dir — a real compile whose output was not flat.

`ray/compiler` 1.15.0 removed that shape. Its `ScriptName` rejects a dependency index carrying a
byte outside `[A-Za-z0-9_.-]` with `InvalidQualifier`, because a qualifier is arbitrary
`->annotatedWith()` input that otherwise reaches the filesystem and the generated code raw — the
same class of concern `BakedPathGuard` exists for, fixed one layer down. `composer.lock` is
gitignored and the constraint is a caret, so this arrived on `main` the day 1.15.0 was released.

Nesting is now reachable only through a `ScriptCompilerInterface` an application supplies, so
`FakeNestingCompiler` writes `nested/compiled.php` at `0o700`/`0o600` and the test moved to
`CompileRunnerTest` — it no longer needs a real compile, which is the whole of what puts a test in
the integration tier ([#140][140]). It keeps its teeth: dropping `normalizeContents()`'s recursive
call fails it on the nested script's mode.

**Would change it:** `ray/compiler` emitting nested output on its own again, which would put a real
compile back within reach of the integration tier.

### Pinning a runtime dependency to a minor so Renovate reports it — [#116][116]

Rejected for both, after being tried on each. `rangeStrategy: "widen"` does not look at the semver
level; it asks only whether the new version falls outside the declared range, and returns the range
untouched when it does not:

```ts
if (rangeStrategy === 'widen' && matches(newVersion, currentValue)) {
    newValue = currentValue;
```

An update whose `newValue` equals its `currentValue` is then dropped entirely, so under `^2.19` a
release like `ray/di 2.22.2` produces no PR, no dashboard entry and no CI run. Replacing the caret
with a chain of pinned minors (`~2.19.0 || ~2.20.0 || …`) puts each new one outside the range, which
turns it into a PR whose matrix runs against it — detection and verification in one, and a range
containing only combinations CI has actually run, so a broken pairing is never installable.

The price is that the range is also a gate. A consumer cannot install the new minor until this
package merges the PR *and* tags a release, and releases here are a few a year. That price is only
worth paying for a dependency the consumer neither names nor expects to control, and **neither of
these is that dependency**. `ray/di` is the framework the application declares itself. Until
[#119][119], `ray/compiler` looked like an implementation detail this package brackets, but was not:
`ContextInterface` documented `getInjectorInstance()` as returning `CompiledInjector($meta->compileDir)`
and `__invoke()` as composing `DiCompileModule`, so a consumer wrote both class names into its own
bootstrap, and that same method's docblock let a third one, `Ray\Compiler\Exception\ScriptDirNotReadable`,
pass through uncaught. Pinning it would hold back an upgrade the consumer has every reason to want,
and the app author would see `composer update` do nothing and need `composer why-not` to find out why.

Both stay carets, and `ci.yml`'s scheduled run is what covers what Renovate then has nothing to
widen: `composer update` resolves each to the newest release its caret allows, so the ranges are
re-tested against versions that did not exist when they were written. What this gives up is real —
a red weekly build means users can already install the broken pairing, where a gate would have made
it unreachable.

Two further measurements support leaving `ray/di` alone specifically: `src/` reaches it through
`AbstractModule` and `InjectorInterface` and nothing else, and `ray/compiler`, far more deeply
coupled to it than this package is, declares `ray/di ^2.19` itself.

**Would change it:** a runtime dependency this package brackets completely, with no class name of
its reaching consumer code — for that one, gating would cost nothing and the pin would be right.
[#119][119] closed the two class names above with `AbstractCompiledContext`, which composed
`DiCompileModule`/`CompiledInjector` internally and rethrew `ScriptDirNotReadable` as this package's
own `Exception\CompileDirUnavailable`; [#148][148] moved that bracketing into `InjectorBuilder`
(and dropped the `DiCompileModule` wrap as measurably inert), so the condition [#119][119]
established still holds — consumer code names no `Ray\Compiler` class. It is not met completely: `CompiledInjector::getInstance()` can still throw
`Ray\Compiler\Exception\Unbound` for a missing binding, unwrapped, and `ScriptCompilerInterface`
remains an explicit escape hatch onto `ray/compiler` for an app that replaces the bundled compiler
(see `src/ScriptCompilerInterface.php`'s docblock and `src/RayScriptCompiler.php`). Whether those two
gaps are worth closing, and whether a mostly-bracketed dependency already clears the bar this entry
set, is a judgment call this PR does not make — the pinning decision itself stays open for a
dedicated follow-up, consistent with [#119][119]'s own scope note that re-evaluating the pin was
left to a later issue.

### Wrapping `CompileRunner::run()`'s compile-step exceptions instead of leaving them raw or documenting the gap — [#131][131]

Three options, continuing the compile-side half of what the `#116` entry above left open after
[#119][119] closed the runtime half: leave `$this->compiler->compile(...)`'s exceptions raw (status
quo), document the gap in `ScriptCompilerInterface`'s docblock without changing behavior, or catch
and wrap them the way the runtime side already wrapped `ScriptDirNotReadable` into
`Exception\CompileDirUnavailable` (then in `AbstractCompiledContext`, now in `InjectorBuilder`).
Wrapping was chosen.

Leaving it raw costs nothing at `bin/ray-di-compile`'s boundary — `Cli::compile()`'s
`catch (Throwable $e)` already turns any exception into exit status `1` — but leaves a consumer
calling `CompileRunner::run()` directly needing to know and catch `ray/compiler`'s or `ray/di`'s own
exception types, exactly the coupling [#119][119] stopped requiring on the runtime side. Documenting
only removes the surprise without removing that coupling: a consumer still has to name
`Ray\Compiler\Exception\Unbound` (or similar) to handle a bad binding.

Wrapping has one real cost, not weighed when the gap was first noticed: `Cli::compile()` picks its
`catch` branch by type (`ExceptionInterface` before `Throwable`, `src/Cli.php:110-114`), and the two
branches format STDERR differently — `Throwable`'s branch prefixes the message with the exception's
class name, `ExceptionInterface`'s does not. Once a compile-step failure is wrapped in this
package's `ExceptionInterface`, it moves branches, so `CompileFailed`'s own message has to carry the
wrapped exception's class and message itself, or `bin/ray-di-compile`'s STDERR gets strictly less
informative for this one failure mode. The exit status (`1`) is unchanged either way.

This changes what the `#116` entry's "not met completely" carve-out describes: `CompileRunner::run()`
now wraps a `compile()` failure, closing that half of the gap the same way [#119][119] closed the
runtime half, for the documented path (`CompileRunner::run()`, not calling `ScriptCompilerInterface`
directly). `ScriptCompilerInterface` remains an "explicit escape hatch onto `ray/compiler`" for an
app calling `compile()` outside `run()` — unaffected, per its own docblock. Re-evaluating the `#116`
pin is still out of scope: closing this half changes what re-evaluating that pin would be *for*, not
a reason to do it here.

**Would change it:** evidence that `bin/ray-di-compile` CLI users depend on the exact
`{class}: {message}` STDERR format for a compile-step failure — at that point `CompileFailed` alone
might not be the right fix and the `Cli` branch selection itself would need revisiting.

### Naming a concrete test count in `CLAUDE.md` or here — [#82][82]

Removed in [28ea330][28ea330] after review, and not to be restored. A total was a tripwire every
test-touching change had to re-measure, and a skip count cannot be measured at all on the machine
that usually runs the suite — it takes a root run to observe. What survives is the part a reader
needs, and it stays true whatever the totals are: a root run skips the tests that assert a denial, a
non-root run skips nothing.

### Leaving `homepage` out of `composer.json` — [#26][26]

There is no site to point at, and Packagist derives the canonical repository link from the VCS URL,
so the key would only duplicate a link Packagist already shows. `support.issues` and `support.source`
carry the two links that exist. `ray/di`, `ray/compiler` and `ray/aop` all leave it unset. The
absence is the decision, not an omission waiting to be filled in.

### Re-pointing a published tag — [#26][26]

Packagist serves whatever the tag points at, so force-pushing one silently changes what every new
install gets. Someone holding a `composer.lock` sees the dist reference SHA stop matching; a fresh
install has nothing to compare against. A repository ruleset targeting tags restricts `update` and
`deletion` across all of them, separately from the branch ruleset on `main`. A mistake in a
published tag is fixed by the next patch version, never by moving the tag.

### A compile-time validation extension point, preload generation, and an order-optimized autoloader — [#127][127]

Three proposals from [#127][127], measured on `ray/di` 2.20.0 and 2.22.2, `ray/compiler` 1.14.0,
PHP 8.4 (no behavioral difference between the two `ray/di` versions). All three declined.

**Validation extension point** (something like a `CompiledGraphValidatorInterface`, mirroring
`BakedPathGuardInterface`). Its motivating defect — a `MultiBinder` bound with `->to(FooA::class)`
but missing the matching `$this->bind(FooA::class)` — is structurally uncatchable by brute-force
instantiation over an app-owned index: the target lives under a `Map-` index, not a class index, so
it is never enumerated, a consumer that only holds the `Map` compiles clean, and the miss surfaces
only on the first iteration (measured: `pass=2 fail=0`). #127's own premise, "instantiability is
already verified at compile success," was too strong: an `#[Set]` binding left entirely unregistered
compiles clean and throws a `TypeError` from `MapProvider` at runtime, and a missing script (reached
through a nested dependency) fails with a bare `Error` because `prototype()` `require`s it
unchecked. What a validator *would* still catch — a Provider's `get()` body, `#[PostConstruct]`,
`toInstance`'s `__wakeup`, artifact completeness — is obtainable from a build-stage script with zero
new API surface ([#130][130]). The shape doesn't fit either: `Cli` never injects an extension point,
so paired with a no-op default it would never fire on the canonical path
(`bin/ray-di-compile`). BEAR.Package (1.21.0) has no dedicated validator part: its verification is a
side effect of `CompilePreload::loadResources()` calling `getInstance()` on every resource, and
`FakeRun` is a class-collection driver, not a validator (`Bootstrap` swallows every `Throwable` and
discards the return value). Upstream is already moving in this space: ray-di/Ray.Di #337 and #338
propose soft-deprecating JIT binding, citing `CompiledInjector`'s differing behavior as evidence;
ray-di/Ray.Compiler #137 proposes generating a singleton manifest at compile time for a warmup API to
consume.

**Preload generation.** BEAR.Package derives its class list as a byproduct of a
`spl_autoload_register` spy plus a fake request that calls `getInstance()` on every resource. This
package is not a framework, so there is no fake request to run and no legitimate way to produce that
list; substituting brute-force `getInstance()` misses classes reached only lazily (`Map` iteration,
`#[Set]`'s deferred providers). An absolute-path `preload.php` written into `compileDir` is rejected
by this package's own `BakedPathGuard` with `BakedPathFound` (measured) — not a false positive; BEAR
itself writes its preload script with a `__DIR__`-relative path into `appDir` rather than baking an
absolute path in. Following that convention would put the artifact outside this package's remit
entirely. The static closure a preload script would need is already fully resolved by compile
(`new \X(...)` inlined, AOP proxies materialized in `compileDir`, target strings serialized), so
adding another compile output is upstream `ray/compiler`'s call, best folded into ray-di/Ray.Compiler #137.

**Order-optimized autoloader** (an autoloader that `require`s classes in call order instead of on
first use). #127's own skepticism held up: where preload is available it is moot, and where it isn't
the only plausible saving is a realpath-cache miss — nowhere near preload's effect. Left undone
unless a `composer dist` fixture benchmark justifies it.

**Would change it:** upstream declining compile-time multibinding-target validation while real
demand emerges for a side-effect-free validator. That would be revisited not as an extension point
but as a separate bin (`ray-di-validate`; public contract limited to arguments and an exit-status
table, implementation `@internal` like `Cli`).

### Splitting `tests/` beyond `#[CoversClass]` — [#140][140]

28 test files exist for 13 `#[CoversClass]`/`#[CoversNothing]` targets (measured on `a5c448e`,
counting `#[Test]` methods per file; `tests/*.php`, excluding the non-test
`tests/docker-check-probe.php`):

| tests | target | files |
|---|---|---|
| 28 | `BakedPathGuard` | 6 |
| 16 | `AppMeta` | 2 |
| 12 | `CompileRunner` | 4 |
| 12 | `Cli` | 2 |
| 10 | `BinCompile` (`#[CoversNothing]`) | 2 |
| 10 | `Cleaner` | 3 |
| 9 | `PermissionNormalizer` | 2 |
| 9 | `MapContextProvider` | 2 |
| 7 | `CompileDirGuard` | 1 |
| 3 | `AbstractContext` | 1 |
| 3 | `AbstractCompiledContext` | 1 |
| 2 | `BakedPathScanner` | 1 |
| 1 | `RayScriptCompiler` | 1 |

No file names this split, so a class with more than one file has been getting there by an
unwritten rule every time. Reading the six `BakedPathGuard` files and the four `CompileRunner`
files shows the rule was already, in practice, `#[CoversClass]` crossed with one more axis:
whether the test starts a separate process (`Support\PhpProcess`, `BinCompile*Test`) or runs the
real `ray/compiler` (not `CompileRunnerOrderingTest`, which passes a `FakeRecordingCompiler` via
the named `compiler:` argument — that one test needed reading, not grepping, since a
`ScriptCompilerInterface` fake is invisible to a pattern match on class names). Three more facts
argue for writing the rule down rather than leaving it implicit:

- **Method count is not a reliable third axis.** `mago.toml` had no `too-many-methods` entry, so
  the default threshold (`mago config`'s resolved value: `10`) was doing unrecorded, silent work.
  `AppMetaFromAppDirTest` sits at exactly 10 methods (`setUp`, `tearDown`, 8 `#[Test]`) and passes
  only because it's at the limit, not under it — one more scenario would have forced a split for a
  reason nobody wrote down. Commit [888a9b1][888a9b1] hit this directly: both `CleanerTest` and
  `BinCompileTest` were split because a merge would have crossed the (unconfigured, undocumented)
  threshold, recording the choice as "どちらも分割で too-many-methods に触れたので、mago.toml は
  緩めず既存パターンで分けた" (both splits touched `too-many-methods`, so `mago.toml` was left
  alone and the existing pattern used instead). **This entry reverses that call**: `too-many-
  methods` is now off for `tests/` (see `mago.toml`), so a future test-count trigger like the one
  `888a9b1` hit is no longer a reason to split — only `#[CoversClass]` and the integration axis
  are.
- **`Rejection` names two unrelated things.** `CleanerRejectionTest`/`PermissionNormalizerRejectionTest`/
  `BakedPathGuardRejectionTest` group tests that hit `Support\PermissionBits::skipUnlessEnforced()`
  (root-ignores-permission-bits skips); `CliRejectionTest`/`BinCompileRejectionTest` group the
  exit-status-2 contract instead. `CleanerGuardTest` is a rejection test in the first sense with
  neither the name nor the grouping.
- **`#[CoversClass]` can drift from what's asserted.** `MapContextProviderResolutionTest`'s three
  tests all call `(new MapContextProvider(...))->get($meta)->getInjectorInstance()` and assert on
  `getInjectorInstance()`'s return value and the resolution it produces —
  `MapContextProvider::get()` is a pass-through step on the way there, already independently
  covered by `MapContextProviderTest::getReturnsMappedContext`. Commit [a788e2e][a788e2e] moved
  these three tests out of `CompileRunnerTest`/`CompileRunnerRelocationTest` (which is why the file
  itself is named after `MapContextProvider`) without revisiting the attribute against what the
  assertions actually target.

**Convention:** split a `#[CoversClass]`-covered class's tests on exactly two axes —
`#[CoversClass]` itself (first tier: one `{CoversClass}Test.php`) and, within that, integration
(second tier: `{CoversClass}IntegrationTest.php`, meaning a separate process via
`Support\PhpProcess` or a real `ray/compiler` run — judged by reading the test). `#[CoversClass]`
must name the class the test's assertions actually target, not merely the class the test happens
to pass through.

`#[CoversNothing]` sits outside that naming rule — it takes no class parameter, so neither
`{CoversClass}Test.php` nor the "must name the class actually targeted" requirement applies to it.
Use it only for a classless integration test that exercises something outside any `src/` class as
a black box; today that means exactly `BinCompileTest`/`BinCompileRejectionTest`, which run
`bin/ray-di-compile` in a separate process via `Support\PhpProcess` and are named after what they
test (`BinCompile`), not after a covered class.

`too-many-methods` is disabled for `tests/` (`mago.toml`) so size is never a third reason to
split, for either case. A `setUp()` that can't be shared across files in the same pair becomes a
`private` helper method, not a reason to split further.

**Out of scope here, left to a follow-up issue:** the actual 28-to-~14 file reorg, fixing
`MapContextProviderResolutionTest`'s `#[CoversClass]`, and unit-izing the one line of
`CompileRunner::run()`'s cleanup `finally` block that the next entry identifies as
unit-testable (via `tests/Fake/FakeRejectingGuard.php`) — all three touch `tests/`, which this
issue does not.

### Measuring coverage and MSI over the whole suite, including integration — [#140][140]

Two quality gates exist — Codecov (`.github/codecov.yml`, `target: 100%`/`threshold: 0%`) and
Infection (`infection.json5`, `minMsi`/`minCoveredMsi` pinned to a measured `87.86`, introduced by
[#137][137]) — and neither's operating rule was written down.

**Both are measured over the full suite, integration tests included, not unit tests alone.**
Measured on `a5c448e` (`php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text`, PHP 8.5.5 with
pcov, `<source>` = `src/` per `phpunit.xml.dist`):

- The full suite: `Classes: 100.00% (12/12)`, `Lines: 100.00% (330/330)`.
- Excluding only `BinCompileTest`/`BinCompileRejectionTest` (the two tests that launch a separate
  process via `Support\PhpProcess` to exercise `bin/ray-di-compile`): unchanged,
  `Classes: 100.00% (12/12)`, `Lines: 100.00% (330/330)`. Pcov only instruments the parent process,
  so these two files contribute zero measured coverage regardless of how thoroughly they exercise
  the binary — Infection's covered-MSI mode is the same story, since a mutant in code a separate
  process runs is never seen as executed either. Keeping them out of the suite `phpunit.xml.dist`
  runs would cost real verification (they're the only tests that go through the actual CLI
  entrypoint) for zero coverage or MSI benefit either way — the two metrics simply cannot see them,
  which is a reason to run them for their own sake, not a reason to also budget for their absence
  from the numbers.
- Further excluding every test method that actually runs the real `ray/compiler` (not just the
  files that contain one — `CompileRunnerContextResolutionTest`'s one test throws inside the
  context provider before `$this->compiler->compile(...)` is ever reached, so despite living
  alongside integration tests it isn't one): `Classes: 91.67% (11/12)`, `Lines: 99.70% (329/330)`.
  `RayScriptCompiler` — the class whose single test is exactly the excluded one — drops to 0%
  (its one line, `(new Compiler())->compile(...)`, is unreached without a real compile); no other
  class drops at all. In particular, `CompileRunner` stays at `100.00% (22/22)`: the
  `finally`-block `$cleaner($meta);` line noted above as the one unit-convertible case is *already*
  reached today, by `CompileRunnerContextResolutionTest`'s own (non-integration) test — any
  exception inside `run()`'s `try` block hits that cleanup, including a context-provider failure
  that never gets near the compiler, so the line was never integration-only to begin with. This
  sharpens rather than weakens the reason coverage alone is not the whole gate for the two classes
  that *don't* show up as dropping: pcov marks a line "covered" once any test reaches it, whether
  the call it makes succeeds or fails, so `AbstractCompiledContext::getInjectorInstance()`'s `new
  CompiledInjector(...)` reads as covered from the existing missing-compile-dir failure test
  alone — a line-coverage number cannot tell "this ran and produced a working injector" apart from
  "this ran and immediately threw." Verifying the success path (a `CompiledInjector` that actually
  resolves a real compiled binding) needs the real dependency to run; no unit rewrite reaches that
  without becoming a test of the fake instead. `RayScriptCompiler` needs no such argument — its
  drop to 0% under this exclusion is the coverage tool agreeing outright that only integration
  verifies it.

None of this is `@codeCoverageIgnore`d — not `RayScriptCompiler`'s one line (measurably reachable
only by integration), not `AbstractCompiledContext`'s success-path body (line coverage happens not
to show the gap, but the gap is real: only integration verifies it *meaningfully*), and not
`BinCompile*`'s two files. All of it is reachable; only integration reaches it in a way that
actually verifies anything. `codecov.yml`'s own stated policy is to ignore what's unreachable and
keep the floor at the real 100% otherwise — an `@codeCoverageIgnore` here would misstate why a line
isn't independently unit-covered as "unreachable," which it is not.

**MSI floor ratchets, coverage floor does not move.** `infection.json5`'s `minMsi`/
`minCoveredMsi` are pinned to a measured value ([#137][137]: `87.86`), not a target chosen up
front; when new tests raise the measured MSI, `infection.json5`'s floor is raised to match by hand
— it has no auto-detect, so leaving it unraised after a real improvement would silently let the
achieved level regress on a future PR without failing CI. `codecov.yml`'s `target: 100%` needs no
equivalent ratchet: it is already the ceiling. This issue does not change either file's current
value.

## Declined for cost

### Release automation with release-drafter — [#26][26]

Rejected on the trade, not on the tool. It wants a workflow, a config file, PR labels maintained by
hand, and a standing `contents: write` grant, and what it produces is what `gh release create
--generate-notes` already produces on demand. Releases here are a few a year.

**Would change it:** a cadence that makes the manual step frequent, or generated notes that need
hand-editing every time.

### `ext-posix` to detect root in tests

Rejected on its own merits: a uid check answers the wrong question. A non-root process holding
`CAP_DAC_OVERRIDE` also ignores permission bits, so `posix_geteuid() !== 0` would let the permission
tests run and fail. `Support\PermissionBits` measures the capability instead — it creates a
directory, makes it unreadable, and checks whether this process is actually denied.

The cost is real too. Unlike `ext-pcre` and `ext-SPL`, `ext-posix` is optional in a PHP build, so
under the rule below it would have to be declared in `require` — a hard install constraint bought
for one branch in a test helper.

### Declaring `ext-pcre` / `ext-SPL` in `composer.json` — [#14][14]

Rejected as zero-information. Both are core extensions with no way to disable them in PHP 8.2, so
the constraint is satisfied by every environment and prevents no failed install. Of 32 packages in
`vendor/`, 13 declare `ext-*` and none declares either of these — PHPUnit declares ext-dom, filter,
json, libxml, mbstring and xmlwriter while using `preg_match` freely. `composer validate --strict`
exits 0 either way.

**The standing rule:** declare an `ext-*` only when that build could actually be missing it.
`ext-pdo` from `ray/di`/`ray/compiler` resolves transitively and must not be re-declared.

**Would change it:** starting to use `mb_*`, `iconv`, `filter_var` or `token_get_all`, which can be
disabled and so make a declaration enforceable.

### Adopting `stolt/lean-package-validator` instead of a bespoke audit script — [#135][135]

Rejected on the trade. Its `validate --validate-git-archive` does what `tests/gitattributes-check.sh`
does — build a real `git archive` tarball from `HEAD` and check its contents — and supports both the
`classic` and `negated` list styles. But it pulls in `symfony/console`, `symfony/finder`,
`sebastian/diff`, `stolt/list-skills-command` and `laravel/agent-detector`; `require-dev` today is
`carthage-software/mago` and `phpunit/phpunit` only, and none of those land in `vendor/`
transitively. Its default `CommonPreset` also excludes `*.{md,MD}` and `LICENSE`, which would drop
`README.md`, `CHANGELOG.md` and `LICENSE` from the distribution unless overridden with an `.lpv`
file — writing that override is the same work as the expected list this issue already needs. The
GitHub Action skips the composer dependency, but its default `lpv-version` is `4.4.4`, too old to
run from `composer tests` locally.

**Would change it:** a release whose default preset stops excluding Markdown/LICENSE, or a second,
unrelated need for `symfony/console` that would already put it in `vendor/`.

## Reversed after an earlier rejection

### Mutation testing with Infection — rejected by [#16][16], adopted by [#137][137]

[#16][16] rejected Infection on a cost/benefit measurement of this package's *test quality*:
`infection` 0.34.0 on PHP 8.5.5 with pcov, one full run, `86 mutants / Covered MSI 90% / 8
survivors`, concluding the tool would motivate at most two more tests and was not worth a
permanent maintenance cost that also nearly doubles `vendor/` (32 → ~65 packages).

[#137][137] reopens it on a different axis entirely: not test quality, but using this package's
small size as a place to build real operational experience running Infection in CI. It does not
dispute [#16][16]'s measurement — it simply is not optimizing for what that measurement was
about. Any improvement to test quality here is a side effect, not the success criterion.

[#16][16]'s premise that `src/` was "functionally frozen" is also stale: 62 commits landed in
`src/` between its comment (2026-07-25) and this one, growing the tree to 34 files / 1557 lines.
Re-measuring was mandatory, not optional — the 86-mutant figure no longer describes this
codebase.

Measurement at adoption time (Linux/PHP 8.2.33/pcov, `infection/infection` 0.32.6, non-root,
`@default` mutators, matching the dedicated CI job and verified identical across 5 independent,
non-bind-mounted containers): 280 mutations, 242 killed, 34 escaped, 2 errors, 2 timed out, 100%
mutation code coverage, `msi = coveredCodeMsi = 87.86`. `infection.json5`'s `minMsi`/
`minCoveredMsi` were pinned to this measured value at the time, not a round number chosen up
front — the CI job only failed on a regression below it. **Historical**: [#143][143] triaged every
escaped mutant this measurement found and re-pinned the floor to `100.0` — see "Why three mutants
are ignored" below for the current state and the up-to-date numbers.

`infection/infection` is pinned to `0.32.6`, not the newest release (`0.34.1`, what [#16][16]
used). Every release `>= 0.33` requires `"php": "^8.3"`; the `lint`/`dist` jobs and the `8.2` leg
of `lowest` run `composer update` (including dev dependencies) under PHP 8.2, where such a
requirement fails the whole update, not just Infection's own install. `0.32.6` is the newest
release that still declares `"php": "^8.2"` (its own caret already reaches 8.3–8.5), so one exact
pin installs cleanly across the whole matrix — the same convention `carthage-software/mago` and
`phpunit/phpunit` already use in `require-dev`.

**Would change it:** an Infection release declaring `"php": "^8.2"` (or wider) again, at which
point the pin could move forward without losing the PHP 8.2 jobs.

### Why two kinds of mutants are ignored

[#143][143] re-measured on the CI environment (`ubuntu-latest`/PHP 8.2/pcov via
`composer infection`) after the `tests/` reorg in [#142][142] changed which of [#16][16]'s
original findings still escape. Four of the six reasons this section used to list no longer
correspond to an escaped mutant — `Cleaner`'s `mkdir()` permission literal, its
`@codeCoverageIgnore`d race branch, `BakedPathGuard::__invoke()`'s `continue`→`break`, and
`PermissionNormalizer::normalizeContents()`'s non-directory `continue` are all killed by the
current suite (checked twice, independently, on the CI-equivalent environment) — so none of them
carries an `infection.json5` ignore entry any more. Three reasons remain or are newly found, each
naming a mutant Infection cannot be made to see without asserting behaviour this package does not
promise; `infection.json5` ignores exactly these three (and nothing else), so the section below
covers every entry `infection.json5` ignores, and vice versa:

- `BakedPathScanner`'s `$offset = $position + 1` mutated to `+2` (`hasBakedPath()` and
  `compileDirRanges()`, both `IncrementInteger`) is an equivalent mutant: every needle passed to
  either method is an absolute path, so the two byte offsets can never produce an observable
  difference. The same `+1` mutated the other way (`DecrementInteger`/`Minus`) does not escape at
  all — the search offset stops advancing, so the mutant either times out (`hasBakedPath()`) or
  exhausts PHP's memory limit building an ever-growing `$ranges` array (`compileDirRanges()`).
  Infection counts a time out or an error the same as killed, so only the `+2` direction needs an
  ignore entry.
- `Cli::write()`'s error-suppression plumbing has two mutants that are unkillable without pinning
  behaviour this package does not promise. `set_error_handler(static fn(): bool => true)` mutated
  to `=> false` (`TrueValue`) changes only what happens to a `file_put_contents()` warning once
  `write()`'s own handler declines it: PHP falls through to its *built-in* default handling for
  that warning, not to any handler a test installs beforehand, and whether that becomes observable
  depends on `display_errors`/`error_reporting`, which this package does not control or promise.
  The surrounding `try { file_put_contents(...) } finally { restore_error_handler(); }`
  (`UnwrapFinally`) is only distinguishable from the two statements run in sequence if
  `file_put_contents()` itself throws, which it never does — it reports failure through the
  warning above, not an exception.

`infection.json5`'s `IncrementInteger`/`TrueValue`/`UnwrapFinally` ignore entries exclude four
entries across three methods (two `BakedPathScanner` methods for the first bullet, `Cli::write()`
twice for the second) from Infection's tested-mutant count entirely, not just from what fails the build.
With them excluded, re-measuring on the same CI environment gives 275 mutations (280 minus the
five ignored), 271 killed, 2 errors, 2 timed out, 0 escaped, 100% mutation code coverage,
`msi = coveredCodeMsi = 100.0` — `infection.json5`'s `minMsi`/`minCoveredMsi` are pinned to this
value, replacing the `87.86` recorded above.

Separately, `infection.json5`'s `testFrameworkOptions: "--exclude-group=infection-excluded"` skips
`tests/BinCompileIntegrationTest.php` (marked `#[Group('infection-excluded')]`) during Infection's
own test runs. This is not a fourth ignored mutant — it drops a whole test file from every killer
process's run, not an escaped mutant from the count. It costs nothing to drop: the file launches
`bin/ray-di-compile` in a separate process via `Support\PhpProcess`, and pcov only instruments the
parent process, so it was already contributing zero measured coverage or MSI before this change
(the file was `BinCompileTest`/`BinCompileRejectionTest` when [#140][140] first measured this;
[#142][142] merged both into today's `BinCompileIntegrationTest`). Re-confirmed for [#143][143]:
`php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text` and the same command with
`--exclude-group=infection-excluded` report identical `Classes: 100.00% (12/12)` /
`Methods: 100.00% (38/38)` / `Lines: 100.00% (330/330)` — excluding the file changes nothing
Infection or Codecov can see, it only removes the one initial-suite run this file cost from every
mutant's killer process. `composer test` and CI's `test` job are unaffected and still run it in
full; only Infection's own invocation skips it.

## Possible, not done

### A CI check for comment length

[#70][70] rejected a line-count gate because `main` then held blocks of 37, 19 and 18 lines, so any
useful threshold failed existing code. **That premise is gone**: after [#71][71] the longest block
is 16 lines (`CompileRunner::run()`, of which 8 are the `@throws` enumeration), and the next is 12.
A threshold around 20 would pass today.

Worth weighing against what it would actually catch. A line count does not see audience, and the
rule that keeps being violated is about audience — a 6-line comment written for the diff passes a
20-line gate. Re-open only with a specific failure it would have caught.

### A CI check for change-narrating words in added lines — [#70][70]

Detecting "previously", "used to" and similar, restricted to added lines of a diff so existing code
cannot fail it. Implementable; nobody has needed it. Would be a separate issue.

[14]: https://github.com/NaokiTsuchiya/RayDiContext/issues/14
[16]: https://github.com/NaokiTsuchiya/RayDiContext/issues/16
[26]: https://github.com/NaokiTsuchiya/RayDiContext/issues/26
[53]: https://github.com/NaokiTsuchiya/RayDiContext/issues/53
[70]: https://github.com/NaokiTsuchiya/RayDiContext/issues/70
[71]: https://github.com/NaokiTsuchiya/RayDiContext/pull/71
[79]: https://github.com/NaokiTsuchiya/RayDiContext/issues/79
[82]: https://github.com/NaokiTsuchiya/RayDiContext/issues/82
[83]: https://github.com/NaokiTsuchiya/RayDiContext/issues/83
[84]: https://github.com/NaokiTsuchiya/RayDiContext/issues/84
[86]: https://github.com/NaokiTsuchiya/RayDiContext/issues/86
[116]: https://github.com/NaokiTsuchiya/RayDiContext/pull/116
[119]: https://github.com/NaokiTsuchiya/RayDiContext/issues/119
[127]: https://github.com/NaokiTsuchiya/RayDiContext/issues/127
[130]: https://github.com/NaokiTsuchiya/RayDiContext/issues/130
[131]: https://github.com/NaokiTsuchiya/RayDiContext/issues/131
[135]: https://github.com/NaokiTsuchiya/RayDiContext/issues/135
[137]: https://github.com/NaokiTsuchiya/RayDiContext/issues/137
[140]: https://github.com/NaokiTsuchiya/RayDiContext/issues/140
[142]: https://github.com/NaokiTsuchiya/RayDiContext/issues/142
[143]: https://github.com/NaokiTsuchiya/RayDiContext/issues/143
[148]: https://github.com/NaokiTsuchiya/RayDiContext/pull/148
[28ea330]: https://github.com/NaokiTsuchiya/RayDiContext/commit/28ea330
[34f6a95]: https://github.com/NaokiTsuchiya/RayDiContext/commit/34f6a95
[888a9b1]: https://github.com/NaokiTsuchiya/RayDiContext/commit/888a9b1
[a788e2e]: https://github.com/NaokiTsuchiya/RayDiContext/commit/a788e2e
