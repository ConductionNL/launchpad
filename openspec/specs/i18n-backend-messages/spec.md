---
status: reviewed
---

# i18n-backend-messages Specification

## Purpose
Defines the requirements for translating user-facing messages in PHP backend controllers and services using Nextcloud's `IL10N` interface, so that API responses are locale-aware.

## ADDED Requirements

### Requirement: il10n-injection
Each PHP controller that returns user-facing messages MUST have `OCP\IL10N` injected via constructor dependency injection.

#### Scenario: Controller constructor includes IL10N
- GIVEN a PHP controller class that returns translated messages
- WHEN the constructor is inspected
- THEN it SHALL accept `IL10N $l10n` as a parameter
- AND it SHALL store it as `$this->l10n`

#### Scenario: IL10N resolved automatically by Nextcloud
- GIVEN a controller with `IL10N $l10n` in its constructor
- WHEN Nextcloud's DI container instantiates the controller
- THEN it SHALL automatically inject the IL10N instance scoped to the app's `l10n/` directory and the current user's locale

#### Scenario: Service classes with user-facing messages
- GIVEN a service class that generates messages returned to users (via controller responses)
- WHEN the service is inspected
- THEN it SHALL also have `IL10N $l10n` injected if it constructs user-facing message strings

### Requirement: translate-response-messages
All hardcoded English strings in JSONResponse data that are shown to users MUST be wrapped with `$this->l10n->t()`.

#### Scenario: Success message translated
- GIVEN a controller returning `new JSONResponse(['message' => 'Item saved successfully'])`
- WHEN the i18n change is applied
- THEN it SHALL become `new JSONResponse(['message' => $this->l10n->t('Item saved successfully')])`

#### Scenario: Error message translated
- GIVEN a controller returning `new JSONResponse(['error' => 'Not Found'], 404)`
- WHEN the i18n change is applied
- THEN it SHALL become `new JSONResponse(['error' => $this->l10n->t('Not Found')], 404)`

#### Scenario: Message with parameters
- GIVEN a controller returning `"Failed to delete $name"`
- WHEN the i18n change is applied
- THEN it SHALL become `$this->l10n->t('Failed to delete %s', [$name])`

### Requirement: exception-message-handling
Exception messages caught and returned to users MUST be translated. Internal exception messages for logging MUST NOT be translated.

#### Scenario: Exception returned to user
- GIVEN a catch block that returns `$e->getMessage()` in a JSONResponse
- WHEN the i18n change is applied
- THEN the response SHALL use a translated generic message: `$this->l10n->t('An error occurred')`
- AND the original `$e->getMessage()` SHALL be logged (untranslated) for debugging

#### Scenario: Internal exception preserved
- GIVEN an exception thrown within service internals (not returned to user)
- WHEN the i18n change is applied
- THEN the exception message SHALL remain in English and NOT be wrapped with `$l->t()`

### Requirement: do-not-translate-internals
Certain backend strings MUST NOT be translated.

#### Scenario: Log messages
- GIVEN a `$this->logger->error('Something failed')` call
- WHEN the i18n change is applied
- THEN log messages SHALL remain in English for debugging consistency

#### Scenario: Exception classes
- GIVEN `throw new NotFoundException('Resource not found')`
- WHEN the i18n change is applied
- THEN internal exception messages SHALL remain in English
- AND only the user-facing response derived from this exception SHALL be translated

#### Scenario: Database values
- GIVEN a string stored in or retrieved from the database
- WHEN the i18n change is applied
- THEN database content SHALL NOT be translated (data is language-neutral)

#### Scenario: API endpoint paths and technical identifiers
- GIVEN route definitions, config keys, or technical identifiers
- WHEN the i18n change is applied
- THEN these SHALL NOT be translated

### Requirement: plural-backend-messages
Backend messages that vary based on count MUST use `$this->l10n->n()`.

#### Scenario: Plural response message
- GIVEN a controller returning a count-dependent message like `"Deleted 1 item"` / `"Deleted 5 items"`
- WHEN the i18n change is applied
- THEN it SHALL use `$this->l10n->n('Deleted %n item', 'Deleted %n items', $count)`

## Affected Apps

All apps with PHP controllers returning user-facing messages: `openregister`, `opencatalogi`, `openconnector`, `docudesk`, `mydash`, `softwarecatalog`, `zaakafhandelapp`, `procest`, `pipelinq`.

Currently NO app uses `$l->t()` in controllers. Only `openregister` and `opencatalogi` use it in Dashboard widget classes.
