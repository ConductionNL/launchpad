/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Add tiles dialog (add-tiles-dialog REQ-TILE-043, REQ-TILE-044): the four
 * sections in board order, counting, adding from each section, no duplicate
 * tile, and the hand-over to the bookmark import.
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md
 */
import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import AddTilesModal from '../AddTilesModal.vue'
import BookmarkImportModal from '../BookmarkImportModal.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k] ?? `{${k}}`)),
}))

const stubs = {
	NcModal: { name: 'NcModal', props: ['name'], emits: ['close'], template: '<div class="nc-modal-stub"><slot /></div>' },
	NcButton: {
		props: ['disabled', 'variant'],
		emits: ['click'],
		template: '<button class="btn" :data-variant="variant" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="field" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
}

const CATALOGUE = [
	{ uuid: 'c1', name: 'Wiki', description: 'Team pages', linkType: 'url', linkValue: 'https://wiki.test' },
	{ uuid: 'c2', name: 'Deck', description: 'Boards', linkType: 'app', linkValue: 'deck' },
]
const FOUND = [
	{ appId: 'grafana', name: 'Grafana', href: '/apps/grafana/' },
	{ appId: 'deck', name: 'Deck', href: '/apps/deck/' },
]
const OFFICE = [
	{ title: 'Word', linkType: 'create', linkValue: 'doc', icon: 'icon-filetype-file', iconType: 'class' },
	{ title: 'Excel', linkType: 'create', linkValue: 'xls', icon: 'icon-filetype-file', iconType: 'class' },
	{ title: 'PowerPoint', linkType: 'create', linkValue: 'ppt', icon: 'icon-filetype-file', iconType: 'class' },
	{ title: 'Outlook web', linkType: 'url', linkValue: 'https://outlook.office.com/mail/', icon: '', iconType: 'class' },
]

function mountModal(props = {}) {
	return mount(AddTilesModal, {
		props: {
			open: true,
			catalogueApps: CATALOGUE,
			discoveredApps: FOUND,
			officeTiles: OFFICE,
			placements: [],
			addTiles: vi.fn().mockResolvedValue(undefined),
			...props,
		},
		global: { stubs },
	})
}

const confirm = (wrapper) => wrapper.find('[data-testid="add-tiles-confirm"]')
const office = (wrapper, key) => wrapper.find(`[data-testid="add-tiles-office-${key}"]`)

describe('AddTilesModal sections', () => {
	it('shows the four sections in board order', () => {
		const wrapper = mountModal()
		const order = ['add-tiles-catalogue', 'add-tiles-found', 'add-tiles-office', 'add-tiles-bookmarks']
		const found = wrapper.findAll('[data-testid^="add-tiles-"]')
			.map((node) => node.attributes('data-testid'))
			.filter((id) => order.includes(id))
		expect(found).toEqual(order)
	})

	it('names how each catalogue app signs on and filters by the search', async () => {
		const wrapper = mountModal()
		const rows = wrapper.findAll('[data-testid="add-tiles-catalogue-row"]')
		expect(rows[0].text()).toContain('Asks for its own sign-in')
		expect(rows[1].text()).toContain('Signs in with your Nextcloud account')
		await wrapper.find('input.field').setValue('boards')
		expect(wrapper.findAll('[data-testid="add-tiles-catalogue-row"]').map((row) => row.text())).toEqual([expect.stringContaining('Deck')])
	})

	it('lists a found app without a tile as Running with Add as tile', () => {
		const wrapper = mountModal({ placements: [{ widgetId: 'app-tile', content: { appId: 'deck' } }] })
		const rows = wrapper.findAll('[data-testid="add-tiles-found-row"]')
		expect(rows).toHaveLength(1)
		expect(rows[0].text()).toContain('Grafana')
		expect(rows[0].text()).toContain('Running')
		expect(rows[0].text()).toContain('Add as tile')
	})
})

describe('AddTilesModal services found on the server (REQ-TILE-044)', () => {
	const SERVICES = [
		{ name: 'Grafana', url: 'http://localhost:3000', port: 3000, state: 'running', foundAt: 1795757445 },
		{ name: 'Uptime Kuma', url: 'http://localhost:3001', port: 3001, state: 'not_responding', foundAt: 1795750000 },
	]

	it('Grafana found: listed as Running with its port and Add as tile (scenario)', () => {
		const wrapper = mountModal({ discoveredApps: [], foundServices: SERVICES })
		expect(wrapper.find('[data-testid="add-tiles-found"]').exists()).toBe(true)
		const rows = wrapper.findAll('[data-testid="add-tiles-found-service"]')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('Grafana')
		expect(rows[0].find('[data-testid="add-tiles-found-service-meta"]').text()).toMatch(/^Running · port 3000 · found .+/)
		expect(rows[0].text()).toContain('Add as tile')
		expect(rows[1].text()).toContain('Not responding · port 3001')
	})

	it('leaves out a service the dashboard already has a tile for', () => {
		const wrapper = mountModal({
			discoveredApps: [],
			foundServices: SERVICES,
			placements: [{ tileLinkType: 'url', tileLinkValue: 'http://localhost:3000' }],
		})
		const rows = wrapper.findAll('[data-testid="add-tiles-found-service"]')
		expect(rows.map((row) => row.text())).toEqual([expect.stringContaining('Uptime Kuma')])
	})

	it('adds a found service as a web-address tile', async () => {
		const addTiles = vi.fn().mockResolvedValue(undefined)
		const wrapper = mountModal({ discoveredApps: [], foundServices: SERVICES, addTiles })
		await wrapper.findAll('[data-testid="add-tiles-found-service"]')[0].find('button').trigger('click')
		expect(confirm(wrapper).text()).toContain('1')
		await confirm(wrapper).trigger('click')
		await flushPromises()
		expect(addTiles).toHaveBeenCalledWith([
			{ key: 'url:http://localhost:3000', kind: 'tile', tile: { title: 'Grafana', icon: 'icon-link', iconType: 'class', linkType: 'url', linkValue: 'http://localhost:3000' } },
		])
	})

	it('shows no Found section when nothing was found and the app menu offers nothing', () => {
		const wrapper = mountModal({ discoveredApps: [], foundServices: [] })
		expect(wrapper.find('[data-testid="add-tiles-found"]').exists()).toBe(false)
	})
})

describe('AddTilesModal Add Office tiles (scenario)', () => {
	it('reads Add 3 tiles after ticking Word, Excel and Outlook web and adds three tiles', async () => {
		const addTiles = vi.fn().mockResolvedValue(undefined)
		const wrapper = mountModal({ addTiles })
		expect(confirm(wrapper).text()).toBe('Add tiles')
		expect(confirm(wrapper).attributes('disabled')).toBeDefined()

		await office(wrapper, 'create:doc').setValue(true)
		expect(confirm(wrapper).text()).toBe('Add 1 tile')
		await office(wrapper, 'create:xls').setValue(true)
		await office(wrapper, 'url:https://outlook.office.com/mail/').setValue(true)
		expect(confirm(wrapper).text()).toBe('Add 3 tiles')

		await confirm(wrapper).trigger('click')
		await flushPromises()
		expect(addTiles).toHaveBeenCalledTimes(1)
		expect(addTiles.mock.calls[0][0].map((choice) => choice.tile.title)).toEqual(['Word', 'Excel', 'Outlook web'])
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('unticking takes a tile off the count', async () => {
		const wrapper = mountModal()
		await office(wrapper, 'create:doc').setValue(true)
		await office(wrapper, 'create:doc').setValue(false)
		expect(confirm(wrapper).text()).toBe('Add tiles')
	})
})

describe('AddTilesModal adding from each section without duplicates', () => {
	it('adds from the catalogue, the found apps and Office together, the same app once', async () => {
		const addTiles = vi.fn().mockResolvedValue(undefined)
		const wrapper = mountModal({ addTiles })
		const catalogueDeck = wrapper.findAll('[data-testid="add-tiles-catalogue-row"]')[1].find('button')
		await catalogueDeck.trigger('click')
		expect(catalogueDeck.text()).toBe('Added')
		await wrapper.findAll('[data-testid="add-tiles-found-row"]')[0].find('button').trigger('click')
		// Deck found on the server is the same tile as Deck from the catalogue:
		// it already reads Added there.
		expect(wrapper.findAll('[data-testid="add-tiles-found-row"]')[1].find('button').text()).toBe('Added')
		await office(wrapper, 'create:ppt').setValue(true)
		expect(confirm(wrapper).text()).toBe('Add 3 tiles')

		await confirm(wrapper).trigger('click')
		await flushPromises()
		const added = addTiles.mock.calls[0][0]
		expect(added.map((choice) => choice.key)).toEqual(['app:deck', 'app:grafana', 'create:ppt'])
		expect(added[1]).toEqual({ key: 'app:grafana', kind: 'app-tile', tile: { appId: 'grafana' } })
	})

	it('shows a catalogue app and an office tile the dashboard holds as such, not as a choice', () => {
		const wrapper = mountModal({
			placements: [
				{ tileLinkType: 'url', tileLinkValue: 'https://wiki.test' },
				{ tileLinkType: 'create', tileLinkValue: 'doc' },
			],
		})
		const wiki = wrapper.findAll('[data-testid="add-tiles-catalogue-row"]')[0]
		expect(wiki.text()).toContain('On your dashboard')
		expect(wiki.find('button').exists()).toBe(false)
		expect(office(wrapper, 'create:doc').element.disabled).toBe(true)
	})

	it('keeps the dialog open and says so when adding fails', async () => {
		const wrapper = mountModal({ addTiles: vi.fn().mockRejectedValue(new Error('409')) })
		await office(wrapper, 'create:doc').setValue(true)
		await confirm(wrapper).trigger('click')
		await flushPromises()
		expect(wrapper.find('[role="alert"]').text()).toBe('The tiles could not be added.')
		expect(wrapper.emitted('close')).toBeUndefined()
	})

	it('forgets the choices when it opens again', async () => {
		const wrapper = mountModal()
		await office(wrapper, 'create:doc').setValue(true)
		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })
		expect(confirm(wrapper).text()).toBe('Add tiles')
	})
})

describe('AddTilesModal ask IT and bookmarks', () => {
	it('asks IT for an app, starting from the search', async () => {
		const requestAppFn = vi.fn().mockResolvedValue({})
		const wrapper = mountModal({ requestAppFn })
		await wrapper.find('input.field').setValue('Grafana')
		await wrapper.find('[data-testid="add-tiles-request"]').trigger('click')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(requestAppFn).toHaveBeenCalledWith({ appName: 'Grafana', purpose: '' })
		expect(wrapper.find('[role="status"]').text()).toBe('Your request was sent to IT')
	})

	it('hides Ask IT without a request function', () => {
		expect(mountModal().find('[data-testid="add-tiles-request"]').exists()).toBe(false)
	})

	it('hands a chosen or dropped bookmarks file to the bookmark import', async () => {
		const wrapper = mountModal()
		const file = { name: 'bookmarks.html' }
		const input = wrapper.find('[data-testid="add-tiles-bookmark-file"]')
		Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
		await input.trigger('change')
		await wrapper.find('[data-testid="add-tiles-drop"]').trigger('drop', { dataTransfer: { files: [file] } })
		expect(wrapper.emitted('importBookmarks')).toEqual([[file], [file]])
	})
})

describe('BookmarkImportModal reads a handed-over file', () => {
	it('reads the initial file when it opens with one', async () => {
		const text = '<DL><p><DT><A HREF="https://wiki.example">Wiki</A></DL>'
		const wrapper = mount(BookmarkImportModal, {
			props: { open: true, importBookmarks: vi.fn(), initialFile: { size: text.length, text: async () => text } },
			global: { stubs },
		})
		await flushPromises()
		expect(wrapper.findAll('[data-testid="bookmark-import-bookmark"]')).toHaveLength(1)
	})
})

describe('AddTilesModal ask IT failure', () => {
	it('keeps what was typed and marks the message when the request fails', async () => {
		const requestAppFn = vi.fn().mockRejectedValue(new Error('offline'))
		const wrapper = mountModal({ requestAppFn })
		await wrapper.find('[data-testid="add-tiles-request"]').trigger('click')
		await wrapper.find('form input.field').setValue('Kaartviewer')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(wrapper.find('[role="status"]').classes()).toContain('add-tiles__message--error')
		expect(wrapper.find('form input.field').element.value).toBe('Kaartviewer')
	})
})
