# Tasks: setup-five-steps-and-admin-tour

- [ ] `lib/Service/SetupWizardService.php`: five steps (Welcome, Groups, Examples, Roles, Done); `computeStepStatuses()` without the footer step
- [ ] `src/modals/SetupWizardModal.vue`: drop the footer step, counter "n / 5", welcome text naming groups, example dashboards and roles
- [ ] Translations: new welcome text in every shipped locale; strings only the footer step used removed
- [x] Admin tour `launchpad:admin-getting-started`: six stops General, Templates, Roles and rights, Sharing, Kiosk, Demo data, each with one line of explanation
- [ ] Unit tests: `SetupWizardServiceTest` (five steps, no footer status), `SetupWizardModal.spec.js` (five steps, no footer editor), the admin tour step list
- [ ] Playwright: `tests/e2e/setup-wizard.spec.ts` walks five steps and finishes; the admin tour shows "1 of 6" on a first visit
