# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: responsive-grid-breakpoints.spec.ts >> responsive grid breakpoints >> visual regression: six-widget layout at each in-table breakpoint
- Location: tests/e2e/responsive-grid-breakpoints.spec.ts:66:6

# Error details

```
Error: A snapshot doesn't exist at /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/mydash/tests/e2e/responsive-grid-breakpoints.spec.ts-snapshots/grid-1500-chromium-linux.png, writing actual.
```

```
Error: A snapshot doesn't exist at /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/mydash/tests/e2e/responsive-grid-breakpoints.spec.ts-snapshots/grid-1200-chromium-linux.png, writing actual.
```

```
Error: A snapshot doesn't exist at /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/mydash/tests/e2e/responsive-grid-breakpoints.spec.ts-snapshots/grid-900-chromium-linux.png, writing actual.
```

```
Error: A snapshot doesn't exist at /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/mydash/tests/e2e/responsive-grid-breakpoints.spec.ts-snapshots/grid-480-chromium-linux.png, writing actual.
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
        - button "More apps" [ref=e24] [cursor=pointer]:
          - img [ref=e27]:
            - img [ref=e28]
    - generic [ref=e30]:
      - button "Unified search" [ref=e33] [cursor=pointer]:
        - img [ref=e36]:
          - img [ref=e37]
      - generic "Notifications" [ref=e40]:
        - button "Notifications" [ref=e41] [cursor=pointer]:
          - img [ref=e45]
      - button "Search contacts" [ref=e49] [cursor=pointer]:
        - img [ref=e52]:
          - img [ref=e53]
      - navigation "Settings menu" [ref=e55]:
        - button "Settings menu" [ref=e56] [cursor=pointer]:
          - img [ref=e60]:
            - img [ref=e61]
        - generic [ref=e63]: Avatar of admin — Online
  - generic [ref=e64]:
    - heading "Nextcloud" [level=1] [ref=e65]
    - generic [ref=e67]:
      - generic [ref=e68]:
        - button "Open menu" [ref=e69] [cursor=pointer]
        - generic [ref=e73]: My Dashboard
      - generic [ref=e74]:
        - button "Add Widget" [ref=e77] [cursor=pointer]
        - button "Save Layout" [ref=e79] [cursor=pointer]
      - generic [ref=e81]:
        - navigation [ref=e82]:
          - generic [ref=e83]:
            - heading [level=2] [ref=e84]: Dashboards
            - button [ref=e85] [cursor=pointer]:
              - img [ref=e86]:
                - img [ref=e87]
          - generic [ref=e90]:
            - heading [level=3] [ref=e91]: My Dashboards
            - list [ref=e92]:
              - button [ref=e93] [cursor=pointer]:
                - img [ref=e95]:
                  - img [ref=e96]
                - generic [ref=e98]: My Dashboard
              - button [ref=e99] [cursor=pointer]:
                - img [ref=e101]:
                  - img [ref=e102]
                - generic [ref=e104]: wewr
        - generic [ref=e105]:
          - button "Dashboards" [ref=e106] [cursor=pointer]:
            - img [ref=e109]:
              - img [ref=e110]
          - generic "Your primary group for shared dashboards" [ref=e112]: Default
          - generic [ref=e113]:
            - generic [ref=e114] [cursor=pointer]: Active dashboard
            - generic [ref=e115]:
              - generic [ref=e116]:
                - generic "My Dashboard" [ref=e118]:
                  - generic [ref=e119]: My Das
                  - generic [ref=e120]: hboard
                - combobox "Active dashboard" [ref=e121] [cursor=pointer]
              - generic [ref=e122]:
                - button "Clear selected" [ref=e123] [cursor=pointer]:
                  - img [ref=e124]:
                    - img [ref=e125]
                - button [ref=e127] [cursor=pointer]:
                  - img [ref=e129]
          - button "Dashboard menu" [ref=e133] [cursor=pointer]:
            - img [ref=e136]:
              - img [ref=e137]
        - generic [ref=e140]:
          - generic [ref=e141]:
            - link "Files" [ref=e145] [cursor=pointer]:
              - /url: ""
              - img [ref=e146]
              - generic [ref=e148]: Files
            - generic [ref=e151]:
              - generic [ref=e152]:
                - generic:
                  - img "Important mail" [ref=e153]
                  - heading "Important mail" [level=3]
              - generic [ref=e155]:
                - generic:
                  - list:
                    - listitem:
                      - generic "burger@test.local":
                        - link "burger@test.local Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e156] [cursor=pointer]:
                          - /url: http://localhost:8080/apps/mail/box/4/thread/41
                          - generic [ref=e158]:
                            - heading "burger@test.local" [level=3]
                            - generic "Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)"
                    - listitem:
                      - generic "leverancier@test.local":
                        - link "leverancier@test.local Technische documentatie API-koppeling OpenRegister" [ref=e159] [cursor=pointer]:
                          - /url: http://localhost:8080/apps/mail/box/4/thread/40
                          - generic [ref=e161]:
                            - heading "leverancier@test.local" [level=3]
                            - generic "Technische documentatie API-koppeling OpenRegister"
                    - listitem:
                      - generic "admin@test.local":
                        - 'link "admin@test.local URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e162] [cursor=pointer]':
                          - /url: http://localhost:8080/apps/mail/box/4/thread/38
                          - generic [ref=e164]:
                            - heading "admin@test.local" [level=3]
                            - 'generic "URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken"'
                    - listitem:
                      - generic "coordinator@test.local":
                        - link "coordinator@test.local Weekplanning team Vergunningen - week 13" [ref=e165] [cursor=pointer]:
                          - /url: http://localhost:8080/apps/mail/box/4/thread/39
                          - generic [ref=e167]:
                            - heading "coordinator@test.local" [level=3]
                            - generic "Weekplanning team Vergunningen - week 13"
                    - listitem:
                      - generic "admin@test.local":
                        - 'link "admin@test.local Herinnering: 3 zaken naderen deadline" [ref=e168] [cursor=pointer]':
                          - /url: http://localhost:8080/apps/mail/box/4/thread/37
                          - generic [ref=e170]:
                            - heading "admin@test.local" [level=3]
                            - 'generic "Herinnering: 3 zaken naderen deadline"'
                    - listitem:
                      - generic "coordinator@test.local":
                        - 'link "coordinator@test.local FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e171] [cursor=pointer]':
                          - /url: http://localhost:8080/apps/mail/box/4/thread/36
                          - generic [ref=e173]:
                            - heading "coordinator@test.local" [level=3]
                            - 'generic "FW: Offerte IT-systeem migratie - ter goedkeuring"'
                  - link "More items …":
                    - /url: http://localhost:8080/apps/mail/
            - link "Calendar" [ref=e177] [cursor=pointer]:
              - /url: ""
              - img [ref=e178]
              - generic [ref=e180]: Calendar
            - generic [ref=e183]:
              - generic [ref=e184]:
                - generic:
                  - img "Upcoming events" [ref=e185]
                  - heading "Upcoming events" [level=3]
              - generic [ref=e187]:
                - generic:
                  - list
                  - note [ref=e188]:
                    - paragraph [ref=e190]: No upcoming events
              - button "More events" [ref=e192] [cursor=pointer]:
                - generic [ref=e194]: More events
            - link "Intranet" [ref=e198] [cursor=pointer]:
              - /url: ""
              - img [ref=e199]
              - generic [ref=e201]: Intranet
            - generic [ref=e204]:
              - generic [ref=e205]:
                - generic:
                  - heading "Overdue Cases" [level=3]
              - generic [ref=e208]:
                - generic:
                  - list
                  - note "No open cases" [ref=e209]:
                    - img [ref=e211]:
                      - img [ref=e212]
            - generic [ref=e216]:
              - generic [ref=e217]:
                - generic:
                  - img "Document Anonymization" [ref=e218]
                  - heading "Document Anonymization" [level=3]
              - generic [ref=e220]:
                - generic:
                  - generic [ref=e222] [cursor=pointer]:
                    - img [ref=e223]:
                      - img [ref=e224]
                    - paragraph: Drop files to anonymize
                  - link "Open DocuDesk" [ref=e226] [cursor=pointer]:
                    - /url: /apps/docudesk
            - generic [ref=e229]:
              - generic [ref=e230]:
                - generic:
                  - heading "My Tasks" [level=3]
              - generic [ref=e233]:
                - generic:
                  - list
                  - note "No tasks found" [ref=e234]:
                    - img [ref=e236]:
                      - img [ref=e237]
            - generic [ref=e241]:
              - generic [ref=e242]:
                - generic:
                  - heading "Start case" [level=3]
              - note "No case types configured" [ref=e247]:
                - img [ref=e249]:
                  - img [ref=e250]
                - paragraph [ref=e252]: Configure case types in Procest admin settings
          - menu [ref=e253]:
            - menuitem "Edit" [ref=e254] [cursor=pointer]
            - menuitem "Remove" [ref=e255] [cursor=pointer]
            - menuitem "Cancel" [ref=e256] [cursor=pointer]
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
  62 | 			expect(actualColumns).toBe(columns)
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
> 73 | 			await expect(page.locator('.grid-stack')).toHaveScreenshot(
     |    ^ Error: A snapshot doesn't exist at /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/mydash/tests/e2e/responsive-grid-breakpoints.spec.ts-snapshots/grid-480-chromium-linux.png, writing actual.
  74 | 				`grid-${width}.png`,
  75 | 				{ maxDiffPixelRatio: 0.02 },
  76 | 			)
  77 | 		}
  78 | 	})
  79 | })
  80 | 
```