/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The permission vocabulary the manifest is written in, and the two rules that
 * read it.
 *
 * 🔴 THE ADMIN MENU ENTRIES RENDERED FOR EVERY ACCOUNT. `manifest.menu`
 * declares `permission: "admin"` on `admin-templates` and `admin-settings`,
 * and `App.vue` passed no `permissions` prop to `CnAppRoot`. `CnAppNav`'s
 * filter is written to treat an absent or empty list as "the app did not say":
 *
 *     if (!item.permission) return true
 *     if (!this.permissions || this.permissions.length === 0) return true
 *     return this.permissions.includes(item.permission)
 *
 * so the second line fired for everyone and the declaration was inert. A gate
 * that fails open is not a gate.
 *
 * WHAT THOSE TWO ENTRIES ACTUALLY REACH, said plainly rather than implied.
 * Both `/admin/templates` and `/admin/settings` render
 * `views/AdminSettingsRedirect.vue`, which bounces to
 * `/settings/admin/launchpad` — a Nextcloud settings section registered by
 * `lib/Settings/LaunchPadAdmin.php` and gated by Nextcloud's own settings
 * framework. So an ordinary account following either entry today lands on a
 * page the server refuses it. This is a broken affordance, not a disclosure:
 * no launchpad data was ever served through those two routes. It is worth
 * fixing anyway, because the next admin surface added under `/admin/*`
 * inherits whichever behaviour is in place when it arrives.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

/**
 * The permission strings the current account holds.
 *
 * Never empty, and that is the point rather than a detail: `CnAppNav` reads an
 * empty array as "unspecified" and renders every entry regardless of what it
 * declares. `user` is what everyone holds and no manifest entry asks for; it
 * exists to keep the list non-empty for accounts that hold nothing else.
 *
 * A BOOLEAN ARGUMENT RATHER THAN A `window` READ, deliberately. Seventeen apps
 * in this fleet compute this list as `window.OC?.currentUser?.permissions ?? []`.
 * `OC.currentUser` is the uid STRING (`core/src/OC/currentuser.js`), so
 * `.permissions` is always `undefined` and that expression always answers `[]`
 * — the empty-list escape, every time, for every account. Launchpad does not
 * need `window` at all: `PageController` already pushes a server-computed
 * `isAdmin` into the initial state (`utils/loadInitialState.js`), defaulting to
 * `false`, and both call sites have it in hand.
 *
 * @param {boolean} isAdmin Whether the server said this account is an admin.
 *
 * @return {Array<string>} The permission strings held, always non-empty.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */
export function currentPermissions(isAdmin) {
	return isAdmin === true ? ['user', 'admin'] : ['user']
}

/**
 * Whether an account holding `permissions` may open something declaring
 * `required`.
 *
 * Deliberately NOT the shape `CnAppNav.passesPermission` uses. There is no
 * "the app did not say" escape here: an empty or absent list denies anything
 * that asks for a permission. On a nav entry an empty list is a visibility
 * bug; on a route it would be the whole gate. The two rules fail in opposite
 * directions on the same input and that is intended, so they are written
 * separately rather than shared.
 *
 * A page that declares nothing stays open, which is every page but the two
 * admin ones.
 *
 * @param {string}        required    The declared permission, or '' / undefined.
 * @param {Array<string>} permissions The permissions held.
 *
 * @return {boolean} True when access is allowed.
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */
export function permits(required, permissions) {
	if (!required) {
		return true
	}
	if (!Array.isArray(permissions)) {
		return false
	}
	return permissions.includes(required)
}
