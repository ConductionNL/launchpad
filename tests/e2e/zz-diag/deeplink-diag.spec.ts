import { expect, test } from '@playwright/test'
import { deprovisionUser, loginAs, provisionThrowawayUser } from '../fixtures/secondary-user.ts'
import { contextFor } from '../support/principals.ts'
import { openDashboardSwitcher } from '../support/dashboardSwitcher.ts'

const APP = '/index.php/apps/launchpad'
const RUN = Date.now()
test('diag pushes', async ({ browser }) => {
	const account = await provisionThrowawayUser('e2e-dldiag')
	try {
		const api = await contextFor(account.username, account.password)
		for (const n of ['a', 'b']) {
			const r = await api.post(`${APP}/api/dashboard`, { data: { name: `Diag ${n} ${RUN}`, slug: `diag-${n}-${RUN}` } })
			console.log('create', n, r.status(), (await r.text()).slice(0, 300))
		}
		const act = await api.get(`${APP}/api/dashboard`)
		console.log('active', (await act.text()).slice(0, 400))
		await api.dispose()
		const { context, page } = await loginAs(browser, account.username, account.password)
		await page.addInitScript(() => {
			const w = window as any
			w.__log = []
			for (const m of ['pushState', 'replaceState']) {
				const o = (window.history as any)[m].bind(window.history)
				;(window.history as any)[m] = (...a: any[]) => { w.__log.push(m + ' ' + String(a[2]) + ' ' + location.pathname + '\n' + new Error().stack); return o(...a) }
			}
		})
		await page.goto(`${APP}/diag-a-${RUN}`)
		await page.waitForTimeout(8000)
		console.log('LOG\n' + (await page.evaluate(() => (window as any).__log.join('\n----\n'))))
		console.log('URL0', page.url())
		await openDashboardSwitcher(page)
		await page.waitForTimeout(2000)
		console.log('LOG2\n' + (await page.evaluate(() => (window as any).__log.join('\n----\n'))))
		console.log('URL', page.url())
		await context.close()
	} finally {
		await deprovisionUser(account.username)
	}
	expect(1).toBe(1)
})
