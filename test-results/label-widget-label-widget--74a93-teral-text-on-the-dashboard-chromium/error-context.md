# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: label-widget.spec.ts >> label widget >> REQ-LBL-001: pasted HTML renders as literal text on the dashboard
- Location: tests/e2e/label-widget.spec.ts:59:6

# Error details

```
Error: locator.click: Error: strict mode violation: getByRole('button', { name: /save|add/i }) resolved to 3 elements:
    1) <button type="button" data-v-04b2d850="" aria-haspopup="true" class="workspace-shell__add-button">↵⇆⇆⇆⇆⇆Add Widget↵⇆⇆⇆⇆</button> aka getByRole('button', { name: 'Add Widget' })
    2) <button type="button" data-v-04b2d850="" class="workspace-shell__save-button">↵⇆⇆⇆⇆Save Layout↵⇆⇆⇆</button> aka getByRole('button', { name: 'Save Layout' })
    3) <button title="" type="button" data-v-8a49b234="" data-v-0860ea72="" class="button-vue button-vue--size-normal button-vue--text-only button-vue--vue-primary">…</button> aka getByRole('button', { name: 'Add', exact: true })

Call log:
  - waiting for getByRole('button', { name: /save|add/i })

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
                - generic [ref=e199]:
                  - img "Important mail" [ref=e200]
                  - heading "Important mail" [level=3]
                - button "Edit widget" [ref=e202] [cursor=pointer]:
                  - img [ref=e205]:
                    - img [ref=e206]
              - generic [ref=e210]:
                - list [ref=e211]:
                  - listitem [ref=e212]:
                    - generic "burger@test.local" [ref=e213]:
                      - link "burger@test.local Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)" [ref=e214] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/41
                        - generic [ref=e216]:
                          - heading "burger@test.local" [level=3]
                          - generic "Bijlagen bij aanvraag omgevingsvergunning - ZK-2026-0142 (dakkapel Kerkstraat 42)"
                  - listitem [ref=e217]:
                    - generic "leverancier@test.local" [ref=e218]:
                      - link "leverancier@test.local Technische documentatie API-koppeling OpenRegister" [ref=e219] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/40
                        - generic [ref=e221]:
                          - heading "leverancier@test.local" [level=3]
                          - generic "Technische documentatie API-koppeling OpenRegister"
                  - listitem [ref=e222]:
                    - generic "admin@test.local" [ref=e223]:
                      - 'link "admin@test.local URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken" [ref=e224] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/38
                        - generic [ref=e226]:
                          - heading "admin@test.local" [level=3]
                          - 'generic "URGENT: Klacht kapvergunning ZK-2026-0034 - direct oppakken"'
                  - listitem [ref=e227]:
                    - generic "coordinator@test.local" [ref=e228]:
                      - link "coordinator@test.local Weekplanning team Vergunningen - week 13" [ref=e229] [cursor=pointer]:
                        - /url: http://localhost:8080/apps/mail/box/4/thread/39
                        - generic [ref=e231]:
                          - heading "coordinator@test.local" [level=3]
                          - generic "Weekplanning team Vergunningen - week 13"
                  - listitem [ref=e232]:
                    - generic "admin@test.local" [ref=e233]:
                      - 'link "admin@test.local Herinnering: 3 zaken naderen deadline" [ref=e234] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/37
                        - generic [ref=e236]:
                          - heading "admin@test.local" [level=3]
                          - 'generic "Herinnering: 3 zaken naderen deadline"'
                  - listitem [ref=e237]:
                    - generic "coordinator@test.local" [ref=e238]:
                      - 'link "coordinator@test.local FW: Offerte IT-systeem migratie - ter goedkeuring" [ref=e239] [cursor=pointer]':
                        - /url: http://localhost:8080/apps/mail/box/4/thread/36
                        - generic [ref=e241]:
                          - heading "coordinator@test.local" [level=3]
                          - 'generic "FW: Offerte IT-systeem migratie - ter goedkeuring"'
                - link "More items …" [ref=e242] [cursor=pointer]:
                  - /url: http://localhost:8080/apps/mail/
            - generic [ref=e245]:
              - generic [ref=e246]:
                - generic [ref=e247]:
                  - img "Upcoming events" [ref=e248]
                  - heading "Upcoming events" [level=3]
                - button "Edit widget" [ref=e250] [cursor=pointer]:
                  - img [ref=e253]:
                    - img [ref=e254]
              - generic [ref=e258]:
                - list
                - note [ref=e259]:
                  - paragraph [ref=e261]: No upcoming events
              - button "More events" [ref=e263] [cursor=pointer]:
                - generic [ref=e265]: More events
            - generic [ref=e268]:
              - button "Edit tile" [ref=e269] [cursor=pointer]
              - link "Calendar" [ref=e271] [cursor=pointer]:
                - /url: ""
                - img [ref=e272]
                - generic [ref=e274]: Calendar
            - generic [ref=e277]:
              - button "Edit tile" [ref=e278] [cursor=pointer]
              - link "Intranet" [ref=e280] [cursor=pointer]:
                - /url: ""
                - img [ref=e281]
                - generic [ref=e283]: Intranet
            - generic [ref=e286]:
              - generic [ref=e287]:
                - generic [ref=e288]:
                  - heading "Overdue Cases" [level=3]
                - button "Edit widget" [ref=e291] [cursor=pointer]:
                  - img [ref=e294]:
                    - img [ref=e295]
              - generic [ref=e299]:
                - list
                - note "No open cases" [ref=e300]:
                  - img [ref=e302]:
                    - img [ref=e303]
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
                - generic [ref=e328]:
                  - heading "Start case" [level=3]
                - button "Edit widget" [ref=e331] [cursor=pointer]:
                  - img [ref=e334]:
                    - img [ref=e335]
              - note "No case types configured" [ref=e340]:
                - img [ref=e342]:
                  - img [ref=e343]
                - paragraph [ref=e345]: Configure case types in Procest admin settings
            - generic [ref=e348]:
              - generic [ref=e349]:
                - generic:
                  - img "Document Anonymization" [ref=e350]
                  - heading "Document Anonymization" [level=3]
                - button "Edit widget" [ref=e352] [cursor=pointer]:
                  - img [ref=e355]:
                    - img [ref=e356]
              - generic [ref=e359]:
                - generic:
                  - generic [ref=e361] [cursor=pointer]:
                    - img [ref=e362]:
                      - img [ref=e363]
                    - paragraph: Drop files to anonymize
                  - link "Open DocuDesk" [ref=e365] [cursor=pointer]:
                    - /url: /apps/docudesk
          - menu [ref=e366]:
            - menuitem "Edit" [ref=e367] [cursor=pointer]
            - menuitem "Remove" [ref=e368] [cursor=pointer]
            - menuitem "Cancel" [ref=e369] [cursor=pointer]
  - img
  - img
  - dialog "Add Widget" [ref=e370]:
    - heading "Add Widget" [level=2] [ref=e372]
    - generic [ref=e374]:
      - dialog "Add Widget" [ref=e376]:
        - heading "Add Widget" [level=2] [ref=e377]
        - generic [ref=e379]:
          - generic [ref=e381]:
            - textbox "Label text" [active] [ref=e382]: <b>HTML</b>
            - generic: Label text
          - generic [ref=e384]:
            - textbox "Font size" [ref=e385] [cursor=pointer]:
              - /placeholder: 16px
              - text: 16px
            - generic: Font size
          - generic [ref=e386] [cursor=pointer]:
            - text: Color
            - textbox "Color" [ref=e387]: "#000000"
          - generic [ref=e388] [cursor=pointer]:
            - text: Background color
            - textbox "Background color" [ref=e389]: "#ffffff"
          - generic [ref=e390]:
            - generic [ref=e391] [cursor=pointer]: Font Weight
            - generic [ref=e392]:
              - generic [ref=e393]:
                - generic "bold" [ref=e395]:
                  - generic [ref=e396]: bold
                - combobox "Font Weight" [ref=e397] [cursor=pointer]
              - button [ref=e399] [cursor=pointer]:
                - img [ref=e401]
          - generic [ref=e403]:
            - generic [ref=e404] [cursor=pointer]: Alignment
            - generic [ref=e405]:
              - generic [ref=e406]:
                - generic "center" [ref=e408]:
                  - generic [ref=e409]: center
                - combobox "Alignment" [ref=e410] [cursor=pointer]
              - button [ref=e412] [cursor=pointer]:
                - img [ref=e414]
        - generic [ref=e416]:
          - button "Cancel" [ref=e417] [cursor=pointer]:
            - generic [ref=e419]: Cancel
          - button "Add" [ref=e420] [cursor=pointer]:
            - generic [ref=e422]: Add
      - button "Close" [ref=e423] [cursor=pointer]:
        - img [ref=e426]:
          - img [ref=e427]
  - img
```

# Test source

```ts
  1  | /*
  2  |  * SPDX-FileCopyrightText: 2026 MyDash Contributors
  3  |  * SPDX-License-Identifier: AGPL-3.0-or-later
  4  |  *
  5  |  * Playwright end-to-end test for the `label` widget covering tasks 6.1..6.3
  6  |  * of the `label-widget` OpenSpec change.
  7  |  *
  8  |  * NOTE: Playwright infrastructure is not yet wired up in mydash. This file
  9  |  * is committed alongside the rest of the change so it runs once the cohort-
  10 |  * wide Playwright bootstrap lands. Do not delete — it is the canonical e2e
  11 |  * coverage for REQ-LBL-001, REQ-LBL-005, REQ-LBL-007.
  12 |  */
  13 | 
  14 | import { test, expect } from '@playwright/test'
  15 | 
  16 | const NEXTCLOUD_URL = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
  17 | 
  18 | test.describe('label widget', () => {
  19 | 	test.beforeEach(async ({ page }) => {
  20 | 		await page.goto(`${NEXTCLOUD_URL}/index.php/apps/mydash`)
  21 | 		// Tests assume the user is already authenticated via Playwright
  22 | 		// storageState; in CI this is set up by the Hydra harness.
  23 | 	})
  24 | 
  25 | 	test('add → fill → save → reopen round-trips all six fields', async ({ page }) => {
  26 | 		// 1. Open Add Widget modal
  27 | 		await page.getByRole('button', { name: /add widget/i }).click()
  28 | 
  29 | 		// 2. Pick the Label type
  30 | 		await page.getByText('Label', { exact: true }).click()
  31 | 
  32 | 		// 3. Fill the form
  33 | 		await page.getByLabel('Label text').fill('Sales Q4')
  34 | 		await page.getByLabel('Font size').fill('24px')
  35 | 		// Color picker assertions are intentionally lenient — different
  36 | 		// browsers render <input type="color"> differently.
  37 | 		await page.locator('input[type="color"]').first().evaluate((el: HTMLInputElement) => {
  38 | 			el.value = '#ff0000'
  39 | 			el.dispatchEvent(new Event('input', { bubbles: true }))
  40 | 		})
  41 | 
  42 | 		// 4. Save
  43 | 		await page.getByRole('button', { name: /save|add/i }).click()
  44 | 
  45 | 		// 5. Verify the rendered widget appears on the dashboard
  46 | 		const placement = page.locator('.label-widget').filter({ hasText: 'Sales Q4' })
  47 | 		await expect(placement).toBeVisible()
  48 | 
  49 | 		// 6. Reopen in edit mode and verify all six fields round-trip
  50 | 		await placement.click({ button: 'right' })
  51 | 		await page.getByRole('menuitem', { name: /edit/i }).click()
  52 | 
  53 | 		await expect(page.getByLabel('Label text')).toHaveValue('Sales Q4')
  54 | 		await expect(page.getByLabel('Font size')).toHaveValue('24px')
  55 | 		const colorInput = page.locator('input[type="color"]').first()
  56 | 		await expect(colorInput).toHaveValue('#ff0000')
  57 | 	})
  58 | 
  59 | 	test('REQ-LBL-001: pasted HTML renders as literal text on the dashboard', async ({ page }) => {
  60 | 		await page.getByRole('button', { name: /add widget/i }).click()
  61 | 		await page.getByText('Label', { exact: true }).click()
  62 | 		await page.getByLabel('Label text').fill('<b>HTML</b>')
> 63 | 		await page.getByRole('button', { name: /save|add/i }).click()
     |                                                         ^ Error: locator.click: Error: strict mode violation: getByRole('button', { name: /save|add/i }) resolved to 3 elements:
  64 | 
  65 | 		const placement = page.locator('.label-widget').filter({ hasText: '<b>HTML</b>' })
  66 | 		await expect(placement).toBeVisible()
  67 | 
  68 | 		// Critical XSS check: there MUST NOT be a <b> element generated from
  69 | 		// the user's input inside the placement.
  70 | 		await expect(placement.locator('b')).toHaveCount(0)
  71 | 	})
  72 | })
  73 | 
```