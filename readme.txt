=== Multilingual Forms for Fluent Forms with Polylang ===
Contributors: dhrupo, pyrobd
Tags: fluent forms, polylang, multilingual, translation, fluentform
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add multilingual form support for Fluent Forms using Polylang. Translate form fields, messages, and notification emails from the Polylang strings page.

== Description ==

Multilingual Forms for Fluent Forms with Polylang registers your Fluent Forms field labels, placeholders, help text, validation messages, confirmation messages, and notification emails as Polylang strings. Translate them from **Languages &rarr; Strings translations** and the right language is served automatically on the front end, in AJAX submissions, and in outgoing emails.

The plugin does not patch or fork Fluent Forms. It integrates through Fluent Forms' own extension points: a custom *Polylang Translations* settings tab per form, Fluent Forms' admin AJAX wrapper, the `fluentform/*` filter set for runtime rendering and notifications, and standard WordPress `option_*` filters for global Fluent Forms settings.

= Key features =

* Per-form opt-in toggle inside Fluent Forms' Settings panel
* Registers field labels, placeholders, help text, choices, validation messages, confirmation/restriction messages, and email subjects/bodies as Polylang strings
* Registers global Fluent Forms settings (default validation messages, double opt-in, admin approval, payment instructions) as Polylang strings
* Translates form rendering, submissions, notifications, and confirmation messages based on the current Polylang language
* Sets the right language for reCAPTCHA, hCaptcha, and Turnstile
* Integrates with the Fluent Forms PDF add-on for language-aware PDF generation
* Supports both Polylang (free) and Polylang Pro

= Requirements =

* WordPress 6.0 or newer
* PHP 7.4 or newer (tested up to PHP 8.3)
* Fluent Forms (free) &mdash; required
* Polylang or Polylang Pro &mdash; required

If either Fluent Forms or Polylang is missing or inactive, the plugin shows an admin notice with an install/activate link and does not boot.

== Installation ==

1. Install and activate **Fluent Forms** and **Polylang** (or Polylang Pro).
2. Upload the `multilingual-forms-fluent-forms-polylang` folder to `/wp-content/plugins/`, or install via **Plugins &rarr; Add New**.
3. Activate **Multilingual Forms for Fluent Forms with Polylang** from the Plugins screen.
4. Edit a form in Fluent Forms and open **Settings &rarr; Polylang Translations** to enable translations for that form.
5. Visit **Languages &rarr; Strings translations** to translate the registered strings.

== Frequently Asked Questions ==

= Do I need Polylang Pro? =

No. The free Polylang plugin is enough. Polylang Pro is also supported.

= What strings get translated? =

Field labels, placeholders, help messages, options for select/radio/checkbox fields, HTML/section-break content, validation messages, the form's confirmation/error/restriction messages, notification email subjects and bodies, and global Fluent Forms settings such as default validation messages, double opt-in emails, admin approval emails, and payment instructions.

= Will my existing forms be re-registered automatically? =

Strings are registered when you enable a form or when you save changes to its fields. If you enable an existing form for the first time, its current strings are registered immediately.

= How do I stop translating a form? =

Open **Settings &rarr; Polylang Translations** for that form and click **Reset Polylang Translation**. The per-form strings are removed from Polylang.

= Does this work with WPML? =

No. This plugin targets Polylang specifically. Fluent Forms already ships its own WPML integration.

= Where do translated strings appear in Polylang? =

Per-form strings appear under the group **Fluent Forms &lt;Form Title&gt;**. Global Fluent Forms settings appear under **Fluent Forms Global Settings**.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
