## ADDED Requirements

### Requirement: Bootable Polylang Integration

The add-on SHALL boot without fatal errors when Fluent Forms and Polylang are active.

#### Scenario: Required controllers are present

- **WHEN** the plugin handles `fluentform/loaded`
- **THEN** it loads existing controller classes and registers Polylang hooks without missing-file or missing-class errors

#### Scenario: Polylang API is incomplete

- **WHEN** Polylang constants exist but required string functions are unavailable
- **THEN** the add-on SHALL show the dependency notice path instead of calling missing functions

### Requirement: Global Fluent Forms Strings

The add-on SHALL register and translate global Fluent Forms option strings through native Polylang string translation.

#### Scenario: Global options are saved

- **WHEN** a tracked Fluent Forms global option is added or updated
- **THEN** the add-on marks the global strings dirty for re-registration

#### Scenario: Global options are read

- **WHEN** Fluent Forms reads a tracked global option in a translated language
- **THEN** the add-on returns translated string values where Polylang has translations and preserves original values otherwise

### Requirement: Per-Form Strings

The add-on SHALL support enabling Polylang translation per Fluent Forms form and register that form's labels, options, messages, and settings.

#### Scenario: Translation is enabled for a form

- **WHEN** an admin enables Polylang translation for a Fluent Forms form
- **THEN** the add-on registers the form's translatable strings in a stable Polylang group and stores the form-level enabled flag

#### Scenario: Form fields change

- **WHEN** an enabled form's fields or settings are updated
- **THEN** the add-on refreshes registered strings for the updated source values

### Requirement: Runtime Translation

The add-on SHALL translate Fluent Forms runtime messages using the current or explicitly captured Polylang language.

#### Scenario: Frontend form renders

- **WHEN** Fluent Forms renders labels, validation messages, confirmation messages, and frontend message payloads
- **THEN** the add-on resolves registered strings in the current Polylang language

#### Scenario: Double opt-in email is sent

- **WHEN** double opt-in email content is generated after submission
- **THEN** the add-on resolves dedicated double opt-in strings for the submitted form and does not lose context because the stored form is an object

### Requirement: Language-Aware Requests

The add-on SHALL preserve language context for Fluent Forms AJAX, PDF, and entry-confirmation flows when Polylang exposes the needed functions.

#### Scenario: AJAX URL is generated

- **WHEN** Fluent Forms generates an AJAX URL on a translated frontend page
- **THEN** the add-on appends the current Polylang language in a way that can be restored during AJAX handling

#### Scenario: Explicit language is unavailable

- **WHEN** a runtime flow has no valid Polylang language code
- **THEN** the add-on preserves Fluent Forms' original output
