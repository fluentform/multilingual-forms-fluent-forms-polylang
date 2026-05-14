## 1. Planning and Baseline

- [x] 1.1 Inspect WPML sibling controller behavior and current Polylang add-on skeleton.
- [x] 1.2 Check Polylang native string API docs and local plugin implementation.
- [x] 1.3 Create OpenSpec proposal, design, spec, and tasks for Polylang form translations.

## 2. Bootable First Slice

- [x] 2.1 Add missing Polylang controller classes so the plugin can boot.
- [x] 2.2 Implement global Fluent Forms option string registration and translation with `pll_register_string()` and `pll__()`.
- [x] 2.3 Add a minimal form-level settings controller scaffold and safe Polylang helpers.
- [x] 2.4 Run PHP syntax checks and OpenSpec validation.

## 3. Per-Form Translation Parity

- [x] 3.1 Add form settings AJAX actions for enabling, disabling, and reading the Polylang translation flag.
- [x] 3.2 Register form fields, submit button, step wrappers, and form settings strings into stable Polylang groups.
- [x] 3.3 Refresh strings when enabled form fields are updated.
- [x] 3.4 Add remove/cleanup behavior that is safe for Polylang's native string storage.

## 4. Runtime Translation Parity

- [x] 4.1 Port core runtime message filters for confirmation, validation, scheduling, login, restriction, captcha, and spam messages.
- [x] 4.2 Port notification/email/feed translation paths in Polylang terms.
- [x] 4.3 Port Pro-sensitive message hooks only where they are present and safe in Free-only installs.
- [x] 4.4 Carry over the WPML double-opt-in object-context fix for Polylang runtime email localization.

## 5. Language Context and Verification

- [x] 5.1 Add language propagation for AJAX URLs and request restoration.
- [x] 5.2 Add PDF and entry-confirmation language helpers where Polylang exposes equivalent APIs.
- [x] 5.3 Verify activation with Fluent Forms active and Polylang active.
- [x] 5.4 Compare top WPML routes/hooks against Polylang coverage and document any intentional gaps.

## 6. Maintainability Follow-Up

- [x] 6.1 Compare the parent Contact Form 7 Polylang plugin structure before refactoring.
- [x] 6.2 Split the large form settings controller into admin settings, runtime translation, and shared form translation service classes.
- [x] 6.3 Keep hook registrations and public behavior stable after the split.
