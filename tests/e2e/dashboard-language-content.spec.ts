/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the per-language dashboard content API
 * (openspec/specs/dashboard-language-content, REQ-DASH-038..043), written for
 * the e2e-coverage-programme.
 *
 * Every scenario here IS an HTTP contract (status codes, envelopes, which
 * variant is returned), so the tests drive the real translation routes in
 * appinfo/routes.php as real, signed-in principals over basic auth:
 *
 *   - `owner`    a throwaway account that owns every dashboard below
 *                ("alice" in the scenarios);
 *   - `stranger` a throwaway ordinary account with no share ("bob"), the
 *                least privileged principal a cross-user refusal is about.
 *
 * The viewer's language is set the way Nextcloud stores it, through the OCS
 * provisioning API (`PUT /ocs/v2.php/cloud/users/<id>`, keys `language` and
 * `locale`). The product reads `core/lang` for the owner's seed language
 * (DashboardTranslationService::resolveOwnerLocale).
 *
 * The scenarios name `GET /api/dashboards/{uuid}` as the read endpoint. No
 * such route exists; the resolver the scenarios describe is
 * `GET /api/dashboards/{uuid}/resolved` (appinfo/routes.php, the
 * `dashboardTranslationApi#resolved` route), which is what the workspace page
 * itself calls (src/services/api.js getResolvedDashboard). The tests use it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-language-content::primary-variant-enforcement
 *   @e2e dashboard-language-content::variant-isolation-from-dashboard-record
 *   @e2e dashboard-language-content::exact-language-match
 *   @e2e dashboard-language-content::language-part-match-prefix-fallback
 *   @e2e dashboard-language-content::primary-fallback-when-no-locale-match
 *   @e2e dashboard-language-content::explicit-language-override-via-query-parameter
 *   @e2e dashboard-language-content::explicit-lang-parameter-with-no-matching-variant-returns-404
 *   @e2e dashboard-language-content::available-languages-list
 *   @e2e dashboard-language-content::create-a-new-translation-variant
 *   @e2e dashboard-language-content::create-variant-without-copyfrom-defaults-to-primary
 *   @e2e dashboard-language-content::create-variant-by-copying-from-non-primary-variant
 *   @e2e dashboard-language-content::duplicate-language-returns-conflict-error
 *   @e2e dashboard-language-content::cross-user-access-is-rejected
 *   @e2e dashboard-language-content::update-translation-name-and-description
 *   @e2e dashboard-language-content::update-translation-widget-tree
 *   @e2e dashboard-language-content::cross-user-update-is-rejected
 *   @e2e dashboard-language-content::update-non-existent-variant-returns-404
 *   @e2e dashboard-language-content::partial-updates-are-supported
 *   @e2e dashboard-language-content::delete-a-non-primary-variant
 *   @e2e dashboard-language-content::delete-when-multiple-variants-remain
 *   @e2e dashboard-language-content::cannot-delete-the-only-remaining-variant
 *   @e2e dashboard-language-content::delete-primary-variant-when-others-exist
 *   @e2e dashboard-language-content::cross-user-delete-is-rejected
 *   @e2e dashboard-language-content::promote-a-secondary-variant
 *   @e2e dashboard-language-content::promote-idempotent-when-already-primary
 *   @e2e dashboard-language-content::only-one-variant-can-be-primary
 *   @e2e dashboard-language-content::cross-user-promotion-is-rejected
 *   @e2e dashboard-language-content::promote-non-existent-variant-returns-404
 *   @e2e action-authorization::the-owner-may-manage-their-own-dashboards-language-versions
 *
 * Four tests are EXPECTED RED until the product is fixed. They were
 * `test.fixme`, so although gate-19 counted them through the list above, no
 * test of theirs ever executed. They now run; this file is not serial, so a
 * red one does not skip the tests after it.
 *   - exact-language-match and language-part-match-prefix-fallback:
 *     DashboardTranslationApiController::resolveLocaleVariant() passes
 *     `preferredLanguage: ''`, so the reader's language is never read and
 *     every request without `?lang=` gets the primary as a fallback.
 *   - available-languages-list: DashboardTranslationMapper::
 *     normaliseLanguageCode() collapses `de-DE` to `de` on write.
 *   - cannot-delete-the-only-remaining-variant: DashboardTranslationService::
 *     deleteVariant() refuses the primary before it counts, and the only
 *     variant is always the primary, so the last-variant message is never
 *     sent.
 *
 * @spec openspec/specs/dashboard-language-content/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()

type Variant = {
	id: number
	dashboardUuid: string
	languageCode: string
	name: string | null
	description: string | null
	widgetTreeJson: string | null
	isPrimary: number
	createdAt: string
	updatedAt: string
}

type Dashboard = { id: number; uuid: string; name: string; description: string }

let admin: APIRequestContext
let owner: Principal | null = null
let stranger: Principal | null = null
let allowBefore: boolean | null = null
const seeded: Dashboard[] = []

/**
 * Set a user's Nextcloud language (and optionally locale) the way the
 * personal settings page stores it: through the provisioning API.
 *
 * @param username the account.
 * @param language a language Nextcloud ships (`en`, `nl`, `de_DE`, `fr` ...).
 * @param locale an optional Nextcloud locale (`nl_BE`, `de_DE` ...).
 * @return nothing; fails the test when Nextcloud refuses the value.
 */
async function setLanguage(
	username: string,
	language: string,
	locale?: string,
): Promise<void> {
	const url = `/ocs/v2.php/cloud/users/${encodeURIComponent(username)}?format=json`
	const lang = await admin.put(url, { form: { key: 'language', value: language } })
	expect(lang.status(), `language=${language}: ${await lang.text()}`).toBe(200)
	if (locale !== undefined) {
		const loc = await admin.put(url, { form: { key: 'locale', value: locale } })
		expect(loc.status(), `locale=${locale}: ${await loc.text()}`).toBe(200)
	}
}

/**
 * Create a personal dashboard as the owner. Its primary translation is
 * seeded by the product in the owner's language.
 *
 * @param label a readable label for the name.
 * @return the dashboard.
 */
async function newDashboard(label: string): Promise<Dashboard> {
	const name = `E2E Lang ${label} ${RUN}`
	const description = `Description of ${label}`
	const res = await owner!.api.post(`${API}/dashboard`, {
		data: { name, description },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body
	const made = { id: Number(dash.id), uuid: String(dash.uuid), name, description }
	expect(made.id, JSON.stringify(body)).toBeTruthy()
	seeded.push(made)
	return made
}

/**
 * The variants of a dashboard, read back as the owner.
 *
 * @param uuid the dashboard.
 * @return every variant.
 */
async function variants(uuid: string): Promise<Variant[]> {
	const res = await owner!.api.get(`${API}/dashboards/${uuid}/translations`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).translations as Variant[]
}

/**
 * One variant by language code.
 *
 * @param list the variants.
 * @param code the language code.
 * @return the variant, or undefined.
 */
function byCode(list: Variant[], code: string): Variant | undefined {
	return list.find((v) => v.languageCode === code)
}

/**
 * The codes of the primary variants in a list.
 *
 * @param list the variants.
 * @return the primary codes.
 */
function primaries(list: Variant[]): string[] {
	return list.filter((v) => Number(v.isPrimary) === 1).map((v) => v.languageCode)
}

/**
 * Create a variant as the owner and expect 201.
 *
 * @param uuid the dashboard.
 * @param body the request body.
 * @return the created variant.
 */
async function addVariant(
	uuid: string,
	body: Record<string, string>,
): Promise<Variant> {
	const res = await owner!.api.post(`${API}/dashboards/${uuid}/translations`, {
		data: body,
	})
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()).translation as Variant
}

/**
 * Update a variant as the owner and expect 200.
 *
 * @param uuid the dashboard.
 * @param code the language code.
 * @param body the patch.
 * @return the updated variant.
 */
async function patchVariant(
	uuid: string,
	code: string,
	body: Record<string, string>,
): Promise<Variant> {
	const res = await owner!.api.put(
		`${API}/dashboards/${uuid}/translations/${code}`,
		{
			data: body,
		},
	)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).translation as Variant
}

/**
 * Ask the resolver, as the owner, for the dashboard in the viewer's language.
 *
 * @param uuid the dashboard.
 * @param lang an explicit `?lang=`, or none.
 * @return the response status and body.
 */
async function resolve(
	uuid: string,
	lang?: string,
): Promise<{ status: number; body: Record<string, unknown> }> {
	const query = lang === undefined ? '' : `?lang=${encodeURIComponent(lang)}`
	const res = await owner!.api.get(`${API}/dashboards/${uuid}/resolved${query}`)
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * A widget tree distinct per caller, as a JSON string (the column is TEXT).
 *
 * @param tag a marker that ends up inside the tree.
 * @return the tree.
 */
function tree(tag: string): string {
	return JSON.stringify({
		widgets: [{ widgetId: 'text', content: { text: tag } }],
	})
}

test.beforeAll(async () => {
	admin = await adminContext()

	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	allowBefore = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	owner = await createPrincipal(admin, 'e2e-lang-owner')
	stranger = await createPrincipal(admin, 'e2e-lang-stranger')
	await assertActingAs(owner.api, owner.username)
	await assertActingAs(stranger.api, stranger.username)
	// Every dashboard below is created in English, so its seeded primary is `en`.
	await setLanguage(owner.username, 'en')
})

test.afterAll(async () => {
	for (const dash of seeded) {
		await owner?.api.delete(`${API}/dashboard/${dash.id}`)
	}
	await removePrincipal(admin, owner)
	await removePrincipal(admin, stranger)
	if (allowBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: allowBefore },
		})
	}
	await admin.dispose()
})

test.describe('REQ-DASH-038 translation schema', () => {
	test('exactly one variant is primary through create and promote', async () => {
		// @e2e dashboard-language-content::primary-variant-enforcement
		const dash = await newDashboard('Primary')

		// A new dashboard carries exactly one variant, seeded as primary.
		const seededList = await variants(dash.uuid)
		expect(seededList.map((v) => v.languageCode)).toEqual(['en'])
		expect(primaries(seededList)).toEqual(['en'])

		await addVariant(dash.uuid, { languageCode: 'nl' })
		await addVariant(dash.uuid, { languageCode: 'de' })
		expect(primaries(await variants(dash.uuid))).toEqual(['en'])

		for (const code of ['de', 'nl', 'en']) {
			const res = await owner!.api.post(
				`${API}/dashboards/${dash.uuid}/translations/${code}/set-primary`,
			)
			expect(res.status(), await res.text()).toBe(200)
			const list = await variants(dash.uuid)
			expect(list).toHaveLength(3)
			expect(primaries(list)).toEqual([code])
		}
	})

	test('editing the dashboard record leaves its translations alone', async () => {
		// @e2e dashboard-language-content::variant-isolation-from-dashboard-record
		const dash = await newDashboard('Isolation')
		const original = await patchVariant(dash.uuid, 'en', {
			widgetTreeJson: tree('isolation'),
		})

		const renamed = await owner!.api.put(`${API}/dashboard/${dash.id}`, {
			data: {
				name: `${dash.name} renamed`,
				description: 'Record description changed',
			},
		})
		expect(renamed.status(), await renamed.text()).toBe(200)
		// The dashboard's own widget tree is its placements; change that too.
		const widget = await owner!.api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { text: 'placed on the record', contentMode: 'markdown' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)

		const after = byCode(await variants(dash.uuid), 'en')!
		expect(after.name).toBe(dash.name)
		expect(after.description).toBe(dash.description)
		expect(after.widgetTreeJson).toBe(tree('isolation'))
		expect(after.updatedAt).toBe(original.updatedAt)

		// Reads take their content from the translation, not from the record.
		const resolved = await resolve(dash.uuid)
		expect(resolved.status).toBe(200)
		expect((resolved.body.dashboard as Record<string, unknown>).name).toBe(
			`${dash.name} renamed`,
		)
		const translation = resolved.body.translation as Variant
		expect(translation.name).toBe(dash.name)
		expect(translation.description).toBe(dash.description)
		expect(translation.widgetTreeJson).toBe(tree('isolation'))
	})
})

test.describe('REQ-DASH-039 locale resolution and primary fallback', () => {
	// EXPECTED RED: the resolver never reads the viewer's language.
	// lib/Controller/DashboardTranslationApiController.php:527-531
	// (resolveLocaleVariant) calls resolveForLocale(preferredLanguage: ''), so
	// the exact match in DashboardTranslationService::exactVariant always
	// misses and every request without `?lang=` gets the primary with
	// `isFallback: true`, whatever the reader's Nextcloud language. Also,
	// every stored code is collapsed to its base (`de-DE` -> `de`,
	// DashboardTranslationMapper::normaliseLanguageCode), so
	// `currentLanguage: 'de-DE'` can never be answered either.
	test('a reader in de-DE gets the de-DE variant', async () => {
		// @e2e dashboard-language-content::exact-language-match
		const dash = await newDashboard('Exact')
		await addVariant(dash.uuid, { languageCode: 'nl', name: 'Exact NL' })
		await addVariant(dash.uuid, { languageCode: 'de-DE', name: 'Exact DE' })
		await setLanguage(owner!.username, 'de_DE', 'de_DE')
		try {
			const { status, body } = await resolve(dash.uuid)
			expect(status).toBe(200)
			expect((body.translation as Variant).name).toBe('Exact DE')
			expect(body.currentLanguage).toBe('de-DE')
			expect(body.isFallback).toBe(false)
		} finally {
			await setLanguage(owner!.username, 'en', 'en_US')
		}
	})

	// EXPECTED RED: see above. Nextcloud 32 ships no `nl_BE` LANGUAGE, so
	// the Belgian reader is a Dutch-language account with the `nl_BE` locale.
	test('a reader in nl-BE gets the nl variant by its language part', async () => {
		// @e2e dashboard-language-content::language-part-match-prefix-fallback
		const dash = await newDashboard('Prefix')
		await addVariant(dash.uuid, { languageCode: 'nl', name: 'Prefix NL' })
		await setLanguage(owner!.username, 'nl', 'nl_BE')
		try {
			const { status, body } = await resolve(dash.uuid)
			expect(status).toBe(200)
			expect((body.translation as Variant).name).toBe('Prefix NL')
			expect(body.currentLanguage).toBe('nl')
			expect(body.isFallback).toBe(false)
		} finally {
			await setLanguage(owner!.username, 'en', 'en_US')
		}
	})

	test('a reader in French gets the English primary, marked as fallback', async () => {
		// @e2e dashboard-language-content::primary-fallback-when-no-locale-match
		const dash = await newDashboard('Fallback')
		await addVariant(dash.uuid, { languageCode: 'nl', name: 'Fallback NL' })
		await addVariant(dash.uuid, { languageCode: 'de', name: 'Fallback DE' })
		expect(primaries(await variants(dash.uuid))).toEqual(['en'])
		await setLanguage(owner!.username, 'fr', 'fr_FR')
		try {
			const { status, body } = await resolve(dash.uuid)
			expect(status).toBe(200)
			const translation = body.translation as Variant
			expect(translation.languageCode).toBe('en')
			expect(translation.name).toBe(dash.name)
			expect(body.currentLanguage).toBe('en')
			expect(body.isFallback).toBe(true)
		} finally {
			await setLanguage(owner!.username, 'en', 'en_US')
		}
	})

	test('?lang=de returns the German variant for an English reader', async () => {
		// @e2e dashboard-language-content::explicit-language-override-via-query-parameter
		const dash = await newDashboard('Override')
		await addVariant(dash.uuid, { languageCode: 'nl', name: 'Override NL' })
		await addVariant(dash.uuid, { languageCode: 'de', name: 'Override DE' })

		const { status, body } = await resolve(dash.uuid, 'de')
		expect(status).toBe(200)
		const translation = body.translation as Variant
		expect(translation.languageCode).toBe('de')
		expect(translation.name).toBe('Override DE')
		expect(body.currentLanguage).toBe('de')
		expect(body.isFallback).toBe(false)
	})

	test('?lang=ja with no Japanese variant is 404, not the primary', async () => {
		// @e2e dashboard-language-content::explicit-lang-parameter-with-no-matching-variant-returns-404
		const dash = await newDashboard('Strict')
		await addVariant(dash.uuid, { languageCode: 'nl' })

		const { status, body } = await resolve(dash.uuid, 'ja')
		expect(status).toBe(404)
		expect(body.error).toBe('language_not_available')
		expect(body).not.toHaveProperty('translation')
		expect(body).not.toHaveProperty('currentLanguage')

		// The same dashboard does resolve, so the 404 is about the language.
		expect((await resolve(dash.uuid)).status).toBe(200)
	})

	// EXPECTED RED (or a scenario at odds with its own spec): every stored
	// code is collapsed to its two-letter base on write
	// (lib/Service/DashboardTranslationService.php:303 unusedLanguageCode via
	// DashboardTranslationMapper::normaliseLanguageCode,
	// lib/Db/DashboardTranslationMapper.php:66-78), so `de-DE` is listed as
	// `de` and the list reads ['de', 'en', 'fr', 'nl'].
	test('availableLanguages lists every variant, sorted', async () => {
		// @e2e dashboard-language-content::available-languages-list
		const dash = await newDashboard('Available')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		await addVariant(dash.uuid, { languageCode: 'fr' })
		await addVariant(dash.uuid, { languageCode: 'de-DE' })

		const { status, body } = await resolve(dash.uuid)
		expect(status).toBe(200)
		expect(body.availableLanguages).toEqual(['de-DE', 'en', 'fr', 'nl'])
	})
})

test.describe('REQ-DASH-040 create translation variant', () => {
	test('POST creates nl with its own name and the primary tree', async () => {
		// @e2e dashboard-language-content::create-a-new-translation-variant
		const dash = await newDashboard('Create')
		await patchVariant(dash.uuid, 'en', {
			widgetTreeJson: tree('primary-create'),
		})

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations`,
			{
				data: {
					languageCode: 'nl',
					name: 'Mijn Dashboard',
					description: 'Beschrijving',
					copyFrom: 'primary',
				},
			},
		)
		expect(res.status(), await res.text()).toBe(201)
		const made = (await res.json()).translation as Variant
		expect(made).toMatchObject({
			dashboardUuid: dash.uuid,
			languageCode: 'nl',
			name: 'Mijn Dashboard',
			description: 'Beschrijving',
			widgetTreeJson: tree('primary-create'),
		})
		expect(Number(made.isPrimary)).toBe(0)
		expect(made.id).toBeGreaterThan(0)

		const stored = byCode(await variants(dash.uuid), 'nl')!
		expect(stored).toMatchObject({
			id: made.id,
			name: 'Mijn Dashboard',
			description: 'Beschrijving',
			widgetTreeJson: tree('primary-create'),
		})
		expect(Number(stored.isPrimary)).toBe(0)
	})

	test('POST with only a language copies everything from the primary', async () => {
		// @e2e dashboard-language-content::create-variant-without-copyfrom-defaults-to-primary
		const dash = await newDashboard('Default copy')
		await patchVariant(dash.uuid, 'en', {
			widgetTreeJson: tree('primary-default'),
		})

		const de = await addVariant(dash.uuid, { languageCode: 'de' })
		expect(de).toMatchObject({
			languageCode: 'de',
			name: dash.name,
			description: dash.description,
			widgetTreeJson: tree('primary-default'),
		})
		expect(Number(de.isPrimary)).toBe(0)

		// The optional name and description override the copy in the same request.
		const fr = await addVariant(dash.uuid, {
			languageCode: 'fr',
			name: 'Mon tableau',
		})
		expect(fr).toMatchObject({
			languageCode: 'fr',
			name: 'Mon tableau',
			description: dash.description,
			widgetTreeJson: tree('primary-default'),
		})
		const italian = await addVariant(dash.uuid, {
			languageCode: 'it',
			description: 'Descrizione',
		})
		expect(italian).toMatchObject({
			name: dash.name,
			description: 'Descrizione',
		})

		const list = await variants(dash.uuid)
		expect(byCode(list, 'de')).toMatchObject({
			name: dash.name,
			widgetTreeJson: tree('primary-default'),
		})
		expect(byCode(list, 'fr')).toMatchObject({ name: 'Mon tableau' })
		expect(primaries(list)).toEqual(['en'])
	})

	test('copyFrom nl seeds the new variant from nl, not the primary', async () => {
		// @e2e dashboard-language-content::create-variant-by-copying-from-non-primary-variant
		const dash = await newDashboard('Copy nl')
		await patchVariant(dash.uuid, 'en', { widgetTreeJson: tree('primary-en') })
		await addVariant(dash.uuid, {
			languageCode: 'nl',
			name: 'Bron NL',
			description: 'Beschrijving NL',
			widgetTreeJson: tree('source-nl'),
		})

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations`,
			{
				data: { languageCode: 'de', copyFrom: 'nl' },
			},
		)
		expect(res.status(), await res.text()).toBe(201)
		const de = (await res.json()).translation as Variant
		expect(de).toMatchObject({
			languageCode: 'de',
			name: 'Bron NL',
			description: 'Beschrijving NL',
			widgetTreeJson: tree('source-nl'),
		})
		expect(byCode(await variants(dash.uuid), 'de')).toMatchObject({
			name: 'Bron NL',
			widgetTreeJson: tree('source-nl'),
		})
	})

	test('a second en variant is refused with 409 and a message', async () => {
		// @e2e dashboard-language-content::duplicate-language-returns-conflict-error
		const dash = await newDashboard('Duplicate')
		const before = await variants(dash.uuid)

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations`,
			{
				data: { languageCode: 'en' },
			},
		)
		expect(res.status(), await res.text()).toBe(409)
		const body = await res.json()
		expect(body.error).toBe('language_exists')
		// The owner reads English, so the message is the English one.
		expect(body.message).toBe('Language variant already exists')

		expect(await variants(dash.uuid)).toEqual(before)
	})

	test('a stranger cannot create a variant; the owner can', async () => {
		// @e2e dashboard-language-content::cross-user-access-is-rejected
		// @e2e action-authorization::the-owner-may-manage-their-own-dashboards-language-versions
		const dash = await newDashboard('Cross create')
		const url = `${API}/dashboards/${dash.uuid}/translations`

		const refused = await stranger!.api.post(url, {
			data: { languageCode: 'nl' },
		})
		expect(refused.status(), await refused.text()).toBe(403)
		expect((await variants(dash.uuid)).map((v) => v.languageCode)).toEqual([
			'en',
		])

		const allowed = await owner!.api.post(url, { data: { languageCode: 'nl' } })
		expect(allowed.status(), await allowed.text()).toBe(201)
		expect(
			(await variants(dash.uuid)).map((v) => v.languageCode).sort(),
		).toEqual(['en', 'nl'])
	})
})

test.describe('REQ-DASH-041 update translation variant', () => {
	test('PUT nl updates name, description and updatedAt', async () => {
		// @e2e dashboard-language-content::update-translation-name-and-description
		const dash = await newDashboard('Update')
		const nl = await addVariant(dash.uuid, {
			languageCode: 'nl',
			name: 'Oud',
			description: 'Oude tekst',
		})
		// `updatedAt` has one-second resolution; let a second pass.
		await new Promise((resolve) => setTimeout(resolve, 1_100))

		const res = await owner!.api.put(
			`${API}/dashboards/${dash.uuid}/translations/nl`,
			{
				data: { name: 'Updated Name', description: 'Updated desc' },
			},
		)
		expect(res.status(), await res.text()).toBe(200)
		const updated = (await res.json()).translation as Variant
		expect(updated).toMatchObject({
			id: nl.id,
			languageCode: 'nl',
			name: 'Updated Name',
			description: 'Updated desc',
		})
		expect(
			updated.updatedAt > nl.updatedAt,
			`${updated.updatedAt} after ${nl.updatedAt}`,
		).toBe(true)
		// "the current timestamp": the server stamps UTC; compare with its own clock.
		const serverNow = Date.parse(res.headers().date)
		const stamped = Date.parse(`${updated.updatedAt.replace(' ', 'T')}Z`)
		expect(Math.abs(serverNow - stamped)).toBeLessThan(120_000)

		expect(byCode(await variants(dash.uuid), 'nl')).toMatchObject({
			name: 'Updated Name',
			description: 'Updated desc',
			updatedAt: updated.updatedAt,
		})
	})

	test('PUT en with a widget tree replaces the whole tree', async () => {
		// @e2e dashboard-language-content::update-translation-widget-tree
		const dash = await newDashboard('Tree')
		await patchVariant(dash.uuid, 'en', { widgetTreeJson: tree('first') })

		const replacement = JSON.stringify({ widgets: [], layout: 'replaced' })
		const res = await owner!.api.put(
			`${API}/dashboards/${dash.uuid}/translations/en`,
			{
				data: { widgetTreeJson: replacement },
			},
		)
		expect(res.status(), await res.text()).toBe(200)
		expect(((await res.json()).translation as Variant).widgetTreeJson).toBe(
			replacement,
		)
		expect(byCode(await variants(dash.uuid), 'en')!.widgetTreeJson).toBe(
			replacement,
		)
	})

	test('a stranger cannot update a variant; the owner can', async () => {
		// @e2e dashboard-language-content::cross-user-update-is-rejected
		const dash = await newDashboard('Cross update')
		const url = `${API}/dashboards/${dash.uuid}/translations/en`

		const refused = await stranger!.api.put(url, { data: { name: 'Hijacked' } })
		expect(refused.status(), await refused.text()).toBe(403)
		expect(byCode(await variants(dash.uuid), 'en')!.name).toBe(dash.name)

		const allowed = await owner!.api.put(url, {
			data: { name: 'Owner renamed' },
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect(byCode(await variants(dash.uuid), 'en')!.name).toBe('Owner renamed')
	})

	test('PUT de on a dashboard without de is 404', async () => {
		// @e2e dashboard-language-content::update-non-existent-variant-returns-404
		const dash = await newDashboard('Missing update')
		await addVariant(dash.uuid, { languageCode: 'nl' })

		const res = await owner!.api.put(
			`${API}/dashboards/${dash.uuid}/translations/de`,
			{
				data: { name: 'Neu' },
			},
		)
		expect(res.status(), await res.text()).toBe(404)
		expect(
			(await variants(dash.uuid)).map((v) => v.languageCode).sort(),
		).toEqual(['en', 'nl'])
	})

	test('PUT with only a name leaves description and tree unchanged', async () => {
		// @e2e dashboard-language-content::partial-updates-are-supported
		const dash = await newDashboard('Partial')
		await addVariant(dash.uuid, {
			languageCode: 'nl',
			name: 'Original Name',
			description: 'Original desc',
			widgetTreeJson: tree('partial'),
		})

		const updated = await patchVariant(dash.uuid, 'nl', { name: 'New Name' })
		expect(updated).toMatchObject({
			name: 'New Name',
			description: 'Original desc',
			widgetTreeJson: tree('partial'),
		})
		expect(byCode(await variants(dash.uuid), 'nl')).toMatchObject({
			name: 'New Name',
			description: 'Original desc',
			widgetTreeJson: tree('partial'),
		})
	})
})

test.describe('REQ-DASH-042 delete translation variant', () => {
	test('DELETE nl removes the secondary variant', async () => {
		// @e2e dashboard-language-content::delete-a-non-primary-variant
		const dash = await newDashboard('Delete')
		await addVariant(dash.uuid, { languageCode: 'nl' })

		const res = await owner!.api.delete(
			`${API}/dashboards/${dash.uuid}/translations/nl`,
		)
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).status).toBe('ok')
		expect((await variants(dash.uuid)).map((v) => v.languageCode)).toEqual([
			'en',
		])
	})

	test('DELETE nl with en and de left keeps the primary readable', async () => {
		// @e2e dashboard-language-content::delete-when-multiple-variants-remain
		const dash = await newDashboard('Delete many')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		await addVariant(dash.uuid, { languageCode: 'de' })

		const res = await owner!.api.delete(
			`${API}/dashboards/${dash.uuid}/translations/nl`,
		)
		expect(res.status(), await res.text()).toBe(200)

		const list = await variants(dash.uuid)
		expect(list.map((v) => v.languageCode).sort()).toEqual(['de', 'en'])
		expect(primaries(list)).toEqual(['en'])
		const { status, body } = await resolve(dash.uuid, 'en')
		expect(status).toBe(200)
		expect((body.translation as Variant).name).toBe(dash.name)
		expect(Number((body.translation as Variant).isPrimary)).toBe(1)
	})

	// EXPECTED RED: the last-variant guard is unreachable.
	// DashboardTranslationService::deleteVariant
	// (lib/Service/DashboardTranslationService.php:426, before the count at
	// :430) refuses the primary FIRST, and the only
	// remaining variant is always the primary, so this request answers
	// `primary_variant` / "Cannot delete the primary variant; promote another
	// variant first". The message the scenario names (ERR_LAST_VARIANT, which
	// also lacks its second sentence) is never sent.
	test('the only variant cannot be deleted', async () => {
		// @e2e dashboard-language-content::cannot-delete-the-only-remaining-variant
		const dash = await newDashboard('Delete last')

		const res = await owner!.api.delete(
			`${API}/dashboards/${dash.uuid}/translations/en`,
		)
		expect(res.status(), await res.text()).toBe(400)
		expect((await res.json()).message).toBe(
			'Cannot delete the only language variant. Promote another variant to primary first.',
		)
		expect((await variants(dash.uuid)).map((v) => v.languageCode)).toEqual([
			'en',
		])
	})

	test('the primary cannot be deleted while others exist', async () => {
		// @e2e dashboard-language-content::delete-primary-variant-when-others-exist
		const dash = await newDashboard('Delete primary')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		await addVariant(dash.uuid, { languageCode: 'de' })

		const res = await owner!.api.delete(
			`${API}/dashboards/${dash.uuid}/translations/en`,
		)
		expect(res.status(), await res.text()).toBe(400)
		const body = await res.json()
		expect(body.error).toBe('primary_variant')
		// "MUST indicate" the primary cannot go and another must be promoted first.
		expect(body.message).toMatch(/^Cannot delete the primary variant/)
		expect(body.message).toMatch(/promote another variant first/i)

		const list = await variants(dash.uuid)
		expect(list.map((v) => v.languageCode).sort()).toEqual(['de', 'en', 'nl'])
		expect(primaries(list)).toEqual(['en'])
	})

	test('a stranger cannot delete a variant; the owner can', async () => {
		// @e2e dashboard-language-content::cross-user-delete-is-rejected
		const dash = await newDashboard('Cross delete')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		const url = `${API}/dashboards/${dash.uuid}/translations/nl`

		const refused = await stranger!.api.delete(url)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(
			(await variants(dash.uuid)).map((v) => v.languageCode).sort(),
		).toEqual(['en', 'nl'])

		const allowed = await owner!.api.delete(url)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect((await variants(dash.uuid)).map((v) => v.languageCode)).toEqual([
			'en',
		])
	})
})

test.describe('REQ-DASH-043 promote variant to primary', () => {
	test('set-primary on nl makes nl primary and en secondary', async () => {
		// @e2e dashboard-language-content::promote-a-secondary-variant
		const dash = await newDashboard('Promote')
		const nl = await addVariant(dash.uuid, {
			languageCode: 'nl',
			name: 'Promoveer',
		})

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations/nl/set-primary`,
		)
		expect(res.status(), await res.text()).toBe(200)
		const promoted = (await res.json()).translation as Variant
		expect(promoted).toMatchObject({
			id: nl.id,
			languageCode: 'nl',
			name: 'Promoveer',
		})
		expect(Number(promoted.isPrimary)).toBe(1)

		const list = await variants(dash.uuid)
		expect(Number(byCode(list, 'nl')!.isPrimary)).toBe(1)
		expect(Number(byCode(list, 'en')!.isPrimary)).toBe(0)
	})

	test('set-primary on the primary is a 200 no-op', async () => {
		// @e2e dashboard-language-content::promote-idempotent-when-already-primary
		const dash = await newDashboard('Idempotent')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		const before = await variants(dash.uuid)
		await new Promise((resolve) => setTimeout(resolve, 1_100))

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations/en/set-primary`,
		)
		expect(res.status(), await res.text()).toBe(200)
		const body = (await res.json()).translation as Variant
		expect(body.languageCode).toBe('en')
		expect(Number(body.isPrimary)).toBe(1)

		// Nothing changed, not even a timestamp.
		expect(await variants(dash.uuid)).toEqual(before)
	})

	test('promoting de leaves exactly one primary', async () => {
		// @e2e dashboard-language-content::only-one-variant-can-be-primary
		const dash = await newDashboard('One primary')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		await addVariant(dash.uuid, { languageCode: 'de' })

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations/de/set-primary`,
		)
		expect(res.status(), await res.text()).toBe(200)

		const list = await variants(dash.uuid)
		expect(Number(byCode(list, 'de')!.isPrimary)).toBe(1)
		expect(Number(byCode(list, 'en')!.isPrimary)).toBe(0)
		expect(Number(byCode(list, 'nl')!.isPrimary)).toBe(0)
		expect(primaries(list)).toEqual(['de'])
	})

	test('a stranger cannot promote a variant; the owner can', async () => {
		// @e2e dashboard-language-content::cross-user-promotion-is-rejected
		const dash = await newDashboard('Cross promote')
		await addVariant(dash.uuid, { languageCode: 'nl' })
		const url = `${API}/dashboards/${dash.uuid}/translations/nl/set-primary`

		const refused = await stranger!.api.post(url)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(primaries(await variants(dash.uuid))).toEqual(['en'])

		const allowed = await owner!.api.post(url)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect(primaries(await variants(dash.uuid))).toEqual(['nl'])
	})

	test('set-primary on a missing ja variant is 404', async () => {
		// @e2e dashboard-language-content::promote-non-existent-variant-returns-404
		const dash = await newDashboard('Promote missing')

		const res = await owner!.api.post(
			`${API}/dashboards/${dash.uuid}/translations/ja/set-primary`,
		)
		expect(res.status(), await res.text()).toBe(404)
		expect(primaries(await variants(dash.uuid))).toEqual(['en'])
	})
})
