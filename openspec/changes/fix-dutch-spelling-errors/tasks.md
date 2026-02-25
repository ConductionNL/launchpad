# Tasks: fix-dutch-spelling-errors

## 1. Setup

### Task 1.1: Create feature branch
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-changes-must-be-isolated-in-a-feature-branch`
- **files**: N/A (git operation)
- **acceptance_criteria**:
  - GIVEN the current working branch WHEN a new branch is created THEN it MUST be named `feature/fix-dutch-spelling`
- [x] Create branch `feature/fix-dutch-spelling` from current working branch

## 2. Constants Files

### Task 2.1: Audit and fix labels.constants.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-constants-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/constants/labels.constants.js`
- **acceptance_criteria**:
  - GIVEN the labels file WHEN every Dutch string value is reviewed THEN all spelling errors MUST be fixed
- [x] Read and audit all Dutch string values
- [x] Fix any spelling or grammar errors (fixed: "Zoekresulten" → "Zoekresultaten")
- [x] Verify object keys are unchanged

### Task 2.2: Audit and fix titles.constants.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-constants-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/constants/titles.constants.js`
- **acceptance_criteria**:
  - GIVEN the titles file WHEN every Dutch string value is reviewed THEN all spelling errors MUST be fixed AND capitalization MUST be consistent
- [x] Read and audit all Dutch string values
- [x] Fix any spelling or grammar errors (no errors found)
- [x] Verify consistent capitalization

### Task 2.3: Audit and fix breadcrumbs.constants.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-constants-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/constants/breadcrumbs.constants.js`
- **acceptance_criteria**:
  - GIVEN the breadcrumbs file WHEN every Dutch string value is reviewed THEN all spelling errors MUST be fixed
- [x] Read and audit all Dutch string values
- [x] Fix any spelling or grammar errors (no errors found)

### Task 2.4: Audit and fix messages.constants.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-constants-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/constants/messages.constants.js`
- **acceptance_criteria**:
  - GIVEN the messages file WHEN every Dutch string value is reviewed THEN all spelling and grammar errors MUST be fixed
- [x] Read and audit all Dutch string values
- [x] Fix any spelling or grammar errors (no errors found)

### Task 2.5: Audit and fix toasters.constants.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-constants-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/constants/toasters.constants.js`
- **acceptance_criteria**:
  - GIVEN the toasters file WHEN every Dutch string value is reviewed THEN all spelling errors MUST be fixed
- [x] Read and audit all Dutch string values (no Dutch user-facing text)
- [x] Fix any spelling or grammar errors (N/A)

### Task 2.6: Audit and fix roles.constants.js and risks.constants.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-constants-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/constants/roles.constants.js`, `tilburg-woo-ui/src/constants/risks.constants.js`
- **acceptance_criteria**:
  - GIVEN the roles and risks files WHEN every Dutch string value is reviewed THEN all spelling errors MUST be fixed
- [x] Read and audit all Dutch string values in both files
- [x] Fix any spelling or grammar errors (no errors found)

### Task 2.7: Commit constants fixes
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-changes-must-be-isolated-in-a-feature-branch`
- **files**: `tilburg-woo-ui/src/constants/*.constants.js`
- **acceptance_criteria**:
  - GIVEN all constants files are audited WHEN changes are committed THEN the commit MUST be on the feature branch
- [ ] Commit all constants file changes with descriptive message

## 3. i18n Translation Files

### Task 3.1: Audit and fix translations.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-i18n-translation-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/assets/locales/translations.js`
- **acceptance_criteria**:
  - GIVEN the master translations file WHEN every Dutch string is reviewed THEN all spelling errors MUST be fixed AND interpolation placeholders MUST NOT be altered
- [ ] Read and audit all Dutch translation values
- [ ] Fix any spelling or grammar errors
- [ ] Verify interpolation placeholders are intact

### Task 3.2: Audit and fix nl/translation.js
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-i18n-translation-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/assets/locales/nl/translation.js`
- **acceptance_criteria**:
  - GIVEN the Dutch locale file WHEN every string is reviewed THEN all spelling errors MUST be fixed
- [ ] Read and audit all Dutch string values
- [ ] Fix any spelling or grammar errors

### Task 3.3: Commit i18n fixes
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-changes-must-be-isolated-in-a-feature-branch`
- **files**: `tilburg-woo-ui/src/assets/locales/**`
- **acceptance_criteria**:
  - GIVEN all i18n files are audited WHEN changes are committed THEN the commit MUST be on the feature branch
- [ ] Commit all i18n file changes with descriptive message

## 4. Form Utility Text Files

### Task 4.1: Audit and fix form texts.utils.js files
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-form-utility-text-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/views/ac-forms/ac-forms-applicatie/utils/texts.utils.js`, `tilburg-woo-ui/src/views/ac-forms/ac-forms-product/utils/texts.utils.js`
- **acceptance_criteria**:
  - GIVEN form text utility files WHEN getPageTitle() and getPageDescription() return values are reviewed THEN all spelling errors MUST be fixed
- [ ] Read and audit all Dutch strings in both texts.utils.js files
- [ ] Fix any spelling or grammar errors

### Task 4.2: Audit and fix form steps.utils.js files
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-form-utility-text-files-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/views/ac-forms/ac-forms-applicatie/utils/steps.utils.js`, `tilburg-woo-ui/src/views/ac-forms/ac-forms-product/utils/steps.utils.js`
- **acceptance_criteria**:
  - GIVEN form step utility files WHEN each step label is reviewed THEN all spelling errors MUST be fixed
- [ ] Read and audit all Dutch step labels in both steps.utils.js files
- [ ] Fix any spelling or grammar errors

### Task 4.3: Commit form utility fixes
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-changes-must-be-isolated-in-a-feature-branch`
- **files**: `tilburg-woo-ui/src/views/ac-forms/*/utils/*.utils.js`
- **acceptance_criteria**:
  - GIVEN all form utility files are audited WHEN changes are committed THEN the commit MUST be on the feature branch
- [ ] Commit all form utility file changes with descriptive message

## 5. Inline Component Strings

### Task 5.1: Audit and fix inline Dutch strings in views
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-inline-component-strings-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/views/**/*.js`
- **acceptance_criteria**:
  - GIVEN view files with inline Dutch strings WHEN error messages, status labels, and user-facing text are reviewed THEN all spelling errors MUST be fixed
- [ ] Search for inline Dutch strings in view files (error messages, status labels, tooltips)
- [ ] Fix any spelling or grammar errors
- [ ] Verify no object keys or interpolation patterns are changed

### Task 5.2: Audit and fix inline Dutch strings in components
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-inline-component-strings-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/components/**/*.js`
- **acceptance_criteria**:
  - GIVEN component files with inline Dutch strings WHEN user-facing text is reviewed THEN all spelling errors MUST be fixed
- [ ] Search for inline Dutch strings in component files
- [ ] Fix any spelling or grammar errors

### Task 5.3: Audit and fix aria-labels and accessibility text
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-inline-component-strings-shall-contain-correctly-spelled-dutch-text`
- **files**: `tilburg-woo-ui/src/views/**/*.js`, `tilburg-woo-ui/src/components/**/*.js`
- **acceptance_criteria**:
  - GIVEN files with Dutch aria-label, title, or placeholder attributes WHEN these strings are reviewed THEN all spelling errors MUST be fixed
- [ ] Search for Dutch aria-label, title, and placeholder attributes
- [ ] Fix any spelling or grammar errors

### Task 5.4: Commit inline string fixes
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-changes-must-be-isolated-in-a-feature-branch`
- **files**: `tilburg-woo-ui/src/views/**/*.js`, `tilburg-woo-ui/src/components/**/*.js`
- **acceptance_criteria**:
  - GIVEN all inline strings are audited WHEN changes are committed THEN the commit MUST be on the feature branch
- [ ] Commit all inline string changes with descriptive message

## 6. Verification

### Task 6.1: Build verification
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-text-corrections-must-not-alter-functionality`
- **files**: N/A (build step)
- **acceptance_criteria**:
  - GIVEN all spelling corrections are applied WHEN `yarn build:web` is run THEN the build MUST succeed without errors
- [ ] Run `yarn build:web` in the Docker container
- [ ] Verify build completes without errors

### Task 6.2: Visual spot check
- **spec_ref**: `specs/dutch-text-correctness/spec.md#requirement-text-corrections-must-not-alter-functionality`
- **files**: N/A (manual test)
- **acceptance_criteria**:
  - GIVEN the build is successful WHEN key pages are loaded in the browser THEN corrected text MUST display properly
- [ ] Load homepage and verify text renders correctly
- [ ] Load a CMS page and verify text renders correctly
- [ ] Verify no broken interpolation or missing text
