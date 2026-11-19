/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Read, write, snapshot and restore the global footer settings
 * (`GET|PUT /api/admin/footer-settings`, appinfo/routes.php) for the
 * footer-customization specs.
 *
 * The footer is instance-wide: one spec that leaves it enabled puts a footer
 * under every other spec's dashboard. So every footer spec takes a snapshot
 * in `beforeAll` and writes it back in `afterAll`, all five keys at once.
 *
 * @spec openspec/specs/footer-customization/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

export const FOOTER_SETTINGS = `${API}/admin/footer-settings`

/** The five keys `GET /api/admin/footer-settings` answers with. */
export const FOOTER_KEYS = [
	'footerEnabled',
	'footerHtml',
	'footerConfig',
	'footerBackgroundColor',
	'footerTextColor',
] as const

export interface FooterSettings {
	footerEnabled: boolean
	footerHtml: string | Record<string, string>
	footerConfig: Record<string, unknown> | unknown[]
	footerBackgroundColor: string | null
	footerTextColor: string | null
}

/** Everything off and empty: the state a fresh installation answers with. */
export const FOOTER_OFF: FooterSettings = {
	footerEnabled: false,
	footerHtml: '',
	footerConfig: [],
	footerBackgroundColor: null,
	footerTextColor: null,
}

/**
 * Read the global footer settings as an administrator.
 *
 * @param api an admin-authenticated request context.
 * @return the five settings.
 */
export async function readFooter(api: APIRequestContext): Promise<FooterSettings> {
	const res = await api.get(FOOTER_SETTINGS)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as FooterSettings
}

/**
 * Patch the global footer settings and insist the write was accepted.
 *
 * @param api an admin-authenticated request context.
 * @param patch any subset of the five keys.
 * @return nothing.
 */
export async function writeFooter(
	api: APIRequestContext,
	patch: Partial<FooterSettings>,
): Promise<void> {
	const res = await api.put(FOOTER_SETTINGS, { data: patch })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Replace all five settings: `FOOTER_OFF` with `patch` laid over it, so a
 * test starts from a known state rather than the previous test's.
 *
 * @param api an admin-authenticated request context.
 * @param patch the keys that differ from everything-off.
 * @return nothing.
 */
export async function resetFooter(
	api: APIRequestContext,
	patch: Partial<FooterSettings> = {},
): Promise<void> {
	await writeFooter(api, { ...FOOTER_OFF, ...patch })
}

/**
 * Write a snapshot from `readFooter` back. Tolerant: it runs in `afterAll`,
 * where a throw would hide the failure that matters.
 *
 * @param api an admin-authenticated request context.
 * @param snapshot the settings to restore, or undefined when none was taken.
 * @return nothing.
 */
export async function restoreFooter(
	api: APIRequestContext,
	snapshot: FooterSettings | undefined,
): Promise<void> {
	if (snapshot === undefined) {
		return
	}
	await api.put(FOOTER_SETTINGS, { data: snapshot })
}
