/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * integrations-office-tiles REQ-OFT-002: a `create` tile opens the name
 * dialog instead of a link, creates the file through the office-tiles
 * endpoint and opens it; a refusal shows the server's message.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const recordTileClick = vi.fn()
const createOfficeFile = vi.fn()
const showError = vi.fn()

vi.mock('@conduction/nextcloud-vue', () => ({
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div class="cog" />' },
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/nc' + path }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/dialogs', () => ({ showError }))
vi.mock('../../services/api.js', () => ({ api: { createOfficeFile } }))
vi.mock('../../composables/useTileClickTracking.js', () => ({
	useTileClickTracking: () => ({ recordTileClick }),
}))
vi.mock('../HealthPingBadge.vue', () => ({ default: { name: 'HealthPingBadge', template: '<span />' } }))
vi.mock('../../dialogs/OfficeFileDialog.vue', () => ({
	default: {
		name: 'OfficeFileDialog',
		props: ['open', 'title', 'defaultName', 'busy'],
		emits: ['update:open', 'confirm'],
		template: '<div v-if="open" class="office-dialog" :data-title="title" :data-name="defaultName" :data-busy="String(busy)" />',
	},
}))

const { default: TileWidget } = await import('../TileWidget.vue')

const CREATE = { id: 7, title: 'New document', linkType: 'create', linkValue: 'richdocuments:.docx', iconType: 'class', icon: 'icon-filetype-file' }

describe('TileWidget create tile', () => {
	let assign

	beforeEach(() => {
		recordTileClick.mockReset()
		createOfficeFile.mockReset()
		showError.mockReset()
		assign = vi.fn()
		vi.stubGlobal('location', { ...window.location, assign })
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		document.head.querySelectorAll('style[id^="tile-"]').forEach((el) => el.remove())
	})

	it('links nowhere and opens the name dialog on a click', async () => {
		const wrapper = mount(TileWidget, { props: { tile: CREATE } })
		expect(wrapper.find('a').attributes('href')).toBe('#')
		expect(wrapper.find('.office-dialog').exists()).toBe(false)

		await wrapper.find('a').trigger('click')
		await flushPromises()

		const dialog = wrapper.find('.office-dialog')
		expect(dialog.exists()).toBe(true)
		expect(dialog.attributes('data-title')).toBe('New document')
		expect(dialog.attributes('data-name')).toBe('New document')
		expect(recordTileClick).toHaveBeenCalledWith(7)
	})

	it('does not open the dialog in edit mode', async () => {
		const wrapper = mount(TileWidget, { props: { tile: CREATE, editMode: true } })
		await wrapper.find('a').trigger('click')
		await flushPromises()

		expect(wrapper.find('.office-dialog').exists()).toBe(false)
	})

	it('creates the file from the creator and opens it', async () => {
		createOfficeFile.mockResolvedValue({ data: { fileId: 42, path: '/Documents/Notulen.docx', url: '/f/42' } })
		const wrapper = mount(TileWidget, { props: { tile: CREATE } })
		await wrapper.find('a').trigger('click')
		await flushPromises()

		wrapper.findComponent({ name: 'OfficeFileDialog' }).vm.$emit('confirm', { name: 'Notulen', folder: '/Documents' })
		await flushPromises()

		expect(createOfficeFile).toHaveBeenCalledWith({ creatorId: 'richdocuments:.docx', name: 'Notulen', folder: '/Documents' })
		expect(assign).toHaveBeenCalledWith('/f/42')
		expect(wrapper.find('.office-dialog').exists()).toBe(false)
		expect(showError).not.toHaveBeenCalled()
	})

	it('keeps the dialog open and shows the refusal message', async () => {
		createOfficeFile.mockRejectedValue({ response: { status: 400, data: { error: 'invalid_filename', message: 'Unknown file creator' } } })
		const wrapper = mount(TileWidget, { props: { tile: CREATE } })
		await wrapper.find('a').trigger('click')
		await flushPromises()

		wrapper.findComponent({ name: 'OfficeFileDialog' }).vm.$emit('confirm', { name: 'Notulen', folder: '/Documents' })
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('Unknown file creator')
		expect(assign).not.toHaveBeenCalled()
		expect(wrapper.find('.office-dialog').attributes('data-busy')).toBe('false')
	})

	it('falls back to a generic message when the server sends none', async () => {
		createOfficeFile.mockRejectedValue(new Error('network'))
		const wrapper = mount(TileWidget, { props: { tile: CREATE } })
		await wrapper.find('a').trigger('click')
		await flushPromises()

		wrapper.findComponent({ name: 'OfficeFileDialog' }).vm.$emit('confirm', { name: 'Notulen', folder: '/Documents' })
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('The file could not be created.')
	})

	it('leaves other tiles without the dialog', () => {
		const wrapper = mount(TileWidget, { props: { tile: { ...CREATE, id: 8, linkType: 'app', linkValue: 'files' } } })

		expect(wrapper.findComponent({ name: 'OfficeFileDialog' }).exists()).toBe(false)
		expect(wrapper.find('a').attributes('href')).toBe('/nc/apps/files')
	})
})
