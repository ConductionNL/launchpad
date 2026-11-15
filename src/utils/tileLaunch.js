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
	const port = stored(content, 'remote', 'port')
	return {
		remoteMode: stored(content, 'remote', 'mode') === 'rdp' ? 'rdp' : 'gateway',
		remoteUrl: stored(content, 'remote', 'url'),
		rdpHost: stored(content, 'remote', 'host'),
		rdpPort: port ? String(port) : '',
		rdpRemoteApp: stored(content, 'remote', 'remoteApp'),
		rdpGateway: stored(content, 'remote', 'gateway'),
		ssoTemplate: stored(content, 'sso', 'template'),
		ssoAppId: stored(content, 'sso', 'appId'),
	}
}

/**
 * One stored launch setting, or '' when it is not set.
 *
 * @param {object} content The placement's content blob.
 * @param {string} group `remote` or `sso`.
 * @param {string} key The setting.
 * @return {string|number}
 */
function stored(content, group, key) {
	return content?.[group]?.[key] || ''
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
		return { remote: form.remoteMode === 'rdp' ? rdpRemote(form) : { mode: 'gateway', url: trimmed(form.remoteUrl) } }
	}
	if (form.linkType === LINK_SSO) {
		return { sso: { template: form.ssoTemplate || '', appId: trimmed(form.ssoAppId) } }
	}
	return {}
}

/**
 * The `remote` block of an RDP tile: its host, and its port, program and
 * gateway only when they are filled in.
 *
 * @param {object} form The editor's form.
 * @return {object}
 */
function rdpRemote(form) {
	const remote = { mode: 'rdp', host: trimmed(form.rdpHost) }
	for (const [key, field] of [['port', 'rdpPort'], ['remoteApp', 'rdpRemoteApp'], ['gateway', 'rdpGateway']]) {
		const value = trimmed(form[field])
		if (value !== '') {
			remote[key] = value
		}
	}
	return remote
}

/**
 * A form value as trimmed text.
 *
 * @param {string|number|null|undefined} value The value.
 * @return {string}
 */
function trimmed(value) {
	return String(value || '').trim()
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
