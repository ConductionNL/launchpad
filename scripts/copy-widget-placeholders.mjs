/**
 * Copies nextcloud-vue's widget-placeholders.json and css/widget-placeholder.css
 * to lib/Service/ so the PHP first-paint shell reads the registry's placeholder
 * declarations (widget-declared-placeholders D3) and inlines the library's
 * placeholder rules (task 4). The copy is committed, because a release
 * carries no node_modules; src/utils/__tests__/widgetPlaceholders.copy.spec.js
 * fails when it differs from the installed package.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-2
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-4
 */
import { copyFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const require = createRequire(import.meta.url)
const service = join(dirname(fileURLToPath(import.meta.url)), '..', 'lib', 'Service')

for (const [from, to] of [
	['@conduction/nextcloud-vue/widget-placeholders.json', 'widget-placeholders.json'],
	['@conduction/nextcloud-vue/css/widget-placeholder.css', 'widget-placeholder.css'],
]) {
	const source = require.resolve(from)
	copyFileSync(source, join(service, to))
	console.log(`copy-widget-placeholders: ${source} -> lib/Service/${to}`)
}
