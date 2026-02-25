# Dutch Text Correctness Specification

## Purpose
Ensures all user-facing Dutch text in the tilburg-woo-ui frontend is free of spelling errors, grammatical mistakes, and inconsistencies. This applies to all hardcoded strings that are displayed to end users.

## ADDED Requirements

### Requirement: Constants files SHALL contain correctly spelled Dutch text
All string values in constants files (`labels.constants.js`, `titles.constants.js`, `breadcrumbs.constants.js`, `messages.constants.js`, `toasters.constants.js`, `roles.constants.js`, `risks.constants.js`) MUST be free of Dutch spelling and grammar errors.

#### Scenario: Label constants are correctly spelled
- GIVEN the file `src/constants/labels.constants.js`
- WHEN a reviewer reads each string value in the LABELS object
- THEN every Dutch word MUST be correctly spelled according to standard Dutch (Nederlands) orthography
- AND no grammatical errors SHALL be present in multi-word labels

#### Scenario: Title constants are correctly spelled
- GIVEN the file `src/constants/titles.constants.js`
- WHEN a reviewer reads each string value in the TITLES object
- THEN every Dutch word MUST be correctly spelled
- AND page titles MUST use consistent capitalization

#### Scenario: Breadcrumb constants are correctly spelled
- GIVEN the file `src/constants/breadcrumbs.constants.js`
- WHEN a reviewer reads each string value
- THEN every Dutch word MUST be correctly spelled
- AND navigation labels MUST be consistent with their corresponding page titles

#### Scenario: Message constants are correctly spelled
- GIVEN the file `src/constants/messages.constants.js`
- WHEN a reviewer reads each string value
- THEN every Dutch word MUST be correctly spelled
- AND sentences MUST be grammatically correct

#### Scenario: Toaster constants are correctly spelled
- GIVEN the file `src/constants/toasters.constants.js`
- WHEN a reviewer reads each string value
- THEN every Dutch word MUST be correctly spelled

#### Scenario: Role and risk constants are correctly spelled
- GIVEN the files `src/constants/roles.constants.js` and `src/constants/risks.constants.js`
- WHEN a reviewer reads each string value
- THEN every Dutch word MUST be correctly spelled

### Requirement: i18n translation files SHALL contain correctly spelled Dutch text
All Dutch translation strings in `src/assets/locales/translations.js` and `src/assets/locales/nl/translation.js` MUST be free of spelling and grammar errors.

#### Scenario: Master translations are correctly spelled
- GIVEN the file `src/assets/locales/translations.js`
- WHEN a reviewer reads each Dutch translation value
- THEN every Dutch word MUST be correctly spelled
- AND interpolation placeholders (e.g., `{{variable}}`) MUST NOT be altered

#### Scenario: Dutch locale mapping is correctly spelled
- GIVEN the file `src/assets/locales/nl/translation.js`
- WHEN a reviewer reads each Dutch string value
- THEN every Dutch word MUST be correctly spelled

### Requirement: Form utility text files SHALL contain correctly spelled Dutch text
All Dutch strings in form utility files (`views/ac-forms/*/utils/texts.utils.js`, `steps.utils.js`) MUST be free of spelling and grammar errors.

#### Scenario: Form page titles and descriptions are correctly spelled
- GIVEN any `texts.utils.js` file under `src/views/ac-forms/`
- WHEN a reviewer reads the return values of `getPageTitle()` and `getPageDescription()`
- THEN every Dutch word MUST be correctly spelled
- AND sentences MUST be grammatically correct

#### Scenario: Form step labels are correctly spelled
- GIVEN any `steps.utils.js` file under `src/views/ac-forms/`
- WHEN a reviewer reads each step label string
- THEN every Dutch word MUST be correctly spelled

### Requirement: Inline component strings SHALL contain correctly spelled Dutch text
All Dutch strings hardcoded in component and view files (JSX text, string literals for error messages, status labels, tooltips, placeholders, aria-labels) MUST be free of spelling and grammar errors.

#### Scenario: Error messages in views are correctly spelled
- GIVEN any `.js` file under `src/views/` or `src/components/`
- WHEN a reviewer identifies inline Dutch string literals displayed to users
- THEN every Dutch word MUST be correctly spelled
- AND sentences MUST be grammatically correct

#### Scenario: Aria-labels and accessibility text are correctly spelled
- GIVEN any `.js` file that contains Dutch `aria-label`, `title`, or `placeholder` attributes
- WHEN a reviewer reads these accessibility strings
- THEN every Dutch word MUST be correctly spelled

### Requirement: Text corrections MUST NOT alter functionality
Spelling corrections MUST only change the text content of strings, never the structure, keys, or logic of the code.

#### Scenario: Object keys remain unchanged
- GIVEN a spelling fix is applied to a constants file
- WHEN the fix modifies a string value
- THEN the object key or export name MUST remain identical to the original

#### Scenario: String interpolation is preserved
- GIVEN a string containing interpolation (e.g., `${variable}`, `{variable}`, `{{variable}}`)
- WHEN a spelling fix is applied to the surrounding text
- THEN all interpolation expressions MUST remain unchanged and functional

#### Scenario: Build succeeds after corrections
- GIVEN all spelling corrections have been applied
- WHEN the project is built with `yarn build:web`
- THEN the build MUST succeed without errors

### Requirement: Changes MUST be isolated in a feature branch
All spelling corrections MUST be committed to a dedicated feature branch, not directly to the main working branch.

#### Scenario: Feature branch is used
- GIVEN the developer starts the spelling audit
- WHEN they create the first commit
- THEN it MUST be on a branch named `feature/fix-dutch-spelling` or similar
- AND the branch MUST be created from the current working branch

#### Scenario: Primary branch is unaffected
- GIVEN spelling corrections are in progress
- WHEN a reviewer checks the main working branch
- THEN it MUST NOT contain any uncommitted spelling changes
