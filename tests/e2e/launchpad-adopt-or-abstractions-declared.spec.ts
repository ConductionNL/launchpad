/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for what the INSTALLED LaunchPad declares about
 * OpenRegister and OpenConnector (openspec/specs/launchpad-adopt-or-abstractions,
 * REQ "LaunchPad MUST NOT declare an install-time dependency"), written for
 * e2e-coverage-programme task 5.8.
 *
 * Both scenarios were excluded as "reads a checked-in file, no runtime
 * surface". That is not quite so: the instance under test has parsed the
 * app's info.xml and serves it over OCS (`GET /ocs/v1.php/cloud/apps/launchpad`,
 * the same parse Nextcloud's dependency analyser reads when an admin enables
 * the app), and the deployed app folder on the instance is reachable through
 * support/occ.ts, the way cli-commands.spec.ts and the analytics specs already
 * read and write there. So these tests judge the app as it is deployed, not the
 * checkout the runner happens to hold.
 *
 * The runtime consequence (LaunchPad enabling and booting with OpenRegister
 * and OpenConnector absent) stays cited by `install-on-or-less-nextcloud` in
 * or-absent.spec.ts; this file pins the declarations behind it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e launchpad-adopt-or-abstractions::appinfo-xml-dependency-check
 *   @e2e launchpad-adopt-or-abstractions::composer-dependency-check
 *
 * @spec openspec/specs/launchpad-adopt-or-abstractions/spec.md
 */

import { expect, test } from '@playwright/test'
import { apiAs } from './support/filterBus.ts'
import {
	appPathOnInstance,
	describeRun,
	onInstance,
	readOnInstance,
} from './support/occ.ts'

/** The two OR-family app ids LaunchPad must never require. */
const OR_FAMILY = /openregister|openconnector/i

/**
 * Wording that would tell an admin OR/OC is required. The spec allows OR to be
 * mentioned as optional (info.xml's comments and description do), so the test
 * looks for a claim of necessity, not for the name.
 */
const REQUIRED_WORDING = [
	/\b(requires?|required|needs?|depends on)\b[^.\n]{0,40}\b(OpenRegister|OpenConnector)\b/i,
	/\b(OpenRegister|OpenConnector)\b[^.\n]{0,40}\b(is|are|must be)\s+(required|needed|mandatory|installed first)\b/i,
]

/**
 * Every string inside a parsed info.xml value (a localised map, a list or a
 * plain string), flattened so wording checks see all languages.
 *
 * @param value the parsed value.
 * @return its strings.
 */
function strings(value: unknown): string[] {
	if (typeof value === 'string') {
		return [value]
	}
	if (Array.isArray(value)) {
		return value.flatMap(strings)
	}
	if (value !== null && typeof value === 'object') {
		return Object.values(value as Record<string, unknown>).flatMap(strings)
	}
	return []
}

// @e2e launchpad-adopt-or-abstractions::appinfo-xml-dependency-check
test('the installed info.xml declares no OpenRegister or OpenConnector dependency, and no wording calls either required', async () => {
	const admin = await apiAs()
	let info: Record<string, unknown>
	try {
		const res = await admin.get('/ocs/v1.php/cloud/apps/launchpad?format=json')
		expect(res.status(), await res.text()).toBe(200)
		info = (await res.json()).ocs?.data ?? {}
	} finally {
		await admin.dispose()
	}

	// Guard against reading an empty parse: the block must be there and carry
	// the dependencies info.xml really declares.
	expect(info.id, 'the OCS answer is LaunchPad\'s own app info').toBe('launchpad')
	const dependencies = info.dependencies as Record<string, unknown> | undefined
	expect(dependencies, 'info.xml has a <dependencies> block').toBeTruthy()
	expect(Object.keys(dependencies!), 'the block is parsed (it names the nextcloud range)').toContain('nextcloud')

	// THEN no <dependencies><app>openregister|openconnector</app>.
	expect(
		JSON.stringify(dependencies),
		'<dependencies> names no OpenRegister or OpenConnector app',
	).not.toMatch(OR_FAMILY)
	// ... and <types> carries neither (it declares no app at all, but an app id
	// there would be the same mistake).
	expect(JSON.stringify(info.types ?? []), '<types> names neither app').not.toMatch(OR_FAMILY)

	// AND no soft-fail "recommended app" string implies that OR is required:
	// what an admin reads on the apps page is the name, summary and description.
	const prose = [info.name, info.summary, info.description].flatMap(strings)
	expect(prose.length, 'the app info carries its summary and description').toBeGreaterThan(1)
	for (const text of prose) {
		for (const claim of REQUIRED_WORDING) {
			expect(text, `"${claim}" must not match the app's own text`).not.toMatch(claim)
		}
	}

	// The same holds for the file the instance parsed: no <app> element names
	// either app anywhere in the <dependencies> block of the deployed info.xml.
	const xml = await readOnInstance(`${await appPathOnInstance()}/appinfo/info.xml`)
	const block = xml.match(/<dependencies>([\s\S]*?)<\/dependencies>/)
	expect(block, 'the deployed info.xml has a <dependencies> block').not.toBeNull()
	const withoutComments = block![1].replace(/<!--[\s\S]*?-->/g, '')
	expect(withoutComments, 'the deployed <dependencies> block names neither app').not.toMatch(OR_FAMILY)
})

// @e2e launchpad-adopt-or-abstractions::composer-dependency-check
test('the deployed composer.json requires no OpenRegister package, and no class is built on an OR type outside the string-aliased MCP provider', async () => {
	const appPath = await appPathOnInstance()

	// THEN no conduction/openregister* package in require or require-dev.
	const composer = JSON.parse(await readOnInstance(`${appPath}/composer.json`))
	expect(composer.require, 'composer.json has a require block').toBeTruthy()
	for (const block of ['require', 'require-dev']) {
		const packages = Object.keys(composer[block] ?? {})
		expect(
			packages.filter((name) => /^conduction\/(openregister|openconnector)/i.test(name)),
			`${block} names no OpenRegister/OpenConnector package`,
		).toEqual([])
	}
	// LaunchPad's own autoloader maps only its own namespace, so nothing loads
	// an OR class through it.
	expect(
		Object.keys(composer.autoload?.['psr-4'] ?? {}),
		'composer autoloads only OCA\\LaunchPad',
	).toEqual(['OCA\\LaunchPad\\'])

	// AND no class import path resolves through OCA\OpenRegister outside an
	// optional service-locator lookup. A `use` of an OR value object inside a
	// method body is resolved only when that code runs, which happens behind
	// the locator that fails first without OR. What PHP resolves while merely
	// loading a LaunchPad class is its parent and its interfaces, so those are
	// what is checked: a LaunchPad class built on an OR type may exist only if
	// it is reached by a string service alias, never by a class reference the
	// bootstrap autoloads.
	const listing = await onInstance(
		'grep', '-rlE', '^use OCA\\\\(OpenRegister|OpenConnector)\\\\', `${appPath}/lib`,
	)
	// grep exits 1 for "no match"; anything above that is a failed run.
	expect(listing.code, describeRun(listing)).toBeLessThan(2)
	const files = listing.stdout.split('\n').map((line) => line.trim()).filter(Boolean)

	const builtOnOr: string[] = []
	for (const file of files) {
		const source = await readOnInstance(file)
		const imported = [...source.matchAll(/^use OCA\\(?:OpenRegister|OpenConnector)\\[\w\\]*?(\w+)(?:\s+as\s+(\w+))?;/gm)]
			.map((match) => match[2] ?? match[1])
		// The parent and interface list: what follows the declared name, up to the body.
		const declaration = source.match(/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+\w+([^{]*)/m)
		if (declaration === null) {
			continue
		}
		const header = declaration[1]
		const direct = /\\?OCA\\(?:OpenRegister|OpenConnector)\\/.test(header)
		const viaImport = imported.some((name) => new RegExp(`\\b${name}\\b`).test(header))
		if (direct || viaImport) {
			builtOnOr.push(file.slice(appPath.length + 1))
		}
	}

	// Today that is the MCP tool provider, and nothing else.
	expect(builtOnOr, 'the only LaunchPad class built on an OR type').toEqual(['lib/Mcp/LaunchPadToolProvider.php'])

	// It is registered under a string alias, so booting LaunchPad never
	// autoloads OpenRegister's interface; only OpenRegister, when present,
	// resolves it.
	const application = await readOnInstance(`${appPath}/lib/AppInfo/Application.php`)
	expect(
		application,
		'the provider is registered by a string alias, not a class reference to the OR interface',
	).toMatch(/registerServiceAlias\(\s*'OCA\\\\OpenRegister\\\\Mcp\\\\IMcpToolProvider::launchpad',\s*LaunchPadToolProvider::class/)
	expect(application, 'Application imports no OR type').not.toMatch(/^use OCA\\(OpenRegister|OpenConnector)\\/m)
})
