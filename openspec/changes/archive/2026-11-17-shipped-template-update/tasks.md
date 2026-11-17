# Tasks: shipped-template-update

- [x] 1. `ShippedTemplateUpdateService`: pair the installed widgets with the shipped ones, write in one transaction, raise the recorded version, re-sync with `merge`. Verify: `ShippedTemplateUpdateServiceTest`.
- [x] 2. Dry run that writes nothing. Verify: `ShippedTemplateUpdateServiceTest::testADryRunWritesNothing`.
- [x] 3. A member's copy follows and keeps the member's own widget, with the real `TemplateService` and `TemplateResyncService`. Verify: `ShippedTemplateUpdateServiceTest::testAMembersCopyFollowsAndKeepsTheirOwnWidget`.
- [x] 4. `occ launchpad:template:install <id> --update [--dry-run]`. Verify: `TemplateInstallCommandTest`.
- [x] 5. `POST /api/admin/templates/shipped/{id}/update` and `updateAvailable` in the listing. Verify: `AdminShippedTemplateControllerTest`, `ShippedTemplateServiceTest::testTheListingSaysWhenAnUpdateIsAvailable`.
- [x] 6. Templates page: "Update to version N" and the confirmation dialog, `en` and `nl` strings. Verify: `TemplatesPage.shippedUpdate.spec.js`.
- [x] 7. `getApplicableTemplate()` picks by one rule. Verify: `TemplateServiceApplicableTemplateTest`.
- [x] 8. Live check of the command on a test instance, and of a member's page in a browser afterwards. See the PR body.
- [x] 9. Open the "Update to version N" button and its dialog in a browser. Done 17 Nov 2026 (live pass): with version 2 on record, the Templates page showed "Update to version 5"; the dialog listed the widgets added, removed and changed and the member copies, and "Update" applied it and the button went away.
