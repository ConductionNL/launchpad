/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared seeding and restore helpers for the navigation-editor-org specs
 * (openspec/specs/navigation-editor-org/spec.md).
 *
 * Every write goes through the real endpoints in appinfo/routes.php:
 *   GET/PUT /api/admin/org-navigation?lang={nl|en}
 *   GET/PUT /api/admin/org-navigation/position
 *   GET/PUT /api/admin/action-matrix
 *
 * WHY THE ACTION MATRIX IS TOUCHED AT ALL. `lib/actions.seed.json:9-10` ships
 * `admin-org-navigation.get-org-navigation` and `.get-position` as admin-only,
 * so an ordinary signed-in user gets HTTP 403 from both reads
 * (`AdminOrgNavigationController::getOrgNavigation` calls
 * `RequestGuard::actionRefusal`). REQ-ONAV-002 says the read is open to every
 * logged-in user. The group-filtering scenarios are about WHAT a user sees, so
 * those describes grant the two read actions to `@all` for their duration and
 * put the matrix back exactly as it was afterwards. The shipped default itself
 * is probed, ungranted, by a separate `test.fixme` in the API spec.
 *
 * WHAT A RESTORE CAN AND CANNOT PUT BACK. The only read of the tree is the
 * group-filtered GET, and it filters for the admin too
 * (`OrgNavigationService::filterTreeByUserGroups` has no admin bypass). A node
 * restricted to a group the admin is not in therefore cannot be read, and so
 * cannot be restored. On the CI instance both language files start empty, so
 * this costs nothing there.
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'

import { expect } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { API } from './principals.ts'

/** The tree endpoint. */
export const ONAV = `${API}/admin/org-navigation`

/** The position endpoint. */
export const ONAV_POSITION = `${API}/admin/org-navigation/position`

/** The action-matrix endpoint (ADR-023). */
export const ACTION_MATRIX = `${API}/admin/action-matrix`

/**
 * How this instance addresses the app. The scenarios write `/apps/launchpad/...`;
 * the CI instance serves it behind the `/index.php` front controller, and the
 * panel compares against `window.location.pathname`, so the nodes carry the
 * same prefix the page does.
 */
export const APP_PATH = '/index.php/apps/launchpad'

/** UUID v1..v5, the exact pattern `OrgNavigationService::isUuid` enforces. */
export const UUID_RE =
	/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

/** The two read actions REQ-ONAV-002/004 open to every logged-in user. */
export const READ_ACTIONS = [
	'admin-org-navigation.get-org-navigation',
	'admin-org-navigation.get-position',
]

/** One node, in the shape of the spec's Data Model. */
export interface NavNode {
	id: string
	label?: string
	icon?: string | null
	url?: string | null
	openInNewTab?: boolean
	groupVisibility?: string[] | null
	children?: NavNode[]
}

/**
 * A node with every field set, so a re-read can be compared with toEqual.
 *
 * @param label the label.
 * @param extra fields to override.
 * @return the node.
 */
export function navNode(label: string, extra: Partial<NavNode> = {}): NavNode {
	return {
		id: randomUUID(),
		label,
		icon: null,
		url: null,
		openInNewTab: false,
		groupVisibility: null,
		children: [],
		...extra,
	}
}

/**
 * Depth of a tree, root level counted as 1.
 *
 * @param tree the nodes.
 * @return the depth; 0 for an empty tree.
 */
export function depthOf(tree: NavNode[]): number {
	if (!Array.isArray(tree) || tree.length === 0) {
		return 0
	}
	return 1 + Math.max(...tree.map((n) => depthOf(n.children ?? [])))
}

/**
 * Every id in a tree, depth first.
 *
 * @param tree the nodes.
 * @return the ids.
 */
export function idsOf(tree: NavNode[]): string[] {
	return (tree ?? []).flatMap((n) => [n.id, ...idsOf(n.children ?? [])])
}

/**
 * Read the tree as a principal.
 *
 * @param api the principal's context.
 * @param lang the language file.
 * @return the status and the parsed body.
 */
export async function readTree(
	api: APIRequestContext,
	lang = 'nl',
): Promise<{ status: number; body: any }> {
	const res = await api.get(`${ONAV}?lang=${lang}`)
	return { status: res.status(), body: await res.json().catch(() => null) }
}

/**
 * Read the tree as the admin, failing unless it answers 200.
 *
 * @param api an admin context.
 * @param lang the language file.
 * @return the tree.
 */
export async function adminTree(
	api: APIRequestContext,
	lang = 'nl',
): Promise<NavNode[]> {
	const { status, body } = await readTree(api, lang)
	expect(status, JSON.stringify(body)).toBe(200)
	return body.tree
}

/**
 * PUT a replacement tree.
 *
 * @param api the principal's context.
 * @param tree the tree.
 * @param lang the language file.
 * @return the raw response.
 */
export async function putTree(
	api: APIRequestContext,
	tree: unknown,
	lang = 'nl',
): Promise<APIResponse> {
	return api.put(`${ONAV}?lang=${lang}`, { data: { tree } })
}

/**
 * Seed a tree as the admin, failing unless it is accepted.
 *
 * @param api an admin context.
 * @param tree the tree.
 * @param lang the language file.
 * @return nothing.
 */
export async function seedTree(
	api: APIRequestContext,
	tree: NavNode[],
	lang = 'nl',
): Promise<void> {
	const res = await putTree(api, tree, lang)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Read the position as a principal.
 *
 * @param api the principal's context.
 * @return the status and the parsed body.
 */
export async function readPosition(
	api: APIRequestContext,
): Promise<{ status: number; body: any }> {
	const res = await api.get(ONAV_POSITION)
	return { status: res.status(), body: await res.json().catch(() => null) }
}

/**
 * Set the position as the admin, failing unless it is accepted.
 *
 * @param api an admin context.
 * @param position left, right, top or hidden.
 * @return nothing.
 */
export async function setPosition(
	api: APIRequestContext,
	position: string,
): Promise<void> {
	const res = await api.put(ONAV_POSITION, { data: { position } })
	expect(res.status(), await res.text()).toBe(200)
	expect(await res.json()).toEqual({ position })
}

/**
 * Read the action matrix as the admin.
 *
 * @param api an admin context.
 * @return the matrix.
 */
async function readMatrix(
	api: APIRequestContext,
): Promise<Record<string, string[]>> {
	const res = await api.get(ACTION_MATRIX)
	expect(res.status(), await res.text()).toBe(200)
	const matrix = (await res.json()).matrix
	// PHP encodes an empty matrix as `[]`; keys set on a JS array never serialise.
	return matrix && !Array.isArray(matrix) ? matrix : {}
}

/**
 * Replace the action matrix as the admin.
 *
 * @param api an admin context.
 * @param matrix the matrix.
 * @return nothing.
 */
async function writeMatrix(
	api: APIRequestContext,
	matrix: Record<string, string[]>,
): Promise<void> {
	const res = await api.put(ACTION_MATRIX, { data: { matrix } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Grant the two org-navigation reads to every signed-in user.
 *
 * @param api an admin context.
 * @return nothing.
 */
export async function grantReadsToEveryone(api: APIRequestContext): Promise<void> {
	const matrix = await readMatrix(api)
	for (const action of READ_ACTIONS) {
		matrix[action] = ['admin', '@all']
	}
	await writeMatrix(api, matrix)
}

/** Everything these specs change, as it was before they changed it. */
export interface Snapshot {
	nl: NavNode[]
	en: NavNode[]
	position: string
	matrix: Record<string, string[]>
}

/**
 * Record the trees, the position and the matrix.
 *
 * @param api an admin context.
 * @return the snapshot.
 */
export async function snapshot(api: APIRequestContext): Promise<Snapshot> {
	const position = await readPosition(api)
	expect(position.status, JSON.stringify(position.body)).toBe(200)
	return {
		nl: await adminTree(api, 'nl'),
		en: await adminTree(api, 'en'),
		position: position.body.position,
		matrix: await readMatrix(api),
	}
}

/**
 * Put back what {@link snapshot} recorded.
 *
 * @param api an admin context.
 * @param snap the snapshot; nothing happens when it is null.
 * @return nothing.
 */
export async function restore(
	api: APIRequestContext,
	snap: Snapshot | null,
): Promise<void> {
	if (snap === null) {
		return
	}
	await seedTree(api, snap.nl, 'nl')
	await seedTree(api, snap.en, 'en')
	await setPosition(api, snap.position)
	await writeMatrix(api, snap.matrix)
}
