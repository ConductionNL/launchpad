/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `TemplateResyncModal.vue` (admin-template-resync).
 * Covers: Apply gated behind a completed Dry-run for the current
 * strategy, strategy selection binding, and strategy-change resetting
 * a stale plan.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const resyncAdminTemplateMock = vi.fn()
vi.mock('../../services/api.js', () => ({
	api: {
		resyncAdminTemplate: (...args) => resyncAdminTemplateMock(...args),
	},
}))

const stubs = {
	NcModal: { template: '<div class="nc-modal"><slot /></div>' },
	NcButton: {
		emits: ['click'],
		template: '<button class="nc-button" :data-testid="$attrs[\'data-testid\']" :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	// Vue 3 renamed the `v-model` contract: the prop is `modelValue`
	// (was `value`) and the event is `update:modelValue` (was `input`).
	// The component under test uses `v-model="strategy"`, so a stub still
	// emitting `input` never writes back — `strategy` stayed on its
	// initial option and the plan-reset assertions failed.
	NcSelect: {
		props: ['modelValue', 'options'],
		emits: ['update:modelValue'],
		template: '<select class="nc-select" :data-testid="$attrs[\'data-testid\']" @change="onChange"><option v-for="o in options" :key="o.id" :value="o.id">{{ o.label }}</option></select>',
		methods: {
			onChange(event) {
				const opt = this.options.find((o) => o.id === event.target.value)
				this.$emit('update:modelValue', opt)
			},
		},
	},
	NcLoadingIcon: { template: '<div class="nc-loading" />' },
}

let TemplateResyncModal

beforeEach(async () => {
	globalThis.t = (_app, text, vars) => {
		if (!vars) return text
		return text.replace(/\{(\w+)\}/g, (_match, key) => vars[key])
	}
	resyncAdminTemplateMock.mockReset()
	TemplateResyncModal = (await import('../TemplateResyncModal.vue')).default
})

function mountModal(propsData = {}) {
	return mount(TemplateResyncModal, {
		propsData: {
			open: true,
			template: { id: 1, name: 'Marketing Dashboard' },
			...propsData,
		},
		stubs,
	})
}

describe('TemplateResyncModal', () => {
	it('disables Apply until a dry-run has been reviewed', async () => {
		const wrapper = mountModal()
		const apply = wrapper.find('[data-testid="template-resync-apply"]')
		expect(apply.attributes('disabled')).toBeDefined()
	})

	it('enables Apply once a dry-run resolves, and calls the API with dryRun:true', async () => {
		resyncAdminTemplateMock.mockResolvedValueOnce({
			data: { totalCopies: 8, affectedCount: 3, copies: [] },
		})

		const wrapper = mountModal()
		await wrapper.find('[data-testid="template-resync-dryrun"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(resyncAdminTemplateMock).toHaveBeenCalledWith(1, {
			strategy: 'overwrite',
			dryRun: true,
		})

		const apply = wrapper.find('[data-testid="template-resync-apply"]')
		expect(apply.attributes('disabled')).toBeUndefined()
	})

	it('resets the plan (re-disabling Apply) when the strategy changes after a dry-run', async () => {
		resyncAdminTemplateMock.mockResolvedValueOnce({
			data: { totalCopies: 8, affectedCount: 3, copies: [] },
		})

		const wrapper = mountModal()
		await wrapper.find('[data-testid="template-resync-dryrun"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-testid="template-resync-apply"]').attributes('disabled')).toBeUndefined()

		// Switch strategy — the previous plan no longer applies.
		await wrapper.find('[data-testid="template-resync-strategy"]').setValue('merge')

		expect(wrapper.vm.strategy.id).toBe('merge')
		expect(wrapper.find('[data-testid="template-resync-apply"]').attributes('disabled')).toBeDefined()
	})

	it('applies with dryRun:false using the selected strategy and emits "resynced"', async () => {
		resyncAdminTemplateMock
			.mockResolvedValueOnce({ data: { totalCopies: 8, affectedCount: 3, copies: [] } })
			.mockResolvedValueOnce({ data: { async: false, affectedCount: 3, totalCopies: 8 } })

		const wrapper = mountModal()
		await wrapper.find('[data-testid="template-resync-dryrun"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		await wrapper.find('[data-testid="template-resync-apply"]').trigger('click')
		await new Promise((resolve) => setTimeout(resolve, 0))
		await wrapper.vm.$nextTick()

		expect(resyncAdminTemplateMock).toHaveBeenLastCalledWith(1, {
			strategy: 'overwrite',
			dryRun: false,
		})
		expect(wrapper.emitted('resynced')).toBeTruthy()
	})

	it('emits "close" when Close is clicked', async () => {
		const wrapper = mountModal()
		await wrapper.find('[data-testid="template-resync-cancel"]').trigger('click')
		expect(wrapper.emitted('close')).toBeTruthy()
	})
})
