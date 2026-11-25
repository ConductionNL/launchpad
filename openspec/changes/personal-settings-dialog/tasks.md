# Tasks: personal-settings-dialog

- [x] `src/App.vue`: section order of LpPersoonlijk (summary, analytics, hidden widgets, profile, credentials, then the library's sections); leave out a section whose feature is off
  (The app's sections in board order, asserted in `settingsSectionOrder.spec.js`. Gebruikscijfers leaves itself out while analytics is off; Mijn profiel leaves itself out while no profile field is defined, since hiding it while the person has filled none would leave the fields unreachable.)
- [x] `src/views/settings/DigestSettings.vue`: Off, Daily, Weekly; stored per person through the preferences API; no-email notice
  (Stored in the person's user preferences through `GET/PUT /api/summary-mail/me`, which validates the three values and says whether the person has an email address; the generic `/api/preferences/{key}` accepts any string and cannot.)
- [x] `lib/BackgroundJob/SummaryMailJob.php` (hourly `TimedJob`): sends at 07:30 in each opted-in person's time zone, daily or on Monday; collects announcements published for the person and the version notes of dashboards they can open since the last summary; skips an empty daily summary; records the last send
- [x] Mail template through `IMailer` and `IEMailTemplate`, translated
- [x] `src/views/settings/HiddenWidgetsSettings.vue` and `GET /api/personal-layer/hidden`, `DELETE /api/personal-layer/hidden/{placementId}` and `DELETE /api/personal-layer/hidden` over the personal layer of REQ-DWMS-001, for the caller only
- [x] Translations in every shipped locale
  (All 36 non-English locales carry the 21 dialog and summary-mail strings in .json and .js, the plural with one form per plural of the locale; `src/__tests__/personalSettingsLocales.spec.js`.)
- [x] Unit tests: section order and omission; preference stored; job timing per time zone, Monday for weekly, empty daily skipped, no email address; hidden list, show one, show all, never another person's layer
- [ ] Playwright: open Persoonlijke instellingen from Meer, set Daily, hide a widget on a shared dashboard and show it again from the dialog
  (Written in `tests/e2e/personal-settings-dialog.spec.ts`, runs in nightly; not yet verified against a live instance.)
