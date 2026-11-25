/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the licence headers of the DEPLOYED
 * LaunchPad (openspec/specs/license-header-consistency), written for
 * e2e-coverage-programme task 5.13.
 *
 * Every scenario was excluded as "reads checked-in files, no running instance
 * serves them". The same argument was made for the appinfo.xml and
 * composer.json checks of 5.8 and turned out not to hold: the app folder on
 * the instance under test is reachable through support/occ.ts
 * (`occ app:getpath launchpad`, then cat/grep there), the way
 * launchpad-adopt-or-abstractions-declared.spec.ts and cli-commands.spec.ts
 * already read it. So these tests judge the headers of the code the instance
 * actually runs, and the regression guard the way an operator or CI would run
 * it: as a script with an exit code and a stderr.
 *
 * Where the deployed folder is a release build without the file a scenario
 * reads (src/, README.md, scripts/), the test skips and says so; the CI
 * instance deploys the checkout as apps/launchpad, so there all of them run.
 *
 * Gate-19 @e2e traceability:
 *   @e2e license-header-consistency::every-php-file-under-lib-declares-eupl-12
 *   @e2e license-header-consistency::frontend-source-headers-declare-eupl-12
 *   @e2e license-header-consistency::phpdoc-license-and-spdx-line-agree
 *   @e2e license-header-consistency::spdx-copyright-matches-reuse-and-phpdoc
 *   @e2e license-header-consistency::badge-and-prose-agree
 *   @e2e license-header-consistency::ci-fails-on-a-reintroduced-agpl-header
 *
 * @spec openspec/specs/license-header-consistency/spec.md
 */

import { expect, test } from '@playwright/test'
import {
	appPathOnInstance,
	describeRun,
	existsOnInstance,
	onInstance,
	readOnInstance,
	writeOnInstance,
} from './support/occ.ts'

/** One `file:line` hit of `grep -rH`. */
interface Hit {
	/** The file, relative to the app folder. */
	file: string
	/** The text of the matching line. */
	line: string
}

/** Licence identifiers other than EUPL-1.2 a header could carry. */
const OTHER_LICENCE = /\b(A?GPL|LGPL|MIT|Apache|BSD|MPL|ISC)(-[\w.+]+)?\b/

/**
 * Every line under `dir` (in the app folder on the instance) that matches the
 * fixed string `needle`, with its file.
 *
 * @param appPath the app folder on the instance.
 * @param dir the folder under it.
 * @param needle the fixed string.
 * @return the hits.
 */
async function grepOnInstance(appPath: string, dir: string, needle: string): Promise<Hit[]> {
	const result = await onInstance('grep', '-rIHF', needle, `${appPath}/${dir}`)
	// grep exits 1 for "no match"; anything above that is a failed run.
	expect(result.code, describeRun(result)).toBeLessThan(2)
	return result.stdout
		.split('\n')
		.filter((raw) => raw.trim() !== '')
		.map((raw) => {
			const at = raw.indexOf(':')
			return { file: raw.slice(appPath.length + 1, at), line: raw.slice(at + 1) }
		})
}

/**
 * The identifier an SPDX line declares, without comment decoration.
 *
 * @param line the header line.
 * @return the identifier.
 */
function identifier(line: string): string {
	return line
		.replace(/^.*SPDX-License-Identifier:\s*/, '')
		.replace(/\s*(\*\/|-->)\s*$/, '')
		.trim()
}

// @e2e license-header-consistency::every-php-file-under-lib-declares-eupl-12
test('every SPDX-License-Identifier under the deployed lib/ reads EUPL-1.2, none AGPL-3.0-or-later', async () => {
	const appPath = await appPathOnInstance()
	const hits = (await grepOnInstance(appPath, 'lib', 'SPDX-License-Identifier:'))
		.filter((hit) => hit.file.endsWith('.php'))

	// Guard against a vacuous pass: the deployed lib/ carries hundreds of them.
	expect(hits.length, 'the deployed lib/ carries SPDX lines').toBeGreaterThan(100)

	const notEupl = hits.filter((hit) => identifier(hit.line) !== 'EUPL-1.2')
	expect(notEupl, 'every SPDX-License-Identifier under lib/ reads EUPL-1.2').toEqual([])
	expect(
		hits.filter((hit) => hit.line.includes('AGPL-3.0')),
		'no SPDX-License-Identifier under lib/ reads AGPL-3.0-or-later',
	).toEqual([])
})

// @e2e license-header-consistency::frontend-source-headers-declare-eupl-12
test('every SPDX-License-Identifier in a deployed src/ Vue/JS/TS/CSS file reads EUPL-1.2', async () => {
	const appPath = await appPathOnInstance()
	test.skip(
		!(await existsOnInstance(`${appPath}/src`)),
		'the deployed app is a release build without src/; the CI instance deploys the checkout',
	)

	const hits = (await grepOnInstance(appPath, 'src', 'SPDX-License-Identifier:'))
		.filter((hit) => /\.(vue|js|ts|css|scss)$/.test(hit.file))
	expect(hits.length, 'the deployed src/ carries SPDX headers').toBeGreaterThan(100)

	const notEupl = hits.filter((hit) => identifier(hit.line) !== 'EUPL-1.2')
	expect(notEupl, 'every src/ SPDX-License-Identifier reads EUPL-1.2').toEqual([])
})

// @e2e license-header-consistency::phpdoc-license-and-spdx-line-agree
test('PermissionService.php: PHPDoc @license EUPL-1.2, SPDX EUPL-1.2, no other licence named', async () => {
	const appPath = await appPathOnInstance()
	const source = await readOnInstance(`${appPath}/lib/Service/PermissionService.php`)

	// GIVEN the PHPDoc @license tag the scenario names.
	expect(source, 'the PHPDoc @license tag reads EUPL-1.2').toMatch(
		/@license\s+EUPL-1\.2 https:\/\/joinup\.ec\.europa\.eu\/collection\/eupl\/eupl-text-eupl-12/,
	)

	// THEN its SPDX-License-Identifier reads EUPL-1.2 (not AGPL-3.0-or-later).
	const spdx = [...source.matchAll(/SPDX-License-Identifier:\s*([^\s*]+)/g)].map((match) => match[1])
	expect(spdx, 'one SPDX-License-Identifier, reading EUPL-1.2').toEqual(['EUPL-1.2'])

	// AND the file contains no other licence identifier: not in a @license tag,
	// not in an SPDX line, not in a licence URL.
	const licenceLines = source
		.split('\n')
		.filter((line) => /@license|SPDX-License-Identifier|licen[cs]e/i.test(line))
	expect(licenceLines.length, 'the header lines are found').toBeGreaterThanOrEqual(2)
	for (const line of licenceLines) {
		expect(line, 'no other licence identifier in the file').not.toMatch(OTHER_LICENCE)
	}
	expect(source, 'no AGPL anywhere in the file').not.toMatch(/AGPL/)
})

// @e2e license-header-consistency::spdx-copyright-matches-reuse-and-phpdoc
test('a deployed PHP file whose PHPDoc names Conduction b.v. has an SPDX copyright naming Conduction B.V., never LaunchPad Contributors', async () => {
	const appPath = await appPathOnInstance()

	// GIVEN the PHP files whose PHPDoc @copyright names Conduction.
	const withPhpdoc = new Set(
		(await grepOnInstance(appPath, 'lib', '@copyright'))
			.filter((hit) => hit.file.endsWith('.php') && /Conduction b\.v\./i.test(hit.line))
			.map((hit) => hit.file),
	)
	expect(withPhpdoc.size, 'the deployed lib/ has PHPDoc @copyright tags').toBeGreaterThan(100)
	expect(withPhpdoc, 'the scenario\'s example file is among them').toContain('lib/Service/PermissionService.php')

	// WHEN their SPDX-FileCopyrightText lines are read.
	const copyright = (await grepOnInstance(appPath, 'lib', 'SPDX-FileCopyrightText:'))
		.filter((hit) => withPhpdoc.has(hit.file))
	expect(copyright.length, 'those files carry SPDX-FileCopyrightText lines').toBeGreaterThan(100)

	// THEN each names Conduction B.V., AND none names LaunchPad Contributors.
	expect(
		copyright.filter((hit) => !/SPDX-FileCopyrightText:\s*(\d{4}(-\d{4})?\s+)?Conduction B\.V\./.test(hit.line)),
		'every SPDX-FileCopyrightText names Conduction B.V.',
	).toEqual([])
	expect(
		copyright.filter((hit) => /LaunchPad Contributors/i.test(hit.line)),
		'no SPDX-FileCopyrightText names LaunchPad Contributors',
	).toEqual([])
})

// @e2e license-header-consistency::badge-and-prose-agree
test('the deployed README: §License prose and the header badge both say EUPL-1.2, the badge not AGPL-3.0', async () => {
	const appPath = await appPathOnInstance()
	const readmePath = `${appPath}/README.md`
	test.skip(
		!(await existsOnInstance(readmePath)),
		'the deployed app is a release build without README.md; the CI instance deploys the checkout',
	)
	const readme = await readOnInstance(readmePath)

	// GIVEN the §License prose.
	const section = readme.split(/\n(?=## )/).find((part) => part.startsWith('## License'))
	expect(section, 'the README has a §License').toBeTruthy()
	expect(section!, 'the §License prose reads "licensed under the [EUPL-1.2]"').toMatch(/licensed under the \[EUPL-1\.2\]/)

	// WHEN the header licence badge is read: the shields.io image whose alt is License.
	const head = readme.slice(0, readme.indexOf('\n## '))
	const badges = [...head.matchAll(/<img[^>]*src="([^"]*shields\.io\/badge\/license-[^"]*)"[^>]*>/gi)].map((match) => match[1])
	expect(badges.length, 'the README header carries one licence badge').toBe(1)

	// THEN it displays EUPL-1.2 (shields.io escapes "-" as "--") AND not AGPL-3.0.
	const label = decodeURIComponent(badges[0].replace(/^.*\/badge\/license-/, '').replace(/-[a-z]+$/i, '')).replace(/--/g, '-')
	expect(label, 'the badge displays EUPL-1.2').toBe('EUPL-1.2')
	expect(badges[0], 'the badge does not display AGPL-3.0').not.toMatch(/AGPL/i)
})

// @e2e license-header-consistency::ci-fails-on-a-reintroduced-agpl-header
test('the licence check fails on an AGPL-3.0 header added under lib/, names the file and EUPL-1.2, and passes without it', async () => {
	const appPath = await appPathOnInstance()
	const script = `${appPath}/scripts/check-license-headers.sh`
	test.skip(
		!(await existsOnInstance(script)),
		'the deployed app is a release build without scripts/; the CI instance deploys the checkout',
	)

	// GIVEN the check CI runs (composer lint:licenses) passes on the deployed tree.
	const clean = await onInstance('bash', script)
	expect(clean.code, describeRun(clean)).toBe(0)
	expect(clean.stdout).toContain('EUPL-1.2 clean')

	// WHEN a commit adds a file with an AGPL SPDX line under lib/: the check is
	// run in a scratch copy of the tree layout, so the deployed app is untouched.
	const mk = await onInstance('mktemp', '-d')
	expect(mk.code, describeRun(mk)).toBe(0)
	const scratch = mk.stdout.trim()
	try {
		const setup = await onInstance(
			'sh', '-c', 'mkdir -p "$1/scripts" "$1/lib/Service" && cp "$2" "$1/scripts/"',
			'sh', scratch, script,
		)
		expect(setup.code, describeRun(setup)).toBe(0)
		await writeOnInstance(
			`${scratch}/lib/Service/GoodService.php`,
			'<?php\n/**\n * SPDX-FileCopyrightText: 2026 Conduction B.V.\n * SPDX-License-Identifier: EUPL-1.2\n */\n',
		)

		// A tree holding only EUPL files passes, so what fails next is the AGPL line.
		const before = await onInstance('bash', `${scratch}/scripts/check-license-headers.sh`)
		expect(before.code, describeRun(before)).toBe(0)

		await writeOnInstance(
			`${scratch}/lib/Service/IntroducedService.php`,
			'<?php\n/**\n * SPDX-FileCopyrightText: 2026 Conduction B.V.\n * SPDX-License-Identifier: AGPL-3.0-or-later\n */\n',
		)
		const after = await onInstance('bash', `${scratch}/scripts/check-license-headers.sh`)

		// THEN the check fails the build.
		expect(after.code, describeRun(after)).not.toBe(0)
		// AND the failure names the offending file and the expected identifier.
		expect(after.stderr, 'the failure names the offending file').toContain('lib/Service/IntroducedService.php')
		expect(after.stderr, 'the failure names the EUPL-1.2 replacement').toContain('SPDX-License-Identifier: EUPL-1.2')
		expect(after.stderr, 'the good file is not named').not.toContain('GoodService.php')
	} finally {
		await onInstance('rm', '-rf', scratch)
	}
})
