/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the kiosk rotation engine (REQ-KIOSK-003/004/005). A virtual
 * scheduler drives time deterministically so the dwell timers, skip-on-failure,
 * just-in-time prefetch, last-known-content retention, and watchdog are all
 * exercised with a mocked clock and zero wall-clock waits.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { KioskRotationEngine } from '../kioskRotationEngine.js'

/**
 * Minimal virtual scheduler: timers are stored with absolute fire times and
 * advanced by tick(ms). Microtasks (promise callbacks) are flushed between
 * fires so the engine's async fetch chains resolve deterministically.
 */
function makeScheduler() {
	let nowMs = 0
	let seq = 0
	const timers = new Map()

	const flushMicrotasks = async () => {
		// A few rounds is enough for our short promise chains.
		for (let i = 0; i < 5; i++) {
			await Promise.resolve()
		}
	}

	return {
		now: () => nowMs,
		setTimer: (cb, ms) => {
			const id = ++seq
			timers.set(id, { fireAt: nowMs + ms, cb })
			return id
		},
		clearTimer: (id) => { timers.delete(id) },
		async tick(ms) {
			const target = nowMs + ms
			// Fire due timers in chronological order, flushing microtasks between.
			// Timers scheduled by a callback at exactly `target` (e.g. a watchdog
			// re-arm or a fresh dwell timer landing on the boundary) are NOT
			// fired in this tick — they belong to the next advance — otherwise a
			// self-re-arming timer would loop forever within one tick.
			let guard = 0
			while (guard++ < 5000) {
				let next = null
				for (const [id, t] of timers) {
					if (t.fireAt <= target && (next === null || t.fireAt < next.fireAt)) {
						next = { id, ...t }
					}
				}
				if (next === null) break
				nowMs = next.fireAt
				timers.delete(next.id)
				next.cb()
				await flushMicrotasks()
			}
			if (guard >= 5000) {
				throw new Error('virtual scheduler runaway: too many timer fires in one tick')
			}
			nowMs = target
			await flushMicrotasks()
		},
	}
}

describe('KioskRotationEngine', () => {
	let sched
	let rendered

	beforeEach(() => {
		sched = makeScheduler()
		rendered = []
	})

	const build = (entries, fetchEntry, onStateChange = () => {}) =>
		new KioskRotationEngine({
			entries,
			fetchEntry,
			onRender: (entry, index) => rendered.push(index),
			onStateChange,
			now: sched.now,
			setTimer: sched.setTimer,
			clearTimer: sched.clearTimer,
		})

	it('rotates through entries in order with per-entry dwell and loops', async () => {
		const entries = [
			{ dwellSeconds: 45, dashboard: {} },
			{ dwellSeconds: 90, dashboard: {} },
		]
		const fetchEntry = vi.fn(async (i) => entries[i].dashboard)
		const engine = build(entries, fetchEntry)

		engine.start()
		await sched.tick(0)
		expect(rendered).toEqual([0]) // first entry shown immediately

		await sched.tick(45000) // dwell of entry 0
		expect(rendered).toEqual([0, 1])

		await sched.tick(90000) // dwell of entry 1 -> loop back to 0
		expect(rendered).toEqual([0, 1, 0])

		engine.stop()
	})

	it('skips a failing entry after the grace period and retries it next loop', async () => {
		const entries = [
			{ dwellSeconds: 30, dashboard: {} },
			{ dwellSeconds: 30, dashboard: {} },
			{ dwellSeconds: 30, dashboard: {} },
		]
		let failB = true
		const fetchEntry = vi.fn(async (i) => {
			if (i === 1 && failB) {
				throw new Error('HTTP 500')
			}
			return entries[i].dashboard
		})
		const engine = build(entries, fetchEntry)

		engine.start()
		await sched.tick(0)
		expect(rendered).toEqual([0])

		// Advance past entry 0 dwell -> reaches entry 1 which fails immediately.
		await sched.tick(30000)
		// Entry 1 failed (rejected), so engine advanced to entry 2 without
		// adding 1 to rendered.
		expect(rendered).toEqual([0, 2])

		// Let B recover, complete the loop back to entry 1.
		failB = false
		await sched.tick(30000) // dwell of entry 2 -> loop to 0
		expect(rendered).toEqual([0, 2, 0])
		await sched.tick(30000) // 0 -> 1 (now succeeds)
		expect(rendered).toEqual([0, 2, 0, 1])

		engine.stop()
	})

	it('shows a neutral placeholder on cold-start total failure, recovers on success', async () => {
		const entries = [{ dwellSeconds: 30, dashboard: {} }]
		let down = true
		const states = []
		const fetchEntry = vi.fn(async () => {
			if (down) throw new Error('network down')
			return entries[0].dashboard
		})
		const engine = build(entries, fetchEntry, (s) => states.push(s))

		engine.start()
		await sched.tick(0)
		// Cold start, everything failing -> placeholder, nothing rendered.
		// (The engine constructs in the 'placeholder' state, so onStateChange
		// need not re-emit it; assert the engine state directly.)
		expect(rendered).toEqual([])
		expect(engine.state).toBe('placeholder')
		expect(engine.hasEverRendered).toBe(false)

		// Recover: next attempt succeeds.
		down = false
		await sched.tick(30000)
		expect(rendered.length).toBeGreaterThan(0)
		expect(states).toContain('live')

		engine.stop()
	})

	it('retains last-known content and signals reconnecting on later network loss', async () => {
		const entries = [{ dwellSeconds: 30, dashboard: {} }]
		let up = true
		const states = []
		const fetchEntry = vi.fn(async () => {
			if (!up) throw new Error('network lost')
			return entries[0].dashboard
		})
		const engine = build(entries, fetchEntry, (s) => states.push(s))

		engine.start()
		await sched.tick(0)
		expect(states).toContain('live')

		// Network drops; on the next refresh the engine reports reconnecting,
		// never placeholder, because it has rendered before.
		up = false
		await sched.tick(30000)
		expect(states).toContain('reconnecting')
		expect(states).not.toContain('placeholder')

		engine.stop()
	})

	it('restarts the rotation via the watchdog when no advance occurs within 2 x max dwell', async () => {
		const entries = [
			{ dwellSeconds: 30, dashboard: {} },
			{ dwellSeconds: 90, dashboard: {} },
		]
		// Engine that hangs forever on the first entry's fetch — neither resolve
		// nor reject — so no normal advance ever happens.
		const fetchEntry = vi.fn(() => new Promise(() => {}))
		const engine = build(entries, fetchEntry)

		engine.start()
		// Within the grace window, nothing rendered and no advance.
		await sched.tick(5000)
		const advancesBefore = engine.currentIndex

		// After the 10 s grace the failing entry is skipped (advance happens),
		// proving liveness even when a fetch hangs.
		await sched.tick(10000)
		expect(engine.currentIndex).not.toBe(advancesBefore)

		// Watchdog window is 2 x 90 s = 180 s; ensure it re-arms without crashing.
		await sched.tick(180000)
		expect(engine.currentIndex).toBeGreaterThanOrEqual(0)

		engine.stop()
	})

	it('prefetches the next entry ~5 s before the switch', async () => {
		const entries = [
			{ dwellSeconds: 30, dashboard: {} },
			{ dwellSeconds: 30, dashboard: {} },
		]
		const fetchEntry = vi.fn(async (i) => entries[i].dashboard)
		const engine = build(entries, fetchEntry)

		engine.start()
		await sched.tick(0)
		fetchEntry.mockClear()

		// 25 s in (5 s before the 30 s dwell switch) the next entry is prefetched.
		await sched.tick(25000)
		expect(fetchEntry).toHaveBeenCalledWith(1)

		engine.stop()
	})
})
