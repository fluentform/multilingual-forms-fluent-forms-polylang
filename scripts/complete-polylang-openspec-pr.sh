#!/usr/bin/env bash

set -euo pipefail

CHANGE_ID="${CHANGE_ID:-add-polylang-form-translations}"
BASE_BRANCH="${BASE_BRANCH:-main}"
BRANCH_NAME="${BRANCH_NAME:-feat/add-polylang-form-translations}"
COMMIT_SUBJECT="${COMMIT_SUBJECT:-FEAT: add Polylang form translations}"
PR_TITLE="${PR_TITLE:-FEAT: add Polylang form translations}"
MERGE_METHOD="${MERGE_METHOD:-squash}"
WAIT_SECONDS="${WAIT_SECONDS:-0}"
POLL_SECONDS="${POLL_SECONDS:-15}"
RUN_COMMAND="${RUN_COMMAND:-}"
MERGE_PR="${MERGE_PR:-1}"
DELETE_BRANCH="${DELETE_BRANCH:-1}"
AUTO_CODEX="${AUTO_CODEX:-0}"
CODEX_MODEL="${CODEX_MODEL:-}"
CODEX_PROMPT="${CODEX_PROMPT:-}"

# Automation modes:
# - AUTO_CODEX=1 runs `codex exec` to complete unchecked OpenSpec tasks first.
# - RUN_COMMAND="..." runs any custom non-interactive implementation command first.
# After either mode, this script validates, requires all tasks checked, commits,
# pushes, creates a PR, and merges unless MERGE_PR=0.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TASKS_FILE="$ROOT_DIR/openspec/changes/$CHANGE_ID/tasks.md"
PR_BODY_FILE="$(mktemp)"

cleanup() {
    rm -f "$PR_BODY_FILE"
}
trap cleanup EXIT

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

run() {
    printf '+ %s\n' "$*"
    "$@"
}

ensure_command() {
    command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

unchecked_tasks() {
    grep -nE '^- \[ \]' "$TASKS_FILE" || true
}

ensure_clean_coauthor_state() {
    if git log -1 --pretty=%B 2>/dev/null | grep -qi '^Co-authored-by:'; then
        die "Latest commit already has a Co-authored-by trailer. Amend it before publishing."
    fi
}

run_codex_task_completion() {
    ensure_command codex

    local model_args=()
    if [[ -n "$CODEX_MODEL" ]]; then
        model_args=(--model "$CODEX_MODEL")
    fi

    local prompt="$CODEX_PROMPT"
    if [[ -z "$prompt" ]]; then
        prompt="$(cat <<'PROMPT'
Complete the unchecked tasks in openspec/changes/add-polylang-form-translations/tasks.md.

Use the WPML sibling plugin at ../multilingual-forms-fluent-forms-wpml and the Contact Form 7 Polylang precedents at ../multilingual-contact-form-7-with-polylang and ../cf7-polylang.

Requirements:
- Implement the remaining Polylang Fluent Forms integration tasks in this repository.
- Keep changes scoped to this plugin.
- Use native Polylang APIs such as pll_register_string, pll__, pll_translate_string, pll_current_language, and pll_languages_list where available.
- Preserve original Fluent Forms output when a translation or language context is unavailable.
- Update openspec/changes/add-polylang-form-translations/tasks.md as tasks are completed.
- Run relevant validation commands before finishing.
- Do not commit, push, create a PR, merge, or add co-author trailers. The shell script will handle publishing after validation.
PROMPT
)"
    fi

    if [[ ${#model_args[@]} -gt 0 ]]; then
        run codex exec \
            --cd "$ROOT_DIR" \
            --sandbox workspace-write \
            "${model_args[@]}" \
            "$prompt"
    else
        run codex exec \
            --cd "$ROOT_DIR" \
            --sandbox workspace-write \
            "$prompt"
    fi
}

write_pr_body() {
    cat > "$PR_BODY_FILE" <<'MARKDOWN'
## What does this PR do and why?

Adds Polylang-backed Fluent Forms translation support from the OpenSpec plan, using the WPML add-on and Contact Form 7 Polylang plugins as implementation precedent.

**Related issue:** N/A

## Scope

- [x] Free plugin
- [ ] Pro plugin
- [ ] Both

## Changes

- [x] PHP (backend logic, models, services, hooks)
- [ ] Vue/React (admin UI, block editor)
- [ ] CSS/SCSS (styling)
- [ ] Database (migrations, schema changes)
- [ ] REST API (new or changed endpoints)
- [x] Build/config (Vite, composer, CI)

## How to test

1. Confirm `openspec validate add-polylang-form-translations --strict --no-interactive` passes.
2. Run PHP syntax checks for the main plugin file and controller files.
3. Activate Fluent Forms and Polylang, then activate this add-on.
4. Confirm global Fluent Forms strings register in Polylang string translations and frontend output keeps original text when no translation exists.

## Screenshots

<!-- Remove if no UI changed. -->

## Anything the reviewer should know?

The merge step is non-interactive and should only be run after the OpenSpec checklist is complete.
MARKDOWN
}

wait_for_tasks() {
    local waited=0

    while true; do
        if [[ ! -f "$TASKS_FILE" ]]; then
            die "Tasks file not found: $TASKS_FILE"
        fi

        local remaining
        remaining="$(unchecked_tasks)"

        if [[ -z "$remaining" ]]; then
            return 0
        fi

        if [[ "$WAIT_SECONDS" -le 0 || "$waited" -ge "$WAIT_SECONDS" ]]; then
            printf '%s\n' "$remaining" >&2
            die "OpenSpec tasks are still unchecked. Complete them and mark tasks.md before creating a PR."
        fi

        printf 'Waiting for OpenSpec tasks to complete... (%ss/%ss)\n' "$waited" "$WAIT_SECONDS"
        sleep "$POLL_SECONDS"
        waited=$((waited + POLL_SECONDS))
    done
}

main() {
    cd "$ROOT_DIR"

    ensure_command git
    ensure_command gh
    ensure_command openspec
    ensure_command php

    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "Not inside a git worktree."

    if [[ "$AUTO_CODEX" == "1" ]]; then
        run_codex_task_completion
    elif [[ -n "$RUN_COMMAND" ]]; then
        printf '+ %s\n' "$RUN_COMMAND"
        bash -lc "$RUN_COMMAND"
    fi

    run openspec validate "$CHANGE_ID" --strict --no-interactive

    run php -l multilingual-forms-for-fluent-forms-with-polylang.php
    find src -name '*.php' -print0 | while IFS= read -r -d '' file; do
        run php -l "$file"
    done

    wait_for_tasks

    if [[ "$(git branch --show-current)" != "$BRANCH_NAME" ]]; then
        run git switch -C "$BRANCH_NAME"
    fi

    if git diff --quiet && git diff --cached --quiet; then
        die "No changes to commit."
    fi

    gh auth status >/dev/null

    run git add -- .

    if [[ -f openspec/config.yaml ]]; then
        run git add -f -- openspec/config.yaml
    fi

    if [[ -d "openspec/changes/$CHANGE_ID" ]]; then
        while IFS= read -r -d '' file; do
            run git add -f -- "$file"
        done < <(find "openspec/changes/$CHANGE_ID" -type f -print0)
    fi

    git commit -m "$COMMIT_SUBJECT" -m "$(cat <<'MARKDOWN'
## What does this PR do and why?

Adds Polylang-backed Fluent Forms translation support from the OpenSpec plan, using WPML and Contact Form 7 Polylang precedents.

**Related issue:** N/A

## Scope

- [x] Free plugin
- [ ] Pro plugin
- [ ] Both

## Changes

- [x] PHP (backend logic, models, services, hooks)
- [ ] Vue/React (admin UI, block editor)
- [ ] CSS/SCSS (styling)
- [ ] Database (migrations, schema changes)
- [ ] REST API (new or changed endpoints)
- [x] Build/config (Vite, composer, CI)

## How to test

1. openspec validate add-polylang-form-translations --strict --no-interactive
2. php -l multilingual-forms-for-fluent-forms-with-polylang.php
3. php -l src/Controllers/GlobalSettingsController.php
4. php -l src/Controllers/SettingsController.php

## Screenshots

<!-- Remove if no UI changed. -->

## Anything the reviewer should know?

The OpenSpec checklist is complete before this commit is published.
MARKDOWN
)"

    ensure_clean_coauthor_state

    run git push -u origin "$BRANCH_NAME"

    write_pr_body

    local pr_url
    pr_url="$(gh pr create --base "$BASE_BRANCH" --head "$BRANCH_NAME" --title "$PR_TITLE" --body-file "$PR_BODY_FILE")"
    printf 'Created PR: %s\n' "$pr_url"

    if [[ "$MERGE_PR" == "1" ]]; then
        local delete_flag=()
        if [[ "$DELETE_BRANCH" == "1" ]]; then
            delete_flag=(--delete-branch)
        fi

        case "$MERGE_METHOD" in
            merge|squash|rebase)
                run gh pr merge "$pr_url" "--$MERGE_METHOD" "${delete_flag[@]}"
                ;;
            *)
                die "Unsupported MERGE_METHOD: $MERGE_METHOD"
                ;;
        esac
    fi
}

main "$@"
