/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard settings pick the hierarchies the filter bus joins widgets on
 * (drill-down-cross-widget-filter, REQ-CWF-011).
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import DashboardConfigModal from '../DashboardConfigModal.vue'
import { api } from '../../services/api.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (p, params = {}) =>
		'/index.php' + p.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key }))
vi.mock('../../services/api.js', () => ({
	api: { listShares: vi.fn(), searchSharees: vi.fn(), replaceShares: vi.fn() },
}))
vi.mock('../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const store = vi.hoisted(() => ({
	fetchShares: vi.fn(),
	createShare: vi.fn(),
	revokeShare: vi.fn(),
}))
vi.mock('../../stores/publicShares.js', () => ({
	usePublicShareStore: () => store,
}))

vi.mock('@nextcloud/vue', async () => {
	const { defineComponent: define, h: render } = await import('vue')
	const named = (name, tag) => define({
		name,
		inheritAttrs: true,
		render() {
			return render(tag, { class: `${name}-stub` }, [this.$slots.default?.(), this.$slots.icon?.()])
		},
	})
	return {
		NcModal: named('NcModal', 'div'),
		NcButton: named('NcButton', 'button'),
		NcTextField: named('NcTextField', 'div'),
		NcSelect: named('NcSelect', 'div'),
		NcCheckboxRadioSwitch: named('NcCheckboxRadioSwitch', 'label'),
	}
})

const IconBrowserStub = defineComponent({
	name: 'CnIconBrowser',
	render() {
		return h('div', { class: 'icon-browser-stub' })
	},
})

/**
 * Mount the modal on an owned dashboard with one share and one public link.
 *
 * @param {object} props Prop overrides
 * @return {Promise<object>} The wrapper, after the shares have loaded
 */
async function mountModal(props = {}) {
	const wrapper = mount(DashboardConfigModal, {
		props: {
			open: true,
			dashboard: {
				id: 3,
				uuid: 'uuid-3',
				name: 'Marketing',
				description: '',
				slug: 'marketing',
				parentUuid: '',
				icon: 'ViewDashboard',
				isOwner: true,
				permissionLevel: 'full',
			},
			mode: 'edit',
			parentOptions: [{ uuid: 'uuid-1', name: 'Root' }],
			...props,
		},
		global: {
			stubs: {
				DetailsTab: true,
				LanguagesTab: true,
				CnIconBrowser: IconBrowserStub,
			},
		},
	})
	await flushPromises()
	return wrapper
}

const BASE = { id: 3, uuid: 'uuid-3', name: 'M', description: '', slug: 'm', parentUuid: '', isOwner: true, permissionLevel: 'full' }
const OWN = { id: 'own-taxonomy', levels: [{ dimension: 'regio', label: 'Regio', parentDimension: null }] }

describe('DashboardConfigModal drill-down levels', () => {
	beforeEach(() => {
		globalThis.t = (_app, key) => key
		vi.clearAllMocks()
		api.listShares.mockResolvedValue({ data: [] })
		api.searchSharees.mockResolvedValue({ data: { users: [], groups: [] } })
		api.replaceShares.mockResolvedValue({ data: {} })
		store.fetchShares.mockResolvedValue([])
	})

	it('offers the presets and keeps a stored hierarchy that is no preset, seeded from the dashboard', async () => {
		const wrapper = await mountModal({ dashboard: { ...BASE, hierarchies: [OWN] } })

		expect(wrapper.vm.hierarchyChoices.map((choice) => choice.id)).toEqual(['nl-administrative', 'own-taxonomy'])
		expect(wrapper.vm.form.hierarchies.map((choice) => choice.id)).toEqual(['own-taxonomy'])
		expect(wrapper.find('[data-testid="dashboard-hierarchies-select"]').exists()).toBe(true)
		expect((await mountModal()).vm.form.hierarchies).toEqual([])
	})

	it('sends the hierarchies only when the choice changed, an empty choice clearing them', async () => {
		const unchanged = await mountModal({ dashboard: { ...BASE, hierarchies: [OWN] } })
		await unchanged.vm.onSave()
		expect(unchanged.emitted('save')[0][0]).not.toHaveProperty('hierarchies')

		const picked = await mountModal()
		picked.vm.form.hierarchies = picked.vm.hierarchyChoices.filter((choice) => choice.id === 'nl-administrative')
		await picked.vm.onSave()
		expect(picked.emitted('save')[0][0].hierarchies).toEqual([{
			id: 'nl-administrative',
			levels: [
				{ dimension: 'provincieCode', label: 'Province', parentDimension: null },
				{ dimension: 'gemeenteCode', label: 'Municipality', parentDimension: 'provincieCode' },
				{ dimension: 'wijkCode', label: 'District', parentDimension: 'gemeenteCode' },
				{ dimension: 'buurtCode', label: 'Neighbourhood', parentDimension: 'wijkCode' },
			],
		}])

		const cleared = await mountModal({ dashboard: { ...BASE, hierarchies: [OWN] } })
		cleared.vm.form.hierarchies = []
		await cleared.vm.onSave()
		expect(cleared.emitted('save')[0][0].hierarchies).toEqual([])
	})
})
