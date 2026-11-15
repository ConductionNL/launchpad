/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the DashboardFooter resolution edges the main spec
 * leaves out: non-string html, empty and non-object config, locale maps on
 * config leaves, address splitting, the links/legal switch and the variant
 * picker's fallbacks.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DashboardFooter from '../DashboardFooter.vue'

/**
 * Mount the footer with the given payload.
 *
 * @param {object|null} footer The footer payload.
 * @param {string} locale The viewer locale.
 * @return {object} The wrapper.
 */
function mountFooter(footer, locale = 'en') {
	return mount(DashboardFooter, { props: { footer, locale } })
}

describe('DashboardFooter edges', () => {
	it('treats a non-string, non-object html as absent', () => {
		const wrapper = mountFooter({ html: 42, config: null })

		expect(wrapper.vm.resolvedHtml).toBeNull()
		expect(wrapper.vm.showFooter).toBe(false)
	})

	it('treats a non-object or empty config as absent', () => {
		expect(mountFooter({ html: null, config: 'x' }).vm.resolvedConfig).toBeNull()
		expect(mountFooter({ html: null, config: {} }).vm.resolvedConfig).toBeNull()
	})

	it('with no config, defaults the derived fields', () => {
		const vm = mountFooter({ html: '<p>x</p>', config: null }).vm

		expect(vm.layoutMode).toBe('columns')
		expect(vm.orgName).toBe('')
		expect(vm.addressLines).toEqual([])
		expect(vm.legalText).toBe('')
		expect(vm.hasLinksOrLegal).toBe(false)
	})

	it('picks the locale on plain locale-map leaves and keeps link objects and arrays', () => {
		const link = { label: 'Home', url: '/' }
		const vm = mountFooter(
			{
				html: null,
				config: {
					organisation: { nl: 'Gemeente', en: 'Municipality' },
					link,
					links: [link],
					layoutMode: 'inline',
				},
			},
			'nl',
		).vm

		expect(vm.resolvedConfig).toEqual({
			organisation: 'Gemeente',
			link,
			links: [link],
			layoutMode: 'inline',
		})
		expect(vm.orgName).toBe('Gemeente')
		expect(vm.layoutMode).toBe('inline')
	})

	it('treats an unknown layout mode as columns', () => {
		const vm = mountFooter({ html: null, config: { layoutMode: 'grid' } }).vm

		expect(vm.layoutMode).toBe('columns')
	})

	it('splits the address into trimmed, non-empty lines', () => {
		const vm = mountFooter({
			html: null,
			config: { address: '  Street 1 \n\n  1234 AB City  \n ' },
		}).vm

		expect(vm.addressLines).toEqual(['Street 1', '1234 AB City'])
	})

	it('hasLinksOrLegal is true for links, legal text or a copyright year alone', () => {
		expect(mountFooter({ config: { links: [{ label: 'a' }] } }).vm.hasLinksOrLegal).toBe(true)
		expect(mountFooter({ config: { legal: 'KvK 1' } }).vm.hasLinksOrLegal).toBe(true)
		expect(mountFooter({ config: { copyrightYear: 2026 } }).vm.hasLinksOrLegal).toBe(true)
		expect(mountFooter({ config: { links: [], organisation: 'X' } }).vm.hasLinksOrLegal).toBe(false)
	})

	it('pickVariant returns an empty string for a non-object or empty map', () => {
		const vm = mountFooter(null).vm

		expect(vm.pickVariant(null)).toBe('')
		expect(vm.pickVariant('nl')).toBe('')
		expect(vm.pickVariant({})).toBe('')
		expect(vm.pickVariant({ de: 'Hallo' })).toBe('Hallo')
	})

	it('applies only the colours that are set', () => {
		expect(mountFooter({ html: 'x', textColor: '#111' }).vm.footerStyle).toEqual({
			color: '#111',
		})
		expect(mountFooter(null).vm.footerStyle).toEqual({})
	})
})
