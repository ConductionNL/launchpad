/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The drill-down performance data set of drill-down-cross-widget-filter
 * (Tasks 10 and 35): one OpenRegister register `cwf-perf` with one schema
 * `cwf-melding`, whose objects carry provincieCode, gemeenteCode and wijkCode
 * along the Dutch administrative hierarchy (12 provinces, 342 gemeenten,
 * a few thousand wijken) and a status.
 *
 * Everything is generated from a fixed seed, so every run makes the same
 * hierarchy and the same objects. GM0355 (Zeist, in Utrecht) is always one of
 * the gemeenten and the largest one, so a spec can click it.
 *
 * Seed the stack (as the administrator, through OpenRegister's bulk save):
 *
 *   node tests/e2e/support/cwfPerfSeed.cli.mts [count]
 *
 * with PLAYWRIGHT_BASE_URL (or BASE_URL) pointing at the instance. It creates
 * the schema and register when missing and refuses to add objects to a
 * register that already holds some. Remove the data set with
 *
 *   node tests/e2e/support/cwfPerfSeed.cli.mts --remove
 */

export const PERF_REGISTER = 'cwf-perf'
export const PERF_SCHEMA = 'cwf-melding'
export const PERF_OBJECT_COUNT = 50_000
export const PERF_GEMEENTE = 'GM0355'

/** CBS province code and the number of gemeenten it holds. */
const PROVINCES: Array<[string, number]> = [
	['PV20', 10], ['PV21', 18], ['PV22', 12], ['PV23', 25],
	['PV24', 6], ['PV25', 51], ['PV26', 26], ['PV27', 44],
	['PV28', 50], ['PV29', 13], ['PV30', 56], ['PV31', 31],
]

const STATUSES = ['open', 'in behandeling', 'afgehandeld', 'bezwaar']

export type Gemeente = { code: string; provincie: string; weight: number; wijken: string[] }

/**
 * A small deterministic random generator (mulberry32).
 *
 * @param seed the seed.
 * @return a function giving the next number in [0, 1).
 */
function generator(seed: number): () => number {
	let state = seed >>> 0
	return () => {
		state = (state + 0x6d2b79f5) >>> 0
		let t = state
		t = Math.imul(t ^ (t >>> 15), t | 1)
		t ^= t + Math.imul(t ^ (t >>> 7), t | 61)
		return ((t ^ (t >>> 14)) >>> 0) / 4_294_967_296
	}
}

/**
 * The hierarchy: every gemeente with its province, its share of the objects
 * and its wijken. GM0355 is placed in PV26 with the largest share.
 *
 * @return the gemeenten.
 */
export function hierarchy(): Gemeente[] {
	const random = generator(20_261_121)
	const used = new Set<number>([355])
	const gemeenten: Gemeente[] = []
	for (const [provincie, count] of PROVINCES) {
		for (let i = 0; i < count; i++) {
			let number = 355
			if (provincie !== 'PV26' || i !== 0) {
				do {
					number = 1 + Math.floor(random() * 1999)
				} while (used.has(number))
				used.add(number)
			}
			const code = `GM${String(number).padStart(4, '0')}`
			// A long-tailed size: a few large gemeenten, many small ones.
			const weight = code === PERF_GEMEENTE ? 40 : 1 + Math.floor(30 * random() ** 3)
			const wijkCount = 3 + Math.floor(weight / 2) + Math.floor(random() * 6)
			const wijken = Array.from({ length: wijkCount }, (_, w) => `WK${code.slice(2)}${String(w + 1).padStart(2, '0')}`)
			gemeenten.push({ code, provincie, weight, wijken })
		}
	}
	return gemeenten
}

/**
 * The objects of the data set, in order.
 *
 * @param count how many.
 * @return the objects.
 */
export function objects(count: number = PERF_OBJECT_COUNT): Array<Record<string, unknown>> {
	const gemeenten = hierarchy()
	const total = gemeenten.reduce((sum, g) => sum + g.weight, 0)
	const random = generator(42)
	const out: Array<Record<string, unknown>> = []
	for (let seq = 0; seq < count; seq++) {
		let pick = random() * total
		let gemeente = gemeenten[gemeenten.length - 1]
		for (const candidate of gemeenten) {
			pick -= candidate.weight
			if (pick < 0) {
				gemeente = candidate
				break
			}
		}
		out.push({
			provincieCode: gemeente.provincie,
			gemeenteCode: gemeente.code,
			wijkCode: gemeente.wijken[Math.floor(random() * gemeente.wijken.length)],
			status: STATUSES[Math.floor(random() * STATUSES.length)],
			seq,
		})
	}
	return out
}

const SCHEMA_BODY = {
	title: 'CWF melding',
	slug: PERF_SCHEMA,
	description: 'Drill-down performance data set (launchpad drill-down-cross-widget-filter, Tasks 10 and 35)',
	properties: {
		provincieCode: { type: 'string', title: 'Provincie', facetable: true },
		gemeenteCode: { type: 'string', title: 'Gemeente', facetable: true },
		wijkCode: { type: 'string', title: 'Wijk', facetable: true },
		status: { type: 'string', title: 'Status', facetable: true },
		seq: { type: 'integer' },
	},
	required: ['gemeenteCode', 'wijkCode'],
}

/**
 * A request to OpenRegister's API as the administrator.
 *
 * @param base the instance URL.
 * @param path the path under /apps/openregister/api.
 * @param init the fetch options.
 * @return the parsed body and the status.
 */
async function or(base: string, path: string, init: RequestInit = {}): Promise<{ status: number; body: any }> {
	const user = process.env.NC_ADMIN_USER ?? 'admin'
	const pass = process.env.NC_ADMIN_PASS ?? 'admin'
	const res = await fetch(`${base}/index.php/apps/openregister/api${path}`, {
		...init,
		headers: {
			'OCS-APIRequest': 'true',
			'Content-Type': 'application/json',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
	})
	const text = await res.text()
	let body: any = text
	try {
		body = JSON.parse(text)
	} catch {
		// a non-JSON body stays text
	}
	return { status: res.status, body }
}

/**
 * The id of the register or schema with this slug, or null.
 *
 * @param base the instance URL.
 * @param kind registers or schemas.
 * @param slug the slug.
 * @return the id or null.
 */
async function idOf(base: string, kind: 'registers' | 'schemas', slug: string): Promise<number | null> {
	const { body } = await or(base, `/${kind}?_limit=500`)
	const found = (body?.results ?? []).find((entry: any) => entry.slug === slug)
	return found ? Number(found.id) : null
}

/**
 * How many objects the data set's register holds now.
 *
 * @param base the instance URL.
 * @return the count.
 */
export async function seededCount(base: string): Promise<number> {
	const { status, body } = await or(base, `/objects/${PERF_REGISTER}/${PERF_SCHEMA}?_limit=1`)
	return status === 200 ? Number(body?.total ?? 0) : 0
}

/**
 * Create the schema and register when missing and add the objects in batches.
 *
 * @param base the instance URL.
 * @param count how many objects.
 * @param batch objects per bulk save.
 * @return the timing per batch in ms.
 */
export async function seed(base: string, count: number, batch = 1000): Promise<number[]> {
	let schema = await idOf(base, 'schemas', PERF_SCHEMA)
	if (schema === null) {
		const created = await or(base, '/schemas', { method: 'POST', body: JSON.stringify(SCHEMA_BODY) })
		schema = Number(created.body.id)
	}
	if ((await idOf(base, 'registers', PERF_REGISTER)) === null) {
		await or(base, '/registers', {
			method: 'POST',
			body: JSON.stringify({ title: 'CWF performance', slug: PERF_REGISTER, schemas: [schema] }),
		})
	}
	const existing = await seededCount(base)
	if (existing > 0) {
		throw new Error(`${PERF_REGISTER} already holds ${existing} objects; remove them first`)
	}
	const all = objects(count)
	const timings: number[] = []
	for (let start = 0; start < all.length; start += batch) {
		const began = Date.now()
		const res = await or(base, `/bulk/${PERF_REGISTER}/${PERF_SCHEMA}/save`, {
			method: 'POST',
			body: JSON.stringify({ objects: all.slice(start, start + batch) }),
		})
		if (res.status >= 300 || res.body?.failed_count > 0) {
			throw new Error(`batch at ${start}: ${res.status} ${JSON.stringify(res.body).slice(0, 500)}`)
		}
		timings.push(Date.now() - began)
		process.stdout.write(`batch ${start / batch + 1}: ${timings[timings.length - 1]} ms\n`)
	}
	return timings
}

/**
 * Remove the register with its objects, and the schema.
 *
 * @param base the instance URL.
 * @return nothing.
 */
export async function remove(base: string): Promise<void> {
	await or(base, `/bulk/${PERF_REGISTER}/${PERF_SCHEMA}/delete-objects`, { method: 'POST', body: JSON.stringify({ hardDelete: true }) })
	const register = await idOf(base, 'registers', PERF_REGISTER)
	if (register !== null) {
		await or(base, `/registers/${register}`, { method: 'DELETE' })
	}
	const schema = await idOf(base, 'schemas', PERF_SCHEMA)
	if (schema !== null) {
		await or(base, `/schemas/${schema}`, { method: 'DELETE' })
	}
}
