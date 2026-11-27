/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every widget type draws its miniature from its sample content with the
 * `preview` flag and sends no request (add-widget-dialog-with-miniatures
 * REQ-WDG-048). A type without sample content fails here, so none is
 * forgotten. nextcloud-vue is a stub under vitest, so its renderers are
 * checked for requests live, in tests/e2e/widget-picker-miniatures.spec.ts.
 *
 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
 */

import { flushPromises, mount } from '@vue/test-utils'
import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const TYPES = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../../lib/widget-types.json'), 'utf8')).types

/**
 * The assistant's renderer chunk does not finish importing in this test
 * bundle when other renderers loaded before it, so it is drawn in
 * AiAssistantWidget's own preview test instead of here.
 */
const DRAWN_ELSEWHERE = ['ai-assistant']

/**
 * What a miniature that draws a loaded state shows from its sample data
 * (board LpWidgetKiezen: the miniature looks like the widget in use).
 */
const SAMPLE_TEXT = {
	announcements: 'New opening hours of the town hall',
	attention: 'Permit Dorpsstraat 12',
	'colleague-activity': 'Anna de Vries',
	livetile: '128',
	'recent-work': 'Planning.xlsx',
	weather: 'Utrecht',
}

const NOT_SAFE = []

let requests

beforeEach(() => {
	globalThis.t = (_app, key) => key
	globalThis.n = (_app, one) => one
	setActivePinia(createPinia())
	requests = []
	vi.spyOn(XMLHttpRequest.prototype, 'open').mockImplementation(function(method, url) {
		requests.push(`${method} ${url}`)
	})
	vi.spyOn(XMLHttpRequest.prototype, 'send').mockImplementation(() => {})
	vi.stubGlobal('fetch', vi.fn(async (url) => {
		requests.push(`fetch ${url}`)
		return { ok: true, json: async () => ({}), text: async () => '' }
	}))
	vi.stubGlobal('IntersectionObserver', undefined)
})

afterEach(() => {
	vi.restoreAllMocks()
	vi.unstubAllGlobals()
})

describe('every widget type as a miniature', () => {
	it('has sample content for every type', async () => {
		const { getWidgetTypeEntry } = await import('../../constants/widgetRegistry.js')
		const { sampleContentOf } = await import('../../constants/widgetSamples.js')
		const { NOT_PREVIEW_SAFE, SAMPLE_CONTENT } = await import('../../constants/widgetSamples.js')
		const missing = TYPES.filter((type) => !SAMPLE_CONTENT[type] && !getWidgetTypeEntry(type)?.sampleContent)
		expect(missing).toEqual([])
		// Until nextcloud-vue honours preview for them, these draw their icon.
		expect(NOT_PREVIEW_SAFE.map((type) => sampleContentOf(type, getWidgetTypeEntry(type))).every((sample) => sample === null)).toBe(true)
	})

	it('has sample content for the content-only nextcloud-vue types the picker offers', async () => {
		const { sampleContentOf } = await import('../../constants/widgetSamples.js')
		expect(sampleContentOf('banner')).toMatchObject({ variant: 'info' })
		expect(sampleContentOf('markdown').markdown).toContain('Team meeting')
	})

	it.each(TYPES.filter((type) => !DRAWN_ELSEWHERE.includes(type)))('draws %s from its sample with preview and sends no request', async (type) => {
		const { getWidgetTypeEntry } = await import('../../constants/widgetRegistry.js')
		const { default: WidgetMiniature } = await import('../WidgetMiniature.vue')
		const wrapper = mount(WidgetMiniature, { props: { type, entry: getWidgetTypeEntry(type) }, attachTo: document.body })
		const state = () => wrapper.find('.widget-miniature').attributes('data-state')
		// Some renderer chunks (the assistant) take seconds to import here.
		await vi.waitFor(() => expect(state()).not.toBe('loading'), { timeout: 12000, interval: 50 })
		await flushPromises()
		await new Promise((resolve) => setTimeout(resolve, 20))

		expect(requests).toEqual([])
		expect(state()).toBe(NOT_SAFE.includes(type) ? 'fallback' : 'ready')
		if (SAMPLE_TEXT[type]) {
			expect(wrapper.text()).toContain(SAMPLE_TEXT[type])
		}
		wrapper.unmount()
	}, 20000)
})
