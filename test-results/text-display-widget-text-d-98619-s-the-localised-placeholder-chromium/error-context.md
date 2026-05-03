# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: text-display-widget.spec.ts >> text-display widget >> REQ-TXT-003: empty-text widget shows the localised placeholder
- Location: tests/e2e/text-display-widget.spec.ts:69:6

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: expect(locator).toBeVisible() failed

Locator:  locator('.text-display-widget__placeholder').first()
Expected: visible
Received: undefined

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for locator('.text-display-widget__placeholder').first()

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
      - button "Search contacts" [ref=e84] [cursor=pointer]:
        - img [ref=e87]:
          - img [ref=e88]
      - navigation "Settings menu" [ref=e90]:
        - button "Settings menu" [ref=e91] [cursor=pointer]:
          - img [ref=e95]:
            - img [ref=e96]
        - generic [ref=e98]: Avatar of admin — Online
  - generic [ref=e99]:
    - heading "Nextcloud" [level=1] [ref=e100]
    - generic [ref=e102]:
      - generic [ref=e103]:
        - button "Open menu" [ref=e104] [cursor=pointer]
        - generic [ref=e108]: My Dashboard
      - generic [ref=e109]:
        - button "Add Widget" [ref=e112] [cursor=pointer]
        - button "Save Layout" [ref=e114] [cursor=pointer]
      - generic [ref=e116]:
        - navigation [ref=e117]:
          - generic [ref=e118]:
            - heading [level=2] [ref=e119]: Dashboards
            - button [ref=e120] [cursor=pointer]:
              - img [ref=e121]:
                - img [ref=e122]
          - generic [ref=e125]:
            - heading [level=3] [ref=e126]: My Dashboards
            - list [ref=e127]:
              - button [ref=e128] [cursor=pointer]:
                - img [ref=e130]:
                  - img [ref=e131]
                - generic [ref=e133]: My Dashboard
              - button [ref=e134] [cursor=pointer]:
                - img [ref=e136]:
                  - img [ref=e137]
                - generic [ref=e139]: wewr
        - generic [ref=e140]:
          - button "Dashboards" [ref=e141] [cursor=pointer]:
            - img [ref=e144]:
              - img [ref=e145]
          - generic "Your primary group for shared dashboards" [ref=e147]: Default
          - generic [ref=e148]:
            - generic [ref=e149] [cursor=pointer]: Active dashboard
            - generic [ref=e150]:
              - combobox "Active dashboard" [ref=e152] [cursor=pointer]
              - button [ref=e154] [cursor=pointer]:
                - img [ref=e156]
          - button "Dashboard menu" [ref=e160] [cursor=pointer]:
            - img [ref=e163]:
              - img [ref=e164]
        - note "No dashboard yet" [ref=e168]:
          - img [ref=e170]:
            - img [ref=e171]
          - generic [ref=e173]: No dashboard yet
          - paragraph [ref=e174]: Personal dashboards are not enabled by your administrator
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
  5  |  * Playwright end-to-end test for the `text` widget covering tasks 7.1..7.3
  6  |  * of the `text-display-widget` OpenSpec change.
  7  |  *
  8  |  * NOTE: Playwright infrastructure is not yet wired up in mydash. This file
  9  |  * is committed alongside the rest of the change so it runs once the cohort-
  10 |  * wide Playwright bootstrap lands. Do not delete — it is the canonical e2e
  11 |  * coverage for REQ-TXT-001, REQ-TXT-003, REQ-TXT-004.
  12 |  */
  13 | 
  14 | import { test, expect } from '@playwright/test'
  15 | 
  16 | const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
  17 | 
  18 | test.describe('text-display widget', () => {
  19 | 	test.beforeEach(async ({ page }) => {
  20 | 		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
  21 | 		// Tests assume the user is already authenticated via Playwright
  22 | 		// storageState; in CI this is set up by the Hydra harness.
  23 | 	})
  24 | 
  25 | 	test('add → fill → save → reload renders text and survives round-trip', async ({ page }) => {
  26 | 		// 1. Open Add Widget modal
  27 | 		await page.getByRole('button', { name: /add widget/i }).click()
  28 | 
  29 | 		// 2. Pick the Text type
  30 | 		await page.getByText('Text', { exact: true }).click()
  31 | 
  32 | 		// 3. Fill the form
  33 | 		await page.getByLabel('Text').fill('Hello <b>world</b>')
  34 | 		await page.getByLabel('Font Size').fill('20px')
  35 | 
  36 | 		// 4. Save
  37 | 		await page.getByRole('button', { name: /save|add/i }).click()
  38 | 
  39 | 		// 5. Verify the rendered widget appears on the dashboard with the
  40 | 		//    sanitised HTML in place — <b> survives, no <script> ever could.
  41 | 		const placement = page.locator('.text-display-widget').filter({ hasText: 'Hello world' })
  42 | 		await expect(placement).toBeVisible()
  43 | 		await expect(placement.locator('b')).toHaveCount(1)
  44 | 
  45 | 		// 6. Reload the page — the placement must still render the same content
  46 | 		await page.reload()
  47 | 		const reloaded = page.locator('.text-display-widget').filter({ hasText: 'Hello world' })
  48 | 		await expect(reloaded).toBeVisible()
  49 | 	})
  50 | 
  51 | 	test('REQ-TXT-004: edit mode pre-fills, change → save → renders new values', async ({ page }) => {
  52 | 		// Assumes the previous test left a placement; if not, re-create.
  53 | 		const placement = page.locator('.text-display-widget').first()
  54 | 		await placement.click({ button: 'right' })
  55 | 		await page.getByRole('menuitem', { name: /edit/i }).click()
  56 | 
  57 | 		await expect(page.getByLabel('Text')).not.toBeEmpty()
  58 | 
  59 | 		await page.getByLabel('Text').fill('Updated content')
  60 | 		await page.getByLabel('Font Size').fill('32px')
  61 | 		await page.getByRole('button', { name: /save|add/i }).click()
  62 | 
  63 | 		const updated = page.locator('.text-display-widget').filter({ hasText: 'Updated content' })
  64 | 		await expect(updated).toBeVisible()
  65 | 		const innerStyle = await updated.locator('.text-display-widget__content').getAttribute('style')
  66 | 		expect(innerStyle || '').toContain('font-size: 32px')
  67 | 	})
  68 | 
  69 | 	test('REQ-TXT-003: empty-text widget shows the localised placeholder', async ({ page }) => {
  70 | 		// Add a text widget but leave the text blank — the modal should
  71 | 		// keep the Add button disabled until validate() returns []. To
  72 | 		// exercise the placeholder branch we create the placement directly
  73 | 		// via the API and then assert the rendered fallback.
  74 | 		await page.evaluate(async () => {
  75 | 			await fetch('/index.php/apps/mydash/api/widget-placements', {
  76 | 				method: 'POST',
  77 | 				headers: { 'Content-Type': 'application/json' },
  78 | 				body: JSON.stringify({
  79 | 					type: 'text',
  80 | 					styleConfig: { type: 'text', content: { text: '' } },
  81 | 				}),
  82 | 			})
  83 | 		})
  84 | 		await page.reload()
  85 | 		const empty = page.locator('.text-display-widget__placeholder').first()
> 86 | 		await expect(empty).toBeVisible()
     |                       ^ Error: expect(locator).toBeVisible() failed
  87 | 		await expect(empty).toHaveText('No text content')
  88 | 	})
  89 | })
  90 | 
```