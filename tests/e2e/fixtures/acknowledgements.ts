/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared e2e fixture that seeds a real, outstanding mandatory-read
 * acknowledgement widget on the admin's active dashboard.
 *
 * REQ-ACK-001 ships no admin-facing form UI for declaring an
 * acknowledgement requirement in this pass (the requirement carries an
 * `@e2e exclude` for its configuration path — see
 * openspec/specs/dashboard-acknowledgements/spec.md). The only way to put
 * a placement into that state is the real, authorised
 * `PUT /api/widgets/{id}` contract, which is itself PHPUnit-covered
 * (PlacementUpdater / TemplateService / WidgetPlacementMapper). The
 * dashboard-acknowledgements e2e specs (REQ-ACK-002 forced-delivery gate,
 * REQ-ACK-004 read-receipt report) are still genuinely UI-observable and
 * need a real placement in that state to drive them, so this fixture
 * creates one through the real API — the same pattern
 * `role-feature-permissions.ts` uses for the widget allow-list — rather
 * than faking DOM state inside the spec.
 *
 * Idempotent: the placement is matched across runs by a `content.text`
 * marker and reused (no unbounded row growth on the shared dev dashboard).
 * Each call bumps `acknowledgementContentVersion` with
 * `reacknowledgeOnChange = 1` so any acknowledgement recorded by a
 * previous run no longer satisfies the gate — the item is guaranteed
 * outstanding for the admin user at the start of every run.
 */

import { request as pwRequest, type APIRequestContext } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const ACTIVE_DASHBOARD_URL = `${BASE}/index.php/apps/launchpad/api/dashboard`
const ADD_WIDGET_URL = (dashboardId: number) => `${BASE}/index.php/apps/launchpad/api/dashboard/${dashboardId}/widgets`
const UPDATE_PLACEMENT_URL = (placementId: number) => `${BASE}/index.php/apps/launchpad/api/widgets/${placementId}`

const MARKER = 'E2E acknowledgement fixture — do not remove'
const PROMPT_TEXT = 'E2E: please confirm you have read this.'

interface Placement {
	id: number
	content?: { text?: string }
	acknowledgementContentVersion?: number
}

/** Build an admin API context (HTTP Basic auth + OCS header). */
async function adminApi(): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Ensure the admin's active dashboard carries a widget placement with
 * `requiresAcknowledgement = 1` for which the admin currently has no
 * receipt. Backs the dashboard-acknowledgements e2e specs (REQ-ACK-002 /
 * REQ-ACK-004). Safe to call in `beforeAll` — idempotent and reuses the
 * marked placement across runs.
 */
export async function ensureOutstandingAcknowledgement(): Promise<void> {
	const api = await adminApi()
	try {
		const activeRes = await api.get(ACTIVE_DASHBOARD_URL)
		if (!activeRes.ok()) {
			throw new Error(`GET /api/dashboard failed: ${activeRes.status()}`)
		}
		const body = await activeRes.json() as { dashboard: { id: number }, placements: Placement[] }
		const dashboardId = body.dashboard.id
		const existing = (body.placements ?? []).find((p) => p.content?.text === MARKER)

		if (!existing) {
			const createRes = await api.post(ADD_WIDGET_URL(dashboardId), {
				data: {
					widgetId: 'label',
					gridX: 0,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 2,
					content: { text: MARKER },
				},
			})
			if (!createRes.ok()) {
				throw new Error(`POST addWidget failed: ${createRes.status()}`)
			}
			const created = await createRes.json() as Placement
			const setRes = await api.put(UPDATE_PLACEMENT_URL(created.id), {
				data: {
					requiresAcknowledgement: 1,
					acknowledgementPrompt: PROMPT_TEXT,
					reacknowledgeOnChange: 1,
				},
			})
			if (!setRes.ok()) {
				throw new Error(`PUT requiresAcknowledgement (create path) failed: ${setRes.status()}`)
			}
			return
		}

		// Reuse the existing marked placement — bump the content version so
		// any prior acknowledgement (from an earlier run) no longer satisfies
		// the gate, guaranteeing the item is outstanding for this run.
		const nextVersion = (existing.acknowledgementContentVersion ?? 0) + 1
		const bumpRes = await api.put(UPDATE_PLACEMENT_URL(existing.id), {
			data: {
				requiresAcknowledgement: 1,
				acknowledgementPrompt: PROMPT_TEXT,
				reacknowledgeOnChange: 1,
				acknowledgementContentVersion: nextVersion,
			},
		})
		if (!bumpRes.ok()) {
			throw new Error(`PUT version bump failed: ${bumpRes.status()}`)
		}
	} finally {
		await api.dispose()
	}
}
