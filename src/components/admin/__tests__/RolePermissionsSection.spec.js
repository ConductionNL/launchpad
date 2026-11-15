/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `RolePermissionsSection.vue`: loading the role
 * permissions, the create and edit forms with their widget CSV fields, the
 * save payload, and the confirmed delete with its failure path.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import RolePermissionsSection from '../RolePermissionsSection.vue'
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

const ROW = {
	id: 4,
	name: 'Editors',
	groupId: 'editors',
	description: 'Can edit',
	allowedWidgets: ['news', 'files'],
	deniedWidgets: ['weather'],
	priorityWeights: { news: 2 },
}

const EditorStub = {
	name: 'RolePermissionEditorModal',
	props: ['row', 'allowedWidgetsCsv', 'deniedWidgetsCsv', 'saving'],
	emits: ['update:row', 'update:allowedWidgetsCsv', 'update:deniedWidgetsCsv', 'save', 'close'],
	template: '<div class="editor-stub" />',
}
const setResult = vi.fn()
const DialogStub = {
	name: 'CnConfirmDialog',
	props: ['message'],
	emits: ['confirm', 'close'],
	methods: { setResult },
	template: '<div class="dialog-stub">{{ message }}</div>',
}

async function mountSection() {
	const wrapper = mount(RolePermissionsSection, {
		global: {
			stubs: {
				RolePermissionEditorModal: EditorStub,
				CnConfirmDialog: DialogStub,
				NcButton: { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
				NcEmptyContent: { template: '<div class="empty-stub" />' },
				AccountGroup: true,
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
	store.current = reactive({
		permissions: [ROW],
		loading: false,
		saving: false,
		error: null,
		loadPermissions: vi.fn().mockResolvedValue(),
		savePermission: vi.fn().mockResolvedValue(),
		deletePermission: vi.fn().mockResolvedValue(),
	})
})

describe('RolePermissionsSection', () => {
	it('loads the permissions on mount and logs a failure', async () => {
		await mountSection()
		expect(store.current.loadPermissions).toHaveBeenCalledTimes(1)

		store.current.loadPermissions.mockRejectedValue(new Error('x'))
		await mountSection()
		expect(logger.error).toHaveBeenCalledWith('Failed to load role permissions', expect.any(Error))
	})

	it('shows the empty state and the store error', async () => {
		store.current.permissions = []
		store.current.error = 'Boom'
		const wrapper = await mountSection()

		expect(wrapper.find('.empty-stub').exists()).toBe(true)
		expect(wrapper.find('.launchpad-admin__error').text()).toBe('Boom')
	})

	it('opens a blank create form', async () => {
		const wrapper = await mountSection()
		wrapper.vm.openEdit(ROW)
		wrapper.vm.openCreate()
		await wrapper.vm.$nextTick()

		expect(wrapper.findComponent(EditorStub).props()).toMatchObject({
			row: { id: null, name: '', groupId: '', description: '', allowedWidgets: [], deniedWidgets: [], priorityWeights: {} },
			allowedWidgetsCsv: '',
			deniedWidgetsCsv: '',
		})
	})

	it('opens an edit form with the widget lists as CSV, defaulting missing ones', async () => {
		const wrapper = await mountSection()
		wrapper.vm.openEdit(ROW)
		await wrapper.vm.$nextTick()
		expect(wrapper.findComponent(EditorStub).props()).toMatchObject({
			allowedWidgetsCsv: 'news, files',
			deniedWidgetsCsv: 'weather',
		})

		wrapper.vm.openEdit({ id: 5, name: 'Bare', groupId: 'g' })
		expect(wrapper.vm.editorRow).toMatchObject({ allowedWidgets: [], deniedWidgets: [], priorityWeights: {} })
		expect(wrapper.vm.allowedWidgetsCsv).toBe('')
	})

	it('saves the edited form with parsed widget lists and closes', async () => {
		const wrapper = await mountSection()
		wrapper.vm.openEdit(ROW)
		await wrapper.vm.$nextTick()
		const editor = wrapper.findComponent(EditorStub)

		editor.vm.$emit('update:row', { ...ROW, description: '' })
		editor.vm.$emit('update:allowedWidgetsCsv', ' news , ,calendar ')
		editor.vm.$emit('update:deniedWidgetsCsv', '')
		editor.vm.$emit('save')
		await flushPromises()

		expect(store.current.savePermission).toHaveBeenCalledWith({
			name: 'Editors',
			groupId: 'editors',
			description: null,
			allowedWidgets: ['news', 'calendar'],
			deniedWidgets: [],
			priorityWeights: { news: 2 },
		})
		expect(wrapper.findComponent(EditorStub).exists()).toBe(false)
	})

	it('keeps the form open and logs when saving fails', async () => {
		store.current.savePermission.mockRejectedValue(new Error('x'))
		const wrapper = await mountSection()
		wrapper.vm.openCreate()
		await wrapper.vm.save()

		expect(wrapper.vm.showEditor).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to save role permission', expect.any(Error))
	})

	it('closes the form from the editor', async () => {
		const wrapper = await mountSection()
		wrapper.vm.openCreate()
		await wrapper.vm.$nextTick()
		wrapper.findComponent(EditorStub).vm.$emit('close')
		await wrapper.vm.$nextTick()
		expect(wrapper.findComponent(EditorStub).exists()).toBe(false)
	})

	it('confirms and deletes a row', async () => {
		const wrapper = await mountSection()
		wrapper.vm.confirmDelete(ROW)
		await wrapper.vm.$nextTick()

		const dialog = wrapper.findComponent(DialogStub)
		expect(dialog.text()).toBe('Delete role permission for group "editors"?')

		dialog.vm.$emit('confirm')
		await flushPromises()

		expect(store.current.deletePermission).toHaveBeenCalledWith(4)
		expect(wrapper.vm.showDeleteDialog).toBe(false)
		expect(wrapper.vm.deleteTarget).toBeNull()
	})

	it('hands the store error to the dialog when deleting fails', async () => {
		store.current.deletePermission.mockImplementation(async () => {
			store.current.error = 'In use'
			throw new Error('x')
		})
		const wrapper = await mountSection()
		wrapper.vm.confirmDelete(ROW)
		await wrapper.vm.$nextTick()

		await wrapper.vm.doDelete()

		expect(setResult).toHaveBeenCalledWith({ error: 'In use' })
		expect(wrapper.vm.showDeleteDialog).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to delete role permission', expect.any(Error))
	})

	it('does nothing when deleting without a target', async () => {
		const wrapper = await mountSection()
		expect(wrapper.vm.deleteMessage).toBe('Delete role permission for group ""?')
		await wrapper.vm.doDelete()
		expect(store.current.deletePermission).not.toHaveBeenCalled()
	})
})
