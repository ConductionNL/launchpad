/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for the createFile modal in `LinkButtonHost.vue` (issue #712,
 * REQ-LBN-003 and REQ-LBN-004): the name is prefilled with
 * `document_<timestamp>`, the first Create never overwrites, a 409
 * `file_exists` answer shows a warning and turns Create into Replace, and
 * only that explicit second click sends `overwrite: true`.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnLinkButtonWidget: { name: 'CnLinkButtonWidget', render: () => null },
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, key, vars) =>
		vars
			? key.replace(/\{(\w+)\}/g, (_, name) => vars[name] ?? `{${name}}`)
			: key,
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

async function flushPromises() {
	for (let i = 0; i < 5; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

function conflict() {
	const error = new Error('Request failed with status code 409')
	error.response = {
		status: 409,
		data: { status: 'error', error: 'file_exists' },
	}
	return error
}

let openSpy

beforeEach(() => {
	postMock.mockReset()
	showErrorMock.mockReset()
	openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
})

afterEach(() => {
	openSpy.mockRestore()
})

async function openModalWith(name) {
	const wrapper = mount(LinkButtonHost, { props: { content: {} } })
	wrapper.vm.onCreateFile('docx')
	await wrapper.vm.$nextTick()
	if (name !== undefined) {
		await wrapper.find('input').setValue(name)
	}
	return wrapper
}

function createButton(wrapper) {
	return wrapper.find('.link-button-host__modal-create')
}

describe('LinkButtonHost createFile modal', () => {
	it('prefills the name with document_<timestamp> (REQ-LBN-003)', async () => {
		const wrapper = await openModalWith()

		expect(wrapper.find('input').element.value).toMatch(/^document_\d+$/)
		expect(createButton(wrapper).attributes('disabled')).toBeUndefined()
	})

	it('first create asks the server not to overwrite', async () => {
		postMock.mockResolvedValueOnce({
			data: { status: 'success', fileId: 1, url: 'https://nc/f?openfile=1' },
		})
		const wrapper = await openModalWith('Report')

		await createButton(wrapper).trigger('click')
		await flushPromises()

		expect(postMock).toHaveBeenCalledTimes(1)
		expect(postMock.mock.calls[0][1]).toEqual({
			filename: 'Report.docx',
			dir: '/',
			content: '',
			overwrite: false,
		})
		expect(openSpy).toHaveBeenCalledWith('https://nc/f?openfile=1', '_blank')
	})

	it('warns on an existing file and only replaces on an explicit second click', async () => {
		postMock.mockRejectedValueOnce(conflict()).mockResolvedValueOnce({
			data: { status: 'success', fileId: 9, url: 'https://nc/f?openfile=9' },
		})
		const wrapper = await openModalWith('Report')

		await createButton(wrapper).trigger('click')
		await flushPromises()

		const warning = wrapper.find('[role="alert"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('Report.docx')
		expect(createButton(wrapper).text()).toBe('Replace')
		expect(openSpy).not.toHaveBeenCalled()
		expect(showErrorMock).not.toHaveBeenCalled()

		await createButton(wrapper).trigger('click')
		await flushPromises()

		expect(postMock).toHaveBeenCalledTimes(2)
		expect(postMock.mock.calls[1][1].overwrite).toBe(true)
		expect(openSpy).toHaveBeenCalledWith('https://nc/f?openfile=9', '_blank')
	})

	it('changing the name after the warning goes back to a safe create', async () => {
		postMock.mockRejectedValueOnce(conflict()).mockResolvedValueOnce({
			data: { status: 'success', fileId: 3, url: 'https://nc/f?openfile=3' },
		})
		const wrapper = await openModalWith('Report')

		await createButton(wrapper).trigger('click')
		await flushPromises()
		await wrapper.find('input').setValue('Report-2')

		expect(createButton(wrapper).text()).toBe('Create')
		await createButton(wrapper).trigger('click')
		await flushPromises()

		expect(postMock.mock.calls[1][1]).toMatchObject({
			filename: 'Report-2.docx',
			overwrite: false,
		})
	})
})
