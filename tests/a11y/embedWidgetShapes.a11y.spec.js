/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Renders representative widget shapes (chart, table, text, image) through
 * the embed page and runs axe-core on each, writing
 * docs/accessibility/axe-results.json for src/axe-core-check.js to judge.
 * Run by `npm run check:a11y` as part of `npm run build`.
 *
 * The shapes go through the real `EmbedView` and `PublicDashboardGrid`, so
 * the report covers what an embed shows: a chart or a table placement shows
 * the signed-in-only notice there today, and is checked as such.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-010--wcag-21-aa-compliance-enforced-in-embed-rendering
 */

import { mount } from '@vue/test-utils'
import axe from 'axe-core'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { afterAll, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import EmbedView from '../../src/views/EmbedView.vue'
import { WCAG_TAGS } from '../../src/axe-core-check.js'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))

const context = vi.hoisted(() => ({ current: null }))
vi.mock('../../src/composables/useEmbedContext.js', () => ({ useEmbedContext: () => context.current }))

const REPORT = resolve(__dirname, '../../docs/accessibility/axe-results.json')

const SCOPE = { mode: 'read-with-interactions', allowedActions: ['drillDown'] }

/** One placement per representative shape, as the embed data route returns them. */
const SHAPES = {
	chart: { id: 1, widgetId: 'chart', gridX: 0, gridY: 0, customTitle: 'Open requests per month', content: { chartKind: 'bar' } },
	table: { id: 2, widgetId: 'table', gridX: 4, gridY: 0, customTitle: 'Requests by department', content: {} },
	text: { id: 3, widgetId: 'text', gridX: 0, gridY: 4, customTitle: 'About these figures', content: { text: 'Updated every night from the case register.' } },
	image: { id: 4, widgetId: 'image', gridX: 4, gridY: 4, customTitle: 'Map of the districts', content: { url: 'data:image/png;base64,iVBORw0KGgo=' } },
	tile: { id: 5, widgetId: 'tile-cases', tileType: 'app', gridX: 8, gridY: 0, tileTitle: 'All cases', tileLinkValue: '/apps/cases' },
}

const results = []

/**
 * An embed context with the given placements loaded.
 *
 * @param {Array<object>} placements The subject's placements.
 * @return {object}
 */
function embedContext(placements) {
	const payload = ref({ scope: SCOPE, filters: {}, placements, dashboard: { name: 'Open requests' } })
	return {
		loading: ref(false),
		error: ref(null),
		payload,
		placements: computed(() => payload.value.placements),
		scope: computed(() => SCOPE),
		filters: computed(() => ({})),
		canRun: (action) => SCOPE.allowedActions.includes(action),
		relay: vi.fn(() => true),
		start: () => () => {},
	}
}

/**
 * Run axe-core on an element with the WCAG 2.1 A and AA rules.
 *
 * @param {Element} element The rendered markup.
 * @return {Promise<object>} The axe results.
 */
function runAxe(element) {
	return axe.run(element, { runOnly: { type: 'tag', values: WCAG_TAGS }, resultTypes: ['violations', 'incomplete'] })
}

/**
 * Mount the embed page with the given placements and check it.
 *
 * @param {string} shape The shape's name in the report.
 * @param {Array<object>} placements The placements to render.
 * @return {Promise<object>} The axe results.
 */
async function checkShape(shape, placements) {
	document.documentElement.lang = 'en'
	document.title = 'Open requests'
	context.current = embedContext(placements)
	const wrapper = mount(EmbedView, {
		attachTo: document.body,
		global: { stubs: { NcLoadingIcon: true, NcEmptyContent: true } },
	})
	const found = await runAxe(wrapper.element)
	wrapper.unmount()
	results.push({
		shape,
		violations: found.violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.length })),
		incomplete: found.incomplete.map((v) => v.id).sort(),
	})
	return found
}

describe('axe-core on the embed widget shapes', () => {
	it.each(Object.keys(SHAPES))('renders and checks the %s shape', async (shape) => {
		const found = await checkShape(shape, [SHAPES[shape]])
		expect(found.passes.length + found.violations.length).toBeGreaterThan(0)
	})

	it('renders and checks all shapes on one dashboard', async () => {
		await checkShape('dashboard', Object.values(SHAPES))
	})

	it('reports a violation it is shown, so a clean report is a real result', async () => {
		const host = document.createElement('div')
		host.innerHTML = '<img src="data:image/png;base64,iVBORw0KGgo="><a href="/x"></a>'
		document.body.appendChild(host)
		const found = await runAxe(host)
		host.remove()
		expect(found.violations.map((v) => v.id)).toEqual(expect.arrayContaining(['image-alt', 'link-name']))
	})

	afterAll(() => {
		mkdirSync(dirname(REPORT), { recursive: true })
		const report = { axeVersion: axe.version, tags: WCAG_TAGS, shapes: results.sort((a, b) => a.shape.localeCompare(b.shape)) }
		writeFileSync(REPORT, JSON.stringify(report, null, '\t') + '\n')
	})
})
