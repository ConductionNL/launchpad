# Tasks: version-change-notes

- [x] `SnapshotChangeDescriber` and `DashboardVersionService`: `@spec` tags to REQ-VERSUI-004
- [x] Re-sync service: write the copy's version with author "Template {name}" and the note "Updated from the template"
- [x] `VersionsTab.vue`: columns When, By, What changed; the newest marked as the current version; a template author shown as the template's name
- [x] Remove any remaining "Version history" entry from the dashboard menus
- [x] Translations in every shipped locale
- [x] Unit tests: each note kind, the "and N more changes" count, title fallback to type, explicit note kept; the re-sync version author and note
- [ ] Playwright: move a widget, open the tab Versions and read "Widget {title} moved"
