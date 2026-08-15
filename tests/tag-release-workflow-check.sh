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

top_permissions="$(yq -o=json -I=0 '.permissions' "${wf}")"
[ "${top_permissions}" = "{}" ] || fail "top-level permissions is not {} (got ${top_permissions})"

# A job-level "write-all" grants everything at once and would not show up as contents: write
# below, so it is checked separately rather than relying on the per-scope check to catch it.
write_all_jobs="$(yq '.jobs | to_entries | .[] | select(.value.permissions == "write-all") | .key' "${wf}")"
[ -z "${write_all_jobs}" ] || fail "job(s) use permissions: write-all instead of naming scopes: ${write_all_jobs}"

# Exactly the tag job may hold contents: write — a job-set check that also covers a job added
# later and never given its own exact-permissions assertion below.
writers="$(yq '.jobs | to_entries | .[] | select(.value.permissions.contents == "write") | .key' "${wf}")"
[ "${writers}" = "tag" ] || fail "expected only the 'tag' job to hold contents: write, got: '${writers}'"

# Each of the three known jobs' permissions are additionally compared for exact equality, not
# just "does it grant contents: write" — a broadened scope such as issues: write or checks: write
# added to an existing job would otherwise pass unnoticed.
validate_permissions="$(yq -o=json -I=0 '.jobs.validate.permissions' "${wf}")"
expected_validate_permissions='{"contents":"read","checks":"read"}'
[ "${validate_permissions}" = "${expected_validate_permissions}" ] \
    || fail "jobs.validate.permissions is not exactly ${expected_validate_permissions} (got: ${validate_permissions})"

tag_permissions="$(yq -o=json -I=0 '.jobs.tag.permissions' "${wf}")"
expected_tag_permissions='{"contents":"write"}'
[ "${tag_permissions}" = "${expected_tag_permissions}" ] \
    || fail "jobs.tag.permissions is not exactly ${expected_tag_permissions} (got: ${tag_permissions})"

summary_permissions="$(yq -o=json -I=0 '.jobs["dry-run-summary"].permissions' "${wf}")"
[ "${summary_permissions}" = "{}" ] \
    || fail "jobs.\"dry-run-summary\".permissions is not exactly {} (got: ${summary_permissions})"

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

expected_new_line='gh release create "${RELEASE_TAG}" --verify-tag --notes-file /tmp/release-notes.md --title "${RELEASE_TAG}" --latest=true'
expected_recovery_line='gh release create "${RELEASE_TAG}" --verify-tag --notes-file /tmp/release-notes.md --title "${RELEASE_TAG}" --latest=false'

# --verify-tag and --latest are checked against the exact, whitespace-trimmed line invoking
# `gh release create`, requiring exactly one match per named step — a substring-anywhere check
# would still pass if the real command were stripped of a flag but a decoy comment or echo kept
# mentioning it; a comment line can never equal the bare command below, since it must start with
# "#".
check_release_create_line() {
    local step_name="$1" expected_line="$2" run line trimmed matches=0

    run="$(yq ".jobs.tag.steps[] | select(.name == \"${step_name}\") | .run" "${wf}")"
    [ -n "${run}" ] || fail "step \"${step_name}\" not found in jobs.tag.steps"

    while IFS= read -r line; do
        trimmed="$(printf '%s' "${line}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        if [ "${trimmed}" = "${expected_line}" ]; then
            matches=$((matches + 1))
        fi
    done <<<"${run}"

    [ "${matches}" = "1" ] \
        || fail "step \"${step_name}\" does not contain exactly one line matching: ${expected_line} (found ${matches})"
}

check_release_create_line "Create the GitHub release (new)" "${expected_new_line}"
check_release_create_line "Create the GitHub release if missing (recovery)" "${expected_recovery_line}"

# Global check across every job and step, not just the two named ones above: enumerates every
# real `gh release create` invocation line in the whole workflow and requires the total to be
# exactly 2, matching exactly the two expected commands — this is what actually rules out a
# second, unguarded invocation added next to a correctly-flagged one in the same or another step.
invocation_lines="$(yq '.jobs[].steps[].run // ""' "${wf}" | grep -E '^[[:space:]]*gh release create ' || true)"

total_count=0
new_count=0
recovery_count=0
while IFS= read -r line; do
    [ -n "${line}" ] || continue

    trimmed="$(printf '%s' "${line}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    total_count=$((total_count + 1))
    case "${trimmed}" in
        "${expected_new_line}") new_count=$((new_count + 1)) ;;
        "${expected_recovery_line}") recovery_count=$((recovery_count + 1)) ;;
        *) fail "unexpected 'gh release create' invocation anywhere in the workflow: ${trimmed}" ;;
    esac
done <<<"${invocation_lines}"

[ "${total_count}" = "2" ] \
    || fail "expected exactly 2 'gh release create' invocations in the whole workflow, found ${total_count}"
[ "${new_count}" = "1" ] \
    || fail "expected exactly 1 invocation matching the new-release command, found ${new_count}"
[ "${recovery_count}" = "1" ] \
    || fail "expected exactly 1 invocation matching the recovery command, found ${recovery_count}"

echo "tag-release-workflow-check: OK — validate/tag/dry-run-summary permissions match exactly, tag holds contents: write behind environment: tag and needs: validate, trigger is exactly workflow_dispatch, if: conditions match exactly, exactly 2 'gh release create' invocations in the whole workflow match the two expected commands"
