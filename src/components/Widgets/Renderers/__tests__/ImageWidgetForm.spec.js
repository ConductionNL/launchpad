/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Image content form (REQ-IMG-020): upload or web address, the chosen
 * file with size and dimensions, the required alternative text and the link.
 */

import { NcSelect, NcTextField } from '@nextcloud/vue'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ImageWidgetForm from '../ImageWidgetForm.vue'

vi.mock('@nextcloud/dialogs', () => ({
	FilePickerClosed: class FilePickerClosed extends Error {},
	getFilePickerBuilder: vi.fn(),
}))

function textField(wrapper, label) {
	return wrapper.findAllComponents(NcTextField).find((field) => field.props('label') === label)
}

function lastEmitted(wrapper) {
	return (wrapper.emitted('update:content') || []).at(-1)?.[0]
}

function imageFile(size = 2048, type = 'image/png', name = 'team.png') {
	const file = new File(['x'], name, { type })
	Object.defineProperty(file, 'size', { value: size })
	return file
}

beforeEach(() => {
	globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview')
	globalThis.URL.revokeObjectURL = vi.fn()
})

describe('ImageWidgetForm source', () => {
	it('starts on Upload file for a new image and on Web address for a stored https image', () => {
		expect(mount(ImageWidgetForm).find('[data-testid="image-source-upload"]').element.checked).toBe(true)
		const stored = mount(ImageWidgetForm, { props: { editingWidget: { content: { url: 'https://cdn.test/a.png', alt: 'A' } } } })
		expect(stored.find('[data-testid="image-source-url"]').element.checked).toBe(true)
		expect(textField(stored, 'Image URL').props('modelValue')).toBe('https://cdn.test/a.png')
	})

	it('writes the web address and clears it when switching to upload', async () => {
		const wrapper = mount(ImageWidgetForm)
		await wrapper.find('[data-testid="image-source-url"]').trigger('change')
		textField(wrapper, 'Image URL').vm.$emit('update:modelValue', 'https://cdn.test/b.png')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).url).toBe('https://cdn.test/b.png')
		await wrapper.find('[data-testid="image-source-upload"]').trigger('change')
		expect(lastEmitted(wrapper).url).toBe('')
	})
})

describe('ImageWidgetForm chosen file', () => {
	it('shows the chosen file with its name and size and uploads it on commit', async () => {
		const fileUploadFn = vi.fn().mockResolvedValue({ url: '/apps/launchpad/img/7' })
		const wrapper = mount(ImageWidgetForm, { props: { fileUploadFn } })
		await wrapper.vm.holdFile(imageFile(1.5 * 1024 * 1024))
		await wrapper.vm.$nextTick()
		const chosen = wrapper.find('[data-testid="image-chosen"]')
		expect(chosen.text()).toContain('team.png')
		expect(chosen.text()).toContain('1.5 MB')
		expect(fileUploadFn).not.toHaveBeenCalled()
		await wrapper.vm.commit()
		expect(fileUploadFn).toHaveBeenCalledTimes(1)
		expect(lastEmitted(wrapper).url).toBe('/apps/launchpad/img/7')
	})

	it('refuses a file over 5 MB and a file that is not an image', async () => {
		const wrapper = mount(ImageWidgetForm)
		await wrapper.vm.holdFile(imageFile(6 * 1024 * 1024))
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[role="alert"]').text()).toBe('The file is larger than 5 MB')
		await wrapper.vm.holdFile(imageFile(10, 'application/pdf', 'a.pdf'))
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[role="alert"]').text()).toBe('Choose an image file')
		expect(wrapper.find('[data-testid="image-chosen"]').exists()).toBe(false)
	})

	it('Choose another drops the chosen file', async () => {
		const wrapper = mount(ImageWidgetForm)
		await wrapper.vm.holdFile(imageFile())
		await wrapper.vm.$nextTick()
		wrapper.vm.chooseAnother()
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-testid="image-chosen"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="image-file-input"]').exists()).toBe(true)
	})

	it('keeps the dialog open when the upload fails', async () => {
		const wrapper = mount(ImageWidgetForm, { props: { fileUploadFn: vi.fn().mockRejectedValue(new Error('500')) } })
		await wrapper.vm.holdFile(imageFile())
		await expect(wrapper.vm.commit()).rejects.toThrow('500')
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[role="alert"]').text()).toBe('The image could not be uploaded')
	})
})

describe('ImageWidgetForm alternative text is required (scenario)', () => {
	it('refuses an image without alternative text and names the field', async () => {
		const wrapper = mount(ImageWidgetForm, { props: { value: { url: 'https://cdn.test/a.png', alt: '' } } })
		expect(wrapper.vm.validate()).toEqual(['Alternative text is required'])
		textField(wrapper, 'Text for who does not see the image').vm.$emit('update:modelValue', 'The team at the town hall')
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.validate()).toEqual([])
		expect(lastEmitted(wrapper)).toEqual({ url: 'https://cdn.test/a.png', alt: 'The team at the town hall', link: '', fit: 'cover' })
	})

	it('marks the field once the editor empties it, not before', async () => {
		const wrapper = mount(ImageWidgetForm)
		const alt = () => textField(wrapper, 'Text for who does not see the image')
		expect(alt().props('error')).toBe(false)
		alt().vm.$emit('update:modelValue', '')
		await wrapper.vm.$nextTick()
		expect(alt().props('error')).toBe(true)
	})

	it('asks for an image when there is none', () => {
		const wrapper = mount(ImageWidgetForm, { props: { value: { alt: 'x' } } })
		expect(wrapper.vm.validate()).toEqual(['Choose an image or enter its web address'])
	})

	it('writes the optional link', async () => {
		const wrapper = mount(ImageWidgetForm)
		textField(wrapper, 'Link (optional)').vm.$emit('update:modelValue', 'https://intranet.test/team')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).link).toBe('https://intranet.test/team')
	})
})

// @spec openspec/specs/image-widget/spec.md#requirement-req-img-005-addedit-form
describe('ImageWidgetForm fit and web-address preview (REQ-IMG-005)', () => {
	it('offers exactly cover, contain, fill and none, with cover for a new image', async () => {
		const wrapper = mount(ImageWidgetForm)
		const fit = wrapper.findComponent(NcSelect)
		expect(fit.props('inputLabel')).toBe('Fit')
		expect(fit.props('options').map((option) => option.id)).toEqual(['cover', 'contain', 'fill', 'none'])
		expect(fit.props('modelValue').id).toBe('cover')
		fit.vm.$emit('update:modelValue', { id: 'contain', label: 'Contain' })
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).fit).toBe('contain')
		fit.vm.$emit('update:modelValue', { id: 'stretch', label: 'Stretch' })
		expect(lastEmitted(wrapper).fit).toBe('cover')
	})

	it('keeps a stored fit when editing', () => {
		const wrapper = mount(ImageWidgetForm, { props: { editingWidget: { content: { url: 'https://cdn.test/a.png', alt: 'A', fit: 'none' } } } })
		expect(wrapper.findComponent(NcSelect).props('modelValue').id).toBe('none')
	})

	it('previews a typed web address, and shows no preview while it is empty', async () => {
		const wrapper = mount(ImageWidgetForm)
		await wrapper.find('[data-testid="image-source-url"]').trigger('change')
		expect(wrapper.find('[data-testid="image-url-preview"]').exists()).toBe(false)
		textField(wrapper, 'Image URL').vm.$emit('update:modelValue', 'https://example.com/x.png')
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-testid="image-url-preview"]').attributes('src')).toBe('https://example.com/x.png')
	})
})
