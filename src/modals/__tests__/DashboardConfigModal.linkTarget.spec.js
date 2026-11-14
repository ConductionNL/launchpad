/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard default for how tiles open (launcher-tile-open-behaviour D2).
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
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

describe('DashboardConfigModal tile link default', () => {
	beforeEach(() => {
		globalThis.t = (_app, key) => key
		vi.clearAllMocks()
		api.listShares.mockResolvedValue({ data: [] })
		api.searchSharees.mockResolvedValue({ data: { users: [], groups: [] } })
		api.replaceShares.mockResolvedValue({ data: {} })
		store.fetchShares.mockResolvedValue([])
	})

	it('offers the link-type rule and the three targets, seeded from the dashboard', async () => {
		const wrapper = await mountModal({
			dashboard: { id: 3, uuid: 'uuid-3', name: 'M', description: '', slug: 'm', parentUuid: '', isOwner: true, permissionLevel: 'full', defaultLinkTarget: 'side-panel' },
		})

		expect(wrapper.vm.linkTargetChoices.map((c) => c.id)).toEqual(['', 'same-tab', 'new-tab', 'side-panel'])
		expect(wrapper.vm.form.defaultLinkTarget.id).toBe('side-panel')
		expect((await mountModal()).vm.form.defaultLinkTarget.id).toBe('')
	})

	it('sends the default only when it changed, an empty one clearing it', async () => {
		const unchanged = await mountModal()
		await unchanged.vm.onSave()
		expect(unchanged.emitted('save')[0][0]).not.toHaveProperty('defaultLinkTarget')

		const changed = await mountModal()
		changed.vm.form.defaultLinkTarget = changed.vm.linkTargetChoices.find((c) => c.id === 'new-tab')
		await changed.vm.onSave()
		expect(changed.emitted('save')[0][0].defaultLinkTarget).toBe('new-tab')

		const cleared = await mountModal({
			dashboard: { id: 3, uuid: 'uuid-3', name: 'M', description: '', slug: 'm', parentUuid: '', isOwner: true, permissionLevel: 'full', defaultLinkTarget: 'new-tab' },
		})
		cleared.vm.form.defaultLinkTarget = cleared.vm.linkTargetChoices[0]
		await cleared.vm.onSave()
		expect(cleared.emitted('save')[0][0].defaultLinkTarget).toBe('')
	})
})
