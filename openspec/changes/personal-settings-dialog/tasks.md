# Tasks: personal-settings-dialog

- [ ] `src/App.vue`: section order of LpPersoonlijk (summary, analytics, hidden widgets, profile, credentials, then the library's sections); leave out a section whose feature is off
- [ ] `src/views/settings/DigestSettings.vue`: Off, Daily, Weekly; stored per person through the preferences API; no-email notice
- [ ] `lib/BackgroundJob/SummaryMailJob.php` (hourly `TimedJob`): sends at 07:30 in each opted-in person's time zone, daily or on Monday; collects announcements published for the person and the version notes of dashboards they can open since the last summary; skips an empty daily summary; records the last send
- [ ] Mail template through `IMailer` and `IEMailTemplate`, translated
- [ ] `src/views/settings/HiddenWidgetsSettings.vue` and `GET /api/personal-layer/hidden`, `DELETE /api/personal-layer/hidden/{placementId}` and `DELETE /api/personal-layer/hidden` over the personal layer of REQ-DWMS-001, for the caller only
- [ ] Translations in every shipped locale
- [ ] Unit tests: section order and omission; preference stored; job timing per time zone, Monday for weekly, empty daily skipped, no email address; hidden list, show one, show all, never another person's layer
- [ ] Playwright: open Persoonlijke instellingen from Meer, set Daily, hide a widget on a shared dashboard and show it again from the dialog
