# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: image-widget.spec.ts >> image widget >> REQ-IMG-005: upload → preview → save → reload still shows image
- Location: tests/e2e/image-widget.spec.ts:26:6

# Error details

```
TimeoutError: locator.selectOption: Timeout 10000ms exceeded.
Call log:
  - waiting for getByLabel('Widget type')

```

# Page snapshot

```yaml
- generic [ref=e1]:
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
        - generic [ref=e120]:
          - button "Add Widget" [expanded] [active] [ref=e121] [cursor=pointer]
          - menu [ref=e122]:
            - menuitem "Label" [ref=e123] [cursor=pointer]
            - menuitem "Text" [ref=e124] [cursor=pointer]
            - menuitem "Image" [ref=e125] [cursor=pointer]
            - menuitem "Link Button" [ref=e126] [cursor=pointer]
            - menuitem "Nextcloud Widget" [ref=e127] [cursor=pointer]
        - button "Save Layout" [ref=e129] [cursor=pointer]
      - generic [ref=e131]:
        - navigation [ref=e132]:
          - generic [ref=e133]:
            - heading [level=2] [ref=e134]: Dashboards
            - button [ref=e135] [cursor=pointer]:
              - img [ref=e136]:
                - img [ref=e137]
          - generic [ref=e140]:
            - heading [level=3] [ref=e141]: My Dashboards
            - list [ref=e142]:
              - button [ref=e143] [cursor=pointer]:
                - img [ref=e145]:
                  - img [ref=e146]
                - generic [ref=e148]: My Dashboard
              - button [ref=e149] [cursor=pointer]:
                - img [ref=e151]:
                  - img [ref=e152]
                - generic [ref=e154]: wewr
        - generic [ref=e155]:
          - button "Dashboards" [ref=e156] [cursor=pointer]:
            - img [ref=e159]:
              - img [ref=e160]
          - generic "Your primary group for shared dashboards" [ref=e162]: Default
          - generic [ref=e163]:
            - generic [ref=e164] [cursor=pointer]: Active dashboard
            - generic [ref=e165]:
              - generic [ref=e166]:
                - generic "My Dashboard" [ref=e168]:
                  - generic [ref=e169]: My Das
                  - generic [ref=e170]: hboard
                - combobox "Active dashboard" [ref=e171] [cursor=pointer]
              - generic [ref=e172]:
                - button "Clear selected" [ref=e173] [cursor=pointer]:
                  - img [ref=e174]:
                    - img [ref=e175]
                - button [ref=e177] [cursor=pointer]:
                  - img [ref=e179]
          - button "Dashboard menu" [ref=e183] [cursor=pointer]:
            - img [ref=e186]:
              - img [ref=e187]
        - generic [ref=e190]:
          - generic [ref=e191]:
            - link "Files" [ref=e195] [cursor=pointer]:
              - /url: ""
              - img [ref=e196]
              - generic [ref=e198]: Files
            - generic [ref=e201]:
              - generic [ref=e203]:
                - img "Important mail" [ref=e204]
                - heading "Important mail" [level=3] [ref=e205]
              - generic [ref=e208]:
                - list [ref=e209]:
                  - listitem [ref=e210]:
                    - generic "burger@test.local" [ref=e211]:
                      - link "burger@test.local Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e212] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/41
                        - generic [ref=e214]:
                          - heading "burger@test.local" [level=3]
                          - generic "Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)"
                  - listitem [ref=e215]:
                    - generic "leverancier@test.local" [ref=e216]:
                      - link "leverancier@test.local Technische documentatie API-koppeling OpenRegister" [ref=e217] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/40
                        - generic [ref=e219]:
                          - heading "leverancier@test.local" [level=3]
                          - generic "Technische documentatie API-koppeling OpenRegister"
                  - listitem [ref=e220]:
                    - generic "admin@test.local" [ref=e221]:
                      - 'link "admin@test.local URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e222] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/38
                        - generic [ref=e224]:
                          - heading "admin@test.local" [level=3]
                          - 'generic "URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken"'
                  - listitem [ref=e225]:
                    - generic "coordinator@test.local" [ref=e226]:
                      - link "coordinator@test.local Weekplanning team Vergunningen - week 13" [ref=e227] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/39
                        - generic [ref=e229]:
                          - heading "coordinator@test.local" [level=3]
                          - generic "Weekplanning team Vergunningen - week 13"
                  - listitem [ref=e230]:
                    - generic "admin@test.local" [ref=e231]:
                      - 'link "admin@test.local Herinnering: 3 zaken naderen deadline" [ref=e232] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/37
                        - generic [ref=e234]:
                          - heading "admin@test.local" [level=3]
                          - 'generic "Herinnering: 3 zaken naderen deadline"'
                  - listitem [ref=e235]:
                    - generic "coordinator@test.local" [ref=e236]:
                      - 'link "coordinator@test.local FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e237] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/36
                        - generic [ref=e239]:
                          - heading "coordinator@test.local" [level=3]
                          - 'generic "FW: Offerte IT-systeem migratie - ter goedkeuring"'
                - link "More items …" [ref=e240] [cursor=pointer]:
                  - /url: http://localhost:8080/apps/mail/
            - generic [ref=e243]:
              - generic [ref=e245]:
                - img "Upcoming events" [ref=e246]
                - heading "Upcoming events" [level=3] [ref=e247]
              - generic [ref=e250]:
                - list
                - note [ref=e251]:
                  - paragraph [ref=e253]: No upcoming events
              - button "More events" [ref=e255] [cursor=pointer]:
                - generic [ref=e257]: More events
            - link "Calendar" [ref=e261] [cursor=pointer]:
              - /url: ""
              - img [ref=e262]
              - generic [ref=e264]: Calendar
            - link "Intranet" [ref=e268] [cursor=pointer]:
              - /url: ""
              - img [ref=e269]
              - generic [ref=e271]: Intranet
            - generic [ref=e274]:
              - heading "Overdue Cases" [level=3] [ref=e278]
              - generic [ref=e281]:
                - list
                - note "No open cases" [ref=e282]:
                  - img [ref=e284]:
                    - img [ref=e285]
            - generic [ref=e289]:
              - generic [ref=e290]:
                - generic:
                  - heading "My Tasks" [level=3]
              - generic [ref=e293]:
                - generic:
                  - list
                  - note "No tasks found" [ref=e294]:
                    - img [ref=e296]:
                      - img [ref=e297]
            - generic [ref=e301]:
              - heading "Start case" [level=3] [ref=e305]
              - note "No case types configured" [ref=e309]:
                - img [ref=e311]:
                  - img [ref=e312]
                - paragraph [ref=e314]: Configure case types in Procest admin settings
            - generic [ref=e317]:
              - generic [ref=e318]:
                - generic:
                  - img "Document Anonymization" [ref=e319]
                  - heading "Document Anonymization" [level=3]
              - generic [ref=e321]:
                - generic:
                  - generic [ref=e323] [cursor=pointer]:
                    - img [ref=e324]:
                      - img [ref=e325]
                    - paragraph: Drop files to anonymize
                  - link "Open DocuDesk" [ref=e327] [cursor=pointer]:
                    - /url: /apps/docudesk
          - menu [ref=e328]:
            - menuitem "Edit" [ref=e329] [cursor=pointer]
            - menuitem "Remove" [ref=e330] [cursor=pointer]
            - menuitem "Cancel" [ref=e331] [cursor=pointer]
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
  5  |  * Playwright end-to-end test for the `image` widget covering tasks 4.7..4.9
  6  |  * of the `image-widget` OpenSpec change.
  7  |  *
  8  |  * NOTE: Playwright infrastructure is not yet wired up in mydash. This file
  9  |  * is committed alongside the rest of the change so it runs once the cohort-
  10 |  * wide Playwright bootstrap lands. Do not delete — it is the canonical e2e
  11 |  * coverage for REQ-IMG-002, REQ-IMG-003, REQ-IMG-005.
  12 |  */
  13 | 
  14 | import { test, expect } from '@playwright/test'
  15 | import * as path from 'path'
  16 | 
  17 | const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
  18 | 
  19 | test.describe('image widget', () => {
  20 | 	test.beforeEach(async ({ page }) => {
  21 | 		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
  22 | 		// Tests assume the user is already authenticated via Playwright
  23 | 		// storageState; in CI this is set up by the Hydra harness.
  24 | 	})
  25 | 
  26 | 	test('REQ-IMG-005: upload → preview → save → reload still shows image', async ({ page }) => {
  27 | 		await page.getByRole('button', { name: /add widget/i }).click()
> 28 | 		await page.getByLabel('Widget type').selectOption({ label: 'Image' })
     |                                        ^ TimeoutError: locator.selectOption: Timeout 10000ms exceeded.
  29 | 
  30 | 		// Upload a tiny PNG bundled with the test fixtures.
  31 | 		const fileChooserPromise = page.waitForEvent('filechooser')
  32 | 		await page.getByLabel('Upload Image').click()
  33 | 		const fc = await fileChooserPromise
  34 | 		await fc.setFiles(path.join(__dirname, 'fixtures', 'tiny.png'))
  35 | 
  36 | 		// Wait for the upload to complete (form.url populated → preview visible).
  37 | 		const preview = page.locator('.image-form__preview')
  38 | 		await expect(preview).toBeVisible()
  39 | 
  40 | 		await page.getByRole('button', { name: /save|add/i }).click()
  41 | 
  42 | 		// Verify the rendered widget appears on the dashboard.
  43 | 		const placement = page.locator('.image-widget__img')
  44 | 		await expect(placement).toBeVisible()
  45 | 
  46 | 		// Reload and verify persistence.
  47 | 		await page.reload()
  48 | 		await expect(page.locator('.image-widget__img')).toBeVisible()
  49 | 	})
  50 | 
  51 | 	test('REQ-IMG-003: external URL with click-through opens new tab', async ({ context, page }) => {
  52 | 		await page.getByRole('button', { name: /add widget/i }).click()
  53 | 		await page.getByLabel('Widget type').selectOption({ label: 'Image' })
  54 | 		await page.getByLabel('Or enter Image URL').fill('https://placehold.co/200x200.png')
  55 | 		await page.getByLabel('Link (optional)').fill('https://example.com')
  56 | 		await page.getByRole('button', { name: /save|add/i }).click()
  57 | 
  58 | 		const cell = page.locator('.image-widget')
  59 | 		await expect(cell).toBeVisible()
  60 | 
  61 | 		// Click triggers a new tab via window.open(..., '_blank',
  62 | 		// 'noopener,noreferrer'). Wait for the new page on the context.
  63 | 		const popupPromise = context.waitForEvent('page')
  64 | 		await cell.click()
  65 | 		const popup = await popupPromise
  66 | 		await popup.waitForLoadState()
  67 | 		expect(popup.url()).toMatch(/example\.com/)
  68 | 	})
  69 | 
  70 | 	test('REQ-IMG-002: empty-URL cell shows camera placeholder and ignores clicks', async ({ context, page }) => {
  71 | 		await page.getByRole('button', { name: /add widget/i }).click()
  72 | 		await page.getByLabel('Widget type').selectOption({ label: 'Image' })
  73 | 		// Force-save with empty URL by typing whitespace then clearing — the
  74 | 		// validator blocks save, so for the placeholder rendering test we use
  75 | 		// the API to seed a placement directly. Skipped here as a TODO once
  76 | 		// the cohort-wide test fixtures expose a programmatic seed helper.
  77 | 		test.skip(true, 'Programmatic seed helper not yet available; placeholder rendering covered by Vitest unit test.')
  78 | 	})
  79 | })
  80 | 
```