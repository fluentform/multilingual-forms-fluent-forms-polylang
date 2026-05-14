## Why

The Polylang add-on currently advertises Fluent Forms multilingual support but is incomplete: the main plugin file includes controller classes that do not exist. The WPML sibling add-on already covers a broad Fluent Forms translation surface, and Polylang's documented string API can support a native, smaller adapter for the same user-facing strings.

## What Changes

- Add missing Polylang controllers so the add-on can boot when Fluent Forms and Polylang are active.
- Register global Fluent Forms option strings with `pll_register_string()` and translate them with `pll__()`.
- Add a per-form translation path adapted from the WPML add-on, using native Polylang string groups instead of WPML string packages.
- Preserve language-aware frontend/AJAX/PDF behavior where Polylang exposes equivalent APIs.
- Include the known WPML double-opt-in runtime translation lesson so Polylang does not repeat the object-vs-array context bug.
- Keep unsupported WPML-only package cleanup behavior out of the first implementation.

## Capabilities

### New Capabilities

- `polylang-form-translations`: Registers and resolves Fluent Forms strings through Polylang for global settings, per-form fields, form settings, runtime messages, notifications, and language-aware URLs.

### Modified Capabilities

- None.

## Impact

- Affected code: `multilingual-forms-for-fluent-forms-with-polylang.php`, `src/Controllers/GlobalSettingsController.php`, `src/Controllers/SettingsController.php`.
- Dependencies: Fluent Forms, Polylang or Polylang Pro, and Polylang native functions `pll_register_string`, `pll__`, `pll_translate_string`, `pll_current_language`, and `pll_languages_list` when available.
- Admin UI: Fluent Forms form settings menu will gain Polylang translation controls in a later slice.
- Runtime: translated Fluent Forms strings are resolved in the current Polylang language while preserving original strings when Polylang cannot resolve a translation.
