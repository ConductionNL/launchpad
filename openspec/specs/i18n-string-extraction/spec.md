---
status: reviewed
---

# i18n-string-extraction Specification

## Purpose
Defines the rules for identifying and wrapping all translatable strings — both in Vue components (frontend) and PHP controllers/services (backend) — with the appropriate translation functions.

## ADDED Requirements

### Requirement: wrap-user-facing-strings
All user-facing strings in Vue templates MUST be wrapped with the `t()` translation function using the app's ID as the first argument.

#### Scenario: Static text in template
- GIVEN a Vue component with a hardcoded string like `<h1>Dashboard</h1>`
- WHEN the i18n extraction is applied
- THEN it SHALL become `<h1>{{ t('appid', 'Dashboard') }}</h1>`

#### Scenario: Attribute text
- GIVEN a Vue component with a translatable attribute like `placeholder="Search..."`
- WHEN the i18n extraction is applied
- THEN it SHALL become `:placeholder="t('appid', 'Search...')"`

#### Scenario: Aria labels
- GIVEN a Vue component with `aria-label="Close dialog"`
- WHEN the i18n extraction is applied
- THEN it SHALL become `:aria-label="t('appid', 'Close dialog')"`

#### Scenario: Toast and notification messages
- GIVEN a Vue component calling `showSuccess('Item saved')` or `showError('Failed to load')`
- WHEN the i18n extraction is applied
- THEN it SHALL become `showSuccess(t('appid', 'Item saved'))` or `showError(t('appid', 'Failed to load'))`

### Requirement: wrap-plural-strings
Strings that vary based on a count MUST use the `n()` plural function.

#### Scenario: Plural string
- GIVEN a string that shows a count like `"1 item" / "5 items"`
- WHEN the i18n extraction is applied
- THEN it SHALL use `n('appid', '%n item', '%n items', count)`

### Requirement: wrap-backend-messages
All user-facing messages in PHP controller and service responses MUST be wrapped with `$this->l10n->t()` or `$this->l10n->n()`.

#### Scenario: Success response message
- GIVEN a PHP controller returning `new JSONResponse(['message' => 'Item deleted successfully'])`
- WHEN the i18n extraction is applied
- THEN it SHALL become `new JSONResponse(['message' => $this->l10n->t('Item deleted successfully')])`

#### Scenario: Error response message
- GIVEN a PHP controller returning `new JSONResponse(['error' => 'Failed to save'], 500)`
- WHEN the i18n extraction is applied
- THEN it SHALL become `new JSONResponse(['error' => $this->l10n->t('Failed to save')], 500)`

#### Scenario: Exception messages passed to response
- GIVEN a controller catching an exception and returning `$e->getMessage()` to the user
- WHEN the i18n extraction is applied
- THEN the catch block SHALL wrap the user-facing message: `$this->l10n->t('An error occurred: %s', [$e->getMessage()])`
- AND the original exception message (typically English from PHP) SHALL be preserved in logs

#### Scenario: Internal exceptions not translated
- GIVEN an exception thrown for internal error handling (not shown to users)
- WHEN the i18n extraction is applied
- THEN internal exception messages SHALL NOT be wrapped with `$l->t()`

### Requirement: correct-app-id
The first argument to frontend `t()` and `n()` MUST be the exact Nextcloud app ID as registered in `appinfo/info.xml`.

#### Scenario: App ID matches info.xml
- GIVEN an app with `<id>openregister</id>` in `appinfo/info.xml`
- WHEN `t()` is called in that app's components
- THEN the first argument SHALL be `'openregister'`

#### Scenario: Backend does not need app ID argument
- GIVEN a PHP controller using `$this->l10n->t('string')`
- WHEN the translation is resolved
- THEN Nextcloud SHALL automatically scope to the app's own `l10n/` directory based on the DI context

### Requirement: non-translatable-exclusions
Certain strings MUST NOT be wrapped with translation functions.

#### Scenario: Technical identifiers excluded
- GIVEN strings that are technical identifiers (CSS classes, API paths, event names, prop names, log messages)
- WHEN the i18n extraction is applied
- THEN these strings SHALL NOT be wrapped with `t()` or `$l->t()`

#### Scenario: Variable-only content excluded
- GIVEN a template expression that only outputs a variable like `{{ item.name }}`
- WHEN the i18n extraction is applied
- THEN it SHALL NOT be wrapped with `t()` (the data itself is not translatable)

#### Scenario: Nextcloud component internal text excluded
- GIVEN Nextcloud components like `<NcButton>`, `<NcDialog>`, `<NcEmptyContent>` that handle their own internal translations
- WHEN the i18n extraction is applied
- THEN only the text passed via props or slots to these components SHALL be translated
- AND the component's own internal UI text SHALL NOT be re-translated

### Requirement: string-parameters
Strings containing dynamic values MUST use parameter substitution.

#### Scenario: Frontend dynamic value
- GIVEN a string like `` `Welcome, ${userName}` ``
- WHEN the i18n extraction is applied
- THEN it SHALL become `t('appid', 'Welcome, {userName}', { userName: userName })`

#### Scenario: Backend dynamic value
- GIVEN a PHP string like `"Failed to delete {$objectName}"`
- WHEN the i18n extraction is applied
- THEN it SHALL become `$this->l10n->t('Failed to delete %s', [$objectName])`

### Requirement: consistent-key-strings
The source string (English) used as the translation key MUST be human-readable and serve as the English translation.

#### Scenario: Readable source strings
- GIVEN a translatable string
- WHEN it is used as a key in `t()` or `$l->t()`
- THEN the key SHALL be the full English text (e.g., `t('app', 'Create new register')`)
- AND it SHALL NOT use technical keys (e.g., NOT `t('app', 'register.create.button')`)

### Requirement: hardcoded-dutch-conversion
Apps with hardcoded Dutch strings (notably zaakafhandelapp) MUST convert these to English source keys with Dutch in the translation file.

#### Scenario: Dutch string converted to English key
- GIVEN a Vue component with hardcoded Dutch text like `<h1>Zaak starten</h1>`
- WHEN the i18n extraction is applied
- THEN it SHALL become `<h1>{{ t('zaakafhandelapp', 'Start case') }}</h1>`
- AND `l10n/nl.json` SHALL contain `"Start case": "Zaak starten"`
