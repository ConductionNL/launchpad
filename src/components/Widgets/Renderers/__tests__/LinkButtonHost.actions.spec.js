/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for the action paths of `LinkButtonHost.vue` not covered by
 * the overwrite flow spec: `internalAction` dispatches through the
 * useInternalActions registry, the createFile modal closes on Cancel,
 * backdrop click and Escape (but not while a create is in flight), Enter in
 * the name field submits, an empty name never posts, and a non-success
 * answer or a non-409 error reports "Failed to create document".
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	__resetInternalActionsForTest,
	useInternalActions,
} from '../../../../composables/useInternalActions.js'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnLinkButtonWidget: { name: 'CnLinkButtonWidget', render: () => null },
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, key) => key,
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `http://localhost${path}`,
}))

const postMock = vi.fn()
vi.mock('@nextcloud/axios', () => ({
	default: { post: (...args) => postMock(...args) },
}))

const showErrorMock = vi.fn()
vi.mock('@nextcloud/dialogs', () => ({
	showError: (...args) => showErrorMock(...args),
}))

const { default: LinkButtonHost } = await import('../LinkButtonHost.vue')

/**
 * Let the dynamic imports and the axios promise inside onCreateConfirm settle.
 */
async function flush() {
	for (let i = 0; i < 10; i++) {
		await Promise.resolve()
	}
	await new Promise((resolve) => setTimeout(resolve, 0))
}

/**
 * Mount the host with the createFile modal open for `ext`.
 *
 * @param {string} ext the extension token.
 * @return {object} the wrapper.
 */
async function mountOpen(ext = 'docx') {
	const wrapper = mount(LinkButtonHost, {
		props: { content: {}, placement: { id: 1 } },
	})
	wrapper.findComponent({ name: 'CnLinkButtonWidget' }).vm.$emit('createFile', ext)
	await wrapper.vm.$nextTick()
	return wrapper
}

let openSpy

beforeEach(() => {
	postMock.mockReset()
	showErrorMock.mockReset()
	__resetInternalActionsForTest()
	openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
})

afterEach(() => {
	openSpy.mockRestore()
})

describe('LinkButtonHost — internal actions', () => {
	it('invokes the registered internal action named by the widget', () => {
		const action = vi.fn()
		useInternalActions().register('open-settings', action)
		const wrapper = mount(LinkButtonHost, { props: { content: {}, placement: {} } })
		wrapper.findComponent({ name: 'CnLinkButtonWidget' }).vm.$emit('internalAction', 'open-settings')
		expect(action).toHaveBeenCalledTimes(1)
	})

	it('does not invoke other registered actions', () => {
		const other = vi.fn()
		useInternalActions().register('other', other)
		const wrapper = mount(LinkButtonHost, { props: { content: {}, placement: {} } })
		wrapper.findComponent({ name: 'CnLinkButtonWidget' }).vm.$emit('internalAction', 'missing')
		expect(other).not.toHaveBeenCalled()
	})
})

describe('LinkButtonHost — createFile modal opening', () => {
	it('normalises the extension token (leading dot, case, whitespace)', async () => {
		const wrapper = await mountOpen('  .DOCX ')
		expect(wrapper.vm.pendingExtension).toBe('docx')
		expect(wrapper.find('.link-button-host__modal-extension').text()).toBe('.docx')
	})

	it('builds the filename without a dot when there is no extension', async () => {
		const wrapper = await mountOpen(null)
		wrapper.vm.filenameDraft = '  notes '
		expect(wrapper.vm.pendingFilename).toBe('notes')
	})
})

describe('LinkButtonHost — closing the modal', () => {
	it('closes on Cancel', async () => {
		const wrapper = await mountOpen()
		await wrapper.find('.link-button-host__modal-cancel').trigger('click')
		expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
	})

	it('closes on a backdrop click', async () => {
		const wrapper = await mountOpen()
		await wrapper.find('.link-button-host__modal-backdrop').trigger('click')
		expect(wrapper.vm.modalOpen).toBe(false)
	})

	it('stays open on a click inside the dialog body', async () => {
		const wrapper = await mountOpen()
		await wrapper.find('.link-button-host__modal').trigger('click')
		expect(wrapper.vm.modalOpen).toBe(true)
	})

	it('closes on Escape', async () => {
		const wrapper = await mountOpen()
		await wrapper.find('.link-button-host__modal-backdrop').trigger('keydown', { key: 'Escape' })
		expect(wrapper.vm.modalOpen).toBe(false)
	})

	it('cannot be closed while a create is in flight', async () => {
		const wrapper = await mountOpen()
		wrapper.vm.isExecuting = true
		wrapper.vm.closeModal()
		expect(wrapper.vm.modalOpen).toBe(true)
		expect(wrapper.vm.createButtonLabel).toBe('Creating…')
	})
})

describe('LinkButtonHost — submitting', () => {
	it('submits on Enter in the filename field', async () => {
		postMock.mockResolvedValue({ data: { status: 'success', url: '/f/1' } })
		const wrapper = await mountOpen('md')
		await wrapper.find('.link-button-host__modal-input').setValue('readme')
		await wrapper.find('.link-button-host__modal-input').trigger('keyup', { key: 'Enter' })
		await flush()
		expect(postMock).toHaveBeenCalledWith(
			'http://localhost/apps/launchpad/api/files/create',
			{ filename: 'readme.md', dir: '/', content: '', overwrite: false },
		)
		expect(openSpy).toHaveBeenCalledWith('/f/1', '_blank')
		expect(wrapper.vm.modalOpen).toBe(false)
		expect(wrapper.vm.isExecuting).toBe(false)
	})

	it('does not post a blank filename', async () => {
		const wrapper = await mountOpen()
		await wrapper.find('.link-button-host__modal-input').setValue('   ')
		expect(wrapper.vm.canCreate).toBe(false)
		await wrapper.vm.onCreateConfirm()
		expect(postMock).not.toHaveBeenCalled()
	})

	it('does not post a second time while a create is in flight', async () => {
		const wrapper = await mountOpen()
		wrapper.vm.isExecuting = true
		await wrapper.vm.onCreateConfirm()
		expect(postMock).not.toHaveBeenCalled()
	})

	it('reports a failure when the answer is not a success with a url', async () => {
		postMock.mockResolvedValue({ data: { status: 'success' } })
		const wrapper = await mountOpen()
		await wrapper.vm.onCreateConfirm()
		await flush()
		expect(showErrorMock).toHaveBeenCalledWith('Failed to create document')
		expect(openSpy).not.toHaveBeenCalled()
		expect(wrapper.vm.modalOpen).toBe(true)
		expect(wrapper.vm.isExecuting).toBe(false)
	})

	it('reports a failure on an error that is not a file_exists conflict', async () => {
		postMock.mockRejectedValue({ response: { status: 409, data: { error: 'other' } } })
		const wrapper = await mountOpen()
		await wrapper.vm.onCreateConfirm()
		await flush()
		expect(showErrorMock).toHaveBeenCalledWith('Failed to create document')
		expect(wrapper.vm.existingFileWarning).toBe('')
		expect(wrapper.vm.isExecuting).toBe(false)
	})

	it('reports a failure on a network error without a response', async () => {
		postMock.mockRejectedValue(new Error('offline'))
		const wrapper = await mountOpen()
		await wrapper.vm.onCreateConfirm()
		await flush()
		expect(showErrorMock).toHaveBeenCalledWith('Failed to create document')
	})
})
