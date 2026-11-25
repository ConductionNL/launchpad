/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `TemplateResyncModal.vue`, the rollout window
 * (admin-templates REQ-RESYNC-008, board LpBeheerSjablonen).
 * Covers: the dry run on open for Keep personal changes, the change summary
 * and changed-copies count, the three choices, a fresh dry run per choice,
 * the push button naming the dashboards, and a stale answer being dropped.
 *
 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

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
	NcModal: {
		props: ['name'],
		template: '<div class="nc-modal" :data-name="name"><slot /></div>',
	},
	NcButton: {
		emits: ['click'],
		template:
			'<button class="nc-button" :data-testid="$attrs[\'data-testid\']" :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcLoadingIcon: { template: '<div class="nc-loading" />' },
}

const PLAN = {
	templateId: 1,
	totalCopies: 212,
	affectedCount: 200,
	copies: [],
	gained: ['Leesbevestiging', 'Verjaardagen'],
	lost: ['Oud nieuws'],
	changedByOwnerCount: 38,
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

async function flush (wrapper) {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await wrapper.vm.$nextTick()
}

function mountModal(propsData = {}) {
	return mount(TemplateResyncModal, {
		propsData: {
			open: true,
			template: { id: 1, name: 'Intranet medewerkers' },
			...propsData,
		},
		stubs,
	})
}

const apply = (w) => w.find('[data-testid="template-resync-apply"]')

describe('TemplateResyncModal (rollout window)', () => {
	it('runs a dry run for Keep personal changes on open and keeps the push disabled until it answers', async () => {
		resyncAdminTemplateMock.mockReturnValueOnce(new Promise(() => {}))
		const wrapper = mountModal()

		expect(resyncAdminTemplateMock).toHaveBeenCalledWith(1, {
			strategy: 'merge',
			dryRun: true,
		})
		expect(
			wrapper.find('[data-testid="template-resync-choice-merge"]').element.checked,
		).toBe(true)
		expect(
			wrapper.find('[data-testid="template-resync-choice-add_only"]').element.checked,
		).toBe(false)
		expect(apply(wrapper).attributes('disabled')).toBeDefined()
	})

	it('says what the template gained and lost, how many people changed their copy, and names the dashboards', async () => {
		resyncAdminTemplateMock.mockResolvedValueOnce({ data: PLAN })
		const wrapper = mountModal()
		await flush(wrapper)

		expect(wrapper.find('[data-testid="template-resync-title"]').text()).toBe(
			'Push changes to 212 dashboards',
		)
		expect(wrapper.find('[data-testid="template-resync-template"]').text()).toBe(
			'Template "Intranet medewerkers" has 212 copies.',
		)
		expect(wrapper.find('[data-testid="template-resync-gained"]').text()).toBe(
			'Added since the last sync: Leesbevestiging, Verjaardagen',
		)
		expect(wrapper.find('[data-testid="template-resync-lost"]').text()).toBe(
			'Removed since the last sync: Oud nieuws',
		)
		expect(
			wrapper.find('[data-testid="template-resync-changed-count"]').text(),
		).toBe('38 people changed their dashboard.')
		expect(apply(wrapper).text()).toBe('Push to 212 dashboards')
		expect(apply(wrapper).attributes('disabled')).toBeUndefined()
	})

	it('offers the three choices, each with its consequence', async () => {
		resyncAdminTemplateMock.mockResolvedValueOnce({ data: PLAN })
		const wrapper = mountModal()
		await flush(wrapper)

		const text = wrapper.find('[data-testid="template-resync-strategy"]').text()
		expect(text).toContain('How to update?')
		expect(text).toContain('Only add new widgets')
		expect(text).toContain('Nothing disappears; personal layout stays.')
		expect(text).toContain('Keep personal changes')
		expect(text).toContain('The template wins, except what someone moved or added themselves.')
		expect(text).toContain('Replace everything with the template')
		expect(text).toContain('Personal changes are lost.')
	})

	it('says nothing changed when the template gained and lost nothing', async () => {
		resyncAdminTemplateMock.mockResolvedValueOnce({
			data: { ...PLAN, gained: [], lost: [], changedByOwnerCount: 0 },
		})
		const wrapper = mountModal()
		await flush(wrapper)

		expect(wrapper.find('[data-testid="template-resync-no-changes"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="template-resync-changed-count"]').exists()).toBe(false)
	})

	it('runs a fresh dry run for another choice and pushes with that strategy', async () => {
		resyncAdminTemplateMock
			.mockResolvedValueOnce({ data: PLAN })
			.mockResolvedValueOnce({ data: { ...PLAN, affectedCount: 150 } })
			.mockResolvedValueOnce({ data: { async: false, affectedCount: 150, totalCopies: 212 } })
		const wrapper = mountModal()
		await flush(wrapper)

		await wrapper.find('[data-testid="template-resync-choice-add_only"]').trigger('change')
		expect(resyncAdminTemplateMock).toHaveBeenLastCalledWith(1, {
			strategy: 'add_only',
			dryRun: true,
		})
		await flush(wrapper)
		expect(wrapper.vm.plan.strategy).toBe('add_only')
		expect(apply(wrapper).attributes('disabled')).toBeUndefined()

		await apply(wrapper).trigger('click')
		await flush(wrapper)
		expect(resyncAdminTemplateMock).toHaveBeenLastCalledWith(1, {
			strategy: 'add_only',
			dryRun: false,
		})
		expect(wrapper.emitted('resynced')).toBeTruthy()
		expect(wrapper.find('[data-testid="template-resync-success"]').text()).toBe(
			'Re-sync applied — 150 copies updated.',
		)
	})

	it('drops a slower answer for an earlier choice', async () => {
		let resolveFirst
		resyncAdminTemplateMock
			.mockReturnValueOnce(new Promise((resolve) => { resolveFirst = resolve }))
			.mockResolvedValueOnce({ data: { ...PLAN, totalCopies: 5 } })
		const wrapper = mountModal()

		await wrapper.find('[data-testid="template-resync-choice-overwrite"]').trigger('change')
		await flush(wrapper)
		resolveFirst({ data: PLAN })
		await flush(wrapper)

		expect(wrapper.vm.plan.strategy).toBe('overwrite')
		expect(apply(wrapper).text()).toBe('Push to 5 dashboards')
	})

	it('shows an error and keeps the push disabled when the dry run fails', async () => {
		resyncAdminTemplateMock.mockRejectedValueOnce(new Error('boom'))
		const wrapper = mountModal()
		await flush(wrapper)

		expect(wrapper.find('[data-testid="template-resync-error"]').text()).toBe(
			'Could not compute the re-sync plan.',
		)
		expect(apply(wrapper).attributes('disabled')).toBeDefined()
	})

	it('emits "close" when Close is clicked', async () => {
		resyncAdminTemplateMock.mockResolvedValueOnce({ data: PLAN })
		const wrapper = mountModal()
		await wrapper.find('[data-testid="template-resync-cancel"]').trigger('click')
		expect(wrapper.emitted('close')).toBeTruthy()
	})
})
