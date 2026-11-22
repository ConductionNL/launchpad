/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * runtime-shell REQ-SHELL-010: the first-visit tour opens on the More menu,
 * where the dashboards live (board LpEersteKeer).
 */

import { describe, expect, it } from 'vitest'
import manifest from '../manifest.json'

describe('the Workspace tour in src/manifest.json', () => {
	const tour = manifest.walkthrough.tours.find((candidate) => candidate.id === 'launchpad:getting-started')

	it('rings the More menu at step 1 and says the dashboards are there', () => {
		const [first] = tour.steps
		expect(first.target).toEqual({ kind: 'element', ref: 'launchpad-more-menu' })
		expect(first.placement).not.toBe('center')
		expect(first.title).toBe('Here you switch between dashboards')
		expect(first.body).toMatch(/dashboards are under More/)
		expect(first.advanceOn).toEqual({ type: 'manual' })
	})

	it('is version 2, so a person who finished the side-bar tour sees the More tour once', () => {
		expect(manifest.walkthrough.version).toBe(2)
		expect(manifest.walkthrough.completionConfigKey).toBe('walkthrough_completed_version')
	})

	it('then asks to open Flows from More', () => {
		expect(tour.steps.map((step) => step.id)).toEqual(['dashboards', 'more-menu'])
		const second = tour.steps[1]
		expect(second.target).toEqual({ kind: 'element', ref: 'launchpad-more-menu' })
		expect(second.advanceOn).toEqual({ type: 'route-match', route: 'Flows' })
		expect(second.allowManualNext).toBe(true)
	})
})
