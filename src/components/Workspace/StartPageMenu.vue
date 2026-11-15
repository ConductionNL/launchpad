<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - StartPageMenu: the navigation panel's destinations as one compact menu
  - in the workspace's top row, for the start page without the panel
  - (runtime-shell REQ-SHELL-009).
  -
  - Renders nothing while the panel is rendered, so the destinations are
  - never offered twice. When the panel is left out, every entry the panel
  - would have shown is here: the app's menu entries by section (main,
  - footer, settings), the personal settings dialog and, for an
  - administrator, the link to Nextcloud's admin settings. The dashboards
  - themselves are not repeated: the dashboard switcher beside this button
  - lists them.
-->

<template>
	<NcActions
		v-if="chrome.railSuppressed && groups.length > 0"
		:ariaLabel="t('launchpad', 'Menu')"
		:forceMenu="true"
		class="launchpad-start-page-menu"
		data-testid="launchpad-start-page-menu">
		<template v-for="(group, index) in groups" :key="group.id">
			<NcActionSeparator v-if="index > 0" />
			<template v-for="item in group.items" :key="item.id">
				<NcActionLink
					v-if="item.href"
					:href="item.href"
					:target="item.external ? '_blank' : '_self'"
					:data-testid="`launchpad-start-page-menu-${item.id}`">
					{{ item.label }}
				</NcActionLink>
				<NcActionRouter
					v-else-if="item.to"
					:to="item.to"
					:data-testid="`launchpad-start-page-menu-${item.id}`">
					{{ item.label }}
				</NcActionRouter>
				<NcActionButton
					v-else
					:data-testid="`launchpad-start-page-menu-${item.id}`"
					@click="item.onClick">
					{{ item.label }}
				</NcActionButton>
			</template>
		</template>
	</NcActions>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcActionButton,
	NcActionLink,
	NcActionRouter,
	NcActions,
	NcActionSeparator,
} from '@nextcloud/vue'

/**
 * The sections of the navigation panel, in the panel's order.
 */
const SECTIONS = ['main', 'footer', 'settings']

/**
 * Whether a destination leaves the instance (scheme-prefixed), which is
 * the same test the panel's `NcAppNavigationItem` applies before it opens
 * a new tab.
 *
 * @param {string} href The destination.
 * @return {boolean} True for a `scheme://` URL.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function isExternal(href) {
	return /^(https?:)?\/\//.test(String(href ?? ''))
}

/**
 * Resolve one panel entry to a menu item: a link for `href`, a router
 * target for `route`, a button for `action: "user-settings"`. The same
 * precedence as the panel (`CnAppNav.itemTo` / `itemHref`): an action
 * never carries a link.
 *
 * @param {object} item The manifest menu entry.
 * @param {(() => void)|null} openUserSettings The injected personal-settings opener.
 * @return {object|null} The menu item, or null when the entry has no destination.
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
 */
export function resolveMenuItem(item, openUserSettings) {
	const label = t('launchpad', String(item.label ?? item.id ?? ''))
	if (item.action === 'user-settings') {
		if (typeof openUserSettings !== 'function') {
			return null
		}
		return { id: item.id, label, onClick: openUserSettings }
	}
	if (item.href) {
		return {
			id: item.id,
			label,
			href: item.href,
			external: isExternal(item.href),
		}
	}
	if (item.route) {
		return {
			id: item.id,
			label,
			to: item.query
				? { name: item.route, query: item.query }
				: { name: item.route },
		}
	}
	return null
}

export default {
	name: 'StartPageMenu',

	components: {
		NcActionButton,
		NcActionLink,
		NcActionRouter,
		NcActionSeparator,
		NcActions,
	},

	inject: {
		/**
		 * What `App.vue` decided about the panel: whether it is left out on
		 * this route, the entries it would have shown, and who is looking.
		 *
		 * @type {{ value: { railSuppressed: boolean, entries: object[], isAdmin: boolean, includePersonalSettings: boolean } }}
		 */
		launchpadChrome: {
			from: 'launchpadChrome',
			default: null,
		},

		/**
		 * The shared library's opener for the per-user settings dialog,
		 * provided by `CnAppRoot`. Absent outside the shell (tests).
		 *
		 * @type {(() => void)|null}
		 */
		cnOpenUserSettings: {
			from: 'cnOpenUserSettings',
			default: null,
		},
	},

	computed: {
		/**
		 * The chrome decision, unwrapped. Defaults keep the panel.
		 *
		 * @return {{ railSuppressed: boolean, entries: object[], isAdmin: boolean, includePersonalSettings: boolean }}
		 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
		 */
		chrome() {
			const value = this.launchpadChrome?.value ?? this.launchpadChrome
			return {
				railSuppressed: value?.railSuppressed === true,
				entries: Array.isArray(value?.entries) ? value.entries : [],
				isAdmin: value?.isAdmin === true,
				includePersonalSettings: value?.includePersonalSettings !== false,
			}
		},

		/**
		 * The menu's groups, one per panel section that has anything to
		 * show. The settings group opens with the personal settings and
		 * the admin settings link, as the panel's settings foldout does.
		 *
		 * @return {Array<{ id: string, items: object[] }>}
		 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
		 */
		groups() {
			const bySection = Object.fromEntries(SECTIONS.map((s) => [s, []]))
			for (const entry of this.chrome.entries) {
				const section = entry.section ?? 'main'
				if (!(section in bySection)) {
					continue
				}
				const item = resolveMenuItem(entry, this.cnOpenUserSettings)
				if (item) {
					bySection[section].push(item)
				}
			}
			const settings = []
			if (
				this.chrome.includePersonalSettings
				&& typeof this.cnOpenUserSettings === 'function'
			) {
				settings.push({
					id: 'personal-settings',
					label: t('launchpad', 'Personal settings'),
					onClick: this.cnOpenUserSettings,
				})
			}
			if (this.chrome.isAdmin) {
				settings.push({
					id: 'admin-settings-link',
					label: t('launchpad', 'Admin settings'),
					href: generateUrl('/settings/admin/{appId}', {
						appId: 'launchpad',
					}),
					external: false,
				})
			}
			bySection.settings = [...settings, ...bySection.settings]
			return SECTIONS.map((id) => ({ id, items: bySection[id] })).filter(
				(group) => group.items.length > 0,
			)
		},
	},

	methods: {
		t,
	},
}
</script>
