/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `RoleLayoutDefaultsSection.vue`: loading the
 * defaults, the rows with their grid and compulsory chips, the create and
 * edit dialog, saving, and the confirmed delete with its failure path.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import RoleLayoutDefaultsSection from '../RoleLayoutDefaultsSection.vue'
import { logger } from '../../../utils/logger.js'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const store = vi.hoisted(() => ({ current: null }))
vi.mock('../../../stores/roleFeaturePermissions.js', () => ({
	useRoleFeaturePermissionStore: () => store.current,
}))

const ROW = { id: 2, name: 'News first', groupId: 'staff', widgetId: 'news', gridX: 0, gridY: 1, gridWidth: 6, gridHeight: 3, isCompulsory: true }

const DialogEditorStub = {
	name: 'RoleLayoutDefaultEditorDialog',
	props: ['open', 'row', 'saving'],
	emits: ['update:open', 'update:row', 'save'],
	template: '<div class="editor-stub" />',
}
const setResult = vi.fn()
const ConfirmStub = {
	name: 'CnConfirmDialog',
	props: ['message'],
	emits: ['confirm', 'close'],
	methods: { setResult },
	template: '<div class="confirm-stub">{{ message }}</div>',
}
const ButtonStub = {
	props: ['ariaLabel'],
	emits: ['click'],
	template: '<button :aria-label="ariaLabel" @click="$emit(\'click\')"><slot /></button>',
}

async function mountSection() {
	const wrapper = mount(RoleLayoutDefaultsSection, {
		global: {
			mocks: { t: globalThis.t },
			stubs: {
				RoleLayoutDefaultEditorDialog: DialogEditorStub,
				CnConfirmDialog: ConfirmStub,
				NcButton: ButtonStub,
				NcEmptyContent: { template: '<div class="empty-stub" />' },
				ViewDashboard: true,
				Plus: true,
				Pencil: true,
				Delete: true,
			},
		},
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	globalThis.t = (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`)
	store.current = reactive({
		layoutDefaults: [ROW],
		loading: false,
		saving: false,
		error: null,
		loadLayoutDefaults: vi.fn().mockResolvedValue(),
		saveLayoutDefault: vi.fn().mockResolvedValue(),
		deleteLayoutDefault: vi.fn().mockResolvedValue(),
	})
})

describe('RoleLayoutDefaultsSection', () => {
	it('loads the defaults and lists them with their chips', async () => {
		const wrapper = await mountSection()

		expect(store.current.loadLayoutDefaults).toHaveBeenCalledTimes(1)
		const row = wrapper.find('.launchpad-admin__role-row')
		expect(row.find('.launchpad-admin__role-group').text()).toBe('staff / news')
		expect(row.find('.launchpad-admin__chip--compulsory').exists()).toBe(true)
		expect(row.findAll('.launchpad-admin__chip')[0].text()).toBe('0,1 6×3')
	})

	it('shows the empty state, the store error, and logs a failed load', async () => {
		store.current.layoutDefaults = []
		store.current.error = 'Boom'
		store.current.loadLayoutDefaults.mockRejectedValue(new Error('x'))
		const wrapper = await mountSection()

		expect(wrapper.find('.empty-stub').exists()).toBe(true)
		expect(wrapper.find('.launchpad-admin__error').text()).toBe('Boom')
		expect(logger.error).toHaveBeenCalledWith('Failed to load layout defaults', expect.any(Error))
	})

	it('opens a blank row for create and the row for edit', async () => {
		const wrapper = await mountSection()

		await wrapper.find('[data-testid="admin-add-layout-default"]').trigger('click')
		expect(wrapper.findComponent(DialogEditorStub).props('row')).toEqual({
			id: null, name: '', groupId: '', widgetId: '', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4, sortOrder: 0, isCompulsory: false, description: '',
		})

		await wrapper.find('.launchpad-admin__role-row button[aria-label="Edit"]').trigger('click')
		expect(wrapper.findComponent(DialogEditorStub).props('row')).toEqual(ROW)
	})

	it('saves the edited row and closes the dialog', async () => {
		const wrapper = await mountSection()
		wrapper.vm.openEdit(ROW)
		await wrapper.vm.$nextTick()
		const dialog = wrapper.findComponent(DialogEditorStub)

		dialog.vm.$emit('update:row', { ...ROW, gridWidth: 12 })
		dialog.vm.$emit('save')
		await flushPromises()

		expect(store.current.saveLayoutDefault).toHaveBeenCalledWith({ ...ROW, gridWidth: 12 })
		expect(wrapper.findComponent(DialogEditorStub).exists()).toBe(false)
	})

	it('keeps the dialog open when saving fails, and closes on update:open', async () => {
		store.current.saveLayoutDefault.mockRejectedValue(new Error('x'))
		const wrapper = await mountSection()
		wrapper.vm.openCreate()
		await wrapper.vm.save()
		expect(wrapper.vm.showEditor).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to save layout default', expect.any(Error))

		await wrapper.vm.$nextTick()
		wrapper.findComponent(DialogEditorStub).vm.$emit('update:open', false)
		await wrapper.vm.$nextTick()
		expect(wrapper.findComponent(DialogEditorStub).exists()).toBe(false)
	})

	it('confirms and deletes a default', async () => {
		const wrapper = await mountSection()
		await wrapper.find('.launchpad-admin__role-row button[aria-label="Delete"]').trigger('click')

		const confirm = wrapper.findComponent(ConfirmStub)
		expect(confirm.text()).toBe('Delete layout default for "staff" / "news"?')
		confirm.vm.$emit('confirm')
		await flushPromises()

		expect(store.current.deleteLayoutDefault).toHaveBeenCalledWith(2)
		expect(wrapper.findComponent(ConfirmStub).exists()).toBe(false)
		expect(wrapper.vm.deleteTarget).toBeNull()
	})

	it('hands the store error to the dialog when deleting fails, and ignores a missing target', async () => {
		store.current.deleteLayoutDefault.mockImplementation(async () => {
			store.current.error = 'Locked'
			throw new Error('x')
		})
		const wrapper = await mountSection()
		await wrapper.vm.confirmDelete()
		expect(store.current.deleteLayoutDefault).not.toHaveBeenCalled()

		wrapper.vm.openDeleteDialog(ROW)
		await wrapper.vm.$nextTick()
		await wrapper.vm.confirmDelete()

		expect(setResult).toHaveBeenCalledWith({ error: 'Locked' })
		expect(wrapper.vm.showDeleteDialog).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to delete layout default', expect.any(Error))
	})
})
