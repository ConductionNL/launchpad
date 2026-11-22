/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed render passes axe-core (embedded-analytics task 12). The embed
 * page is rendered in each representative shape (a dashboard with chart,
 * table, text, image, link and tile widgets, a single widget, a scope with
 * interactions, the loading and the refusal state), axe-core runs over it,
 * the findings are written to `docs/accessibility/axe-results.json`, and a
 * serious or critical violation fails the run. `npm run build` runs this
 * file through `npm run check:a11y`.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-12
 */

import { mount } from '@vue/test-utils'
import axe from 'axe-core'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { computed, nextTick, ref } from 'vue'
import EmbedView from '../views/EmbedView.vue'
import { classify, runAxe } from '../axe-core-check.js'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))

const context = vi.hoisted(() => ({ current: null }))
vi.mock('../composables/useEmbedContext.js', () => ({ useEmbedContext: () => context.current }))

const REPORT = resolve(__dirname, '../../docs/accessibility/axe-results.json')

const READ = { mode: 'read', allowedActions: [], allowedFilters: [] }
const INTERACT = { mode: 'read-with-interactions', allowedActions: ['interact', 'drillDown'], allowedFilters: [] }

const placement = (id, widgetId, extra = {}) => ({ id, widgetId, gridX: id, gridY: 0, gridWidth: 4, ...extra })

const DASHBOARD = [
	placement(1, 'chart', { customTitle: 'Requests per month' }),
	placement(2, 'object-list', { customTitle: 'Open cases' }),
	placement(3, 'header', { content: { text: 'Figures' } }),
	placement(4, 'text', { customTitle: 'About', content: { text: 'Updated every night.' } }),
	placement(5, 'image', { customTitle: 'Town hall', content: { url: 'https://www.example.com/hall.png' } }),
	placement(6, 'link', { content: { url: 'https://www.example.com/report', label: 'Full report' } }),
	placement(7, 'divider'),
	placement(8, 'tile-mail', { tileType: 'app', tileTitle: 'Mail', tileLinkValue: '/apps/mail', tileIcon: '/apps/mail/img/mail.svg' }),
]

const SHAPES = {
	'dashboard, read only': { scope: READ, payload: { dashboard: { name: 'City dashboard' }, placements: DASHBOARD } },
	'dashboard, with interactions': { scope: INTERACT, payload: { dashboard: { name: 'City dashboard' }, placements: DASHBOARD } },
	'widget: chart': { scope: READ, payload: { placements: [placement(1, 'chart', { customTitle: 'Requests per month' })] } },
	'widget: table': { scope: READ, payload: { placements: [placement(2, 'object-list', { customTitle: 'Open cases' })] } },
	'widget: text': { scope: READ, payload: { placements: [placement(4, 'text', { customTitle: 'About', content: { text: 'Updated every night.' } })] } },
	'widget: image': { scope: READ, payload: { placements: [placement(5, 'image', { customTitle: 'Town hall', content: { url: 'https://www.example.com/hall.png' } })] } },
	'widget: untitled image': { scope: READ, payload: { placements: [placement(5, 'image', { content: { url: 'https://www.example.com/hall.png' } })] } },
	'widget: icon-only tile': { scope: INTERACT, payload: { placements: [placement(8, 'tile-mail', { tileType: 'app', tileLinkValue: '/apps/mail', tileIcon: '/apps/mail/img/mail.svg' })] } },
	loading: { scope: READ, loading: true, payload: null },
	refused: { scope: READ, error: { error: 'token_revoked' }, payload: null },
}

/**
 * An embed context in the given state.
 *
 * @param {object} shape The shape: scope, payload, loading, error.
 * @return {object}
 */
function embedContext(shape) {
	const payload = ref(shape.payload)
	return {
		loading: ref(shape.loading === true),
		error: ref(shape.error || null),
		payload,
		placements: computed(() => payload.value?.placements || []),
		scope: computed(() => shape.scope),
		filters: computed(() => ({})),
		canRun: () => false,
		relay: vi.fn(() => false),
		start: () => () => {},
	}
}

const report = {}
let wrapper = null

describe('the embed render passes axe-core', () => {
	beforeAll(() => {
		// The base layout the embed page renders in sets both.
		document.documentElement.lang = 'en'
		document.title = 'Embed - LaunchPad'
	})

	afterEach(() => {
		wrapper?.unmount()
		wrapper = null
	})

	afterAll(() => {
		mkdirSync(dirname(REPORT), { recursive: true })
		writeFileSync(REPORT, JSON.stringify({ axeVersion: axe.version, shapes: report }, null, '\t') + '\n')
	})

	it.each(Object.keys(SHAPES))('%s', async (name) => {
		context.current = embedContext(SHAPES[name])
		wrapper = mount(EmbedView, { attachTo: document.body })
		await nextTick()

		const { failures, warnings } = classify(await runAxe(wrapper.element))
		report[name] = { failures, warnings }
		if (warnings.length > 0) {
			console.warn(`[axe] ${name}: ${warnings.map((warning) => `${warning.id} (${warning.impact})`).join(', ')}`)
		}

		expect(failures, `serious or critical axe violations in "${name}"`).toEqual([])
	})
})
