# Tasks: mijn-werkdag-template

- [x] 1. Write `data/templates/mijn-werkdag.json` from widgets that exist. Verify: `ShippedTemplateServiceTest::testTheShippedDefinitionIsWellFormed`.
- [x] 2. Import carries an admin template's `isCompulsory`, category and description, and leaves it without an owner; the result names the created dashboards. Verify: `ImportServiceTest::testCompulsoryIsCarriedForATemplateOnly`.
- [x] 3. `ShippedTemplateService`: list and install through the importer, remember the install, name missing widgets. Verify: `ShippedTemplateServiceTest`.
- [x] 4. Round trip: shipped definition, install, `launchpad:export`, `launchpad:import` on a second store, same template. Verify: `ShippedTemplateServiceTest::testTheShippedTemplateSurvivesExportAndImport`.
- [x] 5. `occ launchpad:template:install`, registered in `appinfo/info.xml`. Verify: `TemplateInstallCommandTest`.
- [x] 6. `GET /api/admin/templates/shipped` and `POST /api/admin/templates/shipped/{id}/install`. Verify: `AdminShippedTemplateControllerTest`.
- [x] 7. Templates page: ready-made templates section and a download per template, `en` and `nl` strings. Verify: `TemplatesPage.shipped.spec.js`.
- [x] 8. Live check in a browser on a running instance. Done 17 Nov 2026 (live pass, NC 34.0.6 + OpenRegister 2.1.47 rig): the Templates page lists "Mijn werkdag" under Ready-made templates with the registers its lists read; `occ launchpad:template:install mijn-werkdag -g werkdag` installed it, and a new member of `werkdag` got their copy on the first visit and saw it in the browser.
