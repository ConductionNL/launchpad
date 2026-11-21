/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `api` client: for each call, the HTTP verb, the URL (with path
 * segments encoded), the body and the query/request options it sends, and
 * the branches that add or leave out optional parameters.
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const B = '/index.php/apps/launchpad'
const VERBS = ['get', 'post', 'put', 'delete']

beforeEach(() => {
	for (const verb of VERBS) {
		axios[verb].mockReset()
		axios[verb].mockReturnValue(`${verb}-response`)
	}
})

/**
 * Assert exactly one axios call was made, with this verb and arguments,
 * and that the client returned its promise unchanged.
 *
 * @param {*} returned what the api method returned.
 * @param {string} verb the expected axios method.
 * @param {Array} args the expected arguments.
 */
function expectOnly(returned, verb, ...args) {
	expect(returned).toBe(`${verb}-response`)
	expect(axios[verb]).toHaveBeenCalledTimes(1)
	expect(axios[verb]).toHaveBeenCalledWith(...args)
	for (const other of VERBS.filter((v) => v !== verb)) {
		expect(axios[other]).not.toHaveBeenCalled()
	}
}

describe('api: plain calls', () => {
	const data = { name: 'x' }
	const CASES = [
		['getDashboards', [], 'get', [`${B}/api/dashboards`]],
		['createGroupDashboard', ['g 1', data], 'post', [`${B}/api/dashboards/group/g%201`, data]],
		['updateGroupDashboard', ['g/1', 'u?', data], 'put', [`${B}/api/dashboards/group/g%2F1/u%3F`, data]],
		['deleteGroupDashboard', ['g', 'u'], 'delete', [`${B}/api/dashboards/group/g/u`]],
		['getActiveDashboard', [], 'get', [`${B}/api/dashboard`]],
		['setActiveDashboardPreference', ['u1'], 'post', [`${B}/api/dashboards/active`, { uuid: 'u1' }]],
		['setDefaultDashboardPreference', ['u1'], 'post', [`${B}/api/dashboards/default`, { uuid: 'u1' }]],
		['clearDefaultDashboardPreference', [], 'post', [`${B}/api/dashboards/default`, { uuid: '' }]],
		['getDefaultDashboardPreference', [], 'get', [`${B}/api/dashboards/default`]],
		['createDashboard', [data], 'post', [`${B}/api/dashboard`, data]],
		['updateDashboard', [3, data], 'put', [`${B}/api/dashboard/3`, data]],
		['activateDashboard', [3], 'post', [`${B}/api/dashboard/3/activate`]],
		['getDashboardById', [3], 'get', [`${B}/api/dashboard/3`]],
		['listGroupDashboards', ['a&b'], 'get', [`${B}/api/dashboards/group/a%26b`]],
		['setGroupDashboardDefault', ['g', 'u'], 'post', [`${B}/api/dashboards/group/g/default`, { uuid: 'u' }]],
		['acquireLock', ['u 1'], 'post', [`${B}/api/dashboards/u%201/lock`]],
		['heartbeatLock', ['u'], 'put', [`${B}/api/dashboards/u/lock`]],
		['releaseLock', ['u'], 'delete', [`${B}/api/dashboards/u/lock`]],
		['releaseLockOnPageHide', ['u'], 'delete', [`${B}/api/dashboards/u/lock`, { adapter: 'fetch', fetchOptions: { keepalive: true } }]],
		['forceReleaseLock', ['u'], 'post', [`${B}/api/dashboards/u/lock/force-release`]],
		['listVersions', ['u'], 'get', [`${B}/api/dashboards/u/versions`]],
		['createVersion', ['u', 'note'], 'post', [`${B}/api/dashboards/u/versions`, { note: 'note' }]],
		['restoreVersion', ['u', 4], 'post', [`${B}/api/dashboards/u/versions/4/restore`]],
		['publishDashboard', ['u'], 'post', [`${B}/api/dashboards/u/publish`]],
		['unpublishDashboard', ['u'], 'post', [`${B}/api/dashboards/u/unpublish`]],
		['recordDashboardViewEvent', ['u'], 'post', [`${B}/api/dashboards/u/view-event`, {}]],
		['recordTileClick', ['p#1'], 'post', [`${B}/api/tile-click/p%231`, {}]],
		['getTileAnalyticsConfig', [], 'get', [`${B}/api/tile-analytics/config`]],
		['getDashboardTree', [], 'get', [`${B}/api/dashboards/tree`]],
		['getDashboardPath', ['u'], 'get', [`${B}/api/dashboards/u/path`]],
		['listShares', [9], 'get', [`${B}/api/dashboard/9/shares`]],
		['replaceShares', [9, ['s']], 'put', [`${B}/api/dashboard/9/shares`, { shares: ['s'] }]],
		['searchSharees', ['ali'], 'get', [`${B}/api/sharees`, { params: { query: 'ali' } }]],
		['getAvailableWidgets', [], 'get', [`${B}/api/widgets`]],
		['getCalendarWidgetCalendars', [], 'get', [`${B}/api/widgets/calendar/calendars`]],
		['addWidget', [9, data], 'post', [`${B}/api/dashboard/9/widgets`, data]],
		['addTile', [9, data], 'post', [`${B}/api/dashboard/9/tile`, data]],
		['importBookmarks', [9, data], 'post', [`${B}/api/dashboard/9/tiles/import`, data]],
		['discoverApps', [], 'get', [`${B}/api/tiles/discover`]],
		['updateWidgetPlacement', [5, data], 'put', [`${B}/api/widgets/5`, data]],
		['removeWidget', [5], 'delete', [`${B}/api/widgets/5`]],
		['getWidgetRules', [5], 'get', [`${B}/api/widgets/5/rules`]],
		['addWidgetRule', [5, data], 'post', [`${B}/api/widgets/5/rules`, data]],
		['deleteRule', [6], 'delete', [`${B}/api/rules/6`]],
		['updateRule', [6, data], 'put', [`${B}/api/rules/6`, data]],
		['previewVisibility', [data], 'post', [`${B}/api/visibility/preview`, data]],
		['getAdminWidgetsWithRules', [], 'get', [`${B}/api/admin/widgets/with-rules`]],
		['getMetrics', [], 'get', [`${B}/api/metrics`, { responseType: 'text' }]],
		['getHealth', [], 'get', [`${B}/api/health`]],
		['getAdminTemplates', [], 'get', [`${B}/api/admin/templates`]],
		['getShippedTemplates', [], 'get', [`${B}/api/admin/templates/shipped`]],
		['installShippedTemplate', ['t1'], 'post', [`${B}/api/admin/templates/shipped/t1/install`]],
		['createAdminTemplate', [data], 'post', [`${B}/api/admin/templates`, data]],
		['updateAdminTemplate', [2, data], 'put', [`${B}/api/admin/templates/2`, data]],
		['deleteAdminTemplate', [2], 'delete', [`${B}/api/admin/templates/2`]],
		['resyncAdminTemplate', [2, { strategy: 'merge', dryRun: true }], 'post', [`${B}/api/admin/templates/2/resync`, { strategy: 'merge', dryRun: true }]],
		['getAdminSettings', [], 'get', [`${B}/api/admin/settings`]],
		['getStoreConfig', [], 'get', [`${B}/api/store/config`]],
		['updateStoreConfig', [data], 'put', [`${B}/api/store/config`, data]],
		['updateAdminSettings', [data], 'put', [`${B}/api/admin/settings`, data]],
		['getAdminGroups', [], 'get', [`${B}/api/admin/groups`]],
		['updateAdminGroupOrder', [['a', 'b']], 'post', [`${B}/api/admin/groups`, { groups: ['a', 'b'] }]],
		['getActionMatrix', [], 'get', [`${B}/api/admin/action-matrix`]],
		['updateActionMatrix', [{ a: 1 }], 'put', [`${B}/api/admin/action-matrix`, { matrix: { a: 1 } }]],
		['getSetupWizardState', [], 'get', [`${B}/api/admin/setup-wizard/state`]],
		['completeSetupWizard', [], 'post', [`${B}/api/admin/setup-wizard/complete`]],
		['listTranslations', ['u'], 'get', [`${B}/api/dashboards/u/translations`]],
		['createTranslation', ['u', data], 'post', [`${B}/api/dashboards/u/translations`, data]],
		['updateTranslation', ['u', 'pt-BR', data], 'put', [`${B}/api/dashboards/u/translations/pt-BR`, data]],
		['deleteTranslation', ['u', 'de'], 'delete', [`${B}/api/dashboards/u/translations/de`]],
		['setPrimaryTranslation', ['u', 'de'], 'post', [`${B}/api/dashboards/u/translations/de/set-primary`]],
		['getResolvedDashboard', ['u'], 'get', [`${B}/api/dashboards/u/resolved`]],
		['getMetadataFieldDefinitions', [], 'get', [`${B}/api/metadata-fields`]],
		['createMetadataField', [data], 'post', [`${B}/api/admin/metadata-fields`, data]],
		['updateMetadataField', ['f/1', data], 'put', [`${B}/api/admin/metadata-fields/f%2F1`, data]],
		['deleteMetadataField', ['f1'], 'delete', [`${B}/api/admin/metadata-fields/f1`]],
		['getMetadataFields', [], 'get', [`${B}/api/admin/metadata-fields`]],
		['getDashboardMetadata', ['u'], 'get', [`${B}/api/dashboards/u/metadata`]],
		['updateDashboardMetadata', ['u', { k: 'v' }], 'put', [`${B}/api/dashboards/u/metadata`, { metadata: { k: 'v' } }]],
		['getOrgNavigationPosition', [], 'get', [`${B}/api/admin/org-navigation/position`]],
		['updateOrgNavigationPosition', ['top'], 'put', [`${B}/api/admin/org-navigation/position`, { position: 'top' }]],
		['listDemoShowcases', [], 'get', [`${B}/api/admin/demo-showcases`]],
		['uninstallDemoShowcase', ['d 1'], 'delete', [`${B}/api/admin/demo-showcases/d%201`]],
		['getTiles', [], 'get', [`${B}/api/tiles`]],
		['createTile', [data], 'post', [`${B}/api/tiles`, data]],
		['updateTile', [8, data], 'put', [`${B}/api/tiles/8`, data]],
		['deleteTile', [8], 'delete', [`${B}/api/tiles/8`]],
		['getDashboardReactions', ['u'], 'get', [`${B}/api/dashboards/u/reactions`]],
		['addDashboardReaction', ['u', '👍'], 'post', [`${B}/api/dashboards/u/reactions`, { emoji: '👍' }]],
		['getDashboardReactors', ['u', '👍'], 'get', [`${B}/api/dashboards/u/reactions/%F0%9F%91%8D/users`]],
		['removeDashboardReaction', ['u', '👍'], 'delete', [`${B}/api/dashboards/u/reactions/%F0%9F%91%8D`]],
		['getPendingAcknowledgements', [], 'get', [`${B}/api/acknowledgements/pending`]],
		['getAcknowledgementReport', ['k/1'], 'get', [`${B}/api/acknowledgements/report/k%2F1`]],
	]

	it.each(CASES)('%s sends the expected request', (method, args, verb, expected) => {
		expectOnly(api[method](...args), verb, ...expected)
	})
})

describe('api: optional parameters', () => {
	it('getVisibleDashboards adds the metadata filter only when it has keys', () => {
		expectOnly(api.getVisibleDashboards(), 'get', `${B}/api/dashboards/visible`)
		axios.get.mockClear()
		api.getVisibleDashboards({})
		expect(axios.get).toHaveBeenCalledWith(`${B}/api/dashboards/visible`)
		axios.get.mockClear()
		api.getVisibleDashboards({ team: 'ops' })
		expect(axios.get).toHaveBeenCalledWith(`${B}/api/dashboards/visible`, {
			params: { metadata: { team: 'ops' } },
		})
	})

	it('deleteDashboard sends cascade only when asked', () => {
		expectOnly(api.deleteDashboard(4), 'delete', `${B}/api/dashboard/4`)
		axios.delete.mockClear()
		api.deleteDashboard(4, true)
		expect(axios.delete).toHaveBeenCalledWith(`${B}/api/dashboard/4`, {
			params: { cascade: true },
		})
	})

	it.each([[undefined], [null], ['']])('forkDashboard leaves out an empty name (%s)', (name) => {
		expectOnly(api.forkDashboard('s/1', name), 'post', `${B}/api/dashboards/s%2F1/fork`, {})
	})

	it('forkDashboard sends a given name', () => {
		expectOnly(api.forkDashboard('s', 'Copy'), 'post', `${B}/api/dashboards/s/fork`, { name: 'Copy' })
	})

	it('scheduleDashboard adds unpublishAt only when set', () => {
		expectOnly(api.scheduleDashboard('u', '2026-01-01'), 'post', `${B}/api/dashboards/u/schedule`, {
			publishAt: '2026-01-01',
		})
		axios.post.mockClear()
		api.scheduleDashboard('u', '2026-01-01', '2026-02-01')
		expect(axios.post).toHaveBeenCalledWith(`${B}/api/dashboards/u/schedule`, {
			publishAt: '2026-01-01',
			unpublishAt: '2026-02-01',
		})
	})

	it('analytics calls default to 30 days and 10 rows', () => {
		api.getAnalyticsTopTiles()
		api.getAnalyticsTopDashboards('7d', 3)
		api.getAnalyticsTileDashboardBreakdown('u 1')
		api.getAnalyticsInstanceSummary()
		expect(axios.get.mock.calls).toEqual([
			[`${B}/api/admin/analytics/tiles/top`, { params: { period: '30d', limit: 10 } }],
			[`${B}/api/admin/analytics/dashboards/top`, { params: { period: '7d', limit: 3 } }],
			[`${B}/api/admin/analytics/tiles/by-dashboard/u%201`, { params: { period: '30d' } }],
			[`${B}/api/admin/analytics/summary`, { params: { period: '30d' } }],
		])
	})

	it('CSV exports ask for a blob', () => {
		api.getAnalyticsCsvExport()
		api.getTileAnalyticsCsvExport('90d')
		expect(axios.get.mock.calls).toEqual([
			[`${B}/api/admin/analytics/export`, { params: { period: '30d' }, responseType: 'blob' }],
			[`${B}/api/admin/analytics/tiles/export`, { params: { period: '90d' }, responseType: 'blob' }],
		])
	})

	it.each([
		['/team/ops/', 'team/ops'],
		['//a//', 'a'],
		['plain', 'plain'],
		[null, ''],
		[undefined, ''],
	])('getDashboardByPath trims slashes from %s', (path, clean) => {
		expectOnly(api.getDashboardByPath(path), 'get', `${B}/api/dashboards/by-path/${clean}`)
	})

	it('getWidgetItems repeats widgets[] and defaults the limit to 7', () => {
		api.getWidgetItems(['a', 'b c'])
		api.getWidgetItems([], 3)
		expect(axios.get.mock.calls).toEqual([
			[`${B}/api/widgets/items?widgets%5B%5D=a&widgets%5B%5D=b+c&limit=7`],
			[`${B}/api/widgets/items?limit=3`],
		])
	})

	it('updateShippedTemplate defaults dryRun to false', () => {
		api.updateShippedTemplate('t')
		api.updateShippedTemplate('t', { dryRun: true })
		expect(axios.post.mock.calls).toEqual([
			[`${B}/api/admin/templates/shipped/t/update`, { dryRun: false }],
			[`${B}/api/admin/templates/shipped/t/update`, { dryRun: true }],
		])
	})

	it('exportDashboards defaults to site scope and adds a dashboard only when given', () => {
		api.exportDashboards()
		api.exportDashboards({ scope: 'dashboard', dashboardUuid: 'u1' })
		expect(axios.post.mock.calls).toEqual([
			[`${B}/api/admin/export`, null, { params: { scope: 'site' }, responseType: 'blob' }],
			[`${B}/api/admin/export`, null, { params: { scope: 'dashboard', dashboardUuid: 'u1' }, responseType: 'blob' }],
		])
	})

	it('importDashboards uploads the file as multipart with preserveUuids', () => {
		const file = new Blob(['{}'])
		api.importDashboards(file)
		api.importDashboards(file, { preserveUuids: true })

		const [[url, form, options], [, , options2]] = axios.post.mock.calls
		expect(url).toBe(`${B}/api/admin/import`)
		expect(form).toBeInstanceOf(FormData)
		expect(form.get('file')).toBeInstanceOf(Blob)
		expect(options).toEqual({
			params: { preserveUuids: false },
			headers: { 'Content-Type': 'multipart/form-data' },
		})
		expect(options2.params).toEqual({ preserveUuids: true })
	})

	it('confluence import sends the file, with a parent only when given', () => {
		const file = new Blob(['zip'])
		api.confluenceImportDryRun(file)
		api.confluenceImport(file)
		api.confluenceImport(file, { parentUuid: 'p1' })

		const calls = axios.post.mock.calls
		expect(calls[0][0]).toBe(`${B}/api/admin/import/confluence/dry-run`)
		expect(calls[0][1].get('file')).toBeInstanceOf(Blob)
		expect(calls[0][2]).toEqual({ headers: { 'Content-Type': 'multipart/form-data' } })
		expect(calls[1][0]).toBe(`${B}/api/admin/import/confluence`)
		expect(calls[1][2]).toEqual({ params: {}, headers: { 'Content-Type': 'multipart/form-data' } })
		expect(calls[2][2]).toEqual({
			params: { parentUuid: 'p1' },
			headers: { 'Content-Type': 'multipart/form-data' },
		})
	})

	it('org navigation defaults to Dutch', () => {
		api.getOrgNavigation()
		api.getOrgNavigation('en')
		api.updateOrgNavigation([{ id: 1 }])
		expect(axios.get.mock.calls).toEqual([
			[`${B}/api/admin/org-navigation`, { params: { lang: 'nl' } }],
			[`${B}/api/admin/org-navigation`, { params: { lang: 'en' } }],
		])
		expect(axios.put).toHaveBeenCalledWith(
			`${B}/api/admin/org-navigation`,
			{ tree: [{ id: 1 }] },
			{ params: { lang: 'nl' } },
		)
	})

	it('bulk operations send their uuids with dry-run defaults', () => {
		api.bulkDeleteDashboards(['a'])
		api.bulkDeleteDashboards(['a'], { dryRun: true, cascade: true })
		api.bulkMoveDashboards(['a'], 'p')
		api.bulkStatusDashboards(['a'], 'published')
		api.bulkStatusDashboards(['a'], 'scheduled', { publishAt: 'T', dryRun: true })
		api.bulkReindexDashboards(['a'])
		expect(axios.post.mock.calls).toEqual([
			[`${B}/api/admin/dashboards/bulk-delete`, { dashboardUuids: ['a'], dryRun: false, cascade: false }],
			[`${B}/api/admin/dashboards/bulk-delete`, { dashboardUuids: ['a'], dryRun: true, cascade: true }],
			[`${B}/api/admin/dashboards/bulk-move`, { dashboardUuids: ['a'], parentUuid: 'p', dryRun: false }],
			[`${B}/api/admin/dashboards/bulk-status`, { dashboardUuids: ['a'], publicationStatus: 'published', publishAt: null, dryRun: false }],
			[`${B}/api/admin/dashboards/bulk-status`, { dashboardUuids: ['a'], publicationStatus: 'scheduled', publishAt: 'T', dryRun: true }],
			[`${B}/api/admin/dashboards/bulk-reindex`, { dashboardUuids: ['a'], dryRun: false }],
		])
	})

	it('installDemoShowcase defaults to Dutch and sends force only when set', () => {
		api.installDemoShowcase('d/1')
		api.installDemoShowcase('d', { lang: 'en', force: true })
		expect(axios.post.mock.calls).toEqual([
			[`${B}/api/admin/demo-showcases/d%2F1/install`, null, { params: { lang: 'nl' } }],
			[`${B}/api/admin/demo-showcases/d/install`, null, { params: { lang: 'en', force: true } }],
		])
	})

	it('acknowledge defaults the content version to 1', () => {
		api.acknowledge('policy')
		api.acknowledge('policy', 3)
		expect(axios.post.mock.calls).toEqual([
			[`${B}/api/acknowledgements`, { announcementKey: 'policy', contentVersion: 1 }],
			[`${B}/api/acknowledgements`, { announcementKey: 'policy', contentVersion: 3 }],
		])
	})

	it('getAcknowledgementReportCsvUrl builds a URL without a request', () => {
		expect(api.getAcknowledgementReportCsvUrl('a b')).toBe(
			`${B}/api/acknowledgements/report/a%20b/csv`,
		)
		for (const verb of VERBS) {
			expect(axios[verb]).not.toHaveBeenCalled()
		}
	})
})
