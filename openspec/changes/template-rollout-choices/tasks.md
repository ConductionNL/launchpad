# Tasks: template-rollout-choices

- [x] Re-sync service: `add_only` strategy (add missing template placements at the first free spot, touch nothing else, compulsory widgets still reconciled); accept it in the endpoint and the dry run
- [x] Merge: keep the owner's position and size for a template placement the owner moved or resized after provisioning; apply the template's other changes
- [x] Dry run: report widgets gained and lost and the number of copies whose owner changed them
- [x] Rollout window on the templates tab: summary, three choices with their consequences, Keep personal changes preselected, count of changed copies, button with the number of dashboards
- [x] Translations in every shipped locale
- [x] Unit tests: add_only adds and never removes or moves; merge keeps an owner's move; dry-run summary and counts
- [ ] Playwright (written in tests/e2e/template-rollout-choices.spec.ts, runs in nightly): push a template change with Only add new widgets and see the new widget on a copy that kept its own layout
