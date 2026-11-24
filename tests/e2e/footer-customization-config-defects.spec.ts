/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The AND clause of "Structured config schema validation"
 * (openspec/specs/footer-customization, REQ-FTR-003): the 400 for an unknown
 * `footerConfig` key MUST say which keys are allowed.
 *
 * footer-customization-api.spec.ts asserts the 400, that the message names
 * the offending key, and that nothing is stored. This clause was asserted
 * there only by a `test.fixme`, which gate-19 does not count and which never
 * ran. It runs here, in its own file without serial mode, because the API file
 * runs its describe blocks serially and a red there would skip the rest.
 *
 * EXPECTED RED until the product is fixed: FooterService::assertKnownConfigKeys()
 * throws "footerConfig contains unknown key: <key>", which names the offending
 * key but none of FooterService::STRUCTURED_CONFIG_KEYS.
 *
 * The footer is instance-wide. `beforeAll` snapshots the five footer keys and
 * `afterAll` writes them back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e footer-customization::structured-config-schema-validation
 *
 * @spec openspec/specs/footer-customization/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { FooterSettings } from './support/footerSettings.ts'

import { expect, test } from '@playwright/test'
import {
	FOOTER_SETTINGS,
	readFooter,
	resetFooter,
	restoreFooter,
} from './support/footerSettings.ts'
import { adminContext } from './support/principals.ts'

/** FooterService::STRUCTURED_CONFIG_KEYS, the keys REQ-FTR-003 allows. */
const ALLOWED = [
	'logoUrl',
	'organisation',
	'address',
	'links',
	'legal',
	'copyrightYear',
	'layoutMode',
]

let admin: APIRequestContext
let footerSnapshot: FooterSettings | undefined

test.beforeAll(async () => {
	admin = await adminContext()
	footerSnapshot = await readFooter(admin)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	await restoreFooter(admin, footerSnapshot)
	await admin.dispose()
})

test.describe('REQ-FTR-003 the refusal names the allowed keys', () => {
	// @e2e footer-customization::structured-config-schema-validation
	// EXPECTED RED: the message names only the unknown key.
	test('the 400 for an unknown key lists every allowed key', async () => {
		const stored = { organisation: 'ACME', layoutMode: 'inline' }
		await resetFooter(admin, { footerConfig: stored })

		const refused = await admin.put(FOOTER_SETTINGS, {
			data: { footerConfig: { ...stored, unknownField: 'value' } },
		})
		expect(refused.status(), await refused.text()).toBe(400)
		const message = String((await refused.json()).error)
		expect(message).toContain('unknownField')
		for (const key of ALLOWED) {
			expect(message, `the refusal does not name "${key}"`).toContain(key)
		}
		// Nothing was written.
		expect((await readFooter(admin)).footerConfig).toEqual(stored)
	})
})
