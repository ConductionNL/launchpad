# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: responsive-grid-breakpoints.spec.ts >> responsive grid breakpoints >> grid uses 4 columns at 900px viewport
- Location: tests/e2e/responsive-grid-breakpoints.spec.ts:48:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 4
Received: 8
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
        - button "More apps" [ref=e52] [cursor=pointer]:
          - img [ref=e55]:
            - img [ref=e56]
    - generic [ref=e58]:
      - button "Unified search" [ref=e61] [cursor=pointer]:
        - img [ref=e64]:
          - img [ref=e65]
      - generic "Notifications" [ref=e68]:
        - button "Notifications" [ref=e69] [cursor=pointer]:
          - img [ref=e73]
      - button "Search contacts" [ref=e77] [cursor=pointer]:
        - img [ref=e80]:
          - img [ref=e81]
      - navigation "Settings menu" [ref=e83]:
        - button "Settings menu" [ref=e84] [cursor=pointer]:
          - img [ref=e88]:
            - img [ref=e89]
        - generic [ref=e91]: Avatar of admin — Online
  - generic [ref=e92]:
    - heading "Nextcloud" [level=1] [ref=e93]
    - generic [ref=e95]:
      - generic [ref=e96]:
        - button "Open menu" [ref=e97] [cursor=pointer]
        - generic [ref=e101]: My Dashboard
      - generic [ref=e102]:
        - button "Add Widget" [ref=e105] [cursor=pointer]
        - button "Save Layout" [ref=e107] [cursor=pointer]
      - generic [ref=e109]:
        - navigation [ref=e110]:
          - generic [ref=e111]:
            - heading [level=2] [ref=e112]: Dashboards
            - button [ref=e113] [cursor=pointer]:
              - img [ref=e114]:
                - img [ref=e115]
          - generic [ref=e118]:
            - heading [level=3] [ref=e119]: My Dashboards
            - list [ref=e120]:
              - button [ref=e121] [cursor=pointer]:
                - img [ref=e123]:
                  - img [ref=e124]
                - generic [ref=e126]: My Dashboard
              - button [ref=e127] [cursor=pointer]:
                - img [ref=e129]:
                  - img [ref=e130]
                - generic [ref=e132]: wewr
        - generic [ref=e133]:
          - button "Dashboards" [ref=e134] [cursor=pointer]:
            - img [ref=e137]:
              - img [ref=e138]
          - generic "Your primary group for shared dashboards" [ref=e140]: Default
          - generic [ref=e141]:
            - generic [ref=e142] [cursor=pointer]: Active dashboard
            - generic [ref=e143]:
              - generic [ref=e144]:
                - generic "My Dashboard" [ref=e146]:
                  - generic [ref=e147]: My Das
                  - generic [ref=e148]: hboard
                - combobox "Active dashboard" [ref=e149] [cursor=pointer]
              - generic [ref=e150]:
                - button "Clear selected" [ref=e151] [cursor=pointer]:
                  - img [ref=e152]:
                    - img [ref=e153]
                - button [ref=e155] [cursor=pointer]:
                  - img [ref=e157]
          - button "Dashboard menu" [ref=e161] [cursor=pointer]:
            - img [ref=e164]:
              - img [ref=e165]
        - generic [ref=e168]:
          - generic [ref=e169]:
            - link "Files" [ref=e173] [cursor=pointer]:
              - /url: ""
              - img [ref=e174]
              - generic [ref=e176]: Files
            - generic [ref=e179]:
              - generic [ref=e181]:
                - img "Important mail" [ref=e182]
                - heading "Important mail" [level=3] [ref=e183]
              - generic [ref=e186]:
                - list
                - generic [ref=e187]:
                  - generic [ref=e188]:
                    - generic [ref=e191]: "?"
                    - generic [ref=e192]:
                      - heading [level=3] [ref=e193]
                      - paragraph [ref=e194]
                  - generic [ref=e195]:
                    - generic [ref=e198]: "?"
                    - generic [ref=e199]:
                      - heading [level=3] [ref=e200]
                      - paragraph [ref=e201]
                  - generic [ref=e202]:
                    - generic [ref=e205]: "?"
                    - generic [ref=e206]:
                      - heading [level=3] [ref=e207]
                      - paragraph [ref=e208]
                  - generic [ref=e209]:
                    - generic [ref=e212]: "?"
                    - generic [ref=e213]:
                      - heading [level=3] [ref=e214]
                      - paragraph [ref=e215]
                  - generic [ref=e216]:
                    - generic [ref=e219]: "?"
                    - generic [ref=e220]:
                      - heading [level=3] [ref=e221]
                      - paragraph [ref=e222]
                  - generic [ref=e223]:
                    - generic [ref=e226]: "?"
                    - generic [ref=e227]:
                      - heading [level=3] [ref=e228]
                      - paragraph [ref=e229]
                  - generic [ref=e230]:
                    - generic [ref=e233]: "?"
                    - generic [ref=e234]:
                      - heading [level=3] [ref=e235]
                      - paragraph [ref=e236]
            - generic [ref=e239]:
              - generic [ref=e241]:
                - img "Upcoming events" [ref=e242]
                - heading "Upcoming events" [level=3] [ref=e243]
              - generic [ref=e246]:
                - list
                - generic [ref=e247]:
                  - generic [ref=e248]:
                    - generic [ref=e251]: "?"
                    - generic [ref=e252]:
                      - heading [level=3] [ref=e253]
                      - paragraph [ref=e254]
                  - generic [ref=e255]:
                    - generic [ref=e258]: "?"
                    - generic [ref=e259]:
                      - heading [level=3] [ref=e260]
                      - paragraph [ref=e261]
                  - generic [ref=e262]:
                    - generic [ref=e265]: "?"
                    - generic [ref=e266]:
                      - heading [level=3] [ref=e267]
                      - paragraph [ref=e268]
                  - generic [ref=e269]:
                    - generic [ref=e272]: "?"
                    - generic [ref=e273]:
                      - heading [level=3] [ref=e274]
                      - paragraph [ref=e275]
                  - generic [ref=e276]:
                    - generic [ref=e279]: "?"
                    - generic [ref=e280]:
                      - heading [level=3] [ref=e281]
                      - paragraph [ref=e282]
                  - generic [ref=e283]:
                    - generic [ref=e286]: "?"
                    - generic [ref=e287]:
                      - heading [level=3] [ref=e288]
                      - paragraph [ref=e289]
                  - generic [ref=e290]:
                    - generic [ref=e293]: "?"
                    - generic [ref=e294]:
                      - heading [level=3] [ref=e295]
                      - paragraph [ref=e296]
              - button "More events" [ref=e298] [cursor=pointer]:
                - generic [ref=e300]: More events
            - link "Calendar" [ref=e304] [cursor=pointer]:
              - /url: ""
              - img [ref=e305]
              - generic [ref=e307]: Calendar
            - link "Intranet" [ref=e311] [cursor=pointer]:
              - /url: ""
              - img [ref=e312]
              - generic [ref=e314]: Intranet
            - generic [ref=e317]:
              - heading "Overdue Cases" [level=3] [ref=e321]
              - generic [ref=e324]:
                - list
                - note "No open cases" [ref=e325]:
                  - img [ref=e327]:
                    - img [ref=e328]
            - generic [ref=e332]:
              - generic [ref=e334]:
                - heading "My Tasks" [level=3]
              - generic [ref=e338]:
                - list
                - note "No tasks found" [ref=e339]:
                  - img [ref=e341]:
                    - img [ref=e342]
            - generic [ref=e346]:
              - heading "Start case" [level=3] [ref=e350]
              - note "No case types configured" [ref=e354]:
                - img [ref=e356]:
                  - img [ref=e357]
                - paragraph [ref=e359]: Configure case types in Procest admin settings
            - generic [ref=e362]:
              - generic [ref=e364]:
                - img "Document Anonymization" [ref=e365]
                - heading "Document Anonymization" [level=3]
              - generic [ref=e368]:
                - generic [ref=e370] [cursor=pointer]:
                  - img [ref=e371]:
                    - img [ref=e372]
                  - paragraph: Drop files to anonymize
                - link "Open DocuDesk" [ref=e374] [cursor=pointer]:
                  - /url: /apps/docudesk
          - menu [ref=e375]:
            - menuitem "Edit" [ref=e376] [cursor=pointer]
            - menuitem "Remove" [ref=e377] [cursor=pointer]
            - menuitem "Cancel" [ref=e378] [cursor=pointer]
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