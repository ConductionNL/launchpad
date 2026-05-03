# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: responsive-grid-breakpoints.spec.ts >> responsive grid breakpoints >> grid uses 1 columns at 640px viewport
- Location: tests/e2e/responsive-grid-breakpoints.spec.ts:48:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 1
Received: 4
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - link "Skip to main content" [ref=e3] [cursor=pointer]:
    - /url: "#app-workspace"
  - banner [ref=e4]:
    - generic [ref=e5]:
      - link "Go to Dashboard" [ref=e6] [cursor=pointer]:
        - /url: /
      - navigation "Applications menu" [ref=e8]:
        - list "Apps" [ref=e9]:
          - listitem [ref=e10]:
            - link "Dashboard" [ref=e11] [cursor=pointer]:
              - /url: /apps/dashboard/
              - img [ref=e12]
              - generic [ref=e13]: Dashboard
          - listitem [ref=e14]:
            - link "MyDash" [ref=e15] [cursor=pointer]:
              - /url: /apps/mydash/
              - img [ref=e16]
              - generic [ref=e17]: MyDash
          - listitem [ref=e18]:
            - link "Files" [ref=e19] [cursor=pointer]:
              - /url: /apps/files/
              - img [ref=e20]
              - generic [ref=e21]: Files
          - listitem [ref=e22]:
            - link "Photos" [ref=e23] [cursor=pointer]:
              - /url: /apps/photos/
              - img [ref=e24]
              - generic [ref=e25]: Photos
          - listitem [ref=e26]:
            - link "Activity" [ref=e27] [cursor=pointer]:
              - /url: /apps/activity/
              - img [ref=e28]
              - generic [ref=e29]: Activity
        - button "More apps" [ref=e32] [cursor=pointer]:
          - img [ref=e35]:
            - img [ref=e36]
    - generic [ref=e38]:
      - button "Unified search" [ref=e41] [cursor=pointer]:
        - img [ref=e44]:
          - img [ref=e45]
      - generic "Notifications" [ref=e48]:
        - button "Notifications" [ref=e49] [cursor=pointer]:
          - img [ref=e53]
      - button "Search contacts" [ref=e57] [cursor=pointer]:
        - img [ref=e60]:
          - img [ref=e61]
      - navigation "Settings menu" [ref=e63]:
        - button "Settings menu" [ref=e64] [cursor=pointer]:
          - img [ref=e68]:
            - img [ref=e69]
        - generic [ref=e71]: Avatar of admin — Online
  - generic [ref=e72]:
    - heading "Nextcloud" [level=1] [ref=e73]
    - generic [ref=e75]:
      - generic [ref=e76]:
        - button "Open menu" [ref=e77] [cursor=pointer]
        - generic [ref=e81]: My Dashboard
      - generic [ref=e82]:
        - button "Add Widget" [ref=e85] [cursor=pointer]
        - button "Save Layout" [ref=e87] [cursor=pointer]
      - generic [ref=e89]:
        - navigation [ref=e90]:
          - generic [ref=e91]:
            - heading [level=2] [ref=e92]: Dashboards
            - button [ref=e93] [cursor=pointer]:
              - img [ref=e94]:
                - img [ref=e95]
          - generic [ref=e98]:
            - heading [level=3] [ref=e99]: My Dashboards
            - list [ref=e100]:
              - button [ref=e101] [cursor=pointer]:
                - img [ref=e103]:
                  - img [ref=e104]
                - generic [ref=e106]: My Dashboard
              - button [ref=e107] [cursor=pointer]:
                - img [ref=e109]:
                  - img [ref=e110]
                - generic [ref=e112]: wewr
        - generic [ref=e113]:
          - button "Dashboards" [ref=e114] [cursor=pointer]:
            - img [ref=e117]:
              - img [ref=e118]
          - generic "Your primary group for shared dashboards" [ref=e120]: Default
          - generic [ref=e121]:
            - generic [ref=e122] [cursor=pointer]: Active dashboard
            - generic [ref=e123]:
              - generic [ref=e124]:
                - generic "My Dashboard" [ref=e126]:
                  - generic [ref=e127]: My Das
                  - generic [ref=e128]: hboard
                - combobox "Active dashboard" [ref=e129] [cursor=pointer]
              - generic [ref=e130]:
                - button "Clear selected" [ref=e131] [cursor=pointer]:
                  - img [ref=e132]:
                    - img [ref=e133]
                - button [ref=e135] [cursor=pointer]:
                  - img [ref=e137]
          - button "Dashboard menu" [ref=e141] [cursor=pointer]:
            - img [ref=e144]:
              - img [ref=e145]
        - generic [ref=e148]:
          - generic [ref=e149]:
            - link "Files" [ref=e153] [cursor=pointer]:
              - /url: ""
              - img [ref=e154]
              - generic [ref=e156]: Files
            - generic [ref=e159]:
              - generic [ref=e161]:
                - img "Important mail" [ref=e162]
                - heading "Important mail" [level=3] [ref=e163]
              - generic [ref=e166]:
                - list [ref=e167]:
                  - listitem [ref=e168]:
                    - generic "burger@test.local" [ref=e169]:
                      - link "burger@test.local Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e170] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/41
                        - generic [ref=e172]:
                          - heading "burger@test.local" [level=3] [ref=e173]
                          - generic "Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e174]
                  - listitem [ref=e175]:
                    - generic "leverancier@test.local" [ref=e176]:
                      - link "leverancier@test.local Technische documentatie API-koppeling OpenRegister" [ref=e177] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/40
                        - generic [ref=e179]:
                          - heading "leverancier@test.local" [level=3] [ref=e180]
                          - generic "Technische documentatie API-koppeling OpenRegister" [ref=e181]
                  - listitem [ref=e182]:
                    - generic "admin@test.local" [ref=e183]:
                      - 'link "admin@test.local URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e184] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/38
                        - generic [ref=e186]:
                          - heading "admin@test.local" [level=3] [ref=e187]
                          - 'generic "URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e188]'
                  - listitem [ref=e189]:
                    - generic "coordinator@test.local" [ref=e190]:
                      - link "coordinator@test.local Weekplanning team Vergunningen - week 13" [ref=e191] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/39
                        - generic [ref=e193]:
                          - heading "coordinator@test.local" [level=3] [ref=e194]
                          - generic "Weekplanning team Vergunningen - week 13" [ref=e195]
                  - listitem [ref=e196]:
                    - generic "admin@test.local" [ref=e197]:
                      - 'link "admin@test.local Herinnering: 3 zaken naderen deadline" [ref=e198] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/37
                        - generic [ref=e200]:
                          - heading "admin@test.local" [level=3] [ref=e201]
                          - 'generic "Herinnering: 3 zaken naderen deadline" [ref=e202]'
                  - listitem [ref=e203]:
                    - generic "coordinator@test.local" [ref=e204]:
                      - 'link "coordinator@test.local FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e205] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/36
                        - generic [ref=e207]:
                          - heading "coordinator@test.local" [level=3] [ref=e208]
                          - 'generic "FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e209]'
                - link "More items …" [ref=e210] [cursor=pointer]:
                  - /url: http://localhost:8080/apps/mail/
            - generic [ref=e213]:
              - generic [ref=e215]:
                - img "Upcoming events" [ref=e216]
                - heading "Upcoming events" [level=3] [ref=e217]
              - generic [ref=e220]:
                - list
                - generic [ref=e221]:
                  - generic [ref=e222]:
                    - generic [ref=e225]: "?"
                    - generic [ref=e226]:
                      - heading [level=3] [ref=e227]
                      - paragraph [ref=e228]
                  - generic [ref=e229]:
                    - generic [ref=e232]: "?"
                    - generic [ref=e233]:
                      - heading [level=3] [ref=e234]
                      - paragraph [ref=e235]
                  - generic [ref=e236]:
                    - generic [ref=e239]: "?"
                    - generic [ref=e240]:
                      - heading [level=3] [ref=e241]
                      - paragraph [ref=e242]
                  - generic [ref=e243]:
                    - generic [ref=e246]: "?"
                    - generic [ref=e247]:
                      - heading [level=3] [ref=e248]
                      - paragraph [ref=e249]
                  - generic [ref=e250]:
                    - generic [ref=e253]: "?"
                    - generic [ref=e254]:
                      - heading [level=3] [ref=e255]
                      - paragraph [ref=e256]
                  - generic [ref=e257]:
                    - generic [ref=e260]: "?"
                    - generic [ref=e261]:
                      - heading [level=3] [ref=e262]
                      - paragraph [ref=e263]
                  - generic [ref=e264]:
                    - generic [ref=e267]: "?"
                    - generic [ref=e268]:
                      - heading [level=3] [ref=e269]
                      - paragraph [ref=e270]
              - button "More events" [ref=e272] [cursor=pointer]:
                - generic [ref=e274]: More events
            - link "Calendar" [ref=e278] [cursor=pointer]:
              - /url: ""
              - img [ref=e279]
              - generic [ref=e281]: Calendar
            - link "Intranet" [ref=e285] [cursor=pointer]:
              - /url: ""
              - img [ref=e286]
              - generic [ref=e288]: Intranet
            - generic [ref=e291]:
              - heading "Overdue Cases" [level=3] [ref=e295]
              - generic [ref=e298]:
                - list
                - note "No open cases" [ref=e299]:
                  - img [ref=e301]:
                    - img [ref=e302]
            - generic [ref=e306]:
              - heading "My Tasks" [level=3] [ref=e310]
              - generic [ref=e313]:
                - list
                - note "No tasks found" [ref=e314]:
                  - img [ref=e316]:
                    - img [ref=e317]
            - generic [ref=e321]:
              - heading "Start case" [level=3] [ref=e325]
              - note "No case types configured" [ref=e329]:
                - img [ref=e331]:
                  - img [ref=e332]
                - paragraph [ref=e334]: Configure case types in Procest admin settings
            - generic [ref=e337]:
              - generic [ref=e339]:
                - img "Document Anonymization" [ref=e340]
                - heading "Document Anonymization" [level=3] [ref=e341]
              - generic [ref=e344]:
                - generic [ref=e346] [cursor=pointer]:
                  - img [ref=e347]:
                    - img [ref=e348]
                  - paragraph [ref=e350]: Drop files to anonymize
                - link "Open DocuDesk" [ref=e351] [cursor=pointer]:
                  - /url: /apps/docudesk
          - menu [ref=e352]:
            - menuitem "Edit" [ref=e353] [cursor=pointer]
            - menuitem "Remove" [ref=e354] [cursor=pointer]
            - menuitem "Cancel" [ref=e355] [cursor=pointer]
  - img
  - img
  - img
```

# Test source

```ts
  1  | /*
  2  |  * SPDX-FileCopyrightText: 2026 MyDash Contributors
  3  |  * SPDX-License-Identifier: AGPL-3.0-or-later
  4  |  *
  5  |  * Playwright end-to-end test for the responsive grid breakpoints covering
  6  |  * tasks 3.1 + 3.2 of the `responsive-grid-breakpoints` OpenSpec change.
  7  |  *
  8  |  * Asserts:
  9  |  *   - REQ-GRID-007: at five viewport widths (1500 / 1200 / 900 / 640 / 320
  10 |  *     px) the grid's `opts.column` matches the expected entry from the
  11 |  *     BREAKPOINTS table — 12 / 8 / 4 / 1 / 1 respectively. The 320 px case
  12 |  *     verifies "below smallest breakpoint clamps to smallest column count".
  13 |  *   - Visual regression: a six-widget layout snapshot at each of the four
  14 |  *     in-table breakpoints (1500 / 1200 / 900 / 480 px).
  15 |  *
  16 |  * NOTE: Playwright infrastructure is not yet wired up in mydash. This file
  17 |  * is committed alongside the rest of the change so it runs once the cohort-
  18 |  * wide Playwright bootstrap lands. Do not delete — it is the canonical e2e
  19 |  * coverage for REQ-GRID-007 / REQ-GRID-012 / REQ-GRID-013.
  20 |  */
  21 | 
  22 | import { test, expect } from '@playwright/test'
  23 | 
  24 | const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
  25 | 
  26 | /**
  27 |  * (viewportWidthPx, expectedColumnCount) tuples driven from the BREAKPOINTS
  28 |  * table in `src/composables/useGridManager.js`. Includes one width above
  29 |  * the largest entry (1500 -> 12) and one below the smallest (320 -> 1) so
  30 |  * the clamping behaviour at both ends is asserted.
  31 |  */
  32 | const BREAKPOINT_CASES: Array<{ viewport: number, columns: number }> = [
  33 | 	{ viewport: 1500, columns: 12 },
  34 | 	{ viewport: 1200, columns: 8 },
  35 | 	{ viewport: 900, columns: 4 },
  36 | 	{ viewport: 640, columns: 1 },
  37 | 	{ viewport: 320, columns: 1 },
  38 | ]
  39 | 
  40 | test.describe('responsive grid breakpoints', () => {
  41 | 	test.beforeEach(async ({ page }) => {
  42 | 		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
  43 | 		// Tests assume the user is already authenticated via Playwright
  44 | 		// storageState; in CI this is set up by the Hydra harness.
  45 | 	})
  46 | 
  47 | 	for (const { viewport, columns } of BREAKPOINT_CASES) {
  48 | 		test(`grid uses ${columns} columns at ${viewport}px viewport`, async ({ page }) => {
  49 | 			await page.setViewportSize({ width: viewport, height: 900 })
  50 | 			// Wait for the grid to finish reflowing after the resize.
  51 | 			await page.waitForFunction(() => {
  52 | 				const el = document.querySelector('.grid-stack') as
  53 | 					| (HTMLElement & { gridstack?: { opts: { column: number } } })
  54 | 					| null
  55 | 				return Boolean(el?.gridstack)
  56 | 			})
  57 | 			const actualColumns = await page.evaluate(() => {
  58 | 				const el = document.querySelector('.grid-stack') as
  59 | 					HTMLElement & { gridstack: { opts: { column: number } } }
  60 | 				return el.gridstack.opts.column
  61 | 			})
> 62 | 			expect(actualColumns).toBe(columns)
     |                          ^ Error: expect(received).toBe(expected) // Object.is equality
  63 | 		})
  64 | 	}
  65 | 
  66 | 	test('visual regression: six-widget layout at each in-table breakpoint', async ({ page }) => {
  67 | 		// Run sequentially (not table-driven) so the screenshot names group
  68 | 		// under one Playwright report node.
  69 | 		const visualWidths = [1500, 1200, 900, 480]
  70 | 		for (const width of visualWidths) {
  71 | 			await page.setViewportSize({ width, height: 900 })
  72 | 			await page.waitForTimeout(300) // settle reflow animation
  73 | 			await expect(page.locator('.grid-stack')).toHaveScreenshot(
  74 | 				`grid-${width}.png`,
  75 | 				{ maxDiffPixelRatio: 0.02 },
  76 | 			)
  77 | 		}
  78 | 	})
  79 | })
  80 | 
```