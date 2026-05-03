# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: responsive-grid-breakpoints.spec.ts >> responsive grid breakpoints >> grid uses 8 columns at 1200px viewport
- Location: tests/e2e/responsive-grid-breakpoints.spec.ts:48:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 8
Received: 12
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
          - listitem [ref=e30]:
            - link "Mail" [ref=e31] [cursor=pointer]:
              - /url: /apps/mail/
              - img [ref=e32]
              - generic [ref=e33]: Mail
          - listitem [ref=e34]:
            - link "Calendar" [ref=e35] [cursor=pointer]:
              - /url: /apps/calendar/
              - img [ref=e36]
              - generic [ref=e37]: Calendar
          - listitem [ref=e38]:
            - link "Software Catalogs" [ref=e39] [cursor=pointer]:
              - /url: /apps/softwarecatalog
              - img [ref=e40]
              - generic [ref=e41]: Software Catalogs
          - listitem [ref=e42]:
            - link "Procest" [ref=e43] [cursor=pointer]:
              - /url: /apps/procest
              - img [ref=e44]
              - generic [ref=e45]: Procest
          - listitem [ref=e46]:
            - link "Pipelinq" [ref=e47] [cursor=pointer]:
              - /url: /apps/pipelinq
              - img [ref=e48]
              - generic [ref=e49]: Pipelinq
          - listitem [ref=e50]:
            - link "Register" [ref=e51] [cursor=pointer]:
              - /url: /apps/openregister/
              - img [ref=e52]
              - generic [ref=e53]: Register
          - listitem [ref=e54]:
            - link "Catalogi" [ref=e55] [cursor=pointer]:
              - /url: /apps/opencatalogi
              - img [ref=e56]
              - generic [ref=e57]: Catalogi
          - listitem [ref=e58]:
            - link "Larping" [ref=e59] [cursor=pointer]:
              - /url: /apps/larpingapp/
              - img [ref=e60]
              - generic [ref=e61]: Larping
          - listitem [ref=e62]:
            - link "DocuDesk" [ref=e63] [cursor=pointer]:
              - /url: /apps/docudesk
              - img [ref=e64]
              - generic [ref=e65]: DocuDesk
          - listitem [ref=e66]:
            - link "Decidesk" [ref=e67] [cursor=pointer]:
              - /url: /apps/decidesk/
              - img [ref=e68]
              - generic [ref=e69]: Decidesk
          - listitem [ref=e70]:
            - link "App Versions" [ref=e71] [cursor=pointer]:
              - /url: /apps/app_versions/
              - img [ref=e72]
              - generic [ref=e73]: App Versions
    - generic [ref=e74]:
      - button "Unified search" [ref=e77] [cursor=pointer]:
        - img [ref=e80]:
          - img [ref=e81]
      - generic "Notifications" [ref=e84]:
        - button "Notifications" [ref=e85] [cursor=pointer]:
          - img [ref=e89]
      - button "Search contacts" [ref=e93] [cursor=pointer]:
        - img [ref=e96]:
          - img [ref=e97]
      - navigation "Settings menu" [ref=e99]:
        - button "Settings menu" [ref=e100] [cursor=pointer]:
          - img [ref=e104]:
            - img [ref=e105]
        - generic [ref=e107]: Avatar of admin — Online
  - generic [ref=e108]:
    - heading "Nextcloud" [level=1] [ref=e109]
    - generic [ref=e111]:
      - generic [ref=e112]:
        - button "Open menu" [ref=e113] [cursor=pointer]
        - generic [ref=e117]: My Dashboard
      - generic [ref=e118]:
        - button "Add Widget" [ref=e121] [cursor=pointer]
        - button "Save Layout" [ref=e123] [cursor=pointer]
      - generic [ref=e125]:
        - navigation [ref=e126]:
          - generic [ref=e127]:
            - heading [level=2] [ref=e128]: Dashboards
            - button [ref=e129] [cursor=pointer]:
              - img [ref=e130]:
                - img [ref=e131]
          - generic [ref=e134]:
            - heading [level=3] [ref=e135]: My Dashboards
            - list [ref=e136]:
              - button [ref=e137] [cursor=pointer]:
                - img [ref=e139]:
                  - img [ref=e140]
                - generic [ref=e142]: My Dashboard
              - button [ref=e143] [cursor=pointer]:
                - img [ref=e145]:
                  - img [ref=e146]
                - generic [ref=e148]: wewr
        - generic [ref=e149]:
          - button "Dashboards" [ref=e150] [cursor=pointer]:
            - img [ref=e153]:
              - img [ref=e154]
          - generic "Your primary group for shared dashboards" [ref=e156]: Default
          - generic [ref=e157]:
            - generic [ref=e158] [cursor=pointer]: Active dashboard
            - generic [ref=e159]:
              - generic [ref=e160]:
                - generic "My Dashboard" [ref=e162]:
                  - generic [ref=e163]: My Das
                  - generic [ref=e164]: hboard
                - combobox "Active dashboard" [ref=e165] [cursor=pointer]
              - generic [ref=e166]:
                - button "Clear selected" [ref=e167] [cursor=pointer]:
                  - img [ref=e168]:
                    - img [ref=e169]
                - button [ref=e171] [cursor=pointer]:
                  - img [ref=e173]
          - button "Dashboard menu" [ref=e177] [cursor=pointer]:
            - img [ref=e180]:
              - img [ref=e181]
        - generic [ref=e184]:
          - generic [ref=e185]:
            - link "Files" [ref=e189] [cursor=pointer]:
              - /url: ""
              - img [ref=e190]
              - generic [ref=e192]: Files
            - generic [ref=e195]:
              - generic [ref=e197]:
                - img "Important mail" [ref=e198]
                - heading "Important mail" [level=3] [ref=e199]
              - generic [ref=e202]:
                - list [ref=e203]:
                  - listitem [ref=e204]:
                    - generic "burger@test.local" [ref=e205]:
                      - link "burger@test.local Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e206] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/41
                        - generic [ref=e208]:
                          - heading "burger@test.local" [level=3]
                          - generic "Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)"
                  - listitem [ref=e209]:
                    - generic "leverancier@test.local" [ref=e210]:
                      - link "leverancier@test.local Technische documentatie API-koppeling OpenRegister" [ref=e211] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/40
                        - generic [ref=e213]:
                          - heading "leverancier@test.local" [level=3]
                          - generic "Technische documentatie API-koppeling OpenRegister"
                  - listitem [ref=e214]:
                    - generic "admin@test.local" [ref=e215]:
                      - 'link "admin@test.local URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e216] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/38
                        - generic [ref=e218]:
                          - heading "admin@test.local" [level=3]
                          - 'generic "URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken"'
                  - listitem [ref=e219]:
                    - generic "coordinator@test.local" [ref=e220]:
                      - link "coordinator@test.local Weekplanning team Vergunningen - week 13" [ref=e221] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/39
                        - generic [ref=e223]:
                          - heading "coordinator@test.local" [level=3]
                          - generic "Weekplanning team Vergunningen - week 13"
                  - listitem [ref=e224]:
                    - generic "admin@test.local" [ref=e225]:
                      - 'link "admin@test.local Herinnering: 3 zaken naderen deadline" [ref=e226] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/37
                        - generic [ref=e228]:
                          - heading "admin@test.local" [level=3]
                          - 'generic "Herinnering: 3 zaken naderen deadline"'
                  - listitem [ref=e229]:
                    - generic "coordinator@test.local" [ref=e230]:
                      - 'link "coordinator@test.local FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e231] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/36
                        - generic [ref=e233]:
                          - heading "coordinator@test.local" [level=3]
                          - 'generic "FW: Offerte IT-systeem migratie - ter goedkeuring"'
                - link "More items …" [ref=e234] [cursor=pointer]:
                  - /url: http://localhost:8080/apps/mail/
            - generic [ref=e237]:
              - generic [ref=e239]:
                - img "Upcoming events" [ref=e240]
                - heading "Upcoming events" [level=3] [ref=e241]
              - generic [ref=e244]:
                - list
                - note [ref=e245]:
                  - paragraph [ref=e247]: No upcoming events
              - button "More events" [ref=e249] [cursor=pointer]:
                - generic [ref=e251]: More events
            - link "Calendar" [ref=e255] [cursor=pointer]:
              - /url: ""
              - img [ref=e256]
              - generic [ref=e258]: Calendar
            - link "Intranet" [ref=e262] [cursor=pointer]:
              - /url: ""
              - img [ref=e263]
              - generic [ref=e265]: Intranet
            - generic [ref=e268]:
              - heading "Overdue Cases" [level=3] [ref=e272]
              - generic [ref=e275]:
                - list
                - note "No open cases" [ref=e276]:
                  - img [ref=e278]:
                    - img [ref=e279]
            - generic [ref=e283]:
              - generic [ref=e284]:
                - generic:
                  - heading "My Tasks" [level=3]
              - generic [ref=e287]:
                - generic:
                  - list
                  - note "No tasks found" [ref=e288]:
                    - img [ref=e290]:
                      - img [ref=e291]
            - generic [ref=e295]:
              - heading "Start case" [level=3] [ref=e299]
              - note "No case types configured" [ref=e303]:
                - img [ref=e305]:
                  - img [ref=e306]
                - paragraph [ref=e308]: Configure case types in Procest admin settings
            - generic [ref=e311]:
              - generic [ref=e312]:
                - generic:
                  - img "Document Anonymization" [ref=e313]
                  - heading "Document Anonymization" [level=3]
              - generic [ref=e315]:
                - generic:
                  - generic [ref=e317] [cursor=pointer]:
                    - img [ref=e318]:
                      - img [ref=e319]
                    - paragraph: Drop files to anonymize
                  - link "Open DocuDesk" [ref=e321] [cursor=pointer]:
                    - /url: /apps/docudesk
          - menu [ref=e322]:
            - menuitem "Edit" [ref=e323] [cursor=pointer]
            - menuitem "Remove" [ref=e324] [cursor=pointer]
            - menuitem "Cancel" [ref=e325] [cursor=pointer]
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