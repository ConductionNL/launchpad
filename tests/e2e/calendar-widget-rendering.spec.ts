/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for what the calendar widget shows
 * (openspec/specs/calendar-widget, REQ-CAL-008 / 009 / 010), written for
 * e2e-coverage-programme.
 *
 * Each test writes its own calendar and events over CalDAV
 * (`support/calendarFixture.ts`), places a calendar widget on a fresh active
 * dashboard through the widget API (seeding only), and reads the rendered
 * widget. The browser clock is fixed to Wednesday 2026-05-06 10:00 UTC and the
 * timezone to UTC, so the month, week and agenda windows, and every time shown,
 * are the same on every run.
 *
 * The loading, retry and error scenarios are about how the widget renders a
 * slow or failing response, so they hold or fail the events request with
 * `page.route`; every other test reads the real endpoint.
 *
 * Gate-19 @e2e traceability:
 *   @e2e calendar-widget::month-view-renders-grid
 *   @e2e calendar-widget::week-view-renders-7-column-day-view
 *   @e2e calendar-widget::agenda-view-renders-chronological-list-by-day
 *   @e2e calendar-widget::empty-month-view-shows-all-day-event-space
 *   @e2e calendar-widget::failure-notice-in-ui
 *   @e2e calendar-widget::all-ics-urls-fail
 *   @e2e calendar-widget::empty-state-for-no-events
 *   @e2e calendar-widget::empty-state-for-no-sources
 *   @e2e calendar-widget::loading-state
 *   @e2e calendar-widget::loading-state-cleared-on-success
 *   @e2e calendar-widget::error-state-persists-until-retry
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { EventSpec, SeededCalendar } from './support/calendarFixture.ts'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import {
	API,
	calendarKey,
	createCalendar,
	createDashboard,
	deleteCalendar,
	FAILING_ICS,
	placeCalendar,
	putEvent,
} from './support/calendarFixture.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	contextFor,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = Date.now().toString(36)
const NOW = new Date('2026-05-06T10:00:00Z')
const EVENTS_REQUEST = /\/apps\/launchpad\/api\/widgets\/calendar\/\d+\/events/
const RED = { hex: '#e9322d', rgb: 'rgb(233, 50, 45)' }

let admin: APIRequestContext
const calendars: SeededCalendar[] = []
const dashboards: SeededDashboard[] = []

test.use({ timezoneId: 'UTC', locale: 'en-US' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
})

test.afterEach(async ({ page }) => {
	await page.unrouteAll({ behavior: 'ignoreErrors' })
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
 * Seed a calendar with events and a calendar widget showing it on a fresh
 * active dashboard, then open LaunchPad at the fixed date.
 *
 * @param page the admin's page.
 * @param label a readable part of the names.
 * @param events the events to write.
 * @param content the placement content, given the calendar's key.
 * @return the rendered widget.
 */
async function showCalendar(
	page: Page,
	label: string,
	events: EventSpec[],
	content: (key: string) => Record<string, unknown>,
): Promise<Locator> {
	const calendar = await createCalendar(admin, ADMIN_USER, `E2E ${label} ${STAMP}`, RED.hex)
	calendars.push(calendar)
	for (const event of events) {
		await putEvent(admin, calendar, event)
	}
	const key = await calendarKey(admin, calendar.name)
	const dashboard = await seedActiveDashboard(admin, `E2E Calendar ${label} ${STAMP}`)
	dashboards.push(dashboard)
	await placeCalendar(admin, dashboard.id, content(key))

	await page.clock.setFixedTime(NOW)
	await gotoLaunchPad(page)
	const widget = page.locator('.cn-calendar-widget')
	await expect(widget).toHaveCount(1, { timeout: 30_000 })
	return widget
}

/**
 * Wait until the widget has finished loading.
 *
 * @param widget the widget.
 * @return nothing.
 */
async function loaded(widget: Locator): Promise<void> {
	await expect(widget.getByText('Loading calendars…')).toHaveCount(0, { timeout: 20_000 })
}

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-008 — render modes
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-008 render modes', () => {
	// @e2e calendar-widget::month-view-renders-grid
	test('month view is a Sunday-first 7-column grid with each day\'s titles', async ({ page }) => {
		const widget = await showCalendar(page, 'Month', [
			{ uid: `board-${STAMP}`, summary: 'Board meeting', start: '20260512T130000Z', end: '20260512T140000Z' },
		], (key) => ({ internalCalendars: [key], viewMode: 'month' }))
		await loaded(widget)

		await expect(widget.locator('.cn-calendar-widget__month-header'))
			.toHaveText(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])

		// Whole weeks as rows: May 2026 starts on a Friday, so six of them.
		const cells = widget.locator('.cn-calendar-widget__month-cell')
		const count = await cells.count()
		expect(count % 7).toBe(0)
		expect(count).toBeGreaterThanOrEqual(35)
		for (let i = 0; i < count; i++) {
			await expect(cells.nth(i).locator('.cn-calendar-widget__month-day')).toHaveText(/^\d{1,2}$/)
		}

		// Custom HTML/CSS: the grid is a 7-column CSS grid, not a library widget.
		const columns = await widget.locator('.cn-calendar-widget__month')
			.evaluate((el) => getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length)
		expect(columns).toBe(7)
		await expect(page.locator('.fc, .vuecal, .toastui-calendar-layout')).toHaveCount(0)

		// The 12th of this month lists the event, by title only.
		const twelfth = widget.locator('.cn-calendar-widget__month-cell:not(.is-other-month)')
			.filter({ has: page.locator('.cn-calendar-widget__month-day', { hasText: /^12$/ }) })
		await expect(twelfth.locator('.cn-calendar-widget__month-event')).toHaveText(['Board meeting'])
		await expect(twelfth).not.toContainText('13:00')
	})

	// @e2e calendar-widget::week-view-renders-7-column-day-view
	test('week view has one column per day with name, date and timed events in order', async ({ page }) => {
		const widget = await showCalendar(page, 'Week', [
			{ uid: `late-${STAMP}`, summary: 'Late check', start: '20260507T160000Z', end: '20260507T163000Z' },
			{ uid: `review-${STAMP}`, summary: 'Design review', start: '20260507T140000Z', end: '20260507T150000Z' },
		], (key) => ({ internalCalendars: [key], viewMode: 'week' }))
		await loaded(widget)

		const columns = widget.locator('.cn-calendar-widget__week-col')
		await expect(columns).toHaveCount(7)
		await expect(widget.locator('.cn-calendar-widget__week-name'))
			.toHaveText(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])
		// The week of Wednesday 6 May 2026: Sunday 3 to Saturday 9.
		await expect(widget.locator('.cn-calendar-widget__week-num'))
			.toHaveText(['3', '4', '5', '6', '7', '8', '9'])

		const thursday = columns.nth(4).locator('.cn-calendar-widget__week-event')
		await expect(thursday).toHaveCount(2)
		await expect(thursday.nth(0)).toContainText('14:00')
		await expect(thursday.nth(0)).toContainText('Design review')
		await expect(thursday.nth(1)).toContainText('16:00')
		await expect(thursday.nth(1)).toContainText('Late check')
	})

	// @e2e calendar-widget::agenda-view-renders-chronological-list-by-day
	test('agenda groups by day under a date header, sorted, with time range and calendar', async ({ page }) => {
		const widget = await showCalendar(page, 'Agenda', [
			{ uid: `friday-${STAMP}`, summary: 'Friday demo', start: '20260508T110000Z', end: '20260508T120000Z' },
			{ uid: `afternoon-${STAMP}`, summary: 'Afternoon sync', start: '20260506T140000Z', end: '20260506T143000Z' },
			{ uid: `morning-${STAMP}`, summary: 'Morning sync', start: '20260506T090000Z', end: '20260506T100000Z' },
		], (key) => ({ internalCalendars: [key], viewMode: 'agenda' }))
		await loaded(widget)

		// Headers and rows interleave in date order, rows by start time.
		const items = widget.locator('.cn-calendar-widget__agenda > li')
		await expect(items).toHaveCount(5)
		await expect(items.nth(0)).toHaveClass(/agenda-header/)
		await expect(items.nth(0)).toContainText(/Wed/)
		await expect(items.nth(0)).toContainText(/\b6\b/)
		await expect(items.nth(1)).toContainText('Morning sync')
		await expect(items.nth(2)).toContainText('Afternoon sync')
		await expect(items.nth(3)).toHaveClass(/agenda-header/)
		await expect(items.nth(3)).toContainText(/Fri/)
		await expect(items.nth(3)).toContainText(/\b8\b/)
		await expect(items.nth(4)).toContainText('Friday demo')

		// Each entry: title, time RANGE, and the calendar's name and colour.
		const morning = items.nth(1)
		await expect(morning).toContainText('09:00')
		await expect(morning).toContainText('10:00')
		await expect(morning).toContainText(`E2E Agenda ${STAMP}`)
		const accent = await morning.evaluate((el) => getComputedStyle(el).borderLeftColor)
		expect(accent).toBe(RED.rgb)
	})

	// @e2e calendar-widget::empty-month-view-shows-all-day-event-space
	test('a month with no events still draws the full grid with visible cells', async ({ page }) => {
		const widget = await showCalendar(page, 'EmptyMonth', [], (key) => ({
			internalCalendars: [key],
			viewMode: 'month',
		}))
		await loaded(widget)

		await expect(widget.locator('.cn-calendar-widget__month-header')).toHaveCount(7)
		const cells = widget.locator('.cn-calendar-widget__month-cell')
		expect((await cells.count()) % 7).toBe(0)
		await expect(widget.locator('.cn-calendar-widget__month-event')).toHaveCount(0)

		// No cell is blank space: each has a border or its own background.
		const blank = await cells.evaluateAll((els) => els.filter((el) => {
			const style = getComputedStyle(el)
			const border = parseFloat(style.borderTopWidth) > 0 && style.borderTopStyle !== 'none'
			const background = style.backgroundColor !== 'rgba(0, 0, 0, 0)' && style.backgroundColor !== 'transparent'
			return !border && !background
		}).length)
		expect(blank).toBe(0)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-009 — failure tolerance, as the widget shows it
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-009 failure tolerance', () => {
	// @e2e calendar-widget::failure-notice-in-ui
	test('a failed source shows a small notice with details, and the other events', async ({ page }) => {
		const widget = await showCalendar(page, 'Notice', [
			{ uid: `kept-${STAMP}`, summary: 'Still shown', start: '20260507T100000Z', end: '20260507T110000Z' },
		], (key) => ({ internalCalendars: [key], externalIcsUrls: [FAILING_ICS] }))
		await loaded(widget)

		await expect(widget.locator('.cn-calendar-widget__agenda-row').filter({ hasText: 'Still shown' }))
			.toBeVisible()
		const notice = widget.getByText(/1 calendar source unavailable/i)
		await expect(notice).toBeVisible()
		await notice.click()
		await expect(page.getByText(FAILING_ICS)).toBeVisible()
	})

	// @e2e calendar-widget::all-ics-urls-fail
	test('when every feed fails the empty state shows with a prominent notice', async ({ page }) => {
		const second = 'https://127.0.0.1/launchpad-e2e-second.ics'
		const widget = await showCalendar(page, 'AllFail', [], () => ({
			internalCalendars: [],
			externalIcsUrls: [FAILING_ICS, second],
		}))
		await loaded(widget)

		await expect(widget.getByText('No events in the next 14 days')).toBeVisible()
		const notice = widget.getByText(/2 calendar sources unavailable/i)
		await expect(notice).toBeVisible()
		const prominent = await notice.evaluate((el) => {
			const probe = document.createElement('span')
			probe.style.color = 'var(--color-warning)'
			document.body.appendChild(probe)
			const warning = getComputedStyle(probe).color
			probe.remove()
			const style = getComputedStyle(el)
			const box = el.closest('[role="alert"], [role="status"]')
			const dismissable = box !== null && box.querySelector('button') !== null
			return style.color === warning || style.textAlign === 'center' || dismissable
		})
		expect(prominent, 'the notice is in the warning colour, centred, or dismissable').toBe(true)

		// The dashboard is not blocked: its own controls still respond.
		await page.locator(MORE_MENU).locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await expect(page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]')).toBeVisible()
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-010 — empty, loading and error states
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-010 empty state and feedback', () => {
	// @e2e calendar-widget::empty-state-for-no-events
	test('no events in range names the window, in English and in Dutch', async ({ page, browser }) => {
		// Outside a 7-day window from 6 May, so the window is empty.
		const later: EventSpec = {
			uid: `later-${STAMP}`,
			summary: 'Later event',
			start: '20260520T100000Z',
			end: '20260520T110000Z',
		}
		const widget = await showCalendar(page, 'NoEvents', [later], (key) => ({
			internalCalendars: [key],
			viewMode: 'agenda',
			daysAhead: 7,
		}))
		await loaded(widget)
		await expect(widget.getByText('No events in the next 7 days')).toBeVisible()
		await expect(widget.getByText('Later event')).toHaveCount(0)

		// Translatable: the same state for a Dutch-speaking user.
		await allowPersonalDashboards(admin, true)
		const reader = await provisionThrowawayUser('e2e-cal-nl')
		const api = await contextFor(reader.username, reader.password)
		let calendar: SeededCalendar | null = null
		try {
			const language = await admin.put(`/ocs/v1.php/cloud/users/${encodeURIComponent(reader.username)}`, {
				form: { key: 'language', value: 'nl' },
			})
			expect(language.status(), await language.text()).toBeLessThan(300)

			calendar = await createCalendar(api, reader.username, `E2E NL ${STAMP}`)
			await putEvent(api, calendar, later)
			const key = await calendarKey(api, calendar.name)
			const dash = await createDashboard(api, `E2E Agenda NL ${STAMP}`)
			expect((await api.post(`${API}/dashboard/${dash.id}/activate`)).status()).toBeLessThan(300)
			expect((await api.post(`${API}/dashboards/default`, { data: { uuid: dash.uuid } })).status())
				.toBeLessThan(300)
			await placeCalendar(api, dash.id, { internalCalendars: [key], viewMode: 'agenda', daysAhead: 7 })

			const session = await loginAs(browser, reader.username, reader.password)
			try {
				await session.page.clock.setFixedTime(NOW)
				await session.page.goto('/index.php/apps/launchpad')
				const nl = session.page.locator('.cn-calendar-widget')
				await expect(nl).toHaveCount(1, { timeout: 30_000 })
				const state = nl.locator('.cn-calendar-widget__state')
				await expect(state).toContainText('7', { timeout: 20_000 })
				await expect(state).not.toContainText('No events in the next')
			} finally {
				await session.context.close()
			}
			await api.delete(`${API}/dashboard/${dash.id}`)
		} finally {
			await deleteCalendar(api, calendar)
			await api.dispose()
			await deprovisionUser(reader.username)
		}
	})

	// @e2e calendar-widget::empty-state-for-no-sources
	test('a widget with no calendars and no feeds says no calendar is configured', async ({ page }) => {
		const widget = await showCalendar(page, 'NoSources', [], () => ({
			internalCalendars: [],
			externalIcsUrls: [],
		}))
		await loaded(widget)
		await expect(widget.locator('.cn-calendar-widget__state')).toContainText(/no calendars? (is )?(configured|selected)/i)
		// Not the "no events" message, which would be a claim about a diary
		// nobody chose.
		await expect(widget.getByText(/No events in the next/)).toHaveCount(0)
	})

	// @e2e calendar-widget::loading-state
	test('a pending request shows a loading indicator, replaced by a notice after 30s', async ({ page }) => {
		test.setTimeout(150_000)
		// Hold every events request past the 30-second limit.
		await page.route(EVENTS_REQUEST, async (route) => {
			await new Promise((resolve) => setTimeout(resolve, 45_000))
			await route.continue().catch(() => {})
		})
		const widget = await showCalendar(page, 'Loading', [], (key) => ({ internalCalendars: [key] }))

		const loading = widget.getByText('Loading calendars…')
		await expect(loading).toBeVisible({ timeout: 15_000 })
		await expect(widget.getByText(/timed? ?out|taking (too )?long|took too long/i))
			.toBeVisible({ timeout: 35_000 })
		await expect(loading).toHaveCount(0)
	})

	// @e2e calendar-widget::loading-state-cleared-on-success
	test('the loading indicator goes away and the events render when the answer arrives', async ({ page }) => {
		await page.route(EVENTS_REQUEST, async (route) => {
			await new Promise((resolve) => setTimeout(resolve, 3_000))
			await route.continue().catch(() => {})
		})
		const widget = await showCalendar(page, 'Loaded', [
			{ uid: `arrives-${STAMP}`, summary: 'Arrives later', start: '20260507T100000Z', end: '20260507T110000Z' },
		], (key) => ({ internalCalendars: [key] }))

		const loading = widget.getByText('Loading calendars…')
		await expect(loading).toBeVisible({ timeout: 15_000 })
		await expect(widget.locator('.cn-calendar-widget__agenda-row').filter({ hasText: 'Arrives later' }))
			.toBeVisible({ timeout: 20_000 })
		await expect(loading).toHaveCount(0)
	})

	// @e2e calendar-widget::error-state-persists-until-retry
	test('a 5xx shows an error with Retry that stays until Retry re-fetches', async ({ page }) => {
		let failing = true
		let afterRetry = 0
		await page.route(EVENTS_REQUEST, async (route) => {
			if (failing) {
				await route.fulfill({ status: 500, contentType: 'application/json', body: '{"error":"boom"}' })
				return
			}
			afterRetry++
			await route.continue()
		})
		const widget = await showCalendar(page, 'Retry', [
			{ uid: `recovered-${STAMP}`, summary: 'Recovered event', start: '20260507T100000Z', end: '20260507T110000Z' },
		], (key) => ({ internalCalendars: [key] }))

		await expect(widget.getByText('Failed to load events')).toBeVisible({ timeout: 20_000 })
		const retry = widget.getByRole('button', { name: 'Retry' })
		await expect(retry).toBeVisible()

		// It persists: no silent recovery while nobody asks.
		failing = false
		await page.waitForTimeout(3_000)
		await expect(widget.getByText('Failed to load events')).toBeVisible()
		expect(afterRetry).toBe(0)

		await retry.click()
		await expect(widget.locator('.cn-calendar-widget__agenda-row').filter({ hasText: 'Recovered event' }))
			.toBeVisible({ timeout: 20_000 })
		expect(afterRetry).toBeGreaterThan(0)
		await expect(widget.getByText('Failed to load events')).toHaveCount(0)
	})
})
