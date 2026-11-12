# Tasks: record-parity-requirements

- [x] 1. Write the requirements for build-picker, d-auto-refresh and g-health from the code on `development`, and record them on the rows in `openspec/parity/capabilities.json`.
- [x] 2. Record the s-policy and d-tour requirements on their rows and set both rows to `specified`.
- [ ] 3. Build the sharing limits: add `sharing_allowed_groups` and `sharing_recipient_scope` to `lib/Db/AdminSettingKey.php`, enforce them in `lib/Service/DashboardShareService.php` and in the sharee search, and add both fields to `src/components/admin/DashboardSharingPolicy.vue`. Verify: PHPUnit on the service, Vitest on the component.
- [ ] 4. Build the admin tour: declare a `launchpad:admin-getting-started` walkthrough in `src/manifest.json` for the admin settings page. Verify: Vitest on the manifest block.
- [ ] 5. Fold the requirements into the main specs on archive, and set s-policy and d-tour to `built` with evidence lines.
