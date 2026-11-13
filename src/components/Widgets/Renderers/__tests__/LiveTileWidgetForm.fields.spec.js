/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the field handlers of `LiveTileWidgetForm.vue`:
 * switching the source mode, editing the URL (which clears a stale
 * allow-list error), the refresh interval, and the number-format options —
 * each emits the assembled content. The blur-time allow-list check is skipped
 * for connector mode and for an empty URL. `liveTileClient.js` is mocked.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'
import {
	fetchConnectorAvailability,
	validateLiveTileSource,
} from '../../../../services/liveTileClient.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchConnectorAvailability: vi.fn(),
	validateLiveTileSource: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function lastEmitted(wrapper) {
	const events = wrapper.emitted('update:content') || []
	return events.at(-1)?.[0]
}

async function mountForm(value = { url: 'https://a.test/x', valueExpr: '$.n' }) {
	const wrapper = mount(LiveTileWidgetForm, { props: { value } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	fetchConnectorAvailability.mockReset()
	fetchConnectorAvailability.mockResolvedValue(true)
	validateLiveTileSource.mockReset()
	validateLiveTileSource.mockResolvedValue({ valid: true, errors: [] })
})

describe('LiveTileWidgetForm field handlers', () => {
	it('switches the source mode and falls back to url for an empty choice', async () => {
		const wrapper = await mountForm()
		wrapper.vm.onSourceModeChange('connector')
		expect(lastEmitted(wrapper).sourceMode).toBe('connector')
		wrapper.vm.onSourceModeChange(null)
		expect(wrapper.vm.sourceMode).toBe('url')
		expect(lastEmitted(wrapper).sourceMode).toBe('url')
	})

	it('clears a stale allow-list error when the URL changes', async () => {
		const wrapper = await mountForm()
		wrapper.vm.urlAllowListError = 'Host not allowed'
		wrapper.vm.onUrlChange('https://b.test/y')
		expect(wrapper.vm.urlAllowListError).toBe('')
		expect(lastEmitted(wrapper).url).toBe('https://b.test/y')
	})

	it('emits the refresh interval clamped to the minimum', async () => {
		const wrapper = await mountForm()
		wrapper.vm.onRefreshChange('10')
		expect(wrapper.vm.refresh).toBe('10')
		expect(lastEmitted(wrapper).refresh).toBe(30)
		wrapper.vm.onRefreshChange('120')
		expect(lastEmitted(wrapper).refresh).toBe(120)
	})

	it('writes prefix, suffix and thousands into the format, ignoring unknown options', async () => {
		const wrapper = await mountForm()
		wrapper.vm.updateFormat('prefix', '€ ')
		wrapper.vm.updateFormat('suffix', ' open')
		wrapper.vm.updateFormat('thousands', true)
		expect(lastEmitted(wrapper).format).toEqual({
			prefix: '€ ',
			suffix: ' open',
			thousands: true,
		})
		const before = wrapper.emitted('update:content').length
		wrapper.vm.updateFormat('decimals', 2)
		expect(wrapper.emitted('update:content')).toHaveLength(before + 1)
		expect(lastEmitted(wrapper).format).toEqual({
			prefix: '€ ',
			suffix: ' open',
			thousands: true,
		})
	})

	it('reads a stored format over the defaults and coerces thousands to a boolean', async () => {
		const wrapper = await mountForm({ format: { suffix: '%', thousands: 1 } })
		expect(wrapper.vm.formatPrefix).toBe('')
		expect(wrapper.vm.formatSuffix).toBe('%')
		expect(wrapper.vm.formatThousands).toBe(true)
	})

	it('keeps connector mode when the probe reports OpenConnector present', async () => {
		const wrapper = await mountForm({ sourceMode: 'connector', sourceId: 'src-1' })
		expect(wrapper.vm.connectorAvailable).toBe(true)
		expect(wrapper.vm.sourceMode).toBe('connector')
		expect(wrapper.emitted('update:content')).toBeUndefined()
	})
})

describe('LiveTileWidgetForm blur-time allow-list check', () => {
	it('skips the check and clears the error in connector mode', async () => {
		const wrapper = await mountForm({ sourceMode: 'connector', sourceId: 's' })
		wrapper.vm.urlAllowListError = 'old'
		await wrapper.vm.checkUrlAllowed()
		expect(validateLiveTileSource).not.toHaveBeenCalled()
		expect(wrapper.vm.urlAllowListError).toBe('')
	})

	it('skips the check and clears the error for a blank URL', async () => {
		const wrapper = await mountForm({ url: '   ' })
		wrapper.vm.urlAllowListError = 'old'
		await wrapper.vm.checkUrlAllowed()
		expect(validateLiveTileSource).not.toHaveBeenCalled()
		expect(wrapper.vm.urlAllowListError).toBe('')
	})

	it('checks the assembled content for a URL in url mode', async () => {
		const wrapper = await mountForm()
		await wrapper.vm.checkUrlAllowed()
		expect(validateLiveTileSource).toHaveBeenCalledTimes(1)
		expect(validateLiveTileSource.mock.calls[0][0]).toMatchObject({
			sourceMode: 'url',
			url: 'https://a.test/x',
			valueExpr: '$.n',
		})
		expect(wrapper.vm.urlAllowListError).toBe('')
	})
})
