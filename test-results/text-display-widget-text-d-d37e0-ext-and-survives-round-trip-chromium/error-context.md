# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: text-display-widget.spec.ts >> text-display widget >> add → fill → save → reload renders text and survives round-trip
- Location: tests/e2e/text-display-widget.spec.ts:25:6

# Error details

```
Error: locator.fill: Error: strict mode violation: getByLabel('Text') resolved to 3 elements:
    1) <button type="button" data-v-8a49b234="" data-v-2eb04699="" aria-expanded="false" aria-haspopup="dialog" aria-label="Pick text color" class="button-vue button-vue--size-normal button-vue--icon-and-text button-vue--vue-tertiary button-vue--tertiary">…</button> aka getByLabel('Pick text color')
    2) <textarea rows="4" data-v-1adfa618="" required="required" class="text-display-form__textarea"></textarea> aka getByRole('textbox', { name: 'Text', exact: true })
    3) <input type="color" data-v-1adfa618="" class="text-display-form__color"/> aka getByRole('textbox', { name: 'Text Color' })

Call log:
  - waiting for getByLabel('Text')

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
            - generic [ref=e188]:
              - button "Edit tile" [ref=e189] [cursor=pointer]
              - link "Files" [ref=e191] [cursor=pointer]:
                - /url: ""
                - img [ref=e192]
                - generic [ref=e194]: Files
            - generic [ref=e197]:
              - generic [ref=e198]:
                - generic:
                  - img "Important mail" [ref=e199]
                  - heading "Important mail" [level=3]
                - button "Edit widget" [ref=e201] [cursor=pointer]:
                  - img [ref=e204]:
                    - img [ref=e205]
              - generic [ref=e208]:
                - generic:
                  - list:
                    - listitem:
                      - generic "burger@test.local":
                        - link "burger@test.local Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e209] [cursor=pointer]:
                          - /url: http://localhost:8080/apps/mail/box/4/thread/41
                          - generic [ref=e211]:
                            - heading "burger@test.local" [level=3]
                            - generic "Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)"
                    - listitem:
                      - generic "leverancier@test.local":
                        - link "leverancier@test.local Technische documentatie API-koppeling OpenRegister" [ref=e212] [cursor=pointer]:
                          - /url: http://localhost:8080/apps/mail/box/4/thread/40
                          - generic [ref=e214]:
                            - heading "leverancier@test.local" [level=3]
                            - generic "Technische documentatie API-koppeling OpenRegister"
                    - listitem:
                      - generic "admin@test.local":
                        - 'link "admin@test.local URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e215] [cursor=pointer]':
                          - /url: http://localhost:8080/apps/mail/box/4/thread/38
                          - generic [ref=e217]:
                            - heading "admin@test.local" [level=3]
                            - 'generic "URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken"'
                    - listitem:
                      - generic "coordinator@test.local":
                        - link "coordinator@test.local Weekplanning team Vergunningen - week 13" [ref=e218] [cursor=pointer]:
                          - /url: http://localhost:8080/apps/mail/box/4/thread/39
                          - generic [ref=e220]:
                            - heading "coordinator@test.local" [level=3]
                            - generic "Weekplanning team Vergunningen - week 13"
                    - listitem:
                      - generic "admin@test.local":
                        - 'link "admin@test.local Herinnering: 3 zaken naderen deadline" [ref=e221] [cursor=pointer]':
                          - /url: http://localhost:8080/apps/mail/box/4/thread/37
                          - generic [ref=e223]:
                            - heading "admin@test.local" [level=3]
                            - 'generic "Herinnering: 3 zaken naderen deadline"'
                    - listitem:
                      - generic "coordinator@test.local":
                        - 'link "coordinator@test.local FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e224] [cursor=pointer]':
                          - /url: http://localhost:8080/apps/mail/box/4/thread/36
                          - generic [ref=e226]:
                            - heading "coordinator@test.local" [level=3]
                            - 'generic "FW: Offerte IT-systeem migratie - ter goedkeuring"'
                  - link "More items …":
                    - /url: http://localhost:8080/apps/mail/
            - generic [ref=e229]:
              - button "Edit tile" [ref=e230] [cursor=pointer]
              - link "Calendar" [ref=e232] [cursor=pointer]:
                - /url: ""
                - img [ref=e233]
                - generic [ref=e235]: Calendar
            - generic [ref=e238]:
              - generic [ref=e239]:
                - generic:
                  - img "Upcoming events" [ref=e240]
                  - heading "Upcoming events" [level=3]
                - button "Edit widget" [ref=e242] [cursor=pointer]:
                  - img [ref=e245]:
                    - img [ref=e246]
              - generic [ref=e249]:
                - generic:
                  - list
                  - note [ref=e250]:
                    - paragraph [ref=e252]: No upcoming events
              - button "More events" [ref=e254] [cursor=pointer]:
                - generic [ref=e256]: More events
            - generic [ref=e259]:
              - button "Edit tile" [ref=e260] [cursor=pointer]
              - link "Intranet" [ref=e262] [cursor=pointer]:
                - /url: ""
                - img [ref=e263]
                - generic [ref=e265]: Intranet
            - generic [ref=e268]:
              - generic [ref=e269]:
                - generic:
                  - heading "Overdue Cases" [level=3]
                - button "Edit widget" [ref=e272] [cursor=pointer]:
                  - img [ref=e275]:
                    - img [ref=e276]
              - generic [ref=e279]:
                - generic:
                  - list
                  - note "No open cases" [ref=e280]:
                    - img [ref=e282]:
                      - img [ref=e283]
            - generic [ref=e287]:
              - generic [ref=e288]:
                - generic:
                  - img "Document Anonymization" [ref=e289]
                  - heading "Document Anonymization" [level=3]
                - button "Edit widget" [ref=e291] [cursor=pointer]:
                  - img [ref=e294]:
                    - img [ref=e295]
              - generic [ref=e298]:
                - generic:
                  - generic [ref=e300] [cursor=pointer]:
                    - img [ref=e301]:
                      - img [ref=e302]
                    - paragraph: Drop files to anonymize
                  - link "Open DocuDesk" [ref=e304] [cursor=pointer]:
                    - /url: /apps/docudesk
            - generic [ref=e307]:
              - generic [ref=e308]:
                - generic:
                  - heading "My Tasks" [level=3]
                - button "Edit widget" [ref=e311] [cursor=pointer]:
                  - img [ref=e314]:
                    - img [ref=e315]
              - generic [ref=e318]:
                - generic:
                  - list
                  - note "No tasks found" [ref=e319]:
                    - img [ref=e321]:
                      - img [ref=e322]
            - generic [ref=e326]:
              - generic [ref=e327]:
                - generic:
                  - heading "Start case" [level=3]
                - button "Edit widget" [ref=e330] [cursor=pointer]:
                  - img [ref=e333]:
                    - img [ref=e334]
              - note "No case types configured" [ref=e339]:
                - img [ref=e341]:
                  - img [ref=e342]
                - paragraph [ref=e344]: Configure case types in Procest admin settings
          - menu [ref=e345]:
            - menuitem "Edit" [ref=e346] [cursor=pointer]
            - menuitem "Remove" [ref=e347] [cursor=pointer]
            - menuitem "Cancel" [ref=e348] [cursor=pointer]
  - img
  - img
  - dialog "Add Widget" [ref=e349]:
    - heading "Add Widget" [level=2] [ref=e351]
    - generic [ref=e353]:
      - dialog "Add Widget" [ref=e355]:
        - heading "Add Widget" [level=2] [ref=e356]
        - generic [ref=e358]:
          - generic [ref=e359] [cursor=pointer]:
            - text: Text
            - textbox "Text" [active] [ref=e360]
          - generic [ref=e362]:
            - textbox "Font Size" [ref=e363] [cursor=pointer]:
              - /placeholder: 14px
              - text: 14px
            - generic: Font Size
          - generic [ref=e364] [cursor=pointer]:
            - text: Text Color
            - textbox "Text Color" [ref=e365]: "#000000"
          - generic [ref=e366] [cursor=pointer]:
            - text: Background Color
            - textbox "Background Color" [ref=e367]: "#ffffff"
          - generic [ref=e368]:
            - generic [ref=e369] [cursor=pointer]: Alignment
            - generic [ref=e370]:
              - generic [ref=e371]:
                - generic "left" [ref=e373]:
                  - generic [ref=e374]: left
                - combobox "Alignment" [ref=e375] [cursor=pointer]
              - button [ref=e377] [cursor=pointer]:
                - img [ref=e379]
        - generic [ref=e381]:
          - button "Cancel" [ref=e382] [cursor=pointer]:
            - generic [ref=e384]: Cancel
          - button "Add" [disabled] [ref=e385]:
            - generic [ref=e387]: Add
      - button "Close" [ref=e388] [cursor=pointer]:
        - img [ref=e391]:
          - img [ref=e392]
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
> 33 | 		await page.getByLabel('Text').fill('Hello <b>world</b>')
     |                                 ^ Error: locator.fill: Error: strict mode violation: getByLabel('Text') resolved to 3 elements:
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
  86 | 		await expect(empty).toBeVisible()
  87 | 		await expect(empty).toHaveText('No text content')
  88 | 	})
  89 | })
  90 | 
```