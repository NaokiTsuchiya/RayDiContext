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
covers, and its `not-on` names `AbstractContext` alone. An abstract class placed there fails:

```
tests/ProbeRoot.php:8:16: error[must-be-final]: Structural flaw in `NaokiTsuchiya\RayDiContext\AbstractProbeRoot`
 = Every concrete class in the root namespace is final except AbstractContext, which exists to be extended
```

The same probe under `NaokiTsuchiya\RayDiContext\Support` is not reported — `on =
'NaokiTsuchiya\RayDiContext\*'` matches one namespace segment, not a subtree. That is a way past the
rule, not a licence to use it. Shared setup goes through the final helpers in `tests/Support/`:
static utilities (`Fs`, `PermissionBits`, `PhpProcess`) and per-test objects (`CliFixture`).

**Would change it:** a second `not-on` entry naming a base class, worth adding only for a case that
earns its place beside `AbstractContext`.

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

## Declined for cost

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
[53]: https://github.com/NaokiTsuchiya/RayDiContext/issues/53
[70]: https://github.com/NaokiTsuchiya/RayDiContext/issues/70
[71]: https://github.com/NaokiTsuchiya/RayDiContext/pull/71
[79]: https://github.com/NaokiTsuchiya/RayDiContext/issues/79
[34f6a95]: https://github.com/NaokiTsuchiya/RayDiContext/commit/34f6a95
