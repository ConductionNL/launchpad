/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seed or remove the drill-down performance data set (see cwfPerfSeed.ts):
 *
 *   PLAYWRIGHT_BASE_URL=http://localhost:8080 node tests/e2e/support/cwfPerfSeed.cli.mts [count]
 *   PLAYWRIGHT_BASE_URL=http://localhost:8080 node tests/e2e/support/cwfPerfSeed.cli.mts --remove
 */

import { PERF_OBJECT_COUNT, remove, seed, seededCount } from './cwfPerfSeed.ts'

const base = process.env.PLAYWRIGHT_BASE_URL ?? process.env.BASE_URL ?? 'http://localhost:8080'
const arg = process.argv[2]
if (arg === '--remove') {
	await remove(base)
} else {
	const count = arg ? Number(arg) : PERF_OBJECT_COUNT
	const began = Date.now()
	const timings = await seed(base, count)
	const sorted = [...timings].sort((a, b) => a - b)
	process.stdout.write(`seeded ${count} objects in ${timings.length} batches, ${Date.now() - began} ms; batch median ${sorted[Math.floor(sorted.length / 2)]} ms, max ${sorted[sorted.length - 1]} ms; count now ${await seededCount(base)}\n`)
}
