# Design: fix-dutch-spelling-errors

## Architecture Overview
This is a text-only change with no architectural impact. Dutch user-facing strings in the `tilburg-woo-ui` frontend will be audited and corrected in-place. No new components, services, APIs, or data model changes are involved.

All work is performed on a dedicated feature branch (`feature/fix-dutch-spelling`) to isolate changes from the primary codebase.

## API Design
No API changes. This change is purely frontend text corrections.

## Database Changes
None.

## Nextcloud Integration
Not applicable — this change is within the standalone `tilburg-woo-ui` React frontend.

## File Structure
Files to audit, grouped by priority (highest text density first):

```
tilburg-woo-ui/src/
├── constants/                          # Priority 1: Centralized text
│   ├── labels.constants.js             # UI labels (buttons, headings, etc.)
│   ├── titles.constants.js             # Page titles
│   ├── breadcrumbs.constants.js        # Navigation breadcrumbs
│   ├── messages.constants.js           # Error/validation messages
│   ├── toasters.constants.js           # Toast notifications
│   ├── roles.constants.js              # User role labels
│   └── risks.constants.js              # Risk level labels
├── assets/locales/                     # Priority 2: i18n translations
│   ├── translations.js                 # Master translation data
│   └── nl/translation.js               # Dutch locale mapping
├── views/ac-forms/*/utils/             # Priority 3: Form-specific text
│   ├── texts.utils.js                  # Page titles/descriptions
│   └── steps.utils.js                  # Multi-step form labels
└── views/**/*.js, components/**/*.js   # Priority 4: Inline strings
    └── (error messages, status labels, tooltips, aria-labels, placeholders)
```

## Approach

### Audit Method
For each file category:
1. Read the file and identify all Dutch text strings (string literals, template literals, JSX text content)
2. Check each string for spelling errors, grammatical issues, and consistency
3. Fix errors while preserving variable interpolation, formatting, and structure
4. Verify the key/export names remain unchanged (only values change)

### What Constitutes a "Fix"
- Correcting misspelled Dutch words (e.g., "gegevens" not "gegevens", "beschikbaar" not "beschikbar")
- Fixing grammatical errors (e.g., wrong article, incorrect verb conjugation)
- Fixing inconsistent capitalization in similar labels
- Fixing truncated or malformed sentences

### What Does NOT Change
- Variable names, export names, or object keys
- String structure (interpolation patterns like `${var}` or `{var}`)
- The meaning or tone of the text
- English text or technical identifiers

### Branch Strategy
```
main (or current working branch)
  └── feature/fix-dutch-spelling
        ├── commit: fix constants files
        ├── commit: fix i18n translations
        ├── commit: fix form utility texts
        └── commit: fix inline component strings
```

Each file category gets its own commit for easy review and selective rollback.

## Security Considerations
No security impact. Text-only changes do not affect authentication, authorization, CORS, or input validation.

## NL Design System
No NL Design System changes. The text content within existing NL Design System components is being corrected, but no component usage or token changes are made.

## Trade-offs

### Approach: Manual audit vs. automated spell checker
- **Chosen: Manual audit** — Dutch technical/domain text (e.g., "Softwarecatalogus", "Woo-verzoek", "moduleversie") would generate many false positives with automated tools. Manual review ensures domain terminology is preserved while actual errors are caught.
- **Alternative: hunspell/aspell automation** — Would be faster but requires custom dictionaries for Dutch government/IT terminology. Risk of false corrections outweighs speed benefit for this scope.

### Approach: One big commit vs. per-category commits
- **Chosen: Per-category commits** — Makes PR review manageable and allows selective rollback if a category introduces issues.
- **Alternative: Single commit** — Simpler but harder to review and troubleshoot.
