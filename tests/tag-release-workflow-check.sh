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

# A job-level "write-all" grants everything at once and would not show up as contents: write
# below, so it is checked separately rather than relying on the per-scope check to catch it.
write_all_jobs="$(yq '.jobs | to_entries | .[] | select(.value.permissions == "write-all") | .key' "${wf}")"
[ -z "${write_all_jobs}" ] || fail "job(s) use permissions: write-all instead of naming scopes: ${write_all_jobs}"

# Exactly the tag job may hold contents: write.
writers="$(yq '.jobs | to_entries | .[] | select(.value.permissions.contents == "write") | .key' "${wf}")"
[ "${writers}" = "tag" ] || fail "expected only the 'tag' job to hold contents: write, got: '${writers}'"

# The tag job's write access is gated behind the "tag" environment's required reviewers, and can
# only run after validate's checks — both are load-bearing, not incidental.
tag_environment="$(yq '.jobs.tag.environment' "${wf}")"
[ "${tag_environment}" = "tag" ] || fail "jobs.tag.environment is not \"tag\" (got: ${tag_environment}) — the tag job's write access would have no approval gate"

tag_needs="$(yq '.jobs.tag.needs' "${wf}")"
[ "${tag_needs}" = "validate" ] || fail "jobs.tag.needs is not \"validate\" (got: ${tag_needs}) — the tag job could run without validate's checks"

# Exactly one trigger, workflow_dispatch: GITHUB_TOKEN pushes never trigger another workflow, so
# Release creation lives inside this workflow instead of a tag-triggered one (see
# docs/decisions.md); a schedule/pull_request/push trigger added here would run this workflow
# outside the approval-gated path that check exists to protect.
trigger_keys="$(yq -o=json -I=0 '.on | keys' "${wf}")"
[ "${trigger_keys}" = '["workflow_dispatch"]' ] \
    || fail "workflow triggers are not exactly [\"workflow_dispatch\"] (got: ${trigger_keys})"

# `if:` conditions are compared for exact equality, not substring containment — a substring check
# would still pass a condition weakened by e.g. appending "|| true".
validate_if="$(yq '.jobs.validate.if' "${wf}")"
expected_validate_if="\${{ github.ref == 'refs/heads/main' }}"
[ "${validate_if}" = "${expected_validate_if}" ] \
    || fail "jobs.validate.if is not exactly \"${expected_validate_if}\" (got: ${validate_if})"

tag_if="$(yq '.jobs.tag.if' "${wf}")"
expected_tag_if="\${{ inputs.dry_run != true }}"
[ "${tag_if}" = "${expected_tag_if}" ] \
    || fail "jobs.tag.if is not exactly \"${expected_tag_if}\" (got: ${tag_if})"

summary_if="$(yq '.jobs["dry-run-summary"].if' "${wf}")"
expected_summary_if="\${{ inputs.dry_run == true }}"
[ "${summary_if}" = "${expected_summary_if}" ] \
    || fail "jobs.\"dry-run-summary\".if is not exactly \"${expected_summary_if}\" (got: ${summary_if})"

# Only the tag job may create a GitHub Release.
release_creators="$(yq '[.jobs | to_entries[] | select(.value.steps[].run // "" | test("gh release create")) | .key] | unique | .[]' "${wf}")"
[ "${release_creators}" = "tag" ] || fail "expected only the 'tag' job to run 'gh release create', got: '${release_creators}'"

# --verify-tag and --latest are checked on the exact line invoking `gh release create`, not
# anywhere in the step's run: text — a substring-anywhere check would still pass if the flag were
# dropped from the real command but left behind in a comment or echo.
check_release_create_line() {
    local step_name="$1" expected_latest="$2" run line found=0

    run="$(yq ".jobs.tag.steps[] | select(.name == \"${step_name}\") | .run" "${wf}")"
    [ -n "${run}" ] || fail "step \"${step_name}\" not found in jobs.tag.steps"

    while IFS= read -r line; do
        case "${line}" in
            *'gh release create'*)
                found=1
                echo "${line}" | grep -qF -- '--verify-tag' \
                    || fail "\"${step_name}\": 'gh release create' line lacks --verify-tag: ${line}"
                echo "${line}" | grep -qF -- "--latest=${expected_latest}" \
                    || fail "\"${step_name}\": 'gh release create' line does not pass --latest=${expected_latest}: ${line}"
                ;;
        esac
    done <<<"${run}"

    [ "${found}" = "1" ] || fail "step \"${step_name}\" has no 'gh release create' line"
}

check_release_create_line "Create the GitHub release (new)" "true"
check_release_create_line "Create the GitHub release if missing (recovery)" "false"

echo "tag-release-workflow-check: OK — only 'tag' holds contents: write (no write-all) behind environment: tag and needs: validate, trigger is exactly workflow_dispatch, if: conditions match exactly, gh release create carries --verify-tag and the mode-fixed --latest on its own line"
