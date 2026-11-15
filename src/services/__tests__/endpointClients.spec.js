/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The small endpoint clients (health ping, iframe, live tile, weather): the
 * URL each one calls, what it returns from the response, and how the
 * validators and availability checks fail closed when the request errors.
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchHealthPingBadge, validateHealthPingConfig } from '../healthPingClient.js'
import { checkIframeFramable, validateIframeUrl } from '../iframeClient.js'
import {
	fetchConnectorAvailability,
	fetchLiveTileValue,
	validateLiveTileSource,
} from '../liveTileClient.js'
import { fetchWeatherReading } from '../weatherClient.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))

beforeEach(() => {
	axios.get.mockReset()
	axios.post.mockReset()
})

describe('healthPingClient', () => {
	it('fetches the badge of one placement and returns its data', async () => {
		const badge = { state: 'up', checkedAt: '2026-01-01', latencyMs: 12, stale: false }
		axios.get.mockResolvedValue({ data: badge })

		await expect(fetchHealthPingBadge(42)).resolves.toBe(badge)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/health-ping/42')
	})

	it('returns an empty object when the response carries no data', async () => {
		axios.get.mockResolvedValue({ data: null })
		await expect(fetchHealthPingBadge(1)).resolves.toEqual({})
		axios.get.mockResolvedValue(undefined)
		await expect(fetchHealthPingBadge(1)).resolves.toEqual({})
	})

	it('lets a failed badge request reject so the badge can hide', async () => {
		axios.get.mockRejectedValue(new Error('403'))
		await expect(fetchHealthPingBadge(1)).rejects.toThrow('403')
	})

	it('posts the config for validation and returns the verdict', async () => {
		const verdict = { valid: false, errors: ['host_not_allowed'] }
		axios.post.mockResolvedValue({ data: verdict })
		const config = { healthPingEnabled: true, healthUrl: 'https://x.test/h' }

		await expect(validateHealthPingConfig(config)).resolves.toBe(verdict)
		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/health-ping/validate',
			{ config },
		)
	})

	it('treats an empty validation answer as valid', async () => {
		axios.post.mockResolvedValue({ data: null })
		await expect(validateHealthPingConfig({})).resolves.toEqual({ valid: true, errors: [] })
	})

	it('fails closed when the validation endpoint errors', async () => {
		axios.post.mockRejectedValue(new Error('500'))
		await expect(validateHealthPingConfig({})).resolves.toEqual({
			valid: false,
			errors: ['validation_unavailable'],
		})
	})
})

describe('iframeClient', () => {
	it('posts the config to the URL validator and returns the verdict', async () => {
		const verdict = { valid: true, errors: [] }
		axios.post.mockResolvedValue({ data: verdict })
		const config = { url: 'https://embed.test', height: 300 }

		await expect(validateIframeUrl(config)).resolves.toBe(verdict)
		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/iframe/validate-url',
			{ config },
		)
	})

	it('treats an empty validator answer as valid and an error as invalid', async () => {
		axios.post.mockResolvedValueOnce({})
		await expect(validateIframeUrl({})).resolves.toEqual({ valid: true, errors: [] })

		axios.post.mockRejectedValueOnce(new Error('network'))
		await expect(validateIframeUrl({})).resolves.toEqual({
			valid: false,
			errors: ['validation_unavailable'],
		})
	})

	it('asks the server whether a URL may be framed', async () => {
		const answer = { framable: true, reason: 'ok' }
		axios.post.mockResolvedValue({ data: answer })

		await expect(checkIframeFramable('https://embed.test/page')).resolves.toBe(answer)
		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/iframe/framable',
			{ url: 'https://embed.test/page' },
		)
	})

	it('reports not framable on an empty answer or a failed check', async () => {
		axios.post.mockResolvedValueOnce({ data: undefined })
		await expect(checkIframeFramable('u')).resolves.toEqual({
			framable: false,
			reason: 'no_response',
		})

		axios.post.mockRejectedValueOnce(new Error('timeout'))
		await expect(checkIframeFramable('u')).resolves.toEqual({
			framable: false,
			reason: 'check_unavailable',
		})
	})
})

describe('liveTileClient', () => {
	it('fetches the value of one placement', async () => {
		const reading = { value: 7, formatted: '7', badge: null, fetchedAt: null, stale: true }
		axios.get.mockResolvedValue({ data: reading })

		await expect(fetchLiveTileValue('p-9')).resolves.toBe(reading)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/livetile/p-9')
	})

	it('returns an empty object for an empty value response and rejects on error', async () => {
		axios.get.mockResolvedValueOnce({})
		await expect(fetchLiveTileValue(1)).resolves.toEqual({})

		axios.get.mockRejectedValueOnce(new Error('404'))
		await expect(fetchLiveTileValue(1)).rejects.toThrow('404')
	})

	it('offers connector mode only when the status says available === true', async () => {
		axios.get.mockResolvedValueOnce({ data: { available: true } })
		await expect(fetchConnectorAvailability()).resolves.toBe(true)
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/livetile/connector/status',
		)

		axios.get.mockResolvedValueOnce({ data: { available: 'true' } })
		await expect(fetchConnectorAvailability()).resolves.toBe(false)

		axios.get.mockResolvedValueOnce({ data: null })
		await expect(fetchConnectorAvailability()).resolves.toBe(false)
	})

	it('treats an unreachable status endpoint as unavailable', async () => {
		axios.get.mockRejectedValue(new Error('502'))
		await expect(fetchConnectorAvailability()).resolves.toBe(false)
	})

	it('validates a source config and fails closed on error', async () => {
		const verdict = { valid: false, errors: ['bad_expr'] }
		axios.post.mockResolvedValueOnce({ data: verdict })
		const config = { sourceMode: 'url', url: 'https://src.test', valueExpr: '$.n' }

		await expect(validateLiveTileSource(config)).resolves.toBe(verdict)
		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/livetile/validate-source',
			{ config },
		)

		axios.post.mockResolvedValueOnce(null)
		await expect(validateLiveTileSource(config)).resolves.toEqual({ valid: true, errors: [] })

		axios.post.mockRejectedValueOnce(new Error('500'))
		await expect(validateLiveTileSource(config)).resolves.toEqual({
			valid: false,
			errors: ['validation_unavailable'],
		})
	})
})

describe('weatherClient', () => {
	it('fetches the reading of one placement', async () => {
		const reading = { location: 'Amsterdam', tempValue: 11, units: 'metric', stale: false }
		axios.get.mockResolvedValue({ data: reading })

		await expect(fetchWeatherReading(5)).resolves.toBe(reading)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/weather/5')
	})

	it('returns an empty object without data and rejects on error', async () => {
		axios.get.mockResolvedValueOnce({ data: '' })
		await expect(fetchWeatherReading(5)).resolves.toEqual({})

		axios.get.mockRejectedValueOnce(new Error('500'))
		await expect(fetchWeatherReading(5)).rejects.toThrow('500')
	})
})
