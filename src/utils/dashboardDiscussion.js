/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * When a dashboard shows its discussion (openregister-leaf-integrations,
 * REQ-LEAF-002, REQ-LEAF-004): only on a dashboard others may see, and only
 * when OpenRegister (which holds the dashboard's discussion object) and
 * Talk (which hosts the room) are both installed.
 */

/**
 * Whether OpenRegister and Talk are both installed for this user.
 *
 * @param {object|null} [roots] The apps' web roots, `OC.appswebroots` by default.
 * @return {boolean}
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-6
 */
export function discussionAvailable(roots = (typeof window !== 'undefined' && window.OC?.appswebroots) || null) {
	return Boolean(roots) && Object.hasOwn(roots, 'openregister') && Object.hasOwn(roots, 'spreed')
}

/**
 * Whether a dashboard is collaborative: a group dashboard, a dashboard
 * shared with this user, or one of theirs they share with others.
 *
 * @param {object} options What is known about the dashboard.
 * @param {object|null} options.dashboard The dashboard.
 * @param {Array<object>} [options.sharedWithMe] The dashboards shared with this user.
 * @param {Array<object>} [options.ownShares] The dashboard's shares, when this user owns it.
 * @return {boolean}
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
 */
export function isCollaborative({ dashboard, sharedWithMe = [], ownShares = [] }) {
	if (!dashboard || !dashboard.uuid || dashboard.type === 'admin_template') {
		return false
	}
	if (dashboard.type === 'group_shared') {
		return true
	}
	if ((sharedWithMe || []).some((entry) => entry && (entry.id === dashboard.id || entry.uuid === dashboard.uuid))) {
		return true
	}
	return Array.isArray(ownShares) && ownShares.length > 0
}

/**
 * Whether a dashboard's own switch lets its conversation show
 * (REQ-LEAF-006): only an explicit 0 (or false) hides it.
 *
 * @param {object|null} dashboard The dashboard.
 * @return {boolean}
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
 */
export function conversationEnabled(dashboard) {
	const value = dashboard?.conversationEnabled
	return value !== 0 && value !== false
}
