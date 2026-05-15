# Multilingual Forms for Fluent Forms with Polylang

> Translate [Fluent Forms](https://wordpress.org/plugins/fluentform/) using [Polylang](https://wordpress.org/plugins/polylang/) — field labels, validation messages, confirmation messages, and notification emails — all from Polylang's *Strings translations* page.



## Requirements

- WordPress 6.0+
- PHP 7.4+ (tested up to 8.3)
- [Fluent Forms](https://wordpress.org/plugins/fluentform/) (free) — **required**
- [Polylang](https://wordpress.org/plugins/polylang/) or Polylang Pro — **required** (needs `pll_register_string` / `pll__`)

The plugin gates its own boot on both dependencies and emits an admin notice with an install/activate link if either is missing.

## Installation

1. Install and activate **Fluent Forms** and **Polylang** (or Polylang Pro).
2. Clone this repo or upload the `multilingual-forms-fluent-forms-polylang` folder to `wp-content/plugins/`.
3. Activate **Multilingual Forms for Fluent Forms with Polylang** from the Plugins screen.

## Usage

1. Edit a form &rarr; **Settings &rarr; Polylang Translations** &rarr; enable the toggle and save.
2. Open **Languages &rarr; Strings translations** in WP admin. Per-form strings appear under *Fluent Forms &lt;Form Title&gt;*, global FF settings under *Fluent Forms Global Settings*.
3. Translate the strings. The form renders in the visitor's current Polylang language; notifications and confirmations are sent in the language the submission was made in.

To stop translating a form, hit **Reset Polylang Translation** on the same tab.

## Project layout

```
multilingual-forms-for-fluent-forms-with-polylang.php  # bootstrap, dependency gating, admin notices
src/
  Controllers/
    SettingsController.php             # wires up the per-form controllers
    FormSettingsController.php         # form settings tab + AJAX + asset injection
    GlobalSettingsController.php       # global FF option translation
    RuntimeTranslationController.php   # all front-end / submission filters
  Services/
    FormTranslationService.php         # string registration, lookup, language switching
assets/admin/
  ff-polylang-settings.js              # Vue component injected into FF's settings UI
```

## Development notes

- **No build step.** The settings UI is a hand-written Vue 2 render-function component that piggybacks on Fluent Forms' already-registered Vue runtime + Element UI. Edit `assets/admin/ff-polylang-settings.js` directly.
- **Asset injection.** When Fluent Forms' `fluentform_form_settings` script handle is registered, the JS is appended via `wp_add_inline_script(..., 'before')` so it lives inside the host bundle. Otherwise it falls back to a standalone enqueue.
- **Global strings sync.** `GlobalSettingsController` keeps a schema version + dirty flag (`_mfffpll_global_strings_schema_version`, `_mfffpll_global_strings_dirty`) so global strings are re-registered only when a tracked option changes.
- **Language resolution.** AJAX submissions carry the language via a request key (see `FormTranslationService::REQUEST_LANGUAGE_KEY`), set by the `fluentform/ajax_url` filter so Polylang's URL-based detection survives the round trip.

## FAQ

**Do I need Polylang Pro?** No. Both free Polylang and Polylang Pro work.

**Does this conflict with Fluent Forms' WPML integration?** No — this plugin only registers Polylang hooks. The two integrations don't overlap, but you should pick one translation system per site.

**My existing form already has strings — will enabling translation re-scan them?** Yes. Enabling a form registers its current strings immediately, and saving form changes refreshes them.

## Contributing

Issues and PRs welcome at <https://github.com/nkb-bd/multilingual-forms-fluent-forms-polylang>.

## License

GPLv2 or later. See the plugin header for the full notice.
