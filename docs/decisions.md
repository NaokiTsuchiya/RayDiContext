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
namespace still fails the same way; only `AbstractContext` and `AbstractCompiledContext` are exempt.

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

Its `setUp()` is the same four lines as the other four `BakedPathGuard` test classes up to the
`uniqid()` prefix, which is why the fold keeps being proposed. It does not fit: the fixture's
defining act is creating the compile dir, and that class exists to assert what the guard does when
the compile dir is *missing*, is a *file*, or carries a mode it cannot list — so it puts the compile
dir at `{baseDir}/di` and creates nothing. Sharing the fixture would mean two more constructor
arguments (layout, and create-or-not) whose only non-default caller is that one file.

**Would change it:** a second class needing the same non-default shape.

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
[#119][119] closed the two class names above: `AbstractCompiledContext` now composes
`DiCompileModule`/`CompiledInjector` internally, so the README's usage example and
`tests/dist/consumer/bootstrap.php` extend it and implement only `appModule()`; and
`getInjectorInstance()` now catches `ScriptDirNotReadable` and rethrows this package's own
`Exception\CompileDirUnavailable`, the original retrievable via `getPrevious()`. For the documented
path (extend `AbstractCompiledContext`, catch this package's `ExceptionInterface`), the condition is
met. It is not met completely: `CompiledInjector::getInstance()` can still throw
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
and wrap them the way `AbstractCompiledContext::getInjectorInstance()` already wraps
`ScriptDirNotReadable` into `Exception\CompileDirUnavailable`. Wrapping was chosen.

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
[28ea330]: https://github.com/NaokiTsuchiya/RayDiContext/commit/28ea330
[34f6a95]: https://github.com/NaokiTsuchiya/RayDiContext/commit/34f6a95
