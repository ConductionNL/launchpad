/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `FilesWidget.vue` covering the paths the base
 * spec leaves out: the unknown-error state + retry, cursor pagination
 * (REQ-FLS-003), breadcrumb segment navigation and path joining
 * (REQ-FLS-005), the delete modal's cancel / Escape / failure paths
 * (REQ-FLS-008), the upload flow (REQ-FLS-007) and size formatting.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import FilesWidget from '../FilesWidget.vue'

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		delete: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		Object.entries(params).reduce(
			(acc, [key, value]) => acc.replace(`{${key}}`, value),
			path,
		),
}))

const FILE = {
	fileId: 1,
	name: 'budget.pdf',
	size: 12345,
	modifiedAt: '2026-01-01T12:00:00Z',
	isFolder: false,
	canEdit: true,
	canDelete: true,
}
const FOLDER = {
	fileId: 2,
	name: 'images',
	size: 0,
	modifiedAt: '2026-01-02T12:00:00Z',
	isFolder: true,
	canEdit: true,
	canDelete: false,
}

const CONTENTS_URL = '/apps/launchpad/api/widgets/files/7/contents'

// Interpolating stand-in for Nextcloud's `t()`, used both by script code
// (global) and by the template (instance mock).
function tInterpolate(_app, key, vars) {
	if (!vars) {
		return key
	}
	return Object.entries(vars).reduce(
		(acc, [varName, value]) => acc.replace(`{${varName}}`, value),
		key,
	)
}

function mountWidget(content = {}, placement = { id: 7 }) {
	return mount(FilesWidget, {
		props: { placement, content: { folderPath: '/x', ...content } },
		global: { mocks: { t: tInterpolate } },
		attachTo: document.body,
	})
}

beforeEach(() => {
	globalThis.t = tInterpolate
	vi.clearAllMocks()
})

afterEach(() => {
	document.body.innerHTML = ''
	vi.restoreAllMocks()
})

describe('FilesWidget — fetching', () => {
	it('does not request anything without a placement id', async () => {
		const wrapper = mountWidget({}, {})
		await flushPromises()

		expect(axios.get).not.toHaveBeenCalled()
		expect(wrapper.find('.files-widget__state--empty').exists()).toBe(true)
	})

	it('sends currentPath and limit on the first request', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		mountWidget()
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith(CONTENTS_URL, {
			params: { currentPath: '/', limit: 50 },
		})
	})

	it('treats a missing body / non-array items as an empty folder', async () => {
		axios.get.mockResolvedValue({ data: { items: 'nope' } })
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.text()).toContain('This folder is empty.')

		axios.get.mockResolvedValue(undefined)
		await wrapper.find('.files-widget__crumb--root').trigger('click')
		await flushPromises()
		expect(wrapper.text()).toContain('This folder is empty.')
		expect(wrapper.find('.files-widget__pagination').exists()).toBe(false)
	})

	it('shows the generic error with a Retry button that refetches', async () => {
		axios.get.mockRejectedValueOnce({ response: { status: 500 } })
		const wrapper = mountWidget({ allowUpload: true })
		await flushPromises()

		const errorState = wrapper.find('.files-widget__state--error')
		expect(errorState.exists()).toBe(true)
		expect(errorState.text()).toContain('Failed to load folder contents.')
		// Upload is hidden while in an error state.
		expect(wrapper.find('.files-widget__upload').exists()).toBe(false)

		axios.get.mockResolvedValueOnce({ data: { items: [FILE] } })
		await wrapper.find('.files-widget__retry').trigger('click')
		await flushPromises()

		expect(axios.get).toHaveBeenCalledTimes(2)
		expect(wrapper.find('.files-widget__state--error').exists()).toBe(false)
		expect(wrapper.text()).toContain('budget.pdf')
	})

	it('maps an error body code without status to the matching state', async () => {
		axios.get.mockRejectedValueOnce({ response: { data: { error: 'no_access' } } })
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.find('.files-widget__state--no-access').exists()).toBe(true)

		axios.get.mockRejectedValueOnce(new Error('network'))
		await wrapper.find('.files-widget__crumb--root').trigger('click')
		await flushPromises()
		expect(wrapper.find('.files-widget__state--error').exists()).toBe(true)
	})

	it('shows the loading state while a request is pending', async () => {
		let resolve
		axios.get.mockReturnValue(new Promise((r) => { resolve = r }))
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.text()).toContain('Loading folder…')
		resolve({ data: { items: [FILE] } })
		await flushPromises()
		expect(wrapper.text()).not.toContain('Loading folder…')
		expect(wrapper.text()).toContain('budget.pdf')
	})

	it('refetches from root when the placement prop changes', async () => {
		axios.get.mockResolvedValue({ data: { items: [FOLDER] } })
		const wrapper = mountWidget()
		await flushPromises()
		await wrapper.find('.files-widget__row-name').trigger('click')
		await flushPromises()
		expect(axios.get).toHaveBeenLastCalledWith(CONTENTS_URL, {
			params: { currentPath: '/images', limit: 50 },
		})

		await wrapper.setProps({ placement: { id: 9 } })
		await flushPromises()
		expect(axios.get).toHaveBeenLastCalledWith(
			'/apps/launchpad/api/widgets/files/9/contents',
			{ params: { currentPath: '/', limit: 50 } },
		)
		expect(wrapper.findAll('.files-widget__crumb')).toHaveLength(1)
	})
})

describe('FilesWidget — pagination', () => {
	it('Load more appends the next page using the cursor', async () => {
		axios.get.mockResolvedValueOnce({ data: { items: [FILE], nextCursor: 'c1' } })
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.find('.files-widget__more').exists()).toBe(true)

		axios.get.mockResolvedValueOnce({ data: { items: [FOLDER], nextCursor: null } })
		await wrapper.find('.files-widget__more').trigger('click')
		await flushPromises()

		expect(axios.get).toHaveBeenLastCalledWith(CONTENTS_URL, {
			params: { currentPath: '/', limit: 50, cursor: 'c1' },
		})
		const labels = wrapper.findAll('.files-widget__row-label').map((w) => w.text())
		expect(labels).toEqual(['budget.pdf', 'images'])
		expect(wrapper.find('.files-widget__pagination').exists()).toBe(false)
	})

	it('a failed next page keeps the already loaded items and cursor', async () => {
		axios.get.mockResolvedValueOnce({ data: { items: [FILE], nextCursor: 'c1' } })
		const wrapper = mountWidget()
		await flushPromises()

		axios.get.mockRejectedValueOnce({ response: { status: 500 } })
		await wrapper.find('.files-widget__more').trigger('click')
		await flushPromises()

		expect(wrapper.vm.items).toEqual([FILE])
		expect(wrapper.vm.nextCursor).toBe('c1')
		expect(wrapper.find('.files-widget__state--error').exists()).toBe(true)
	})

	it('loadMore is a no-op without a cursor', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		const wrapper = mountWidget()
		await flushPromises()

		wrapper.vm.loadMore()
		await flushPromises()
		expect(axios.get).toHaveBeenCalledTimes(1)
	})
})

describe('FilesWidget — navigation', () => {
	it('clicking a middle breadcrumb navigates to that segment', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		const wrapper = mountWidget()
		await flushPromises()

		wrapper.vm.navigateTo('/a/b/c')
		await flushPromises()

		const crumbs = wrapper.findAll('.files-widget__crumb')
		expect(crumbs.map((c) => c.text())).toEqual(['Root', 'a', 'b', 'c'])
		expect(crumbs.at(0).attributes('aria-current')).toBeUndefined()
		expect(crumbs.at(3).attributes('aria-current')).toBe('page')
		expect(crumbs.at(2).attributes('aria-current')).toBeUndefined()

		await crumbs.at(2).trigger('click')
		await flushPromises()

		expect(axios.get).toHaveBeenLastCalledWith(CONTENTS_URL, {
			params: { currentPath: '/a/b', limit: 50 },
		})
		expect(wrapper.findAll('.files-widget__crumb').map((c) => c.text()))
			.toEqual(['Root', 'a', 'b'])
	})

	it('root crumb is aria-current at root', async () => {
		axios.get.mockResolvedValue({ data: { items: [] } })
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.find('.files-widget__crumb--root').attributes('aria-current'))
			.toBe('page')
	})

	it('navigateTo with a falsy path resets to root', async () => {
		axios.get.mockResolvedValue({ data: { items: [] } })
		const wrapper = mountWidget()
		await flushPromises()
		wrapper.vm.navigateTo('')
		await flushPromises()
		expect(axios.get).toHaveBeenLastCalledWith(CONTENTS_URL, {
			params: { currentPath: '/', limit: 50 },
		})
	})

	it('descending from a sub-folder joins the path with a single slash', async () => {
		axios.get.mockResolvedValue({ data: { items: [FOLDER] } })
		const wrapper = mountWidget()
		await flushPromises()

		wrapper.vm.navigateTo('/a/')
		await flushPromises()
		await wrapper.find('.files-widget__row-name').trigger('click')
		await flushPromises()

		expect(axios.get).toHaveBeenLastCalledWith(CONTENTS_URL, {
			params: { currentPath: '/a/images', limit: 50 },
		})
	})

	it('joinPath tolerates stray slashes and empty values', async () => {
		axios.get.mockResolvedValue({ data: { items: [] } })
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.vm.joinPath('/', '/x')).toBe('/x')
		expect(wrapper.vm.joinPath('', 'x')).toBe('/x')
		expect(wrapper.vm.joinPath('/a//', '//b')).toBe('/a/b')
		expect(wrapper.vm.joinPath('/a', undefined)).toBe('/a/')
	})

	it('a file without a fileId does not open a window', async () => {
		axios.get.mockResolvedValue({ data: { items: [{ ...FILE, fileId: 0 }] } })
		const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
		const wrapper = mountWidget()
		await flushPromises()

		await wrapper.find('.files-widget__row-name').trigger('click')
		expect(openSpy).not.toHaveBeenCalled()
	})

	it('the deep link url-encodes the file id', async () => {
		axios.get.mockResolvedValue({ data: { items: [{ ...FILE, fileId: 'a b' }] } })
		const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
		const wrapper = mountWidget()
		await flushPromises()

		await wrapper.find('.files-widget__row-name').trigger('click')
		expect(openSpy).toHaveBeenCalledWith(
			'/apps/files/?fileid=a%20b',
			'_blank',
			'noopener,noreferrer',
		)
	})
})

describe('FilesWidget — delete', () => {
	async function openConfirm() {
		axios.get.mockResolvedValue({ data: { items: [FILE, FOLDER] } })
		const wrapper = mountWidget({ allowDelete: true })
		await flushPromises()
		const button = wrapper.find('.files-widget__row-delete')
		expect(button.attributes('aria-label')).toBe('Delete budget.pdf')
		await button.trigger('click')
		await wrapper.vm.$nextTick()
		return wrapper
	}

	it('opening the confirm dialog names the file and focuses the dialog', async () => {
		const wrapper = await openConfirm()
		const dialog = wrapper.find('.files-widget__modal-backdrop')
		expect(dialog.exists()).toBe(true)
		expect(dialog.text()).toContain('Are you sure you want to delete budget.pdf?')
		expect(document.activeElement).toBe(dialog.element)
	})

	it('Cancel closes the dialog without deleting', async () => {
		const wrapper = await openConfirm()
		const cancel = wrapper.findAll('.files-widget__modal-actions button').at(0)
		expect(cancel.text()).toBe('Cancel')
		await cancel.trigger('click')

		expect(wrapper.find('.files-widget__modal-backdrop').exists()).toBe(false)
		expect(axios.delete).not.toHaveBeenCalled()
	})

	it('Escape closes the dialog', async () => {
		const wrapper = await openConfirm()
		await wrapper.find('.files-widget__modal-backdrop').trigger('keydown.esc')
		expect(wrapper.find('.files-widget__modal-backdrop').exists()).toBe(false)
		expect(axios.delete).not.toHaveBeenCalled()
	})

	it('clicking the backdrop itself closes the dialog, clicking inside does not', async () => {
		const wrapper = await openConfirm()
		await wrapper.find('.files-widget__modal').trigger('click')
		expect(wrapper.find('.files-widget__modal-backdrop').exists()).toBe(true)

		await wrapper.find('.files-widget__modal-backdrop').trigger('click')
		expect(wrapper.find('.files-widget__modal-backdrop').exists()).toBe(false)
	})

	it('a failed delete refetches the listing and closes the dialog', async () => {
		const wrapper = await openConfirm()
		axios.delete.mockRejectedValueOnce(new Error('boom'))

		await wrapper.find('.files-widget__modal-confirm').trigger('click')
		await flushPromises()

		expect(axios.delete).toHaveBeenCalledWith(
			'/apps/launchpad/api/widgets/files/7/files/1',
		)
		expect(axios.get).toHaveBeenCalledTimes(2)
		expect(wrapper.find('.files-widget__modal-backdrop').exists()).toBe(false)
		expect(wrapper.text()).toContain('budget.pdf')
	})

	it('performDelete without a target does nothing', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		const wrapper = mountWidget({ allowDelete: true })
		await flushPromises()

		await wrapper.vm.performDelete()
		expect(axios.delete).not.toHaveBeenCalled()
	})
})

describe('FilesWidget — upload', () => {
	function fakeFiles(...names) {
		return names.map((name) => new File(['x'], name, { type: 'text/plain' }))
	}

	it('the Upload button clicks the hidden file input', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		const wrapper = mountWidget({ allowUpload: true })
		await flushPromises()

		const input = wrapper.find('input[type="file"]')
		const clickSpy = vi.spyOn(input.element, 'click')
		await wrapper.find('.files-widget__upload').trigger('click')
		expect(clickSpy).toHaveBeenCalledTimes(1)
	})

	it('upload is hidden when no item is editable', async () => {
		axios.get.mockResolvedValue({ data: { items: [{ ...FILE, canEdit: false }] } })
		const wrapper = mountWidget({ allowUpload: true })
		await flushPromises()
		expect(wrapper.find('.files-widget__upload').exists()).toBe(false)
	})

	it('choosing files POSTs them as files[] to the current folder and refetches', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		axios.post.mockResolvedValue({ data: {} })
		const wrapper = mountWidget({ allowUpload: true })
		await flushPromises()
		wrapper.vm.navigateTo('/docs')
		await flushPromises()
		axios.get.mockClear()

		const files = fakeFiles('a.txt', 'b.txt')
		const input = wrapper.find('input[type="file"]')
		await wrapper.vm.onFileInputChange({ target: { files } })
		await flushPromises()

		expect(axios.post).toHaveBeenCalledTimes(1)
		const [url, formData, config] = axios.post.mock.calls[0]
		expect(url).toBe('/apps/launchpad/api/widgets/files/7/upload')
		expect(formData).toBeInstanceOf(FormData)
		expect(formData.getAll('files[]').map((f) => f.name)).toEqual(['a.txt', 'b.txt'])
		expect(config).toEqual({ params: { currentPath: '/docs' } })
		expect(axios.get).toHaveBeenCalledWith(CONTENTS_URL, {
			params: { currentPath: '/docs', limit: 50 },
		})
		expect(input.element.value).toBe('')
	})

	it('a failed upload still refetches the listing', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		axios.post.mockRejectedValue(new Error('too big'))
		const wrapper = mountWidget({ allowUpload: true })
		await flushPromises()
		axios.get.mockClear()

		await wrapper.vm.onFileInputChange({ target: { files: fakeFiles('a.txt') } })
		await flushPromises()

		expect(axios.post).toHaveBeenCalledTimes(1)
		expect(axios.get).toHaveBeenCalledTimes(1)
	})

	it('an empty selection uploads nothing', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE] } })
		const wrapper = mountWidget({ allowUpload: true })
		await flushPromises()

		await wrapper.find('input[type="file"]').trigger('change')
		await wrapper.vm.onFileInputChange({ target: { files: [] } })
		await wrapper.vm.onFileInputChange(undefined)
		await flushPromises()

		expect(axios.post).not.toHaveBeenCalled()
	})
})

describe('FilesWidget — search box', () => {
	it('typing in the search box filters rows and shows the no-match copy', async () => {
		axios.get.mockResolvedValue({ data: { items: [FILE, FOLDER] } })
		const wrapper = mountWidget()
		await flushPromises()

		const search = wrapper.find('input[type="search"]')
		await search.setValue('IMA')
		expect(wrapper.findAll('.files-widget__row-label').map((w) => w.text()))
			.toEqual(['images'])

		await search.setValue('zzz')
		expect(wrapper.find('.files-widget__list').exists()).toBe(false)
		expect(wrapper.text()).toContain("No files matching 'zzz'")
		expect(axios.get).toHaveBeenCalledTimes(1)
	})
})

describe('FilesWidget — size column', () => {
	it('formats sizes per unit and leaves folders blank', async () => {
		axios.get.mockResolvedValue({
			data: {
				items: [
					{ ...FILE, fileId: 11, name: 'zero', size: 0 },
					{ ...FILE, fileId: 12, name: 'bytes', size: 512 },
					{ ...FILE, fileId: 13, name: 'kb', size: 1536 },
					{ ...FILE, fileId: 14, name: 'mb', size: 5 * 1024 * 1024 },
					{ ...FILE, fileId: 15, name: 'huge', size: 3 * 1024 ** 5 },
					{ ...FILE, fileId: 16, name: 'none', size: undefined },
					FOLDER,
				],
			},
		})
		const wrapper = mountWidget()
		await flushPromises()

		const sizes = wrapper.findAll('.files-widget__row-size').map((w) => w.text())
		expect(sizes).toEqual(['0 B', '512 B', '1.5 KB', '5.0 MB', '3072.0 TB', '0 B', ''])
		expect(wrapper.find('.files-widget__row--folder .files-widget__row-label').text())
			.toBe('images')
	})
})
