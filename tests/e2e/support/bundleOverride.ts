/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Serve LaunchPad's compiled JavaScript from a local directory instead of from
 * the instance under test.
 *
 * 🔴 WHY THIS EXISTS. A guard test is not finished until you have watched it
 * fail, and the guard this repo needed to prove — the manifest's `permission`
 * field — lives entirely in the BUNDLE: the manifest is compiled in
 * (`import bundledStub from './manifest.json'`), the nav filter runs in
 * `CnAppNav`, and the route guard is installed by `main.js`. There is no
 * runtime switch that turns it off, so the only way to see the assertions
 * redden is to run the same spec against a bundle built without the fix.
 *
 * Doing that by swapping the app's `js/` directory means writing into a
 * checkout that other sessions share, and a build artefact left behind after
 * an interrupted run is indistinguishable from a real regression. Routing the
 * requests instead leaves the instance untouched, and — more importantly —
 * puts BOTH legs of the comparison through the identical mechanism, so
 * anything the interception itself perturbs is common-mode and cancels.
 *
 * INERT UNLESS ASKED. With `LAUNCHPAD_BUNDLE_DIR` unset this installs no
 * route at all, which is how CI and an ordinary local run behave: they test
 * the bundle the instance actually serves. Set it only to run the comparison:
 *
 *   LAUNCHPAD_BUNDLE_DIR=/path/to/js npx playwright test <spec>
 *
 * A request for a file the directory does not hold falls through to the
 * server rather than 404ing, so a lazily-loaded chunk that only exists in the
 * deployed build still resolves.
 */

import type { BrowserContext } from '@playwright/test'

import * as fs from 'fs'
import * as path from 'path'

/** Where LaunchPad's compiled assets are requested from, under any webroot. */
const BUNDLE_URL_GLOB = '**/custom_apps/launchpad/js/**'

/** Content types keyed by the extensions the bundle directory actually holds. */
const CONTENT_TYPES: Record<string, string> = {
	'.js': 'application/javascript; charset=utf-8',
	'.map': 'application/json; charset=utf-8',
	'.txt': 'text/plain; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
}

/**
 * The directory to serve the bundle from, or null when the override is off.
 *
 * Throws rather than silently disabling itself when the variable names a
 * directory that is not there: a comparison run that quietly tested the
 * deployed bundle twice would report two identical results and read as
 * "the fix changes nothing".
 *
 * @return {string|null} An absolute directory path, or null.
 */
export function bundleOverrideDir(): string | null {
	const raw = process.env.LAUNCHPAD_BUNDLE_DIR?.trim()
	if (!raw) {
		return null
	}
	const dir = path.resolve(raw)
	if (!fs.existsSync(path.join(dir, 'launchpad-main.js'))) {
		throw new Error(
			`LAUNCHPAD_BUNDLE_DIR=${raw} holds no launchpad-main.js.\n`
				+ 'Refusing to run: an override that silently did nothing would\n'
				+ 'test the deployed bundle and report it as the one you built.\n',
		)
	}
	return dir
}

/**
 * Install the override on one browser context, if it is switched on.
 *
 * Call it on every context a spec creates — the default `page` fixture's
 * context included — because Playwright routing is per context.
 *
 * @param {BrowserContext} context The context to route.
 * @return {Promise<string|null>} The directory now being served, or null.
 */
export async function installBundleOverride(
	context: BrowserContext,
): Promise<string | null> {
	const dir = bundleOverrideDir()
	if (!dir) {
		return null
	}

	await context.route(BUNDLE_URL_GLOB, async (route) => {
		const name = path.basename(new URL(route.request().url()).pathname)
		const file = path.join(dir, name)

		// `path.basename` already strips any directory part, so a crafted
		// path cannot escape the directory; this only guards a typo.
		if (!file.startsWith(dir + path.sep) || !fs.existsSync(file)) {
			await route.fallback()
			return
		}

		await route.fulfill({
			status: 200,
			contentType: CONTENT_TYPES[path.extname(name)] ?? 'application/octet-stream',
			body: fs.readFileSync(file),
		})
	})

	return dir
}
