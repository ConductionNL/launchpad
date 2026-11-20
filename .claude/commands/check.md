---
description: Run LaunchPad's PHP and frontend quality checks
---

Run the quality suite from the repository root and report each failure with its
file and line. Dependencies must be installed first (`composer install`,
`npm ci`).

1. PHP: `composer check:strict` (lint, lint:initial-state, lint:openapi,
   lint:spec-annotations, lint:licenses, phpcs, phpmd, psalm, phpstan,
   test:all). For a quicker pass use `composer check` (lint, phpcs, psalm,
   test:unit).
2. Frontend: `npm run lint` (eslint, check:manifest, lint:initial-state,
   lint:translation-domain), then `npm run stylelint` and `npm run format`.
3. Translations: `npm run check:l10n-parity`.

Auto-fixers, only when asked: `composer cs:fix`, `npm run lint:fix`,
`npm run stylelint:fix`, `npm run format:fix`.
