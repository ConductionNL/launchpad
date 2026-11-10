/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Characterisation tests pinning the initial form state of
 * `NewsWidgetForm.vue` and `LiveTileWidgetForm.vue`, and the error and
 * paging state `FilesWidget.vue` keeps after a contents fetch, so those
 * functions can be split into named steps without changing behaviour.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FilesWidget from '../FilesWidget.vue'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'
import NewsWidgetForm from '../NewsWidgetForm.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path, params = {}) =>
		Object.entries(params).reduce(
			(acc, [key, value]) => acc.replace(`{${key}}`, value),
			path,
		),
}))
vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchConnectorAvailability: vi.fn(() => Promise.resolve(false)),
	validateLiveTileSource: vi.fn(() => Promise.resolve({ valid: true, errors: [] })),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
})

const NEWS_KEYS = [
	'feedUrls', 'layout', 'itemLimit', 'showThumbnails', 'showSummary',
	'summaryMaxChars', 'dateFormat', 'metadataFilterEnabled',
	'metadataFieldKey', 'metadataValue',
]

function newsState(props) {
	const vm = mount(NewsWidgetForm, { props }).vm
	return Object.fromEntries(NEWS_KEYS.map((key) => [key, vm.$data[key]]))
}

describe('NewsWidgetForm initial state', () => {
	it('falls back to defaults for malformed values', () => {
		expect(newsState({
			value: {
				feedUrls: 'x',
				layout: 'masonry',
				itemLimit: '5',
				showThumbnails: 'yes',
				showSummary: undefined,
				summaryMaxChars: null,
				dateFormat: 'ABSOLUTE',
				metadataFilter: { fieldKey: 3, value: null },
			},
		})).toEqual({
			feedUrls: [],
			layout: 'list',
			itemLimit: 10,
			showThumbnails: false,
			showSummary: true,
			summaryMaxChars: 200,
			dateFormat: 'relative',
			metadataFilterEnabled: true,
			metadataFieldKey: '',
			metadataValue: '',
		})
	})

	it('reads the value prop when not editing and copies feed URLs', () => {
		const feedUrls = ['https://a.example/rss']
		const state = newsState({
			value: { feedUrls, layout: 'grid', itemLimit: 0, showSummary: false, metadataFilter: null },
		})
		expect(state).toMatchObject({
			feedUrls,
			layout: 'grid',
			itemLimit: 0,
			showThumbnails: true,
			showSummary: false,
			metadataFilterEnabled: false,
			metadataFieldKey: '',
			metadataValue: '',
		})
	})

	it('prefers the editing widget content over the value prop', () => {
		const state = newsState({
			editingWidget: { content: { layout: 'carousel', metadataFilter: { fieldKey: 'k', value: 'v' } } },
			value: { layout: 'grid' },
		})
		expect(state).toMatchObject({
			layout: 'carousel',
			metadataFilterEnabled: true,
			metadataFieldKey: 'k',
			metadataValue: 'v',
		})
	})

	it('treats a string metadata filter as no filter', () => {
		expect(newsState({ value: { metadataFilter: 'x' } })).toMatchObject({
			metadataFilterEnabled: false,
			metadataFieldKey: '',
		})
	})
})

const LIVE_KEYS = [
	'label', 'sourceMode', 'url', 'sourceId', 'valueExpr', 'refresh',
	'formatPrefix', 'formatSuffix', 'formatThousands', 'badge', 'linkUrl',
	'linkTarget', 'connectorAvailable', 'urlAllowListError', 'MIN_REFRESH_SECONDS',
]

function liveState(props) {
	const vm = mount(LiveTileWidgetForm, { props }).vm
	return Object.fromEntries(LIVE_KEYS.map((key) => [key, vm.$data[key]]))
}

describe('LiveTileWidgetForm initial state', () => {
	it('keeps falsy-but-set values and defaults null ones', () => {
		const state = liveState({
			value: {
				label: '',
				sourceMode: null,
				url: 'https://x.example',
				sourceId: 0,
				valueExpr: undefined,
				refresh: 0,
				format: { prefix: '€', thousands: 1 },
				badge: { enabled: true },
				linkUrl: null,
				linkTarget: 'new-tab',
			},
		})
		const defaults = liveState({ value: {} })
		expect(state).toEqual({
			...defaults,
			label: '',
			url: 'https://x.example',
			sourceId: 0,
			refresh: 0,
			formatPrefix: '€',
			formatThousands: true,
			badge: { ...defaults.badge, enabled: true },
			linkTarget: 'new-tab',
		})
		expect(defaults.connectorAvailable).toBe(false)
		expect(defaults.urlAllowListError).toBe('')
		expect(defaults.MIN_REFRESH_SECONDS).toBe(30)
		expect(defaults.formatThousands).toBe(false)
	})

	it('prefers the editing widget content and survives null format and badge', () => {
		const state = liveState({
			editingWidget: { content: { label: 'Edit', format: null, badge: null } },
			value: { label: 'Value' },
		})
		const defaults = liveState({ value: {} })
		expect(state).toEqual({ ...defaults, label: 'Edit' })
	})
})

function mountFiles() {
	return mount(FilesWidget, {
		propsData: { placement: { id: 7 }, content: { folderPath: '/x' } },
	})
}

describe('FilesWidget contents fetch state', () => {
	it.each([
		[{ response: { status: 500, data: { error: 'folder_not_found' } } }, 'folder_not_found'],
		[{ response: { status: 500, data: { error: 'no_access' } } }, 'no_access'],
		[{ response: { status: 404, data: { error: 'no_access' } } }, 'folder_not_found'],
		[{ response: { status: 500 } }, 'unknown_error'],
		[new Error('network'), 'unknown_error'],
	])('maps a failure to its error code', async (err, code) => {
		axios.get.mockRejectedValue(err)
		const wrapper = mountFiles()
		await flushPromises()
		expect(wrapper.vm.errorCode).toBe(code)
		expect(wrapper.vm.items).toEqual([])
		expect(wrapper.vm.nextCursor).toBe(null)
		expect(wrapper.vm.loading).toBe(false)
	})

	it('appends the next page with the cursor and keeps items when a later page fails', async () => {
		axios.get.mockResolvedValueOnce({ data: { items: [{ fileId: 1, name: 'a' }], nextCursor: 'c1' } })
		const wrapper = mountFiles()
		await flushPromises()
		expect(axios.get).toHaveBeenLastCalledWith(
			'/apps/launchpad/api/widgets/files/7/contents',
			{ params: { currentPath: '/', limit: 50 } },
		)

		axios.get.mockResolvedValueOnce({ data: { items: 'bad', nextCursor: '' } })
		await wrapper.vm.fetchContents(true)
		expect(axios.get).toHaveBeenLastCalledWith(
			'/apps/launchpad/api/widgets/files/7/contents',
			{ params: { currentPath: '/', limit: 50, cursor: 'c1' } },
		)
		expect(wrapper.vm.items).toEqual([{ fileId: 1, name: 'a' }])
		expect(wrapper.vm.nextCursor).toBe(null)

		wrapper.vm.nextCursor = 'c2'
		axios.get.mockRejectedValueOnce({ response: { status: 403 } })
		await wrapper.vm.fetchContents(true)
		expect(wrapper.vm.errorCode).toBe('no_access')
		expect(wrapper.vm.items).toEqual([{ fileId: 1, name: 'a' }])
		expect(wrapper.vm.nextCursor).toBe('c2')
	})

	it('sends no cursor on a replace fetch and treats a missing body as empty', async () => {
		axios.get.mockResolvedValueOnce({})
		const wrapper = mountFiles()
		await flushPromises()
		expect(wrapper.vm.items).toEqual([])
		expect(wrapper.vm.nextCursor).toBe(null)
		expect(wrapper.vm.errorCode).toBe(null)
	})

	it('does not fetch for an unsaved placement', async () => {
		mount(FilesWidget, { propsData: { placement: { id: 0 }, content: {} } })
		await flushPromises()
		expect(axios.get).not.toHaveBeenCalled()
	})
})
