/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ShippedTemplateUpdateDialog (admin-templates REQ-TMPL-020): opening asks
 * the server for a dry run and shows what is added, removed and changed;
 * "Update" runs the real update and emits the result; a failed dry run or
 * update shows its message and keeps Update disabled.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const post = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { post: (...a) => post(...a) } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k]),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['open', 'name'],
		emits: ['update:open'],
		template: '<div v-if="open"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['disabled', 'variant'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}))

const { default: ShippedTemplateUpdateDialog } = await import('../ShippedTemplateUpdateDialog.vue')

const URL = '/index.php/apps/launchpad/api/admin/templates/shipped/7/update'
const shipped = { id: 7, name: 'Intranet' }

/**
 * Let the axios promise and the follow-up render settle.
 *
 * @param {object} wrapper the mounted dialog.
 */
async function settle(wrapper) {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await wrapper.vm.$nextTick()
}

/**
 * Build a dry-run plan.
 *
 * @param {object} overrides fields to replace.
 * @return {object} the plan.
 */
function plan(overrides = {}) {
	return {
		name: 'Intranet',
		installedVersion: '1.0',
		version: '1.2',
		added: [],
		removed: [],
		changed: [],
		copies: 3,
		...overrides,
	}
}

beforeEach(() => {
	post.mockReset()
})

describe('ShippedTemplateUpdateDialog — dry run', () => {
	it('asks for a dry run when mounted open and shows the plan', async () => {
		post.mockResolvedValue({
			data: plan({
				added: ['news'],
				removed: ['clock'],
				changed: [{ widget: 'weather' }],
			}),
		})
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		expect(wrapper.find('[data-testid="shipped-update-loading"]').exists()).toBe(true)
		await settle(wrapper)

		expect(post).toHaveBeenCalledTimes(1)
		expect(post).toHaveBeenCalledWith(URL, { dryRun: true })
		expect(wrapper.find('[data-testid="shipped-update-loading"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-plan"]').text()).toContain(
			'This brings "Intranet" from version 1.0 to version 1.2.',
		)
		expect(wrapper.find('[data-testid="shipped-update-added"]').text()).toBe('news')
		expect(wrapper.find('[data-testid="shipped-update-removed"]').text()).toBe('clock')
		expect(wrapper.find('[data-testid="shipped-update-changed"]').text()).toBe('weather')
		expect(wrapper.find('[data-testid="shipped-update-nothing"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-copies"]').text()).toBe(
			'Members with a copy of this template: 3. Their copies follow. A widget a member added stays.',
		)
		expect(wrapper.vm.hasChanges).toBe(true)
	})

	it('says the widgets stay when nothing is added, removed or changed', async () => {
		post.mockResolvedValue({ data: plan() })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await settle(wrapper)
		expect(wrapper.vm.hasChanges).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-nothing"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="shipped-update-added"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-removed"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-changed"]').exists()).toBe(false)
	})

	it('reports no changes before a plan is loaded', () => {
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: false, shipped } })
		expect(wrapper.vm.hasChanges).toBe(false)
	})

	it('does not ask while closed, and asks once it opens', async () => {
		post.mockResolvedValue({ data: plan() })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: false, shipped } })
		await settle(wrapper)
		expect(post).not.toHaveBeenCalled()
		await wrapper.setProps({ open: true })
		await settle(wrapper)
		expect(post).toHaveBeenCalledWith(URL, { dryRun: true })
	})

	it('does not ask without a template', async () => {
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped: null } })
		await settle(wrapper)
		expect(post).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="shipped-update-confirm"]').attributes('disabled')).toBeDefined()
	})

	it('shows an error and keeps Update disabled when the dry run fails', async () => {
		post.mockRejectedValue(new Error('500'))
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await settle(wrapper)
		expect(wrapper.find('[data-testid="shipped-update-error"]').text()).toBe(
			'The changes could not be worked out. Nothing was updated.',
		)
		expect(wrapper.vm.plan).toBeNull()
		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-confirm"]').attributes('disabled')).toBeDefined()
	})

	it('clears an earlier error when the plan is loaded again', async () => {
		post.mockRejectedValueOnce(new Error('500')).mockResolvedValueOnce({ data: plan() })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await settle(wrapper)
		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })
		await settle(wrapper)
		expect(wrapper.vm.errorMessage).toBe('')
		expect(wrapper.find('[data-testid="shipped-update-plan"]').exists()).toBe(true)
	})
})

describe('ShippedTemplateUpdateDialog — update', () => {
	it('runs the real update and emits its result', async () => {
		post
			.mockResolvedValueOnce({ data: plan({ added: ['news'] }) })
			.mockResolvedValueOnce({ data: { id: 7, version: '1.2' } })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await settle(wrapper)
		const confirm = wrapper.find('[data-testid="shipped-update-confirm"]')
		expect(confirm.attributes('disabled')).toBeUndefined()
		await confirm.trigger('click')
		await settle(wrapper)
		expect(post).toHaveBeenNthCalledWith(2, URL, { dryRun: false })
		expect(wrapper.emitted('updated')).toEqual([[{ id: 7, version: '1.2' }]])
		expect(wrapper.vm.updating).toBe(false)
	})

	it('names the template and drops the plan when the update fails', async () => {
		post
			.mockResolvedValueOnce({ data: plan() })
			.mockRejectedValueOnce(new Error('409'))
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await settle(wrapper)
		await wrapper.vm.confirm()
		await settle(wrapper)
		expect(wrapper.emitted('updated')).toBeUndefined()
		expect(wrapper.vm.plan).toBeNull()
		expect(wrapper.vm.updating).toBe(false)
		expect(wrapper.find('[data-testid="shipped-update-error"]').text()).toBe(
			'The template "Intranet" could not be updated.',
		)
	})

	it('disables Update while an update is in flight', async () => {
		post.mockResolvedValueOnce({ data: plan() })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await settle(wrapper)
		wrapper.vm.updating = true
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-testid="shipped-update-confirm"]').attributes('disabled')).toBeDefined()
	})
})

describe('ShippedTemplateUpdateDialog — closing', () => {
	it('asks the parent to close on Cancel', async () => {
		post.mockResolvedValue({ data: plan() })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		await wrapper.find('[data-testid="shipped-update-cancel"]').trigger('click')
		expect(wrapper.emitted('update:open')).toEqual([[false]])
	})

	it('passes a close from the dialog chrome on to the parent', async () => {
		post.mockResolvedValue({ data: plan() })
		const wrapper = mount(ShippedTemplateUpdateDialog, { props: { open: true, shipped } })
		wrapper.findComponent({ name: 'NcDialog' }).vm.$emit('update:open', false)
		expect(wrapper.emitted('update:open')).toEqual([[false]])
	})
})
