/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the single link button
 * (openspec/specs/link-button-widget, REQ-LBN-001..007), written for the
 * e2e-coverage-programme change.
 *
 * The admin gets a dashboard of their own. Each test seeds the placement its
 * scenario names through the API, opens LaunchPad, and clicks or hovers the
 * rendered button the way a person does. The createFile endpoint is driven
 * for real where a scenario is about what lands in Files, and answered by a
 * route stub where the scenario fixes the response (`openfile=42`) or needs a
 * request held in flight.
 *
 * The list mode, the edit form and the backward-compatibility scenarios live
 * in `link-button-widget-list.spec.ts` and `link-button-widget-form.spec.ts`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e link-button-widget::external-link-opens-in-new-tab
 *   @e2e link-button-widget::click-in-edit-mode-is-suppressed
 *   @e2e link-button-widget::disabled-while-action-is-in-flight
 *   @e2e link-button-widget::custom-url-icon
 *   @e2e link-button-widget::no-icon
 *   @e2e link-button-widget::document-modal-opens-with-prefilled-name
 *   @e2e link-button-widget::create-posts-and-opens-result
 *   @e2e link-button-widget::an-existing-file-is-not-replaced-without-a-warning
 *   @e2e link-button-widget::empty-filename-disables-create
 *   @e2e link-button-widget::path-traversal-rejected
 *   @e2e link-button-widget::disallowed-extension-rejected
 *   @e2e link-button-widget::existing-file-overwritten
 *   @e2e link-button-widget::unknown-action-id-warns-but-does-not-crash
 *   @e2e link-button-widget::theme-defaults
 *   @e2e link-button-widget::hover-lift-effect
 *
 * @spec openspec/specs/link-button-widget/spec.md
 */

import type { APIRequestContext, Request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	API,
	BUTTON,
	collectPopups,
	davDelete,
	davFileId,
	davGet,
	davPut,
	liftOf,
	MODAL,
	openWithWidget,
	removePlacements,
	seedLink,
	stubHost,
} from './support/linkButton.ts'

const RUN = Date.now()
const CREATE = '**/apps/launchpad/api/files/create'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const seeded: number[] = []

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Link Button ${RUN}`)
})

test.afterEach(async () => {
	await removePlacements(api, seeded)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Seed a placement on this file's dashboard and remember it for cleanup.
 *
 * @param content the placement content.
 * @param box optional grid box.
 * @param box.gridX column.
 * @param box.gridY row.
 * @return the placement id.
 */
async function place(
	content: Record<string, unknown>,
	box: { gridX?: number; gridY?: number } = {},
): Promise<number> {
	const id = await seedLink(api, dashboard!.id, content, box)
	seeded.push(id)
	return id
}

test.describe('link button: actions (REQ-LBN-001)', () => {
	// @e2e link-button-widget::external-link-opens-in-new-tab
	test('an external link opens its URL in a new tab with no opener and no referrer', async ({
		context,
		page,
	}) => {
		await stubHost(context, 'https://example.com')
		const id = await place({ actionType: 'external', url: 'https://example.com', label: 'Docs' })
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)
		await expect(button).toHaveText('Docs')

		const popupPromise = context.waitForEvent('page')
		await button.click()
		const popup = await popupPromise
		await popup.waitForLoadState()

		expect(popup.url()).toBe('https://example.com/')
		// '_blank' + 'noopener,noreferrer': a new browsing context that can
		// neither reach back into LaunchPad nor learn where it came from.
		expect(await popup.evaluate(() => window.opener === null)).toBe(true)
		expect(await popup.evaluate(() => document.referrer)).toBe('')
		// The dashboard itself did not navigate away.
		expect(page.url()).toContain('/apps/launchpad')
		await popup.close()
	})

	// @e2e link-button-widget::click-in-edit-mode-is-suppressed
	test('in edit mode, clicking a link button opens nothing, posts nothing and shows no modal', async ({
		context,
		page,
	}) => {
		await stubHost(context, 'https://example.com')
		const external = await place({ actionType: 'external', url: 'https://example.com', label: 'Docs' })
		const createFile = await place(
			{ actionType: 'createFile', url: 'docx', label: 'New report' },
			{ gridX: 6 },
		)
		await openWithWidget(page, external)
		await expect(page.locator(`[data-placement-id="${createFile}"] ${BUTTON}`)).toBeVisible()
		await enterEditMode(page)

		const popups = collectPopups(context)
		const creates: Request[] = []
		page.on('request', (r) => {
			if (r.url().includes('/api/files/create')) {
				creates.push(r)
			}
		})

		await page.locator(`[data-placement-id="${external}"] ${BUTTON}`).click()
		await page.locator(`[data-placement-id="${createFile}"] ${BUTTON}`).click()
		// Give a popup or a modal the time it would take to appear.
		await page.waitForTimeout(1_500)

		expect(popups.map((p) => p.url()), 'no tab may open in edit mode').toEqual([])
		expect(creates.length, 'no file-create call may fire in edit mode').toBe(0)
		await expect(page.locator(MODAL)).toHaveCount(0)
		// Still in edit mode: the clicks were absorbed, not acted on.
		await expect(page.locator('.launchpad-edit-mode')).toBeVisible()
	})

	// @e2e link-button-widget::disabled-while-action-is-in-flight
	test('while the create request is in flight, Create is disabled and a second click sends nothing', async ({
		context,
		page,
	}) => {
		await stubHost(context, 'https://nc')
		const id = await place({ actionType: 'createFile', url: 'docx', label: 'New report' })

		let release!: () => void
		const held = new Promise<void>((resolve) => {
			release = resolve
		})
		let calls = 0
		await page.route(CREATE, async (route) => {
			calls += 1
			await held
			await route.fulfill({
				json: { status: 'success', fileId: 1, url: 'https://nc/index.php/apps/files/?openfile=1' },
			})
		})

		const widget = await openWithWidget(page, id)
		await widget.locator(BUTTON).click()
		const modal = page.locator(MODAL)
		await expect(modal).toBeVisible()
		await modal.locator('.link-button-host__modal-input').fill(`inflight-${RUN}`)
		const create = modal.locator('.link-button-host__modal-create')
		await create.click()

		await expect.poll(() => calls).toBe(1)
		await expect(create).toBeDisabled()
		await expect(create).toHaveText(/Creating/)
		await expect(modal.locator('.link-button-host__modal-cancel')).toBeDisabled()

		// A user clicking again, and pressing Enter in the name field, must not
		// start a second create.
		await create.click({ force: true })
		await modal.locator('.link-button-host__modal-input').press('Enter')
		await page.waitForTimeout(500)
		expect(calls).toBe(1)

		const popupPromise = context.waitForEvent('page')
		release()
		const popup = await popupPromise
		await expect(modal).toHaveCount(0)
		expect(calls).toBe(1)
		await popup.close()
	})
})

test.describe('link button: icon (REQ-LBN-002)', () => {
	// @e2e link-button-widget::custom-url-icon
	test('a custom URL icon renders as a 48 px image with the label below it', async ({ page }) => {
		const id = await place({
			actionType: 'external',
			url: 'https://example.com',
			icon: '/apps/launchpad/resource/x.png',
			label: 'Open',
		})
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)
		const img = button.locator('img')
		await expect(img).toHaveCount(1)
		await expect(img).toHaveAttribute('src', '/apps/launchpad/resource/x.png')

		const label = button.locator('.cn-link-button-widget__label')
		await expect(label).toHaveText('Open')
		const imgBox = await img.boundingBox()
		const labelBox = await label.boundingBox()
		expect(imgBox).not.toBeNull()
		expect(labelBox).not.toBeNull()
		expect(Math.round(imgBox!.height)).toBe(48)
		expect(Math.round(imgBox!.width)).toBe(48)
		// Stacked: the label starts below the image's bottom edge.
		expect(labelBox!.y).toBeGreaterThanOrEqual(imgBox!.y + imgBox!.height - 1)
	})

	// @e2e link-button-widget::no-icon
	test('an empty icon renders the label alone', async ({ page }) => {
		const id = await place({ actionType: 'external', url: 'https://example.com', icon: '', label: 'Click me' })
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)
		await expect(button.locator('img')).toHaveCount(0)
		await expect(button.locator('svg')).toHaveCount(0)
		await expect(button.locator('.cn-link-button-widget__label')).toBeVisible()
		await expect(button).toHaveText('Click me')
	})
})

test.describe('link button: createFile (REQ-LBN-003)', () => {
	// @e2e link-button-widget::document-modal-opens-with-prefilled-name
	test('clicking a createFile button opens the modal with the extension and a timestamped name', async ({
		page,
	}) => {
		const id = await place({ actionType: 'createFile', url: 'docx', label: 'New report' })
		const widget = await openWithWidget(page, id)
		await expect(page.locator(MODAL)).toHaveCount(0)

		const before = Date.now()
		await widget.locator(BUTTON).click()
		const after = Date.now()

		const modal = page.locator(MODAL)
		await expect(modal).toBeVisible()
		await expect(modal.locator('.link-button-host__modal-extension')).toHaveText('.docx')

		const name = await modal.locator('.link-button-host__modal-input').inputValue()
		const match = /^document_(\d+)$/.exec(name)
		expect(match, `prefilled name "${name}" is not document_<timestamp>`).not.toBeNull()
		// A unix timestamp of the moment of the click, in seconds or in ms.
		const stamp = Number(match![1])
		const ms = stamp < 1e11 ? stamp * 1000 : stamp
		expect(ms).toBeGreaterThanOrEqual(Math.floor(before / 1000) * 1000 - 1000)
		expect(ms).toBeLessThanOrEqual(after + 1000)

		await expect(modal.locator('.link-button-host__modal-create')).toBeEnabled()
	})

	// @e2e link-button-widget::create-posts-and-opens-result
	test('Create posts the typed name with the extension and opens the returned URL', async ({
		context,
		page,
	}) => {
		await stubHost(context, 'https://nc')
		const id = await place({ actionType: 'createFile', url: 'docx', label: 'New report' })
		const bodies: unknown[] = []
		await page.route(CREATE, async (route) => {
			bodies.push(route.request().postDataJSON())
			await route.fulfill({
				json: {
					status: 'success',
					fileId: 42,
					url: 'https://nc/index.php/apps/files/?openfile=42',
				},
			})
		})

		const widget = await openWithWidget(page, id)
		await widget.locator(BUTTON).click()
		const modal = page.locator(MODAL)
		await modal.locator('.link-button-host__modal-input').fill('Q4-report')

		const popupPromise = context.waitForEvent('page')
		await modal.locator('.link-button-host__modal-create').click()
		const popup = await popupPromise
		await popup.waitForLoadState()

		expect(bodies).toEqual([
			{ filename: 'Q4-report.docx', dir: '/', content: '', overwrite: false },
		])
		expect(popup.url()).toBe('https://nc/index.php/apps/files/?openfile=42')
		await expect(modal).toHaveCount(0)
		await popup.close()
	})

	// @e2e link-button-widget::an-existing-file-is-not-replaced-without-a-warning
	test('an existing file is refused with a warning, and only Replace overwrites it', async ({
		page,
	}) => {
		const base = `Report-${RUN}`
		const file = `${base}.docx`
		await davPut(api, file, 'original content')
		try {
			const id = await place({ actionType: 'createFile', url: 'docx', label: 'New report' })
			const sent: Array<Record<string, unknown>> = []
			page.on('request', (r) => {
				if (r.url().includes('/api/files/create') && r.method() === 'POST') {
					sent.push(r.postDataJSON())
				}
			})

			const widget = await openWithWidget(page, id)
			await widget.locator(BUTTON).click()
			const modal = page.locator(MODAL)
			await modal.locator('.link-button-host__modal-input').fill(base)
			const create = modal.locator('.link-button-host__modal-create')
			await expect(create).toHaveText('Create')

			const refused = page.waitForResponse((r) => r.url().includes('/api/files/create'))
			await create.click()
			const conflict = await refused
			expect(conflict.status()).toBe(409)
			expect((await conflict.json()).error).toBe('file_exists')
			expect(sent).toHaveLength(1)
			expect(sent[0].overwrite).toBe(false)

			// Left unchanged on disk.
			expect(await davGet(api, file)).toEqual({ status: 200, body: 'original content' })

			// The modal warns, naming the file, and Create became Replace.
			await expect(modal.getByRole('alert')).toContainText(`${file} already exists`)
			await expect(create).toHaveText('Replace')
			await expect(modal).toBeVisible()

			// Changing the name returns to a normal Create; restoring it is Replace again.
			await modal.locator('.link-button-host__modal-input').fill(`${base}-other`)
			await expect(create).toHaveText('Create')
			await modal.locator('.link-button-host__modal-input').fill(base)
			await expect(create).toHaveText('Replace')
			expect(sent).toHaveLength(1)

			// Only the click on Replace sends overwrite: true.
			const popupPromise = page.context().waitForEvent('page')
			const replaced = page.waitForResponse((r) => r.url().includes('/api/files/create'))
			await create.click()
			expect((await replaced).status()).toBe(200)
			expect(sent).toHaveLength(2)
			expect(sent[1]).toEqual({ filename: file, dir: '/', content: '', overwrite: true })
			const popup = await popupPromise
			await popup.close()

			expect(await davGet(api, file)).toEqual({ status: 200, body: '' })
		} finally {
			await davDelete(api, file)
		}
	})

	// @e2e link-button-widget::empty-filename-disables-create
	test('clearing the filename disables Create', async ({ page }) => {
		const id = await place({ actionType: 'createFile', url: 'docx', label: 'New report' })
		const widget = await openWithWidget(page, id)
		await widget.locator(BUTTON).click()
		const modal = page.locator(MODAL)
		const create = modal.locator('.link-button-host__modal-create')
		await expect(create).toBeEnabled()

		await modal.locator('.link-button-host__modal-input').fill('')
		await expect(create).toBeDisabled()
		// Whitespace is not a name either.
		await modal.locator('.link-button-host__modal-input').fill('   ')
		await expect(create).toBeDisabled()
		// And a name enables it again, so the disabled state is the input's doing.
		await modal.locator('.link-button-host__modal-input').fill('x')
		await expect(create).toBeEnabled()
	})
})

test.describe('link button: file-creation endpoint (REQ-LBN-004)', () => {
	// @e2e link-button-widget::path-traversal-rejected
	test('a traversing filename is refused with 400 invalid_filename and nothing is written', async () => {
		const res = await api.post(`${API}/files/create`, { data: { filename: '../../etc/passwd' } })
		expect(res.status()).toBe(400)
		const body = await res.json()
		expect(body.error).toBe('invalid_filename')
		expect(body.message).toBe('Invalid filename')

		expect(await davFileId(api, 'passwd')).toBeNull()
		expect(await davFileId(api, 'etc')).toBeNull()
		expect(await davFileId(api, 'etc/passwd')).toBeNull()

		// The same endpoint does create a valid name, so the refusal is the
		// validator's and not a broken route.
		const ok = `traversal-control-${RUN}.txt`
		try {
			const created = await api.post(`${API}/files/create`, { data: { filename: ok } })
			expect(created.status(), await created.text()).toBe(200)
			expect(await davFileId(api, ok)).not.toBeNull()
		} finally {
			await davDelete(api, ok)
		}
	})

	// @e2e link-button-widget::disallowed-extension-rejected
	test('with the allow-list narrowed to txt, md, docx, an .exe is refused with file_type_not_allowed', async () => {
		const current = await api.get(`${API}/admin/settings`)
		expect(current.status()).toBe(200)
		const settings = await current.json()
		const previous: string[] = settings.linkCreateFileExtensions
			?? settings.data?.linkCreateFileExtensions
			?? ['txt', 'md', 'docx', 'xlsx', 'csv', 'odt']
		const narrowed = await api.put(`${API}/admin/settings`, {
			data: { linkCreateFileExts: ['txt', 'md', 'docx'] },
		})
		expect(narrowed.status(), await narrowed.text()).toBeLessThan(300)
		const allowed = `allowed-${RUN}.txt`
		try {
			const exe = await api.post(`${API}/files/create`, { data: { filename: `foo-${RUN}.exe` } })
			expect(exe.status()).toBe(400)
			const body = await exe.json()
			expect(body.error).toBe('file_type_not_allowed')
			expect(body.message).toBe('File type not allowed')
			expect(await davFileId(api, `foo-${RUN}.exe`)).toBeNull()

			// The narrowed list is the one applied: xlsx is a default but is now out.
			const xlsx = await api.post(`${API}/files/create`, { data: { filename: `foo-${RUN}.xlsx` } })
			expect(xlsx.status()).toBe(400)
			expect((await xlsx.json()).error).toBe('file_type_not_allowed')

			// And a listed type still goes through.
			const txt = await api.post(`${API}/files/create`, { data: { filename: allowed } })
			expect(txt.status(), await txt.text()).toBe(200)
		} finally {
			await davDelete(api, allowed)
			await api.put(`${API}/admin/settings`, { data: { linkCreateFileExts: previous } })
		}
	})

	// @e2e link-button-widget::existing-file-overwritten
	test('posting an existing name without overwrite: false replaces the file and returns its id and Files URL', async () => {
		const file = `report-${RUN}.docx`
		await davPut(api, file, 'old content')
		try {
			const existingId = await davFileId(api, file)
			expect(existingId).not.toBeNull()

			const res = await api.post(`${API}/files/create`, { data: { filename: file, content: '' } })
			expect(res.status(), await res.text()).toBe(200)
			const body = await res.json()
			expect(body.status).toBe('success')
			// The same file, not a sibling copy.
			expect(body.fileId).toBe(existingId)
			const url = new URL(body.url)
			expect(url.protocol).toMatch(/^https?:$/)
			expect(url.pathname).toMatch(/\/apps\/files\/?$/)
			expect(url.searchParams.get('openfile')).toBe(String(existingId))

			expect(await davGet(api, file)).toEqual({ status: 200, body: '' })
		} finally {
			await davDelete(api, file)
		}
	})
})

test.describe('link button: internal actions (REQ-LBN-005)', () => {
	// @e2e link-button-widget::unknown-action-id-warns-but-does-not-crash
	test('an unknown internal action warns on the console and the page keeps working', async ({
		page,
	}) => {
		const id = await place({ actionType: 'internal', url: 'does-not-exist', label: 'Mystery' })
		const warnings: string[] = []
		const errors: string[] = []
		page.on('console', (m) => {
			if (m.type() === 'warning') {
				warnings.push(m.text())
			}
		})
		page.on('pageerror', (e) => errors.push(e.message))

		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)
		// An internal action is a button, not a link that could navigate.
		await expect(button).toHaveJSProperty('tagName', 'BUTTON')
		await button.click()

		await expect.poll(() => warnings).toContain('Unknown internal action: does-not-exist')
		expect(errors).toEqual([])
		expect(page.url()).toContain('/apps/launchpad')
		// Still usable: the same button can be clicked again, warning again.
		await button.click()
		await expect
			.poll(() => warnings.filter((w) => w === 'Unknown internal action: does-not-exist').length)
			.toBe(2)
		expect(errors).toEqual([])
	})
})

test.describe('link button: default styling (REQ-LBN-007)', () => {
	// @e2e link-button-widget::theme-defaults
	test('empty colours fall back to the theme primary and primary-text variables', async ({ page }) => {
		const id = await place({
			label: 'X',
			url: 'y',
			actionType: 'external',
			backgroundColor: '',
			textColor: '',
		})
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)

		const inline = await button.evaluate((el) => ({
			bg: (el as HTMLElement).style.getPropertyValue('background-color'),
			fg: (el as HTMLElement).style.getPropertyValue('color'),
		}))
		expect(inline).toEqual({ bg: 'var(--color-primary)', fg: 'var(--color-primary-text)' })

		const [actual, expected] = await button.evaluate((el) => {
			const probe = document.createElement('span')
			probe.style.backgroundColor = 'var(--color-primary)'
			probe.style.color = 'var(--color-primary-text)'
			el.parentElement!.appendChild(probe)
			const a = getComputedStyle(el)
			const p = getComputedStyle(probe)
			const pair = [
				{ bg: a.backgroundColor, fg: a.color },
				{ bg: p.backgroundColor, fg: p.color },
			]
			probe.remove()
			return pair
		})
		expect(actual).toEqual(expected)

		// It follows a theme override of the variables.
		const overridden = await button.evaluate((el) => {
			const host = el.parentElement!
			host.style.setProperty('--color-primary', 'rgb(1, 2, 3)')
			host.style.setProperty('--color-primary-text', 'rgb(4, 5, 6)')
			const s = getComputedStyle(el)
			const out = { bg: s.backgroundColor, fg: s.color }
			host.style.removeProperty('--color-primary')
			host.style.removeProperty('--color-primary-text')
			return out
		})
		expect(overridden).toEqual({ bg: 'rgb(1, 2, 3)', fg: 'rgb(4, 5, 6)' })
	})

	// @e2e link-button-widget::hover-lift-effect
	test('hovering lifts the button by 2 px and adds a drop shadow', async ({ page }) => {
		const id = await place({ label: 'Lift', url: 'https://example.com', actionType: 'external' })
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)

		await page.mouse.move(0, 0)
		const rest = await liftOf(button)
		expect(rest.transform).toBe('none')
		expect(rest.shadow).toBe('none')

		await button.hover()
		await expect.poll(async () => (await liftOf(button)).transform).toBe('matrix(1, 0, 0, 1, 0, -2)')
		const lifted = await liftOf(button)
		expect(lifted.shadow).not.toBe('none')
		expect(lifted.shadow).toMatch(/rgba\(0, 0, 0, 0\.\d+\)/)
	})
})
