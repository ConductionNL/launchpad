# i18n-dutch-translations Specification

## Purpose
Defines the requirements for Dutch (nl) translations across all Conduction Nextcloud apps, ensuring consistent, accurate, and complete translations for Dutch-speaking users — covering both frontend and backend strings.

## ADDED Requirements

### Requirement: complete-dutch-translations
Every string wrapped with `t()` (frontend) or `$l->t()` (backend) in an app MUST have a corresponding Dutch translation in `l10n/nl.json`.

#### Scenario: All keys translated
- GIVEN an app's `l10n/en.json` with N translation keys
- WHEN `l10n/nl.json` is checked
- THEN it SHALL contain exactly N translation keys
- AND every key in `en.json` SHALL have a corresponding entry in `nl.json`

#### Scenario: No empty translations
- GIVEN the `l10n/nl.json` file
- WHEN the translations are inspected
- THEN no translation value SHALL be an empty string

#### Scenario: Backend error messages translated
- GIVEN a PHP controller returning `$this->l10n->t('Failed to save')`
- WHEN `l10n/nl.json` is checked
- THEN it SHALL contain `"Failed to save": "Opslaan mislukt"`

### Requirement: english-source-file
The `l10n/en.json` file MUST contain all translatable strings with key and value being identical (the English source string).

#### Scenario: English file is identity mapping
- GIVEN `l10n/en.json`
- WHEN a translation entry is read
- THEN the key and value SHALL be identical (e.g., `"Dashboard": "Dashboard"`)

#### Scenario: English file matches code usage
- GIVEN all `t('appid', 'string')` and `$l->t('string')` calls in the app's source code
- WHEN `l10n/en.json` is checked
- THEN every unique string argument SHALL have a corresponding key in `en.json`

### Requirement: consistent-terminology
Common terms that appear across multiple apps MUST be translated consistently.

#### Scenario: Shared UI terms
- GIVEN common UI terms used across apps
- WHEN these terms appear in different apps' `nl.json` files
- THEN they SHALL use the same Dutch translations:
  - "Save" → "Opslaan"
  - "Cancel" → "Annuleren"
  - "Delete" → "Verwijderen"
  - "Search" → "Zoeken"
  - "Loading" → "Laden"
  - "Error" → "Fout"
  - "Settings" → "Instellingen"
  - "Create" → "Aanmaken"
  - "Edit" → "Bewerken"
  - "Close" → "Sluiten"
  - "Back" → "Terug"
  - "Next" → "Volgende"
  - "Name" → "Naam"
  - "Description" → "Beschrijving"
  - "Actions" → "Acties"
  - "Status" → "Status"
  - "Type" → "Type"
  - "Refresh" → "Vernieuwen"
  - "Export" → "Exporteren"
  - "Import" → "Importeren"
  - "Upload" → "Uploaden"
  - "Download" → "Downloaden"

#### Scenario: Domain-specific terms
- GIVEN domain-specific terms used across apps
- WHEN these terms appear in different apps' `nl.json` files
- THEN they SHALL use consistent Dutch translations:
  - "Register" → "Register"
  - "Schema" → "Schema"
  - "Object" → "Object"
  - "Connector" → "Connector"
  - "Source" → "Bron"
  - "Mapping" → "Mapping"
  - "Endpoint" → "Endpoint"
  - "Synchronization" → "Synchronisatie"
  - "Publication" → "Publicatie"
  - "Catalog" → "Catalogus"
  - "Case" → "Zaak"
  - "Task" → "Taak"
  - "Document" → "Document"
  - "Decision" → "Besluit"
  - "Lead" → "Lead"
  - "Pipeline" → "Pipeline"
  - "Dashboard" → "Dashboard"

#### Scenario: Backend error message terms
- GIVEN common backend error patterns across apps
- WHEN these appear in different apps' `nl.json` files
- THEN they SHALL use consistent Dutch translations:
  - "Failed to save" → "Opslaan mislukt"
  - "Failed to delete" → "Verwijderen mislukt"
  - "Not found" → "Niet gevonden"
  - "Successfully created" → "Succesvol aangemaakt"
  - "Successfully updated" → "Succesvol bijgewerkt"
  - "Successfully deleted" → "Succesvol verwijderd"
  - "An error occurred" → "Er is een fout opgetreden"

### Requirement: dutch-language-quality
Dutch translations MUST follow standard Dutch language conventions.

#### Scenario: Formal register
- GIVEN a Dutch translation
- WHEN the tone is assessed
- THEN it SHALL use formal Dutch ("u" form, not "je/jij")
- AND it SHALL follow the conventions used in Dutch government digital services

#### Scenario: Correct grammar
- GIVEN a Dutch translation
- WHEN the grammar is checked
- THEN it SHALL use correct Dutch grammar, spelling, and punctuation
- AND it SHALL follow the "Woordenlijst Nederlandse Taal" (Green Booklet) conventions

#### Scenario: No English left untranslated
- GIVEN the `l10n/nl.json` file
- WHEN translation values are inspected
- THEN no value SHALL be identical to its English key unless the term is a proper noun or an accepted Dutch loanword (e.g., "API", "JSON", "Schema", "Dashboard", "Pipeline", "Lead")

### Requirement: plural-translations
Dutch plural forms MUST be provided for all strings using `n()` or `$l->n()`.

#### Scenario: Plural forms present
- GIVEN a string using `n('appid', '%n item', '%n items', count)` or `$l->n('%n item', '%n items', count)`
- WHEN `l10n/nl.json` is checked
- THEN it SHALL contain translations for both singular and plural forms
