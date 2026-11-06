/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Deleting a compulsory widget from a template copy says why it cannot be
 * done and sends nothing; any other widget is removed as before
 * (admin-templates REQ-TMPL-023). Both menus end in `removeWidget`, which is
 * what is called here, the way the menus call it.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

let Views
let showError

/**
 * @param {object[]} placements The dashboard's placements.
 * @param {string} permissionLevel The copy's permission level.
 * @return {object} host with the real Views methods.
 */
function makeHost(placements, permissionLevel = 'add_only') {
	const host = {
		widgetPlacements: placements,
		permissionLevel,
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	// The store action is mapped into `methods`; the double goes on after
	// the bind, as ViewsPersonalHide.spec.js does with `switchDashboard`.
	host.removeWidgetFromDashboard = vi.fn().mockResolvedValue()
	return host
}

const attention = {
	id: 7,
	widgetId: 'attention',
	customTitle: 'Vandaag eerst',
	isCompulsory: 1,
}
const cases = {
	id: 8,
	widgetId: 'object-list',
	customTitle: 'Mijn zaken',
	isCompulsory: 0,
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
	showError = (await import('@nextcloud/dialogs')).showError
})

describe('Views removes a widget', () => {
	it('explains a compulsory widget and sends nothing', async () => {
		const host = makeHost([attention, cases])
		await host.removeWidget(7)
		expect(showError).toHaveBeenCalledWith(
			'"Vandaag eerst" is part of the start page your administrator set for your group and cannot be removed.',
		)
		expect(host.removeWidgetFromDashboard).not.toHaveBeenCalled()
	})

	it('removes a widget that is not compulsory, with no message', async () => {
		const host = makeHost([attention, cases])
		await host.removeWidget(8)
		expect(showError).not.toHaveBeenCalled()
		expect(host.removeWidgetFromDashboard).toHaveBeenCalledWith(8)
	})

	it('lets a full-permission copy remove a compulsory widget', async () => {
		const host = makeHost([attention], 'full')
		await host.removeWidget(7)
		expect(showError).not.toHaveBeenCalled()
		expect(host.removeWidgetFromDashboard).toHaveBeenCalledWith(7)
	})
})
