#!/usr/bin/env node

/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CI guard for the i18n-translation-domain capability.
 *
 * Every `t('<domain>', ...)` / `n('<domain>', ...)` call in `src/` MUST
 * use the translation-domain string the shipped `l10n/<lang>.js` bundles
 * register under — `'launchpad'`. LaunchPad's Nextcloud app id is still
 * `mydash` (routes / DB tables), but the translation bundles register
 * under `launchpad`, so `t('mydash', …)` silently returns the untranslated
 * English source on every non-English locale (@nextcloud/l10n's
 * `translate(app, text)` is a strict key match — no alias, no fallback).
 *
 * This guard fails the build with file:line output when any `t(`/`n(`
 * call in `src/` uses a string-literal domain other than `'launchpad'`.
 * It is a pure-Node text scan — no extra deps, no AST — matching both
 * single- and double-quoted forms.
 */

const fs = require('node:fs')
const path = require('node:path')

const ROOT = path.resolve(__dirname, '..')
const SRC_DIR = path.join(ROOT, 'src')
const ALLOWED_DOMAIN = 'launchpad'

// Matches a real Nextcloud translate call: `t('domain', 'text', …)` /
// `n('domain', …)`. The trailing comma is required so the FIRST argument
// is genuinely the translation *domain* (a valid `translate(app, text)`
// call always has a second argument) — this excludes illustrative
// single-arg snippets in comments like `t('Save')` and single-arg
// helpers that are not translation calls. Group 1 = the quote, group 2 =
// the domain literal. Variable-domain calls (`t(app, …)`) are
// intentionally NOT matched — only string literals are checkable, and
// only string literals can be wrong at build time.
const CALL_PATTERN = /(?<![A-Za-z0-9_$.])[tn]\(\s*(['"])([^'"]*)\1\s*,/g

/**
 * Walk a directory recursively yielding every source file path under it,
 * skipping `node_modules`, `__tests__`, and non-source artefacts.
 *
 * @param {string} dir Directory to walk.
 * @return {Array<string>} Absolute file paths.
 */
function walk(dir) {
	const out = []
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		const full = path.join(dir, entry.name)
		if (entry.isDirectory()) {
			if (entry.name === 'node_modules' || entry.name === '__tests__') {
				continue
			}
			out.push(...walk(full))
			continue
		}
		if (/\.(?:js|ts|vue|mjs|cjs)$/.test(entry.name)) {
			out.push(full)
		}
	}
	return out
}

const offenders = []
for (const file of walk(SRC_DIR)) {
	const content = fs.readFileSync(file, 'utf8')
	const lines = content.split('\n')
	lines.forEach((line, index) => {
		CALL_PATTERN.lastIndex = 0
		let match
		while ((match = CALL_PATTERN.exec(line)) !== null) {
			const domain = match[2]
			if (domain !== ALLOWED_DOMAIN) {
				offenders.push({
					file: path.relative(ROOT, file),
					line: index + 1,
					domain,
				})
			}
		}
	})
}

if (offenders.length > 0) {
	process.stderr.write(
		'lint:translation-domain — i18n-translation-domain violation:\n'
			+ `  Every t()/n() call in src/ must use the '${ALLOWED_DOMAIN}' translation domain\n`
			+ '  (the domain the shipped l10n/<lang>.js bundles register under).\n'
			+ '  Offending calls:\n'
			+ offenders
				.map((o) => `    - ${o.file}:${o.line} uses t/n('${o.domain}', …)\n`)
				.join(''),
	)
	process.exit(1)
}

process.stdout.write(
	`lint:translation-domain — OK (all t()/n() calls use the '${ALLOWED_DOMAIN}' domain)\n`,
)
