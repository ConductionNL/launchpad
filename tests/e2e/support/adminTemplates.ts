/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seeding helpers for the admin-templates specs
 * (`tests/e2e/admin-templates-*.spec.ts`).
 *
 * WHY TEMPLATES WITH WIDGETS ARE SEEDED THROUGH THE IMPORTER. An admin
 * template is owned by nobody (`userId` null), and the widget API resolves
 * access by ownership or share, so `POST /api/dashboard/{templateId}/widgets`
 * has no path to a template at all (PermissionService::canAddWidget). The
 * only writers of template widgets the product has are the dashboard
 * importer (`POST /api/admin/import`), the shipped-template install and the
 * shipped-template update. A spec that needs a template WITH widgets as its
 * GIVEN therefore imports a one-dashboard archive, exactly the way an
 * administrator restores a downloaded template.
 *
 * Everything here acts over basic auth (support/principals.ts explains why).
 */

import type { APIRequestContext } from '@playwright/test'

import { randomUUID } from 'crypto'
import { API } from './principals.ts'
import { buildZip, manifest } from './zipArchive.ts'

/** A template row as `GET /api/admin/templates` returns it. */
export interface TemplateRow {
	id: number
	uuid: string
	name: string
	description: string | null
	type: string
	userId: string | null
	targetGroups: string[]
	isDefault: number
	permissionLevel: string
	gridColumns: number
	basedOnTemplate: number | null
	isActive: number
	createdAt: string
	updatedAt: string
	templateCategory: string | null
	templateDescription: string | null
	templatePreviewImage: string | null
	[key: string]: unknown
}

/** A placement as the API serialises it. */
export interface PlacementRow {
	id: number
	dashboardId: number
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	isCompulsory: number
	customTitle: string | null
	content: Record<string, unknown> | null
	templatePlacementId: number | null
	tileType?: string | null
	tileTitle?: string | null
	tileIcon?: string | null
	tileBackgroundColor?: string | null
	tileTextColor?: string | null
	tileLinkType?: string | null
	tileLinkValue?: string | null
	version?: number
	[key: string]: unknown
}

/** One widget in an imported template. */
export interface SeedWidget {
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	isCompulsory?: number
	customTitle?: string
	content?: Record<string, unknown>
	[key: string]: unknown
}

/** A text widget whose body is `text`, so the page shows it verbatim. */
export function textWidget(
	text: string,
	at: { x: number; y: number },
	extra: Partial<SeedWidget> = {},
): SeedWidget {
	return {
		widgetId: 'text',
		gridX: at.x,
		gridY: at.y,
		gridWidth: 4,
		gridHeight: 2,
		isCompulsory: 0,
		customTitle: text,
		content: { text, contentMode: 'markdown' },
		...extra,
	}
}

/**
 * Add a titled text widget to a dashboard the caller may edit, the way the
 * workspace does: `POST /api/dashboard/{id}/widgets` creates the placement
 * (it takes no title), then `PUT /api/widgets/{id}` sets `customTitle`.
 *
 * @param api the caller's context.
 * @param dashboardId the dashboard.
 * @param text the widget's title and body.
 * @param at the grid position.
 * @return the status of the create and of the title update.
 */
export async function addTitledTextWidget(
	api: APIRequestContext,
	dashboardId: number,
	text: string,
	at: { x: number; y: number },
): Promise<{ created: number; titled: number }> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: textWidget(text, at),
	})
	if (res.status() !== 201) {
		return { created: res.status(), titled: 0 }
	}
	const body = await res.json()
	const placement = (body.data ?? body) as PlacementRow
	const titled = await api.put(`${API}/widgets/${placement.id}`, {
		data: { customTitle: text, version: placement.version },
	})
	return { created: res.status(), titled: titled.status() }
}

/** Every template on the instance. */
export async function listTemplates(api: APIRequestContext): Promise<TemplateRow[]> {
	const res = await api.get(`${API}/admin/templates`)
	if (res.status() !== 200) {
		throw new Error(`listing templates answered ${res.status()}`)
	}
	return (await res.json()) as TemplateRow[]
}

/** One template and its placements. */
export async function getTemplate(
	api: APIRequestContext,
	id: number,
): Promise<{ template: TemplateRow; placements: PlacementRow[] }> {
	const res = await api.get(`${API}/admin/templates/${id}`)
	if (res.status() !== 200) {
		throw new Error(`reading template ${id} answered ${res.status()}`)
	}
	return res.json()
}

/**
 * Import a one-dashboard archive holding an admin template, and answer with
 * the row the importer made. The name must be unique: the importer hands out
 * a fresh UUID, so the row is found by name.
 *
 * @param api an admin context.
 * @param name the template's unique name.
 * @param options the template's fields and widgets.
 * @return the created template row.
 */
export async function importTemplate(
	api: APIRequestContext,
	name: string,
	options: {
		widgets?: SeedWidget[]
		targetGroups?: string[]
		permissionLevel?: string
		templateCategory?: string | null
		templateDescription?: string | null
		description?: string | null
	} = {},
): Promise<TemplateRow> {
	const uuid = randomUUID()
	const payload: Record<string, unknown> = {
		uuid,
		name,
		type: 'admin_template',
		userId: null,
		gridColumns: 12,
		permissionLevel: options.permissionLevel ?? 'add_only',
		targetGroups: options.targetGroups ?? [],
		widgets: options.widgets ?? [],
	}
	if (options.description !== undefined) {
		payload.description = options.description
	}
	if (
		options.templateCategory !== undefined
		&& options.templateCategory !== null
	) {
		payload.templateCategory = options.templateCategory
	}
	if (
		options.templateDescription !== undefined
		&& options.templateDescription !== null
	) {
		payload.templateDescription = options.templateDescription
	}

	const archive = buildZip([
		{ name: 'manifest.json', content: manifest({ dashboardCount: 1 }) },
		{ name: `dashboards/${uuid}.json`, content: JSON.stringify(payload) },
	])
	const res = await api.post(`${API}/admin/import`, {
		multipart: {
			file: {
				name: 'template.zip',
				mimeType: 'application/zip',
				buffer: archive,
			},
		},
	})
	const body = await res.json().catch(() => null)
	if (res.status() !== 200 || body?.importedDashboardCount !== 1) {
		throw new Error(
			`importing template "${name}" answered ${res.status()}: ${JSON.stringify(body)}`,
		)
	}

	const row = (await listTemplates(api)).find((t) => t.name === name)
	if (row === undefined) {
		throw new Error(`the imported template "${name}" is not in the listing`)
	}
	return row
}

/** Create a template through `POST /api/admin/templates`; throws unless 201. */
export async function createTemplate(
	api: APIRequestContext,
	data: Record<string, unknown>,
): Promise<TemplateRow> {
	const res = await api.post(`${API}/admin/templates`, { data })
	if (res.status() !== 201) {
		throw new Error(
			`creating a template answered ${res.status()}: ${await res.text()}`,
		)
	}
	return res.json()
}

/** Delete templates, tolerating ones already gone. */
export async function deleteTemplates(
	api: APIRequestContext,
	ids: Array<number | null | undefined>,
): Promise<void> {
	for (const id of ids) {
		if (id !== null && id !== undefined) {
			await api.delete(`${API}/admin/templates/${id}`)
		}
	}
}

/**
 * Remember which template is the instance default, so a spec that makes its
 * own template the default can hand the flag back. Setting a default clears
 * every other one (`clearDefaultTemplates()`), so without this a pre-existing
 * default would be lost for every later suite.
 */
export async function rememberDefaultTemplate(
	api: APIRequestContext,
): Promise<number | null> {
	const current = (await listTemplates(api)).find((t) => Number(t.isDefault) === 1)
	return current?.id ?? null
}

/** Hand the default flag back to the template that had it (or to nobody). */
export async function restoreDefaultTemplate(
	api: APIRequestContext,
	id: number | null,
): Promise<void> {
	if (id !== null) {
		await api.put(`${API}/admin/templates/${id}`, { data: { isDefault: true } })
	}
}

/** Read `allowUserDashboards`. */
export async function readAllowUserDashboards(
	api: APIRequestContext,
): Promise<boolean> {
	const res = await api.get(`${API}/admin/settings`)
	if (res.status() !== 200) {
		throw new Error(`admin settings answered ${res.status()}`)
	}
	return (await res.json()).allowUserDashboards === true
}

/** Set `allowUserDashboards`; throws unless the server took it. */
export async function setAllowUserDashboards(
	api: APIRequestContext,
	allowed: boolean,
): Promise<void> {
	const res = await api.put(`${API}/admin/settings`, {
		data: { allowUserDashboards: allowed },
	})
	if (res.status() !== 200) {
		throw new Error(`setting allowUserDashboards answered ${res.status()}`)
	}
	if ((await readAllowUserDashboards(api)) !== allowed) {
		throw new Error(`allowUserDashboards did not become ${allowed}`)
	}
}

/** What `GET /api/dashboard` answers for a principal. */
export interface ActiveDashboard {
	status: number
	dashboard: TemplateRow
	placements: PlacementRow[]
	permissionLevel: string
}

/**
 * The principal's effective dashboard (`GET /api/dashboard`): the call the
 * page makes on a first visit, and the one that hands out a template copy.
 */
export async function activeDashboard(
	api: APIRequestContext,
): Promise<ActiveDashboard> {
	const res = await api.get(`${API}/dashboard`)
	const body = await res.json().catch(() => ({}))
	return { status: res.status(), ...body }
}

/** The principal's visible dashboards (`GET /api/dashboards/visible`). */
export async function visibleDashboards(
	api: APIRequestContext,
): Promise<Array<TemplateRow & { source?: string }>> {
	const res = await api.get(`${API}/dashboards/visible`)
	if (res.status() !== 200) {
		throw new Error(`visible dashboards answered ${res.status()}`)
	}
	return (await res.json()).items
}

/** The copies of a template a principal can see. */
export async function copiesOf(
	api: APIRequestContext,
	templateId: number,
): Promise<TemplateRow[]> {
	return (await visibleDashboards(api)).filter(
		(d) => d.basedOnTemplate === templateId,
	)
}

/** A dashboard's placements as its owner reads them. */
export async function placementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<PlacementRow[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	if (res.status() !== 200) {
		throw new Error(`reading dashboard ${dashboardId} answered ${res.status()}`)
	}
	return (await res.json()).placements
}

/** The layout fields of a placement, for comparing two layouts. */
export function layoutOf(p: PlacementRow): Record<string, unknown> {
	return {
		widgetId: p.widgetId,
		gridX: p.gridX,
		gridY: p.gridY,
		gridWidth: p.gridWidth,
		gridHeight: p.gridHeight,
		isCompulsory: p.isCompulsory,
		customTitle: p.customTitle,
	}
}

/** Sort layouts so two lists compare regardless of row order. */
export function sortedLayout(ps: PlacementRow[]): Array<Record<string, unknown>> {
	return ps
		.map(layoutOf)
		.sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b)))
}

/** The app-config key the shipped install of a template is recorded under. */
export function shippedConfigUrl(key: string): string {
	return `/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/${key}`
}

/** Read one launchpad app-config value ('' when unset). */
export async function readAppConfig(
	api: APIRequestContext,
	key: string,
): Promise<string> {
	const res = await api.get(`${shippedConfigUrl(key)}?format=json`)
	if (res.status() !== 200) {
		return ''
	}
	const body = await res.json().catch(() => null)
	return String(body?.ocs?.data?.data ?? '')
}

/** Write (or, with '', delete) one launchpad app-config value. */
export async function writeAppConfig(
	api: APIRequestContext,
	key: string,
	value: string,
): Promise<void> {
	const res =
		value === ''
			? await api.delete(shippedConfigUrl(key))
			: await api.post(shippedConfigUrl(key), { form: { value } })
	if (!res.ok()) {
		throw new Error(`app config ${key} answered ${res.status()}`)
	}
}
