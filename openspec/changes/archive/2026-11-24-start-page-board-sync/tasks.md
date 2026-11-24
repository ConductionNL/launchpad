# Tasks: start-page-board-sync

- [x] Header greeting: confirm the header placement renders the day-part greeting, first name, date and week number from the reader's display name, language and time zone; add the `@spec` tag to the code that does it
- [x] Attention widget: `@spec` tags for `itemCards` in `AttentionWidget.vue` and `expandToRecords` in `services/attentionFeed.js` pointing at REQ-ATT-007
- [x] Unit tests: greeting per part of the day (11:59, 12:00, 17:59, 18:00) and the week number; `itemCards` true and false in `AttentionWidget.spec.js`, the limit across sources
- [x] Playwright: the start page header shows only Meer and the greeting; Vandaag eerst shows one card per due case with "Zaak openen" (written, runs in nightly: tests/e2e/lpvandaag-start-page.spec.ts)
