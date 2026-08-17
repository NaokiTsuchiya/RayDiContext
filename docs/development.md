# Development

CI-specific behavior that isn't obvious from the workflow file alone.

CI also runs weekly on a schedule, and `workflow_dispatch` runs it on demand. The `test` job installs
with `composer update`, so a scheduled run tests the declared ranges against whatever upstream has
released since — a red one means the newest release broke something, not that anyone changed this
repository. `composer show --direct` in the job log records which versions it resolved.

`phpunit.xml.dist` sets `failOnDeprecation="true"`. Combined with the PHP 8.5 job in the CI matrix, a
deprecation raised by `ray/di` or `ray/aop` under a newer PHP version can turn CI red with zero
changes in this repository — if a Renovate PR or a new PHP minor suddenly fails for no apparent
reason, check here first. If it happens, either add `#[WithoutErrorHandler]` to the affected test or
relax `failOnDeprecation` for that PHP version only.

See [decisions.md](decisions.md) for why this weekly gate isn't a hard release blocker.

## Releasing

Before tagging, move the entries from `CHANGELOG.md`'s `## [Unreleased]` section into a new
`## [x.y.z] - <date>` section. Keep an empty `## [Unreleased]` section at the top. The tag is never
re-pointed, so the changelog entry has to be right the first time.

Tags carry no `v` prefix — `0.1.0`, not `v0.1.0`. Composer accepts either, so the only thing that
matters is not mixing the two.

Once the CHANGELOG PR is merged, run the "Tag release" workflow
(`.github/workflows/tag-release.yml`) from the Actions tab, or with `gh workflow run`, passing the
version you just confirmed:

```bash
gh workflow run tag-release.yml -f version=0.1.0
```

For a version with no existing tag, it refuses to push a tag that does not match `CHANGELOG.md`'s
latest confirmed section, refuses a `v`-prefixed input, and refuses to push if main's `ci` job is not
green for the commit being tagged; once those pass it pushes the tag and creates the GitHub Release
from that section's text (`gh release create --verify-tag`). Pushing the tag and creating the Release
both need approval from the `tag` environment's required reviewers; before approving, the workflow
run's summary page already shows the resolved version, mode, and the exact release notes about to be
published — no need to dig through job logs first. Pass `dry_run=true` to run every check and see the
extracted release notes without pushing a tag or creating a release.

If the run fails or is interrupted after the tag is pushed but before the Release is created, run the
same command with the same `version` again — the workflow detects that the tag already exists and
only (re)creates the Release, reading `CHANGELOG.md` from that tag rather than from main. Running it
again once the Release already exists is a no-op. A Release created this way is never marked "Latest"
on the releases page, even if it is in fact the newest version — run `gh release edit <version>
--latest` by hand afterward if that matters.

Packagist builds the version from the git tag, not from the GitHub release — the release is for
humans. A published tag is never re-pointed: the tag ruleset restricts updates and deletions, and
anything wrong in a tag is fixed by the next patch version.

See [decisions.md](decisions.md) for why this workflow shape (a single `tag` job with a
`contents: write` grant scoped to it, instead of a tag-triggered second workflow or
`release-drafter`) was chosen.
