# Give every LaunchPad scenario its own e2e verdict

## Why

LaunchPad's specs describe 2,834 scenarios across 78 capabilities. On 2026-09-11, 147 of them (5%) were cited by a Playwright test. 46 specs covered every scenario with one spec-level `@e2e exclude` line, which hides 1,862 scenarios from gate-19.

A blanket exclusion says nothing about any single scenario. It cannot tell a scenario nobody can test from one nobody has tried. The first spec to lose its blanket shows what that costs.

`dashboard-export-import` had 48 scenarios under one exclusion. Covering them one by one (#616) found three real defects. A corrupt file was reported with a blank ID. A skipped dashboard was reported under an invented ID. The admin page replaced the server's refusal reason with "Import failed. Please try again." Before that, #612 found that no import had ever landed a dashboard. The importer never set two required columns, and the unit tests mocked the database, so nothing noticed.

Every blanket still in place may hide the same kind of defect.

## What changes

Each spec in LaunchPad ends with one verdict per scenario and no spec-level blanket. A scenario gets exactly one of three verdicts:

- **Cited.** A Playwright test carries `// @e2e <spec>::<scenario-slug>` and asserts every THEN and AND clause of that scenario. A test that only loads a page does not count.
- **New e2e.** Someone writes the test. It drives what a person does through the product's own controls, and uses the API only to seed and tear down.
- **Excluded, with a reason.** The scenario carries `@e2e exclude <reason>`. The reason names the unit or Newman test that pins the behaviour, or says plainly that none exists or the feature was never built. Check `git log --all` before writing "never built".

When a test shows the product disagreeing with its scenario, that is a defect. It gets fixed with a unit test. The e2e is not weakened to match the product.

This change adds no requirements. It changes tests, the `@e2e` annotations in `openspec/specs/`, and product code wherever a test exposes a defect.

## Order of work

Specs are worked in five tiers. The tier decides the order, not the size.

1. **Tier 0, safety (8 specs, 343 scenarios).** Who may see, change or share what. A false green here reads as proof that a protection works. Started on 2026-09-11.
2. **Tier 1, data integrity (11 specs, 341 scenarios).** Deletes, cascades, versions, locks, quotas and bulk writes. #612 shows why: write paths tested only against a mocked database.
   Since #617, CI's PHPUnit boots a real Nextcloud, so a write path can be pinned by a database test as well as an e2e. #617 checked all 33 insert sites against the 73 required columns and found two more silent failures: Confluence import never saved a dashboard, and a role permission saved without a name hit the database constraint.
3. **Tier 2, core surfaces (19 specs, 823 scenarios).** Dashboards, widgets, the grid, tiles, templates and the setup wizard.
4. **Tier 3, widgets (24 specs, 817 scenarios).** One spec per widget type.
5. **Tier 4, admin, operations and integrations (14 specs, 419 scenarios).** CLI commands, background jobs, metrics and search providers. Many of these have no browser surface, so expect honest exclusions naming the PHPUnit or Newman test, rather than new e2e.

## Rules for safety specs

These apply in tier 0 and to any refusal in a later tier.

- Probe a refusal with the least privileged principal that should be refused, logged in. An ordinary account proves the rule. A superuser success proves almost nothing, and an anonymous probe says nothing about a logged-in user.
- Assert the exact status the spec names, not "not 200". Then re-read as the owner to confirm that nothing was written.
- Prove the allowed principal can do it, in the same test. Otherwise a broken endpoint passes as a refusal.
- Create test accounts over basic auth with an explicit header. Nextcloud expires password confirmation 30 minutes after login, so accounts created through the admin session fail when a run starts late.

## How a spec is done

- Every scenario has one verdict, and the spec-level blanket is gone.
- Every new guard has been seen to fail. Break the rule in product code, watch the right assertion go red, then restore the file. Turn PHP opcache revalidation off in the test container first. On #616, two mutations read green because the container still ran the cached, unbroken code.
- `composer check:strict` exits 0, and so do `npm run lint` and `npm run format`. The scoped hydra gates exit 0, including gate-19.
- The PR's CI shows the new tests ran. A spec that did not run looks exactly like one that passed.

## Capacity

One spec of about 50 scenarios took one agent roughly an hour, plus a CI run of 20 to 30 minutes. At that rate the whole programme is about 60 agent-hours. With three agents in parallel it is two to three working days of wall time, bounded by CI queues rather than writing.

A PR per spec keeps each review small, and lets one spec's defect ship without waiting for the others.

## Out of scope

- dossiq and nextcloud-vue. dossiq already runs its own programme (`e2e-citation-integrity`, dossiq #2461 and its follow-ups). nextcloud-vue gets its own change when this one is past tier 1.
- Rewriting scenarios. Where a scenario is wrong, fix the scenario text in the same PR and say why. Do not delete a scenario to make the count look better.
