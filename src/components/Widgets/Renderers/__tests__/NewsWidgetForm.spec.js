/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `NewsWidgetForm.vue`.
 *
 * The form moved here from `@conduction/nextcloud-vue`, where it was
 * `CnNewsWidgetForm`. The content shape it assembles is what LaunchPad's
 * NewsWidgetService reads off a placement, so these tests pin that shape
 * rather than the markup: a placement authored before the move must keep
 * rendering, and one authored after must still be readable by the service.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'

let NewsWidgetForm

beforeEach(async () => {
	globalThis.t = (_app, key) => key
	NewsWidgetForm = (await import('../NewsWidgetForm.vue')).default
})

/** @return {object} a mounted form in create mode. */
function mountForm(props = {}) {
	return mount(NewsWidgetForm, { props })
}

describe('NewsWidgetForm', () => {
	it('assembles the content shape NewsWidgetService reads', () => {
		const wrapper = mountForm()
		wrapper.vm.updateField('layout', 'grid')

		const payload = wrapper.emitted('update:content').pop()[0]
		expect(payload).toEqual({
			feedUrls: [],
			layout: 'grid',
			itemLimit: 10,
			showThumbnails: true,
			showSummary: true,
			summaryMaxChars: 200,
			dateFormat: 'relative',
			metadataFilter: null,
		})
	})

	it('pre-fills from a placement authored before the move', () => {
		const wrapper = mountForm({
			editingWidget: {
				content: {
					feedUrls: ['https://example.com/rss'],
					layout: 'carousel',
					itemLimit: 25,
					showThumbnails: false,
					showSummary: false,
					summaryMaxChars: 80,
					dateFormat: 'absolute',
					metadataFilter: { fieldKey: 'department', value: 'marketing' },
				},
			},
		})

		expect(wrapper.vm.assembledContent).toEqual({
			feedUrls: ['https://example.com/rss'],
			layout: 'carousel',
			itemLimit: 25,
			showThumbnails: false,
			showSummary: false,
			summaryMaxChars: 80,
			dateFormat: 'absolute',
			metadataFilter: { fieldKey: 'department', value: 'marketing' },
		})
	})

	it('adds and removes feed URLs', async () => {
		const wrapper = mountForm()
		wrapper.vm.addFeedUrl()
		wrapper.vm.updateFeedUrl(0, 'https://example.com/feed.xml')
		expect(wrapper.vm.assembledContent.feedUrls).toEqual([
			'https://example.com/feed.xml',
		])

		wrapper.vm.removeFeedUrl(0)
		expect(wrapper.vm.assembledContent.feedUrls).toEqual([])
	})

	it('clamps the item limit to the range the endpoint accepts', () => {
		const wrapper = mountForm()
		// WidgetApiController::newsItems rejects anything outside 1..50.
		wrapper.vm.updateNumericField('itemLimit', '999', 1, 50)
		expect(wrapper.vm.assembledContent.itemLimit).toBe(50)
		wrapper.vm.updateNumericField('itemLimit', '0', 1, 50)
		expect(wrapper.vm.assembledContent.itemLimit).toBe(1)
	})

	it('drops the metadata filter when it is switched off', () => {
		const wrapper = mountForm()
		wrapper.vm.toggleMetadataFilter(true)
		wrapper.vm.updateMetadataField('fieldKey', 'department')
		expect(wrapper.vm.assembledContent.metadataFilter).toEqual({
			fieldKey: 'department',
			value: '',
		})

		wrapper.vm.toggleMetadataFilter(false)
		expect(wrapper.vm.assembledContent.metadataFilter).toBeNull()
	})
})
