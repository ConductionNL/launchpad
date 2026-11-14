/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the display, mapping and preset of
 * `LiveTileWidgetForm.vue` (REQ-LTC-001, REQ-LTC-004). `liveTileClient.js` is mocked.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'
import {
	fetchConnectorAvailability,
	validateLiveTileSource,
} from '../../../../services/liveTileClient.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchConnectorAvailability: vi.fn(),
	validateLiveTileSource: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function lastEmitted(wrapper) {
	return (wrapper.emitted('update:content') || []).at(-1)?.[0]
}

async function mountForm(value = { url: 'https://a.test/x', valueExpr: '$.n' }) {
	const wrapper = mount(LiveTileWidgetForm, { props: { value } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	fetchConnectorAvailability.mockReset()
	fetchConnectorAvailability.mockResolvedValue(true)
	validateLiveTileSource.mockReset()
	validateLiveTileSource.mockResolvedValue({ valid: true, errors: [] })
})

describe('LiveTileWidgetForm display and mapping', () => {
	it('a single-value tile is stored without display keys', async () => {
		const wrapper = await mountForm()
		wrapper.vm.updateField('label', 'Open')
		const content = lastEmitted(wrapper)
		expect(content).not.toHaveProperty('display')
		expect(content).not.toHaveProperty('mapping')
		expect(content).not.toHaveProperty('limit')
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('choosing List starts one title field and stores display, mapping and limit', async () => {
		const wrapper = await mountForm()
		wrapper.vm.onDisplayChange('list')
		expect(lastEmitted(wrapper)).toMatchObject({ display: 'list', limit: 10, mapping: [{ label: '', path: '', role: 'title' }] })
		expect(wrapper.vm.validate()).toEqual(['Every field needs a path'])

		wrapper.vm.updateMapping(0, 'path', 'briefDescription')
		wrapper.vm.addMapping()
		wrapper.vm.updateMapping(1, 'path', 'url')
		wrapper.vm.updateMapping(1, 'role', 'link')
		wrapper.vm.onLimitChange('5')
		expect(lastEmitted(wrapper)).toMatchObject({
			display: 'list',
			limit: 5,
			mapping: [
				{ label: '', path: 'briefDescription', role: 'title' },
				{ label: '', path: 'url', role: 'link' },
			],
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('allows six fields at most, and checks the limit', async () => {
		const wrapper = await mountForm()
		wrapper.vm.onDisplayChange('table')
		for (let i = 0; i < 8; i++) {
			wrapper.vm.addMapping()
		}
		expect(wrapper.vm.mapping).toHaveLength(6)
		wrapper.vm.mapping.forEach((_, index) => wrapper.vm.updateMapping(index, 'path', 'f' + index))
		wrapper.vm.onLimitChange('51')
		expect(wrapper.vm.validate()).toEqual(['The number of rows is from 1 to 50'])
		wrapper.vm.removeMapping(0)
		expect(lastEmitted(wrapper).mapping).toHaveLength(5)
	})

	it('a key-value tile stores no limit', async () => {
		const wrapper = await mountForm()
		wrapper.vm.onDisplayChange('keyvalue')
		expect(lastEmitted(wrapper)).not.toHaveProperty('limit')
	})

	it('the SharePoint preset fills a latest-documents list for the author to confirm', async () => {
		const wrapper = await mountForm({ sourceMode: 'connector', sourceId: 'intranet-docs', valueExpr: '' })
		wrapper.vm.onDisplayChange('list')
		wrapper.vm.applyPreset({ value: 'sharepoint' })
		expect(lastEmitted(wrapper)).toMatchObject({
			sourceId: 'intranet-docs',
			valueExpr: '$.value',
			display: 'list',
			limit: 10,
			mapping: [
				{ label: 'Document', path: 'name', role: 'title' },
				{ label: 'Open', path: 'webUrl', role: 'link' },
				{ label: 'Last modified', path: 'lastModifiedDateTime', role: 'date' },
				{ label: 'Modified by', path: 'lastModifiedBy.user.displayName', role: 'field' },
			],
		})
	})

	it('the Confluence preset reads the results array', async () => {
		const wrapper = await mountForm()
		wrapper.vm.applyPreset('confluence')
		expect(lastEmitted(wrapper)).toMatchObject({ valueExpr: '$.results', mapping: [{ path: 'title' }, { path: '_links.webui' }, { path: 'version.when' }, { path: 'version.by.displayName' }] })
	})

	it('reopens a saved list tile with its mapping', async () => {
		const wrapper = await mountForm({ url: 'https://a.test/x', valueExpr: '$.items', display: 'table', limit: 3, mapping: [{ label: 'Naam', path: 'name', role: 'title' }] })
		expect(wrapper.vm.display).toBe('table')
		expect(wrapper.vm.limit).toBe(3)
		expect(wrapper.vm.mapping).toEqual([{ label: 'Naam', path: 'name', role: 'title' }])
	})
})
