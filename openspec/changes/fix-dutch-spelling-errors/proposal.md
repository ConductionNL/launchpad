# Proposal: fix-dutch-spelling-errors

## Summary
Audit and fix all Dutch spelling errors in user-facing text across the tilburg-woo-ui frontend. This ensures a professional, consistent user experience for Dutch government users. All work happens in a separate feature branch to avoid disrupting the primary codebase.

## Motivation
The frontend contains Dutch text across constants files, utility functions, i18n translations, and inline component strings. Spelling errors in a government-facing application undermine credibility and user trust. A dedicated audit ensures all visible text is correct before production deployment.

## Affected Projects
- [x] Project: `tilburg-woo-ui` — Audit and fix Dutch spelling in all user-facing text

## Scope
### In Scope
- Fix spelling errors in centralized constants files (`labels.constants.js`, `titles.constants.js`, `breadcrumbs.constants.js`, `messages.constants.js`, `toasters.constants.js`, `roles.constants.js`, `risks.constants.js`)
- Fix spelling errors in i18n translation files (`assets/locales/translations.js`, `nl/translation.js`)
- Fix spelling errors in form utility text files (`views/ac-forms/*/utils/texts.utils.js`, `steps.utils.js`)
- Fix spelling errors in inline Dutch strings within component/view files (error messages, status labels, tooltips, placeholders, aria-labels)
- Fix spelling errors in Dutch text within SCSS files (if any content strings)

### Out of Scope
- Changing the meaning or tone of existing text
- Adding new translations or i18n coverage
- Refactoring text to be centralized (that's a separate task)
- Backend/PHP spelling fixes (separate project)
- Fixing spelling in comments or non-user-facing code
- Content from CMS/API (managed by editors, not in code)

## Approach
1. Create a feature branch `feature/fix-dutch-spelling` from the current working branch
2. Systematically audit each category of files containing Dutch text:
   - Constants files (highest density of text)
   - i18n translation files
   - Form utility text files
   - Component files with inline Dutch strings
3. For each file, review all Dutch text strings for spelling, grammar, and consistency
4. Fix errors while preserving the original intent and structure
5. Build and verify no functionality is broken
6. Submit as a PR for review

## Cross-Project Dependencies
- None. This change is purely cosmetic/textual within `tilburg-woo-ui` and does not affect API contracts, data models, or other projects.

## Rollback Strategy
- Revert the feature branch merge or cherry-pick individual commits
- Since changes are text-only (no logic changes), rollback risk is minimal
- The feature branch ensures the primary branch is unaffected until changes are reviewed and merged

## Open Questions
- None. The scope is well-defined: find and fix Dutch spelling errors in code files.
