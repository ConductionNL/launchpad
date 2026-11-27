/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The "Choose the app" list of the App or link tile form
 * (app-link-tile-widget REQ-APPTILE-004, REQ-TILE-044): the Nextcloud apps,
 * the catalogue, the found services and Office in one list; ticking several
 * adds one tile each; a tile on the dashboard is marked; Request app from IT
 * and the bookmark import.
 *
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
 */
import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TileAppChooser from '../TileAppChooser.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k] ?? `{${k}}`)),
}))

const stubs = {
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
	{ uuid: 'c2', name: 'Zaaksysteem', description: 'Cases', linkType: 'url', linkValue: 'https://zaken.test', signOn: 'sso' },
]
const APPS = [
	{ appId: 'files', name: 'Files', href: '/apps/files/', icon: '/core/img/places/files.svg', count: 14 },
	{ appId: 'calendar', name: 'Agenda', href: '/apps/calendar/' },
]
const SERVICES = [{ name: 'Grafana', url: 'http://localhost:3000', state: 'running', port: 3000, foundAt: 0 }]
const OFFICE = [{ title: 'Word', linkType: 'create', linkValue: 'doc', icon: 'icon-filetype-file', iconType: 'class' }]

function mountChooser(props = {}) {
	return mount(TileAppChooser, {
		props: { catalogueApps: CATALOGUE, discoveredApps: APPS, foundServices: SERVICES, officeTiles: OFFICE, placements: [], ...props },
		global: { stubs },
	})
}

const tick = (wrapper, key) => wrapper.find(`[data-testid="tile-app-tick-${key}"]`)

describe('TileAppChooser', () => {
	it('lists the Nextcloud apps, the catalogue, the found services and Office in one list', () => {
		const wrapper = mountChooser()
		const sources = wrapper.findAll('li').map((li) => li.attributes('data-testid'))
		expect(sources).toEqual([
			'tile-app-row-nextcloud', 'tile-app-row-nextcloud',
			'tile-app-row-catalogue', 'tile-app-row-catalogue',
			'tile-app-row-found', 'tile-app-row-office',
		])
		expect(wrapper.text()).toContain('Choose the app')
		expect(wrapper.text()).toContain('Single sign-on')
		expect(wrapper.text()).toContain('Running · port 3000')
	})

	it('lists an app two sources offer once: the Office pack\'s Files is the Nextcloud app Files', async () => {
		const wrapper = mountChooser({
			officeTiles: [{ title: 'Files', linkType: 'app', linkValue: 'files', icon: '', iconType: 'class' }, ...OFFICE],
		})
		expect(wrapper.findAll('[data-testid="tile-app-tick-app:files"]')).toHaveLength(1)
		expect(wrapper.find('[data-testid="tile-app-row-nextcloud"]').text()).toContain('Files')
		await tick(wrapper, 'app:files').setValue(true)
		const emitted = wrapper.emitted('update:chosen')
		expect(emitted[emitted.length - 1][0]).toHaveLength(1)
	})

	it('shows the count of new items where an app has one', () => {
		const counts = mountChooser().findAll('[data-testid="tile-app-count"]')
		expect(counts).toHaveLength(1)
		expect(counts[0].text()).toBe('14 new')
	})

	it('adds two apps at once: ticking Files and Agenda emits two tiles', async () => {
		const wrapper = mountChooser()
		await tick(wrapper, 'app:files').setValue(true)
		await tick(wrapper, 'app:calendar').setValue(true)
		const last = wrapper.emitted('update:chosen').at(-1)[0]
		expect(last.map((choice) => [choice.kind, choice.tile.appId])).toEqual([['app', 'files'], ['app', 'calendar']])

		await tick(wrapper, 'app:files').setValue(false)
		expect(wrapper.emitted('update:chosen').at(-1)[0]).toHaveLength(1)
	})

	it('adds a found service and a catalogue app as link tiles', async () => {
		const wrapper = mountChooser()
		await tick(wrapper, 'url:http://localhost:3000').setValue(true)
		await tick(wrapper, 'url:https://wiki.test').setValue(true)
		const last = wrapper.emitted('update:chosen').at(-1)[0]
		expect(last.map((choice) => [choice.kind, choice.tile.linkValue])).toEqual([['tile', 'http://localhost:3000'], ['tile', 'https://wiki.test']])
	})

	it('marks an app with a tile on the dashboard and does not offer it again', () => {
		const wrapper = mountChooser({ placements: [{ widgetId: 'tile', content: { mode: 'app', appId: 'files' } }] })
		expect(tick(wrapper, 'app:files').element.disabled).toBe(true)
		expect(wrapper.findAll('[data-testid="tile-app-on-dashboard"]')).toHaveLength(1)
	})

	it('filters by the search', async () => {
		const wrapper = mountChooser()
		await wrapper.find('input.field').setValue('team')
		expect(wrapper.findAll('li').map((li) => li.text())).toHaveLength(1)
		await wrapper.find('input.field').setValue('nothing like it')
		expect(wrapper.text()).toContain('No apps found')
	})

	it('asks IT for a missing app, starting from the search', async () => {
		const requestAppFn = vi.fn().mockResolvedValue({ data: {} })
		const wrapper = mountChooser({ requestAppFn })
		await wrapper.find('input.field').setValue('Topdesk')
		await wrapper.find('[data-testid="tile-app-request"]').trigger('click')
		const fields = wrapper.findAll('input.field')
		expect(fields[1].element.value).toBe('Topdesk')
		await wrapper.find('form').trigger('submit')
		await flushPromises()
		expect(requestAppFn).toHaveBeenCalled()
	})

	it('hides Request app without a request function', () => {
		expect(mountChooser().find('[data-testid="tile-app-request"]').exists()).toBe(false)
	})

	it('hands a chosen bookmarks file to the bookmark import', async () => {
		const wrapper = mountChooser()
		const file = new File(['<dl></dl>'], 'bookmarks.html', { type: 'text/html' })
		wrapper.vm.onFile(file)
		wrapper.vm.onFile(undefined)
		expect(wrapper.emitted('importBookmarks')).toEqual([[file]])
	})
})
