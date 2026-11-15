/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `tileLaunch.js`: the content a launch tile is saved
 * with, the editor fields read back from it, and the link and target the
 * browser follows per type (launcher-tile-launch-types D2).
 */

import { describe, expect, it } from 'vitest'
import {
	DEFAULT_RDP_PORT,
	isLaunchType,
	launchContent,
	launchFields,
	launchHref,
	launchTarget,
} from '../tileLaunch.js'

const generateUrl = (path) => '/nc' + path

describe('tileLaunch', () => {
	it('knows the three launch types', () => {
		expect(['program', 'remote-desktop', 'sso'].map(isLaunchType)).toEqual([true, true, true])
		expect(['url', 'app', 'internal', undefined].map(isLaunchType)).toEqual([false, false, false, false])
		expect(DEFAULT_RDP_PORT).toBe(3389)
	})

	it('saves a gateway tile with its address only', () => {
		expect(launchContent({ linkType: 'remote-desktop', remoteMode: 'gateway', remoteUrl: ' https://desktop.gemeente.nl/ ', rdpHost: 'stale' }))
			.toEqual({ remote: { mode: 'gateway', url: 'https://desktop.gemeente.nl/' } })
	})

	it('saves an RDP tile with the fields that were filled in', () => {
		expect(launchContent({ linkType: 'remote-desktop', remoteMode: 'rdp', rdpHost: ' rds01.gemeente.local ', rdpPort: '', rdpRemoteApp: '||Belastingen', rdpGateway: 'rdgw.gemeente.nl' }))
			.toEqual({ remote: { mode: 'rdp', host: 'rds01.gemeente.local', remoteApp: '||Belastingen', gateway: 'rdgw.gemeente.nl' } })
		expect(launchContent({ linkType: 'remote-desktop', remoteMode: 'rdp', rdpHost: 'pc12', rdpPort: '3390' }))
			.toEqual({ remote: { mode: 'rdp', host: 'pc12', port: '3390' } })
	})

	it('saves an sso tile with its template and app id, and other types with nothing', () => {
		expect(launchContent({ linkType: 'sso', ssoTemplate: 'entra', ssoAppId: ' a1b2c3 ' })).toEqual({ sso: { template: 'entra', appId: 'a1b2c3' } })
		expect(launchContent({ linkType: 'program', linkValue: 'ms-word:x' })).toEqual({})
		expect(launchContent({ linkType: 'url' })).toEqual({})
	})

	it('reads the editor fields back from the content', () => {
		expect(launchFields({ remote: { mode: 'rdp', host: 'pc12', port: 3390, remoteApp: '||App', gateway: 'gw' }, sso: { template: 'entra', appId: 'a1' } }))
			.toEqual({ remoteMode: 'rdp', remoteUrl: '', rdpHost: 'pc12', rdpPort: '3390', rdpRemoteApp: '||App', rdpGateway: 'gw', ssoTemplate: 'entra', ssoAppId: 'a1' })
		expect(launchFields(null)).toEqual({ remoteMode: 'gateway', remoteUrl: '', rdpHost: '', rdpPort: '', rdpRemoteApp: '', rdpGateway: '', ssoTemplate: '', ssoAppId: '' })
	})

	it('links each launch type as design D2 says', () => {
		expect(launchHref({ linkType: 'program', linkValue: 'ms-word:ofe' }, null, 7, generateUrl)).toBe('ms-word:ofe')
		expect(launchHref({ linkType: 'sso', linkValue: 'https://idp/a1' }, { sso: {} }, 7, generateUrl)).toBe('https://idp/a1')
		expect(launchHref({ linkType: 'remote-desktop', linkValue: 'https://gw/' }, { remote: { mode: 'gateway' } }, 7, generateUrl)).toBe('https://gw/')
		expect(launchHref({ linkType: 'remote-desktop', linkValue: '' }, { remote: { mode: 'rdp' } }, 7, generateUrl)).toBe('/nc/apps/launchpad/api/tiles/7/rdp')
		expect(launchHref({ linkType: 'remote-desktop', linkValue: '' }, { remote: { mode: 'rdp' } }, null, generateUrl)).toBeNull()
		expect(launchHref({ linkType: 'url', linkValue: 'https://example.org' }, null, 7, generateUrl)).toBeNull()
	})

	it('opens gateway and sso tiles in a new tab, program and RDP tiles in place', () => {
		expect(launchTarget({ linkType: 'sso' }, null)).toBe('_blank')
		expect(launchTarget({ linkType: 'remote-desktop' }, { remote: { mode: 'gateway' } })).toBe('_blank')
		expect(launchTarget({ linkType: 'remote-desktop' }, { remote: { mode: 'rdp' } })).toBe('_self')
		expect(launchTarget({ linkType: 'program' }, null)).toBe('_self')
		expect(launchTarget({ linkType: 'url' }, null)).toBeNull()
	})
})
