# Tasks: dashboard-theme-readonly

- [x] Details tab: a read-only Thema field with the instance theme name (thematiq's active theme when set, else Nextcloud theming) and its explanation line, under Weergave
- [x] Details tab: the look fields of REQ-THM-001 in an Own look block; remove any separate Look tab; no theme select and no layout preset field
- [x] Translations in every shipped locale
- [x] Unit tests: the theme name source (thematiq set, not set); the field is read-only; no layout field; the look still saves from the Details tab
- [x] Playwright: open Dashboard settings, Details, read the theme name and set an accent under Own look (written, runs in nightly: tests/e2e/dashboard-theme-readonly.spec.ts)
