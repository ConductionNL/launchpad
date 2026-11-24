/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * InstallBanner: the phone offer to put LaunchPad on the home screen
 * (platform-baseline REQ-PLB-009, board LpMobiel).
 *
 * @spec openspec/changes/archive/2026-11-24-phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text) => text,
	translate: (_app, text) => text,
	translatePlural: (_app, one, many, count) => (count === 1 ? one : many),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))

import InstallBanner from '../InstallBanner.vue'
import { capturePrompt, clearPrompt, NOT_NOW_KEY, NOT_NOW_MS } from '../../../utils/installPrompt.js'

function setWidth(width) {
	Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: width })
}

function setStandalone(matches) {
	window.matchMedia = vi.fn(() => ({ matches }))
}

beforeEach(() => {
	window.localStorage.clear()
	setWidth(390)
	setStandalone(false)
	clearPrompt()
})

afterEach(() => {
	vi.restoreAllMocks()
})

describe('InstallBanner', () => {
	it('shows on a phone with the board text and both buttons', () => {
		const wrapper = mount(InstallBanner)
		const banner = wrapper.find('[data-testid="install-banner"]')
		expect(banner.exists()).toBe(true)
		expect(banner.attributes('role')).toBe('region')
		expect(banner.text()).toContain('Put LaunchPad on your home screen')
		expect(banner.text()).toContain('Then your workplace opens with one tap, like an app')
		expect(wrapper.find('[data-testid="install-banner-not-now"]').text()).toBe('Not now')
		expect(wrapper.find('[data-testid="install-banner-install"]').text()).toBe('Install')
	})

	it('does not show on a 1440 px screen', () => {
		setWidth(1440)
		expect(mount(InstallBanner).find('[data-testid="install-banner"]').exists()).toBe(false)
	})

	it('hides when the window grows past phone width', async () => {
		const wrapper = mount(InstallBanner)
		setWidth(1024)
		window.dispatchEvent(new Event('resize'))
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-testid="install-banner"]').exists()).toBe(false)
	})

	it('does not show when opened as an installed app', () => {
		setStandalone(true)
		expect(mount(InstallBanner).find('[data-testid="install-banner"]').exists()).toBe(false)
	})

	it('does not show in kiosk or on a public page', () => {
		expect(mount(InstallBanner, { props: { kiosk: true } }).find('[data-testid="install-banner"]').exists()).toBe(false)
		expect(mount(InstallBanner, { props: { isPublic: true } }).find('[data-testid="install-banner"]').exists()).toBe(false)
	})

	it('Install opens the browser prompt and hides once accepted', async () => {
		const prompt = { preventDefault: vi.fn(), prompt: vi.fn(() => Promise.resolve()), userChoice: Promise.resolve({ outcome: 'accepted' }) }
		capturePrompt(prompt)
		const wrapper = mount(InstallBanner)
		await wrapper.find('[data-testid="install-banner-install"]').trigger('click')
		await flushPromises()
		expect(prompt.prompt).toHaveBeenCalledTimes(1)
		expect(wrapper.find('[data-testid="install-banner"]').exists()).toBe(false)
	})

	it('Install keeps the banner when the prompt is dismissed', async () => {
		capturePrompt({ preventDefault: vi.fn(), prompt: vi.fn(() => Promise.resolve()), userChoice: Promise.resolve({ outcome: 'dismissed' }) })
		const wrapper = mount(InstallBanner)
		await wrapper.find('[data-testid="install-banner-install"]').trigger('click')
		await flushPromises()
		expect(wrapper.find('[data-testid="install-banner"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="install-banner-steps"]').exists()).toBe(false)
	})

	it('Install without a browser prompt shows the steps', async () => {
		const wrapper = mount(InstallBanner)
		await wrapper.find('[data-testid="install-banner-install"]').trigger('click')
		const steps = wrapper.find('[data-testid="install-banner-steps"]')
		expect(steps.exists()).toBe(true)
		expect(steps.text()).toContain('Share')
		expect(steps.text()).toContain('Add to Home Screen')
	})

	it('Not now hides it and stores the moment for 30 days', async () => {
		const wrapper = mount(InstallBanner)
		await wrapper.find('[data-testid="install-banner-not-now"]').trigger('click')
		expect(wrapper.find('[data-testid="install-banner"]').exists()).toBe(false)
		expect(Number(window.localStorage.getItem(NOT_NOW_KEY))).toBeGreaterThan(0)
		expect(mount(InstallBanner).find('[data-testid="install-banner"]').exists()).toBe(false)
	})

	it('shows again 30 days after Not now', () => {
		window.localStorage.setItem(NOT_NOW_KEY, String(Date.now() - NOT_NOW_MS - 1000))
		expect(mount(InstallBanner).find('[data-testid="install-banner"]').exists()).toBe(true)
	})

	it('a blocked storage only means the banner shows', async () => {
		vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('SecurityError') })
		vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('SecurityError') })
		const wrapper = mount(InstallBanner)
		expect(wrapper.find('[data-testid="install-banner"]').exists()).toBe(true)
		await wrapper.find('[data-testid="install-banner-not-now"]').trigger('click')
		expect(wrapper.find('[data-testid="install-banner"]').exists()).toBe(false)
	})
})
