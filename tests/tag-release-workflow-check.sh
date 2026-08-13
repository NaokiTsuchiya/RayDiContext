#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
wf="${root}/.github/workflows/tag-release.yml"

fail() {
    echo "tag-release-workflow-check: $1" >&2
    exit 1
}

command -v yq >/dev/null 2>&1 || fail "yq was not found on PATH"
[ -f "${wf}" ] || fail "${wf} not found"

top_permissions="$(yq -o=json '.permissions' "${wf}")"
[ "${top_permissions}" = "{}" ] || fail "top-level permissions is not {} (got ${top_permissions})"

# Exactly the tag job may hold contents: write.
writers="$(yq '.jobs | to_entries | .[] | select(.value.permissions.contents == "write") | .key' "${wf}")"
[ "${writers}" = "tag" ] || fail "expected only the 'tag' job to hold contents: write, got: '${writers}'"

# workflow_dispatch can target any ref; validate must refuse anything but main so a tag can
# never be resolved (or pushed) for a non-main commit.
validate_if="$(yq '.jobs.validate.if' "${wf}")"
echo "${validate_if}" | grep -qF "github.ref == 'refs/heads/main'" \
    || fail "jobs.validate.if does not look like it restricts to refs/heads/main (got: ${validate_if})"

triggers="$(yq '.on | keys | .[]' "${wf}")"
echo "${triggers}" | grep -qx "workflow_dispatch" || fail "workflow_dispatch trigger missing"
echo "${triggers}" | grep -qx "push" && fail "a push trigger is present — a tag push must not auto-run this workflow (that's #155's job)"

# The tag job must not run when dry_run is true, and dry-run-summary must run only then —
# otherwise dry_run=true would still push a tag (acceptance 7), or a real run would silently
# skip pushing (acceptance 1's "no human action, no tag").
tag_if="$(yq '.jobs.tag.if' "${wf}")"
echo "${tag_if}" | grep -qF 'inputs.dry_run != true' \
    || fail "jobs.tag.if does not look like it skips when dry_run is true (got: ${tag_if})"

summary_if="$(yq '.jobs["dry-run-summary"].if' "${wf}")"
echo "${summary_if}" | grep -qF 'inputs.dry_run == true' \
    || fail "jobs.\"dry-run-summary\".if does not look like it runs only when dry_run is true (got: ${summary_if})"

echo "tag-release-workflow-check: OK — only 'tag' holds contents: write, trigger is workflow_dispatch only, tag/dry-run-summary if: conditions are complementary on dry_run"
