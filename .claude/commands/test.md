---
description: Run LaunchPad's PHPUnit and Vitest unit tests
---

Run the unit tests from the repository root and summarise failures.

1. PHP: `composer test:unit` (PHPUnit, suite `tests/Unit`, config `phpunit.xml`).
   To run one file: `./vendor/bin/phpunit --configuration phpunit.xml <path>`.
2. Frontend: `npm test` (Vitest, config `vitest.config.js`, specs in
   `src/**/__tests__/` and `tests/vitest/`). To run one file:
   `npx vitest run <path>`.

If `$ARGUMENTS` names a file or folder, run only the matching suite for it.
