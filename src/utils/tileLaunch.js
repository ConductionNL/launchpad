/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The three tile link types that start something other than a web page
 * (launcher-tile-launch-types): a program on the user's computer, a remote
 * desktop or published program, and a single sign-on app. The server checks
 * and resolves every one of them (TileLinkValidator); these helpers only
 * build the editor's payload and the link the browser follows (design D2).
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
 */

export const LINK_PROGRAM = 'program'
export const LINK_REMOTE = 'remote-desktop'
export const LINK_SSO = 'sso'

/** The default RDP port, as the server writes it when the tile names none. */
export const DEFAULT_RDP_PORT = 3389

/**
 * Whether a link type is one of the three launch types.
 *
 * @param {string} linkType The tile's link type.
 * @return {boolean}
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
 */
export function isLaunchType(linkType) {
	return [LINK_PROGRAM, LINK_REMOTE, LINK_SSO].includes(linkType)
}

/**
 * The editor fields of a tile's launch settings, read from its content.
 *
 * @param {object} content The placement's content blob.
 * @return {{remoteMode: string, remoteUrl: string, rdpHost: string, rdpPort: string, rdpRemoteApp: string, rdpGateway: string, ssoTemplate: string, ssoAppId: string}}
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
 */
export function launchFields(content) {
	const remote = content?.remote || {}
	const sso = content?.sso || {}
	return {
		remoteMode: remote.mode === 'rdp' ? 'rdp' : 'gateway',
		remoteUrl: remote.url || '',
		rdpHost: remote.host || '',
		rdpPort: remote.port ? String(remote.port) : '',
		rdpRemoteApp: remote.remoteApp || '',
		rdpGateway: remote.gateway || '',
		ssoTemplate: sso.template || '',
		ssoAppId: sso.appId || '',
	}
}

/**
 * The content blob a launch tile is saved with: `remote` for a
 * remote-desktop tile, `sso` for a single sign-on tile, nothing else for
 * the other types. Only the fields of the chosen mode are sent.
 *
 * @param {object} form The editor's form (linkType plus launchFields()).
 * @return {object}
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-4
 */
export function launchContent(form) {
	if (form.linkType === LINK_REMOTE) {
		if (form.remoteMode === 'rdp') {
			const remote = { mode: 'rdp', host: String(form.rdpHost || '').trim() }
			const port = String(form.rdpPort || '').trim()
			if (port !== '') {
				remote.port = port
			}
			const remoteApp = String(form.rdpRemoteApp || '').trim()
			if (remoteApp !== '') {
				remote.remoteApp = remoteApp
			}
			const gateway = String(form.rdpGateway || '').trim()
			if (gateway !== '') {
				remote.gateway = gateway
			}
			return { remote }
		}
		return { remote: { mode: 'gateway', url: String(form.remoteUrl || '').trim() } }
	}
	if (form.linkType === LINK_SSO) {
		return { sso: { template: form.ssoTemplate || '', appId: String(form.ssoAppId || '').trim() } }
	}
	return {}
}

/**
 * The address a launch tile's link points at, or null for another type.
 *
 * A program tile follows its scheme address, a gateway or single sign-on
 * tile the https address the server stored, and an RDP tile the endpoint
 * that writes its `.rdp` file.
 *
 * @param {object} tile The tile (linkType, linkValue).
 * @param {object|null} content The placement's content blob.
 * @param {number|string|null} placementId The tile's placement id.
 * @param {(path: string) => string} generateUrl Nextcloud's generateUrl.
 * @return {string|null}
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-5
 */
export function launchHref(tile, content, placementId, generateUrl) {
	if (tile.linkType === LINK_REMOTE && content?.remote?.mode === 'rdp') {
		return placementId ? generateUrl('/apps/launchpad/api/tiles/' + encodeURIComponent(String(placementId)) + '/rdp') : null
	}
	if (isLaunchType(tile.linkType)) {
		return tile.linkValue || null
	}
	return null
}

/**
 * Where a tile's link opens: gateway and single sign-on tiles in a new tab,
 * program and RDP tiles in place (the browser hands them to a program).
 *
 * @param {object} tile The tile.
 * @param {object|null} content The placement's content blob.
 * @return {string|null} `_blank`, `_self`, or null for another type.
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-5
 */
export function launchTarget(tile, content) {
	if (tile.linkType === LINK_SSO) {
		return '_blank'
	}
	if (tile.linkType === LINK_REMOTE) {
		return content?.remote?.mode === 'rdp' ? '_self' : '_blank'
	}
	return tile.linkType === LINK_PROGRAM ? '_self' : null
}
