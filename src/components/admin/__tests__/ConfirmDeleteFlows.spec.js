/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The confirm-then-act flows of four admin screens: deleting a group
 * dashboard, a role permission and a role layout default, and removing a
 * demo showcase. Each case opens the confirmation, checks its texts, then
 * confirms or cancels and checks what reached the store or the API.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ManageGroupDashboardsModal from '../../../dialogs/ManageGroupDashboardsModal.vue'
import AdminDemoData from '../AdminDemoData.vue'
import RoleLayoutDefaultsSection from '../RoleLayoutDefaultsSection.vue'
import RolePermissionsSection from '../RolePermissionsSection.vue'
import { api } from '../../../services/api.js'
import { useGroupDashboardsStore } from '../../../stores/groupDashboards.js'
import { useRoleFeaturePermissionStore } from '../../../stores/roleFeaturePermissions.js'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(() => Promise.resolve({ data: [] })),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn(), showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text) => text,
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	n: (_app, one, many, count) => (count === 1 ? one : many),
}))
vi.mock('../../../services/api.js', () => ({
	api: {
		listDemoShowcases: vi.fn(),
		installDemoShowcase: vi.fn(),
		uninstallDemoShowcase: vi.fn(),
	},
}))

vi.mock('@conduction/nextcloud-vue', () => {
	const ncButton = {
		name: 'NcButton',
		props: ['variant', 'disabled'],
		emits: ['click'],
		template:
			'<button :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
	}
	return {
		NcButton: ncButton,
		NcEmptyContent: { name: 'NcEmptyContent', template: '<div><slot /></div>' },
		NcModal: { name: 'NcModal', template: '<div><slot /></div>' },
		NcTextField: { name: 'NcTextField', template: '<input>' },
		NcCheckboxRadioSwitch: {
			name: 'NcCheckboxRadioSwitch',
			template: '<div />',
		},
		NcSelect: { name: 'NcSelect', template: '<div />' },
		NcSelectTags: { name: 'NcSelectTags', template: '<div />' },
		NcPasswordField: { name: 'NcPasswordField', template: '<input>' },
		NcLoadingIcon: { name: 'NcLoadingIcon', template: '<span />' },
		NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
		CnDashboardIcon: { name: 'CnDashboardIcon', template: '<span />' },
		CnIconBrowser: { name: 'CnIconBrowser', template: '<div />' },
		CnSettingsSection: {
			name: 'CnSettingsSection',
			template: '<div><slot /></div>',
		},
		CnAdminSettingsShell: {
			name: 'CnAdminSettingsShell',
			template: '<div><slot /></div>',
		},
		NcDialog: {
			name: 'NcDialog',
			props: ['name', 'open'],
			emits: ['update:open'],
			template:
				'<div v-if="open" class="confirm"><h2>{{ name }}</h2><slot /><slot name="actions" /></div>',
		},
		CnConfirmDialog: {
			name: 'CnConfirmDialog',
			components: { NcButton: ncButton },
			props: ['dialogTitle', 'message', 'confirmLabel', 'variant'],
			emits: ['confirm', 'close'],
			data: () => ({ result: null }),
			methods: {
				setResult(result) {
					this.result = result
				},
			},
			template:
				'<div class="confirm"><h2>{{ dialogTitle }}</h2><p>{{ message }}</p>'
				+ '<p v-if="result" class="result">{{ result.error }}</p>'
				+ '<NcButton @click="$emit(\'close\')">Cancel</NcButton>'
				+ '<NcButton :variant="variant" @click="$emit(\'confirm\')">{{ confirmLabel }}</NcButton></div>',
		},
	}
})
vi.mock('@nextcloud/vue', () => {
	const plain = (name) => ({ name, template: '<div><slot /></div>' })
	return {
		NcButton: {
			name: 'NcButton',
			props: ['variant', 'disabled'],
			emits: ['click'],
			template:
				'<button :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
		},
		NcDialog: {
			name: 'NcDialog',
			props: ['name', 'open'],
			template: '<div><slot /><slot name="actions" /></div>',
		},
		NcEmptyContent: plain('NcEmptyContent'),
		NcLoadingIcon: plain('NcLoadingIcon'),
		NcNoteCard: plain('NcNoteCard'),
	}
})

const tMixin = {
	methods: {
		t: (_app, text, vars = {}) =>
			text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
		n: (_app, one, many, count) => (count === 1 ? one : many),
	},
}

/**
 * Mount a component with the global t/n mixin the app installs.
 *
 * @param {object} component The component.
 * @param {object} props Its props.
 * @return {object} The wrapper.
 */
function mountWith(component, props = {}) {
	return mount(component, {
		props,
		global: { mixins: [tMixin], stubs: { teleport: true } },
	})
}

/**
 * The open confirmation, its texts and its two buttons.
 *
 * @param {object} wrapper The wrapper.
 * @return {object} The confirmation's parts.
 */
function confirmation(wrapper) {
	const box = wrapper.find('.confirm')
	if (!box.exists()) {
		return null
	}
	const buttons = box.findAll('button')
	return {
		text: box.text(),
		cancel: buttons.find((b) => b.text() === 'Cancel'),
		confirm: buttons[buttons.length - 1],
	}
}

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('group dashboard delete', () => {
	/**
	 * Mount the modal with one group dashboard and open its delete question.
	 *
	 * @return {Promise<object>} The wrapper and the store.
	 */
	async function openDelete() {
		const store = useGroupDashboardsStore()
		store.fetchGroupDashboards = vi.fn(() => Promise.resolve())
		store.delete = vi.fn(() => Promise.resolve())
		const wrapper = mountWith(ManageGroupDashboardsModal, {
			group: { id: 'staff', displayName: 'Staff' },
		})
		await flushPromises()
		wrapper.vm.onDelete({ uuid: 'd-1', name: 'Rooster' })
		await flushPromises()
		return { wrapper, store }
	}

	it('asks first, then deletes the dashboard of that group', async () => {
		const { wrapper, store } = await openDelete()
		const c = confirmation(wrapper)
		expect(c.text).toContain('Delete dashboard')
		expect(c.text).toContain('Delete this dashboard? This cannot be undone.')
		expect(c.confirm.text()).toBe('Delete')
		expect(c.confirm.attributes('data-variant')).toBe('error')
		expect(store.delete).not.toHaveBeenCalled()
		await c.confirm.trigger('click')
		await flushPromises()
		expect(store.delete).toHaveBeenCalledWith('staff', 'd-1')
		expect(confirmation(wrapper)).toBeNull()
	})

	it('cancel closes without deleting', async () => {
		const { wrapper, store } = await openDelete()
		await confirmation(wrapper).cancel.trigger('click')
		await flushPromises()
		expect(confirmation(wrapper)).toBeNull()
		expect(store.delete).not.toHaveBeenCalled()
	})
})

describe('role permission delete', () => {
	/**
	 * Mount the section and open the delete question for one row.
	 *
	 * @param {Function} deleteImpl The store's delete.
	 * @return {Promise<object>} The wrapper and the store.
	 */
	async function openDelete(deleteImpl) {
		const store = useRoleFeaturePermissionStore()
		store.loadPermissions = vi.fn(() => Promise.resolve())
		store.deletePermission = vi.fn(deleteImpl)
		const wrapper = mountWith(RolePermissionsSection)
		await flushPromises()
		wrapper.vm.confirmDelete({ id: 7, groupId: 'finance' })
		await flushPromises()
		return { wrapper, store }
	}

	it('names the group, deletes on confirm and closes', async () => {
		const { wrapper, store } = await openDelete(() => Promise.resolve())
		const c = confirmation(wrapper)
		expect(c.text).toContain('Delete role permission')
		expect(c.text).toContain('Delete role permission for group "finance"?')
		expect(c.confirm.attributes('data-variant')).toBe('error')
		await c.confirm.trigger('click')
		await flushPromises()
		expect(store.deletePermission).toHaveBeenCalledWith(7)
		expect(confirmation(wrapper)).toBeNull()
	})

	it('a failed delete keeps the question open and leaves the row', async () => {
		const { wrapper, store } = await openDelete(() => {
			store.error = 'Server said no'
			return Promise.reject(new Error('Server said no'))
		})
		await confirmation(wrapper).confirm.trigger('click')
		await flushPromises()
		expect(store.deletePermission).toHaveBeenCalledWith(7)
		expect(confirmation(wrapper)).not.toBeNull()
		expect(wrapper.vm.deleteTarget).toEqual({ id: 7, groupId: 'finance' })
	})

	it('a failed delete shows the store error in the confirmation', async () => {
		const { wrapper, store } = await openDelete(() => {
			store.error = 'Server said no'
			return Promise.reject(new Error('Server said no'))
		})
		await confirmation(wrapper).confirm.trigger('click')
		await flushPromises()
		expect(wrapper.find('.result').text()).toBe('Server said no')
	})

	it('cancel closes without deleting', async () => {
		const { wrapper, store } = await openDelete(() => Promise.resolve())
		await confirmation(wrapper).cancel.trigger('click')
		await flushPromises()
		expect(confirmation(wrapper)).toBeNull()
		expect(store.deletePermission).not.toHaveBeenCalled()
	})
})

describe('role layout default delete', () => {
	/**
	 * Mount the section and open the delete question for one row.
	 *
	 * @param {Function} deleteImpl The store's delete.
	 * @return {Promise<object>} The wrapper and the store.
	 */
	async function openDelete(deleteImpl) {
		const store = useRoleFeaturePermissionStore()
		store.loadLayoutDefaults = vi.fn(() => Promise.resolve())
		store.deleteLayoutDefault = vi.fn(deleteImpl)
		const wrapper = mountWith(RoleLayoutDefaultsSection)
		await flushPromises()
		wrapper.vm.openDeleteDialog({ id: 3, groupId: 'hr', widgetId: 'news' })
		await flushPromises()
		return { wrapper, store }
	}

	it('names the group and widget, deletes on confirm and closes', async () => {
		const { wrapper, store } = await openDelete(() => Promise.resolve())
		const c = confirmation(wrapper)
		expect(c.text).toContain('Delete layout default')
		expect(c.text).toContain('Delete layout default for "hr" / "news"?')
		expect(c.confirm.attributes('data-variant')).toBe('error')
		await c.confirm.trigger('click')
		await flushPromises()
		expect(store.deleteLayoutDefault).toHaveBeenCalledWith(3)
		expect(confirmation(wrapper)).toBeNull()
	})

	it('a failed delete keeps the question open', async () => {
		const { wrapper, store } = await openDelete(() =>
			Promise.reject(new Error('no')),
		)
		await confirmation(wrapper).confirm.trigger('click')
		await flushPromises()
		expect(store.deleteLayoutDefault).toHaveBeenCalledWith(3)
		expect(confirmation(wrapper)).not.toBeNull()
	})

	it('a failed delete shows the store error in the confirmation', async () => {
		const { wrapper, store } = await openDelete(() => {
			store.error = 'Server said no'
			return Promise.reject(new Error('Server said no'))
		})
		await confirmation(wrapper).confirm.trigger('click')
		await flushPromises()
		expect(wrapper.find('.result').text()).toBe('Server said no')
	})

	it('cancel closes without deleting', async () => {
		const { wrapper, store } = await openDelete(() => Promise.resolve())
		await confirmation(wrapper).cancel.trigger('click')
		await flushPromises()
		expect(confirmation(wrapper)).toBeNull()
		expect(store.deleteLayoutDefault).not.toHaveBeenCalled()
	})
})

describe('demo showcase removal', () => {
	/**
	 * Mount the demo data screen with one installed showcase and ask to remove it.
	 *
	 * @return {Promise<object>} The wrapper.
	 */
	async function openRemove() {
		api.listDemoShowcases.mockResolvedValue({ data: [] })
		api.uninstallDemoShowcase.mockResolvedValue({ data: {} })
		const wrapper = mountWith(AdminDemoData)
		await flushPromises()
		wrapper.vm.uninstallTarget = { id: 'school', name: 'School' }
		await flushPromises()
		return wrapper
	}

	it('names the showcase and removes it on confirm', async () => {
		const wrapper = await openRemove()
		const c = confirmation(wrapper)
		expect(c.text).toContain('Remove showcase dashboard')
		expect(c.text).toContain(
			'Remove the School showcase dashboard for all users? You can reinstall it later.',
		)
		expect(c.confirm.text()).toBe('Remove')
		expect(c.confirm.attributes('data-variant')).toBe('error')
		await c.confirm.trigger('click')
		await flushPromises()
		expect(api.uninstallDemoShowcase).toHaveBeenCalledWith('school')
		expect(confirmation(wrapper)).toBeNull()
	})

	it('cancel closes without removing', async () => {
		const wrapper = await openRemove()
		await confirmation(wrapper).cancel.trigger('click')
		await flushPromises()
		expect(confirmation(wrapper)).toBeNull()
		expect(api.uninstallDemoShowcase).not.toHaveBeenCalled()
	})
})
