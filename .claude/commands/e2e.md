---
description: Run LaunchPad's Playwright end-to-end tests against a Nextcloud instance
---

Run the Playwright suite (config `playwright.config.ts`, tests in `tests/e2e/`,
details in `tests/e2e/README.md`).

1. The suite writes to the target instance and has no default URL. Ask which
   instance to use unless one of `PLAYWRIGHT_BASE_URL`, `BASE_URL`,
   `NEXTCLOUD_URL` or `NC_BASE_URL` is already set. Admin credentials come
   from `NC_ADMIN_USER` / `NC_ADMIN_PASS` (default `admin` / `admin`).
2. Install the browser once: `npx playwright install chromium`.
3. Run: `NC_BASE_URL=<url> npm run test:e2e`, or a single spec with
   `NC_BASE_URL=<url> npx playwright test tests/e2e/<file>.spec.ts`.

Other configs: `npm run test:e2e:excluded` (specs excluded from CI) and
`npm run test:e2e:docs` (documentation screenshots).
