/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the template wiring of `DashboardConfigModal.vue`:
 * every field, picker and button in the rendered modal reaches the form
 * state, method or emit it is bound to. The sibling specs call the methods
 * directly; these drive the controls the user touches.
 *
 * The Nextcloud components are named stubs that render their slots, so the
 * test can find each one and emit the event the real component would.
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

/**
 * The text field with the given label.
 *
 * @param {object} wrapper The mounted modal
 * @param {string} label The field label
 * @return {object} The stub
 */
function textField(wrapper, label) {
	return wrapper.findAllComponents({ name: 'NcTextField' }).find((c) => c.vm.$attrs.label === label)
}

/**
 * The button with the given aria-label or text.
 *
 * @param {object} wrapper The mounted modal
 * @param {string} name The label
 * @return {object} The button wrapper
 */
function button(wrapper, name) {
	return wrapper.findAll('button').find((b) => b.attributes('aria-label') === name || b.text().trim() === name)
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
	api.listShares.mockResolvedValue({
		data: [{ shareType: 'user', shareWith: 'alice', permissionLevel: 'view_only', displayName: 'Alice' }],
	})
	api.searchSharees.mockResolvedValue({ data: { users: [{ id: 'bob', displayName: 'Bob' }], groups: [] } })
	api.replaceShares.mockResolvedValue({ data: {} })
	store.fetchShares.mockResolvedValue([{ id: 11, token: 'tok11', passwordRequired: true, expiresAt: '2026-12-31' }])
	store.createShare.mockResolvedValue({})
	store.revokeShare.mockResolvedValue({})
})

describe('DashboardConfigModal wiring: details fields', () => {
	it('writes the title, web address name and default switch into the form', async () => {
		const wrapper = await mountModal()

		textField(wrapper, 'Title').vm.$emit('update:modelValue', 'Sales')
		textField(wrapper, 'Web address name').vm.$emit('update:modelValue', 'sales')
		wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)

		expect(wrapper.vm.form).toMatchObject({ name: 'Sales', slug: 'sales', isDefault: true })
	})

	it('writes the description, the parent and the icon into the form', async () => {
		const wrapper = await mountModal()

		await wrapper.find('[data-testid="dashboard-description-input"]').setValue('For the sales team')
		const parent = wrapper.findAllComponents({ name: 'NcSelect' }).find((c) => c.attributes('data-testid') === 'dashboard-parent-select')
		parent.vm.$emit('update:modelValue', { uuid: 'uuid-1', name: 'Root' })
		wrapper.findComponent({ name: 'CnIconBrowser' }).vm.$emit('input', '/apps/launchpad/resource/7')

		expect(wrapper.vm.form.description).toBe('For the sales team')
		expect(wrapper.vm.form.parent).toEqual({ uuid: 'uuid-1', name: 'Root' })
		expect(wrapper.vm.form.icon).toBe('/apps/launchpad/resource/7')
	})

	it('ignores a write to the admin-managed permission level', async () => {
		const wrapper = await mountModal()

		wrapper.vm.selectedPermission = { value: 'view_only' }

		expect(wrapper.vm.selectedPermission.value).toBe('full')
	})
})

describe('DashboardConfigModal wiring: user and group shares', () => {
	it('buffers a sharee picked in the search select, and searches what is typed', async () => {
		const wrapper = await mountModal()
		const picker = wrapper.findAllComponents({ name: 'NcSelect' }).find((c) => c.vm.$attrs.trackBy === 'key')

		picker.vm.$emit('update:modelValue', { shareType: 'group', id: 'ops', displayName: 'Ops' })
		picker.vm.$emit('search', 'bo')
		await flushPromises()

		expect(wrapper.vm.localShares.map((s) => s.shareWith)).toEqual(['alice', 'ops'])
		expect(api.searchSharees).toHaveBeenCalledWith('bo')
	})

	it('changes a share level from its row select, and removes it from its row button', async () => {
		const wrapper = await mountModal()
		const level = wrapper.findAllComponents({ name: 'NcSelect' }).find((c) => c.classes().includes('dashboard-config__share-level'))

		level.vm.$emit('update:modelValue', { value: 'full', label: 'Full' })
		expect(wrapper.vm.localShares[0].permissionLevel).toBe('full')

		await button(wrapper, 'Remove share').trigger('click')
		expect(wrapper.vm.localShares).toEqual([])
	})
})

describe('DashboardConfigModal wiring: public links', () => {
	it('selects the link text on focus', async () => {
		const wrapper = await mountModal()
		const input = wrapper.find('.dashboard-config__public-url')
		const select = vi.spyOn(input.element, 'select')

		await input.trigger('focus')

		expect(select).toHaveBeenCalled()
		expect(input.element.value).toBe(`${window.location.origin}/index.php/apps/launchpad/s/tok11`)
	})

	it('copies a link from its copy button', async () => {
		const writeText = vi.fn().mockResolvedValue()
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		const wrapper = await mountModal()

		await button(wrapper, 'Copy link').trigger('click')
		await flushPromises()

		expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/index.php/apps/launchpad/s/tok11`)
	})

	it('revokes a link from its revoke button', async () => {
		const wrapper = await mountModal()

		await button(wrapper, 'Revoke link').trigger('click')
		await flushPromises()

		expect(store.revokeShare).toHaveBeenCalledWith('uuid-3', 11)
	})

	it('creates a link from the password and expiry the user entered', async () => {
		const wrapper = await mountModal()

		textField(wrapper, 'Password (optional)').vm.$emit('update:modelValue', 's3cret')
		await wrapper.find('.dashboard-config__public-date').setValue('2027-01-31')
		await button(wrapper, 'Create public link').trigger('click')
		await flushPromises()

		expect(store.createShare).toHaveBeenCalledWith('uuid-3', { password: 's3cret', expiresAt: '2027-01-31T23:59:59Z' })
	})
})

describe('DashboardConfigModal wiring: actions', () => {
	it('emits close from the modal and from Cancel', async () => {
		const wrapper = await mountModal()

		wrapper.findComponent({ name: 'NcModal' }).vm.$emit('close')
		await button(wrapper, 'Cancel').trigger('click')

		expect(wrapper.emitted('close')).toHaveLength(2)
	})

	it('emits delete from the delete button, which only shows when allowed', async () => {
		expect((await mountModal()).find('[data-testid="dashboard-delete-button"]').exists()).toBe(false)
		const wrapper = await mountModal({ canDelete: true })

		await wrapper.find('[data-testid="dashboard-delete-button"]').trigger('click')

		expect(wrapper.emitted('delete')).toEqual([[expect.objectContaining({ id: 3, uuid: 'uuid-3' })]])
	})

	it('emits save from the save button', async () => {
		const wrapper = await mountModal()

		await wrapper.find('[data-testid="dashboard-save-button"]').trigger('click')
		await flushPromises()

		expect(wrapper.emitted('save')).toHaveLength(1)
		expect(wrapper.emitted('save')[0][0]).toMatchObject({ id: 3, name: 'Marketing' })
	})
})
