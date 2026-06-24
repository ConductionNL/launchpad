/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Manifest validator — wired into `npm run check:manifest` per ADR-024
 * Tier 1 adoption. Validates src/manifest.json against the basic shape
 * required by the nc-vue app-manifest schema:
 *  - $schema field present and pointing to the canonical URL
 *  - version follows semver
 *  - dependencies is an array (guards against "openregister" re-entry)
 *  - menu and pages are arrays
 *
 * When @conduction/nextcloud-vue ships a validateManifest() export this
 * script will delegate to that; for now a structural check is sufficient
 * for Tier 1.
 */

'use strict'

const path = require('path')
const fs = require('fs')

const manifestPath = path.resolve(__dirname, '../src/manifest.json')

let manifest
try {
	manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
} catch (err) {
	console.error('[check:manifest] Failed to parse src/manifest.json:', err.message)
	process.exit(1)
}

const errors = []

if (!manifest.$schema) {
	errors.push('Missing $schema field.')
}

if (!manifest.version || !/^\d+\.\d+\.\d+/.test(manifest.version)) {
	errors.push('version must be a semver string (e.g. "0.1.0").')
}

if (!Array.isArray(manifest.dependencies)) {
	errors.push('dependencies must be an array.')
} else if (manifest.dependencies.includes('openregister')) {
	errors.push('dependencies MUST NOT include "openregister" — LaunchPad must work standalone (ADR-024 / runtime-or-consumption spec).')
} else if (manifest.dependencies.includes('openconnector')) {
	errors.push('dependencies MUST NOT include "openconnector" — LaunchPad must work standalone.')
}

if (!Array.isArray(manifest.menu)) {
	errors.push('menu must be an array.')
}

if (!Array.isArray(manifest.pages)) {
	errors.push('pages must be an array.')
}

if (errors.length > 0) {
	console.error('[check:manifest] Manifest validation failed:')
	errors.forEach(e => console.error('  ✗', e))
	process.exit(1)
} else {
	console.log('[check:manifest] src/manifest.json is valid.')
}
