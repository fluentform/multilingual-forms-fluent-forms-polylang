## Context

The WPML add-on is the closest working precedent. It uses WPML string packages, `wpml_register_string`, `wpml_translate_string`, and package cleanup hooks. The Polylang docs describe the native path differently: plugins register strings with `pll_register_string($name, $string, $group, $multiline)` and resolve current-language strings with `pll__($string)` or a specific language with `pll_translate_string($string, $lang)`.

The closest Contact Form 7 Polylang precedent in the parent plugin folder uses three relevant patterns: admin-side registration of source strings into Polylang, recursive frontend/mail/message translation through `pll__()`, and explicit locale/language repair for AJAX submissions where Polylang does not naturally know the submitted form language.

## Goals

- Make the Polylang add-on bootable.
- Keep string registration and lookup native to Polylang.
- Preserve Fluent Forms behavior when Polylang is inactive, incomplete, or missing a translation.
- Port WPML coverage in small, testable slices instead of copying the full controller at once.

## Non-Goals

- Do not emulate WPML string package cleanup in the first slice.
- Do not require Polylang Pro-only features.
- Do not change Fluent Forms storage formats.

## Decisions

### Use Native Polylang Strings

Use `pll_register_string()` for registration and `pll__()` for current-language lookup. Use `pll_translate_string()` only when a saved submission, PDF, or email path has an explicit language code.

### Group Names

Use stable groups:

- `Fluent Forms Global Settings`
- `Fluent Forms Form {form_id}: {form_title}`

This maps cleanly to Polylang's Languages > Translations filter and avoids WPML package-only structures.

### Translation Keys

Keep WPML-style deterministic keys for source discovery, but remember that Polylang translates by original string value. Keys are for admin sorting and collision clarity, not lookup identity.

### Runtime Context

Carry the WPML double-opt-in fix forward: when a form object is stored in context, compare it as an object, not through array helpers.

## Risks and Trade-Offs

- Polylang native strings are automatically listed while the registering code is active; deleting unused per-form strings is less direct than WPML package cleanup.
- Translating by original string means changing source text can disconnect existing translations. The implementation must avoid unnecessary source rewrites.
- Some WPML language switching hooks do not have one-to-one Polylang equivalents and need a narrower, evidence-backed port.

## Migration Plan

1. Bootable baseline with global string registration/translation.
2. Per-form toggle and registration of field/settings strings.
3. Runtime message and notification translation.
4. Language propagation for AJAX, email, entry confirmation, and PDF.
5. Cleanup and parity review against WPML sibling coverage.

## Automation

The helper script `scripts/complete-polylang-openspec-pr.sh` validates this change, blocks on unchecked OpenSpec tasks, creates an uppercase-prefixed commit without co-author trailers, opens a PR, and can merge it non-interactively. It intentionally refuses to publish if the OpenSpec checklist is not complete.

## Class Structure

The parent Contact Form 7 Polylang plugin keeps admin string registration separate from frontend string/message/submission translation. The Fluent Forms Polylang implementation follows the same maintainability boundary:

- `FormSettingsController` owns admin AJAX actions, the form settings menu entry, enabled-form metadata, and form-update registration refreshes.
- `RuntimeTranslationController` owns frontend/runtime hooks: rendered form strings, validation/messages, email/feed parsing, AJAX language restoration, double opt-in localization, entry confirmation, and PDF language filters.
- `FormTranslationService` owns shared Polylang API access, form enablement, per-form string registration, recursive translation, form caches, and language extraction/switching helpers.
- `SettingsController` remains a small bootstrap facade so the plugin entrypoint does not need to know every sub-controller.

## WPML Parity Notes

The Polylang controller covers the top WPML runtime surfaces with native string APIs rather than WPML packages:

- Admin form toggle routes: `fluentform_get_polylang_settings`, `fluentform_store_polylang_settings`, and `fluentform_delete_polylang_settings`.
- Per-form registration refresh: `fluentform/form_fields_update`, `fluentform/after_form_delete`, form fields, submit button, step wrapper data, and form meta settings.
- Frontend/runtime translation: `fluentform/rendering_form`, confirmation/restriction/schedule/login messages, validation messages, spam/CAPTCHA messages, JavaScript message payloads, payment/quiz/subscription messages, notification feeds, and email subject/body filters.
- Language context: `fluentform/ajax_url`, AJAX request language restoration, entry-confirmation restoration, double-opt-in context localization, and Fluent Forms PDF language filters.

Intentional gaps:

- Polylang native string storage does not expose the same package cleanup contract as WPML String Translation. Disable/delete flows turn off the form flag and clear runtime caches, but do not delete native Polylang string rows.
- WPML-specific package keys are not copied as runtime lookup dependencies because Polylang translates by source string value. Keys remain stable registration names for the Polylang translation UI.
- Pro-sensitive hooks are registered defensively and translate only when Fluent Forms/Pro emits those filters and the form is enabled, so Free-only installs preserve original output.
