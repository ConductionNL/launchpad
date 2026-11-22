/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for finding, adding and configuring the
 * calendar widget (openspec/specs/calendar-widget, REQ-CAL-001 / 002 and the
 * persistence scenario of REQ-CAL-008), written for e2e-coverage-programme.
 *
 * Every configuration is made the way a person makes it: More → "Add custom
 * widget…" → the Add Widget modal → widget type "Calendar" → the calendar
 * form. What the form stored is then read back from the dashboard API, and
 * where the scenario is about an effect (days-ahead, view mode) the rendered
 * widget is read too. Each test seeds its own calendar over CalDAV and its own
 * active dashboard, and removes both.
 *
 * Gate-19 @e2e traceability:
 *   @e2e calendar-widget::widget-appears-in-picker
 *   @e2e calendar-widget::multiple-instances-allowed
 *   @e2e calendar-widget::widget-registration-survives-app-reload
 *   @e2e calendar-widget::config-for-internal-calendars
 *   @e2e calendar-widget::config-for-external-ics-urls
 *   @e2e calendar-widget::config-for-view-mode
 *   @e2e calendar-widget::config-for-days-ahead-threshold
 *   @e2e calendar-widget::config-for-color-by-calendar-preference
 *   @e2e calendar-widget::config-is-json-serializable
 *   @e2e calendar-widget::view-mode-selection-is-persistent
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededCalendar } from './support/calendarFixture.ts'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import {
	calendarKey,
	calendarPlacements,
	createCalendar,
	deleteCalendar,
	getEvents,
	placeCalendar,
	putEvent,
} from './support/calendarFixture.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = Date.now().toString(36)
const NOW = new Date('2026-05-06T10:00:00Z')

let admin: APIRequestContext
const calendars: SeededCalendar[] = []
const dashboards: SeededDashboard[] = []

test.use({ timezoneId: 'UTC', locale: 'en-US' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
})

test.afterEach(async () => {
	for (const dashboard of dashboards.splice(0)) {
		await removeSeededDashboard(admin, dashboard)
	}
	for (const calendar of calendars.splice(0)) {
		await deleteCalendar(admin, calendar)
	}
})

test.afterAll(async () => {
	await admin?.dispose()
})

/**
 * A calendar of the admin's and a fresh active dashboard, then LaunchPad.
 *
 * @param page the admin's page.
 * @param label a readable part of the names.
 * @return the calendar and the dashboard.
 */
async function prepare(
	page: Page,
	label: string,
): Promise<{ calendar: SeededCalendar, dashboard: SeededDashboard }> {
	const calendar = await createCalendar(admin, ADMIN_USER, `E2E ${label} ${STAMP}`)
	calendars.push(calendar)
	const dashboard = await seedActiveDashboard(admin, `E2E Calendar ${label} ${STAMP}`)
	dashboards.push(dashboard)
	await page.clock.setFixedTime(NOW)
	await gotoLaunchPad(page)
	return { calendar, dashboard }
}

/**
 * Open the Add Widget modal for the active dashboard from the More menu.
 *
 * @param page the page.
 * @return the dialog.
 */
async function openAddWidget(page: Page): Promise<Locator> {
	await page.locator(MORE_MENU).locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
	await page.locator('[data-testid="widget-picker-type-calendar"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Open the modal and pick the Calendar widget type.
 *
 * @param page the page.
 * @return the dialog, showing the calendar form.
 */
async function openCalendarForm(page: Page): Promise<Locator> {
	const dialog = await openAddWidget(page)
	await expect(dialog.locator('.cn-calendar-widget-form')).toBeVisible()
	return dialog
}

/**
 * Choose a calendar in the form's calendar picker.
 *
 * @param page the page (the dropdown renders in the body).
 * @param dialog the dialog.
 * @param name the calendar's display name.
 * @return nothing.
 */
async function pickCalendar(page: Page, dialog: Locator, name: string): Promise<void> {
	const field = dialog.locator('.cn-calendar-widget-form__field').filter({ hasText: 'Calendars' }).first()
	// The picker replaces the free-text box once the user's calendars load.
	const toggle = field.locator('.vs__dropdown-toggle')
	await expect(toggle).toBeVisible({ timeout: 15_000 })
	await toggle.click()
	await page.locator('.vs__dropdown-option').filter({ hasText: name }).first().click()
	await field.locator('.cn-calendar-widget-form__label').first().click()
	await expect(field.locator('.vs__selected').filter({ hasText: name })).toBeVisible()
}

/**
 * Choose a view mode in the form.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @param mode `month`, `week` or `agenda`.
 * @return nothing.
 */
async function pickViewMode(page: Page, dialog: Locator, mode: string): Promise<void> {
	const select = dialog.locator('.cn-calendar-widget-form .v-select').first()
	await select.locator('.vs__dropdown-toggle').click()
	await page.locator('.vs__dropdown-option').filter({ hasText: new RegExp(`^\\s*${mode}\\s*$`) }).first().click()
	await expect(select.locator('.vs__selected')).toHaveText(mode)
}

/**
 * Save the modal and wait for it to close.
 *
 * @param dialog the dialog.
 * @return nothing.
 */
async function save(dialog: Locator): Promise<void> {
	const button = dialog.locator('[data-testid="add-widget-save"]')
	await expect(button).toBeEnabled({ timeout: 5_000 })
	await button.click()
	await expect(dialog).not.toBeVisible({ timeout: 10_000 })
}

/**
 * The one calendar placement of a dashboard, read back from the API.
 *
 * @param dashboard the dashboard.
 * @return the placement.
 */
async function onlyPlacement(dashboard: SeededDashboard): Promise<Record<string, any>> {
	let placements: Array<Record<string, any>> = []
	await expect.poll(async () => {
		placements = await calendarPlacements(admin, dashboard.id)
		return placements.length
	}, { timeout: 10_000 }).toBe(1)
	return placements[0]
}

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-001 — the widget in the picker
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-001 widget registration', () => {
	// @e2e calendar-widget::widget-appears-in-picker
	test('"Calendar" is offered in the picker, can be placed, and shows an icon', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'Picker')
		const dialog = await openAddWidget(page)

		const option = dialog.locator('[data-testid="widget-type-option-calendar"]')
		await expect(option).toHaveCount(1)
		await expect(option).toHaveText('Calendar')

		// Selectable for placement.
		await dialog.locator('[data-testid="widget-type-select"]').selectOption('calendar')
		await expect(dialog.locator('.cn-calendar-widget-form')).toBeVisible()
		// The picker shows the type with an icon.
		await expect(dialog.locator('.cn-add-widget-modal__type').locator('svg, img, .material-design-icon'))
			.not.toHaveCount(0)

		await pickCalendar(page, dialog, calendar.name)
		await save(dialog)
		await onlyPlacement(dashboard)
		await expect(page.locator('.cn-calendar-widget')).toHaveCount(1, { timeout: 15_000 })
	})

	// @e2e calendar-widget::multiple-instances-allowed
	test('a second calendar widget coexists with the first, each with its own config', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'Twice')
		const second = await createCalendar(admin, ADMIN_USER, `E2E Twice second ${STAMP}`)
		calendars.push(second)
		const firstKey = await calendarKey(admin, calendar.name)
		const secondKey = await calendarKey(admin, second.name)
		const firstId = await placeCalendar(admin, dashboard.id, { internalCalendars: [firstKey], viewMode: 'agenda' })
		await gotoLaunchPad(page)
		await expect(page.locator('.cn-calendar-widget')).toHaveCount(1, { timeout: 30_000 })

		const dialog = await openCalendarForm(page)
		await pickCalendar(page, dialog, second.name)
		await pickViewMode(page, dialog, 'week')
		await save(dialog)

		let placements: Array<Record<string, any>> = []
		await expect.poll(async () => {
			placements = await calendarPlacements(admin, dashboard.id)
			return placements.length
		}, { timeout: 10_000 }).toBe(2)
		const first = placements.find((p) => Number(p.id) === firstId)!
		const added = placements.find((p) => Number(p.id) !== firstId)!
		expect(first.content.internalCalendars).toEqual([firstKey])
		expect(first.content.viewMode).toBe('agenda')
		expect(added.content.internalCalendars).toEqual([secondKey])
		expect(added.content.viewMode).toBe('week')

		// Both render, each in its own mode.
		const widgets = page.locator('.cn-calendar-widget')
		await expect(widgets).toHaveCount(2, { timeout: 15_000 })
		await expect(page.locator('.cn-calendar-widget:has(.cn-calendar-widget__week)')).toHaveCount(1)
		await expect(widgets.filter({
			has: page.locator('.cn-calendar-widget__mode-btn.is-active', { hasText: /^\s*Agenda\s*$/ }),
		})).toHaveCount(1)
	})

	// @e2e calendar-widget::widget-registration-survives-app-reload
	test('the Calendar type is still in the picker after the app reloads', async ({ page }) => {
		await prepare(page, 'Reload')
		let dialog = await openAddWidget(page)
		await expect(dialog.locator('[data-testid="widget-type-option-calendar"]')).toHaveText('Calendar')
		await dialog.getByRole('button', { name: /cancel/i }).click()
		await expect(dialog).not.toBeVisible()

		await page.reload()
		await page.waitForSelector(MORE_MENU, { timeout: 30_000 })
		dialog = await openAddWidget(page)
		await expect(dialog.locator('[data-testid="widget-type-option-calendar"]')).toHaveText('Calendar')
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-002 — what the form stores
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-002 placement configuration', () => {
	// @e2e calendar-widget::config-for-internal-calendars
	test('picking a calendar stores its key in internalCalendars, and it resolves', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'Internal')
		await putEvent(admin, calendar, {
			uid: `internal-${STAMP}`,
			summary: 'Picked calendar event',
			start: '20260507T100000Z',
			end: '20260507T110000Z',
		})
		const key = await calendarKey(admin, calendar.name)

		const dialog = await openCalendarForm(page)
		await pickCalendar(page, dialog, calendar.name)
		await save(dialog)

		const placement = await onlyPlacement(dashboard)
		expect(placement.content.internalCalendars).toEqual([key])
		// The stored entry is one the calendar manager resolves.
		const { status, body } = await getEvents(admin, Number(placement.id))
		expect(status).toBe(200)
		expect((body.events ?? []).map((e: Record<string, any>) => e.title)).toContain('Picked calendar event')
	})

	// @e2e calendar-widget::config-for-external-ics-urls
	test('feed URLs are stored as an array of URLs; a non-URL is refused', async ({ page }) => {
		const { dashboard } = await prepare(page, 'Feeds')
		const dialog = await openCalendarForm(page)
		const feeds = dialog.getByLabel(/external ics urls/i)
		const button = dialog.locator('[data-testid="add-widget-save"]')

		await feeds.fill('not a url')
		await expect(button).toBeDisabled()

		await feeds.fill('https://calendar.example.org/team.ics\nhttps://feeds.example.org/agenda/feed')
		await save(dialog)

		const placement = await onlyPlacement(dashboard)
		expect(placement.content.externalIcsUrls).toEqual([
			'https://calendar.example.org/team.ics',
			'https://feeds.example.org/agenda/feed',
		])
		for (const url of placement.content.externalIcsUrls) {
			expect(() => new URL(url)).not.toThrow()
			expect(new URL(url).protocol).toMatch(/^https?:$/)
		}
	})

	// @e2e calendar-widget::config-for-view-mode
	test('the chosen view mode is stored; unset, the widget opens in agenda', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'ViewMode')
		const dialog = await openCalendarForm(page)
		await expect(dialog.locator('.cn-calendar-widget-form .v-select').first().locator('.vs__selected'))
			.toHaveText('agenda')
		await pickCalendar(page, dialog, calendar.name)
		await pickViewMode(page, dialog, 'week')
		await save(dialog)
		expect((await onlyPlacement(dashboard)).content.viewMode).toBe('week')

		// A placement whose content names no view mode opens in agenda.
		const other = await seedActiveDashboard(admin, `E2E Calendar ViewMode default ${STAMP}`)
		dashboards.push(other)
		await placeCalendar(admin, other.id, { internalCalendars: [await calendarKey(admin, calendar.name)] })
		await gotoLaunchPad(page)
		const widget = page.locator('.cn-calendar-widget')
		await expect(widget).toHaveCount(1, { timeout: 30_000 })
		await expect(widget.locator('.cn-calendar-widget__mode-btn.is-active')).toHaveText('Agenda')
	})

	// @e2e calendar-widget::config-for-days-ahead-threshold
	test('days ahead defaults to 14, is stored as a number, and only bounds agenda', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'DaysAhead')
		// Friday 8 May: two days after the fixed "today".
		await putEvent(admin, calendar, {
			uid: `friday-${STAMP}`,
			summary: 'Two days out',
			start: '20260508T120000Z',
			end: '20260508T130000Z',
		})

		const dialog = await openCalendarForm(page)
		const days = dialog.getByLabel('Days ahead')
		await expect(days).toHaveValue('14')
		await pickCalendar(page, dialog, calendar.name)
		await days.fill('1')
		await save(dialog)
		expect((await onlyPlacement(dashboard)).content.daysAhead).toBe(1)

		const widget = page.locator('.cn-calendar-widget')
		await expect(widget).toHaveCount(1, { timeout: 15_000 })
		await expect(widget.getByText('No events in the next 1 days')).toBeVisible({ timeout: 20_000 })
		await expect(widget.getByText('Two days out')).toHaveCount(0)

		// Week and month ignore the threshold.
		await widget.locator('.cn-calendar-widget__mode-btn', { hasText: 'Week' }).click()
		await expect(widget.locator('.cn-calendar-widget__week-event').filter({ hasText: 'Two days out' }))
			.toBeVisible({ timeout: 20_000 })
		await widget.locator('.cn-calendar-widget__mode-btn', { hasText: 'Month' }).click()
		await expect(widget.locator('.cn-calendar-widget__month-event').filter({ hasText: 'Two days out' }))
			.toBeVisible({ timeout: 20_000 })
	})

	// @e2e calendar-widget::config-for-color-by-calendar-preference
	test('color-by-calendar defaults to on and is stored as a boolean', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'Color')
		const dialog = await openCalendarForm(page)
		const toggle = dialog.locator('.checkbox-radio-switch').filter({ hasText: 'Color by calendar' })
		await expect(toggle.locator('input')).toBeChecked()
		await pickCalendar(page, dialog, calendar.name)
		await toggle.getByText('Color by calendar').click()
		await expect(toggle.locator('input')).not.toBeChecked()
		await save(dialog)
		expect((await onlyPlacement(dashboard)).content.colorByCalendar).toBe(false)

		// Left alone, it is stored as true.
		const other = await seedActiveDashboard(admin, `E2E Calendar Color default ${STAMP}`)
		dashboards.push(other)
		await gotoLaunchPad(page)
		const again = await openCalendarForm(page)
		await pickCalendar(page, again, calendar.name)
		await save(again)
		expect((await onlyPlacement(other)).content.colorByCalendar).toBe(true)
	})

	// @e2e calendar-widget::config-is-json-serializable
	test('the stored content reads back as a clean object with trimmed strings', async ({ page }) => {
		const { calendar, dashboard } = await prepare(page, 'Json')
		const key = await calendarKey(admin, calendar.name)
		const dialog = await openCalendarForm(page)
		await pickCalendar(page, dialog, calendar.name)
		await dialog.getByLabel(/external ics urls/i)
			.fill('   https://a.example.org/kalender-été.ics   \n\n\thttps://b.example.org/y.ics \n')
		await save(dialog)

		const res = await admin.get(`/index.php/apps/launchpad/api/dashboard/${dashboard.id}`)
		const raw = await res.text()
		const body = JSON.parse(raw)
		const placement = (body.placements ?? []).find((p: Record<string, any>) => p.widgetId === 'calendar')
		const content = placement.content
		expect(typeof content).toBe('object')
		expect(Array.isArray(content)).toBe(false)
		expect(JSON.parse(JSON.stringify(content))).toEqual(content)
		expect(content.internalCalendars).toEqual([key])
		expect(content.externalIcsUrls).toEqual([
			'https://a.example.org/kalender-été.ics',
			'https://b.example.org/y.ics',
		])
		for (const value of [...content.internalCalendars, ...content.externalIcsUrls]) {
			expect(typeof value).toBe('string')
			expect(value).toBe(value.trim())
			// No control characters and no U+FFFD replacement character (mojibake).
			expect([...value].filter((c) => c.charCodeAt(0) < 0x20 || c === '\uFFFD')).toEqual([])
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-008 — the view mode survives a reload
// ─────────────────────────────────────────────────────────────────────────────

// @e2e calendar-widget::view-mode-selection-is-persistent
test('a widget configured for month view is still in month view after a reload', async ({ page }) => {
	const { calendar, dashboard } = await prepare(page, 'Persist')
	const dialog = await openCalendarForm(page)
	await pickCalendar(page, dialog, calendar.name)
	await pickViewMode(page, dialog, 'month')
	await save(dialog)

	const widget = page.locator('.cn-calendar-widget')
	await expect(widget.locator('.cn-calendar-widget__month')).toBeVisible({ timeout: 15_000 })

	await page.reload()
	await page.waitForSelector(MORE_MENU, { timeout: 30_000 })
	await expect(widget.locator('.cn-calendar-widget__month')).toBeVisible({ timeout: 30_000 })
	await expect(widget.locator('.cn-calendar-widget__mode-btn.is-active')).toHaveText('Month')
	expect((await onlyPlacement(dashboard)).content.viewMode).toBe('month')
})
