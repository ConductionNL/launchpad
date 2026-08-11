<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<li
		class="org-nav-item"
		:class="{
			'org-nav-item--active': isActive,
			'org-nav-item--has-active-child': hasActiveDescendant,
			'org-nav-item--section': !node.url,
		}"
		role="treeitem"
		:aria-expanded="hasChildren ? expanded : null">
		<!--
			The disclosure toggle is a SIBLING of the row, not a child of it.

			It used to be a `<span @click.prevent.stop="toggle">` INSIDE the
			`<a href>`. That is a nested interactive control: the browser gives
			the anchor the tab stop and the span never gets one, so expanding a
			section was reachable with a mouse and by no other means. Giving the
			span `role="button" tabindex="0"` would have satisfied the gate
			while leaving a control inside a control — still invalid HTML and
			still an axe `nested-interactive` violation. Hoisting it out is the
			fix that actually makes the toggle operable, so it is a real
			`<button>` with its own tab stop and Enter/Space handling for free.

			The indent moves to the wrapper so the toggle indents with its row
			instead of sitting flush left at every depth.
		-->
		<div class="org-nav-item__rowwrap">
			<span class="org-nav-item__indent" :style="{ paddingLeft: indentPx }" />
			<button
				v-if="hasChildren"
				type="button"
				class="org-nav-item__toggle"
				:aria-label="toggleLabel"
				@click="toggle">
				{{ expanded ? '▾' : '▸' }}
			</button>
			<span
				v-else
				class="org-nav-item__toggle org-nav-item__toggle--placeholder"
				aria-hidden="true" />
			<a
				v-if="node.url"
				:href="node.url"
				:target="node.openInNewTab ? '_blank' : null"
				:rel="node.openInNewTab ? 'noopener noreferrer' : null"
				class="org-nav-item__row"
				@click="onActivate">
				<CnDashboardIcon
					v-if="node.icon"
					class="org-nav-item__icon"
					:name="iconName"
					:size="24" />
				<span class="org-nav-item__label">{{ node.label }}</span>
			</a>
			<button
				v-else
				type="button"
				class="org-nav-item__row org-nav-item__row--section"
				@click="toggle">
				<CnDashboardIcon
					v-if="node.icon"
					class="org-nav-item__icon"
					:name="iconName"
					:size="24" />
				<span class="org-nav-item__label">{{ node.label }}</span>
			</button>
		</div>
		<ul v-if="hasChildren && expanded" class="org-nav-item__children" role="group">
			<OrgNavigationItem
				v-for="child in node.children"
				:key="child.id"
				:node="child"
				:level="level + 1"
				:current-url="currentUrl"
				@navigate="$emit('navigate')" />
		</ul>
	</li>
</template>

<script>
import { CnDashboardIcon } from '@conduction/nextcloud-vue'
import { normaliseIconValue } from '../services/iconCatalogue.js'

/**
 * OrgNavigationItem — recursive node renderer for the org-nav tree
 * (REQ-ONAV-005, REQ-ONAV-006, REQ-ONAV-009).
 *
 * Each node renders as either:
 *   - an `<a>` link when `node.url` is non-null; clicking emits
 *     `navigate` so the parent panel can close the mobile drawer
 *   - a `<button>` section header that toggles the children list
 *
 * Active-item detection (REQ-ONAV-009) compares the node's `url` to
 * the panel-supplied `currentUrl` using prefix-or-equal semantics.
 * Sections that contain an active descendant auto-expand
 * (`expanded = true` on mount when `hasActiveDescendant`).
 *
 * Icons (REQ-ONAV-006) render via the shared `CnDashboardIcon`, which
 * resolves any value the icon picker emits — a URL (→ `<img>`), an SVG
 * path string (→ inline `<svg>`), or a registry key (→ MDI component).
 */
export default {
	name: 'OrgNavigationItem',

	components: {
		CnDashboardIcon,
	},

	props: {
		node: {
			type: Object,
			required: true,
		},
		level: {
			type: Number,
			default: 1,
		},
		currentUrl: {
			type: String,
			default: '',
		},
	},

	emits: ['navigate'],

	data() {
		return {
			expanded: false,
		}
	},

	computed: {
		hasChildren() {
			return Array.isArray(this.node.children) && this.node.children.length > 0
		},

		/**
		 * Accessible name for the disclosure toggle.
		 *
		 * The glyph the button renders is `▾`/`▸`, which a screen reader
		 * announces as "black down-pointing small triangle" or skips entirely
		 * — neither says what the control does or which section it belongs
		 * to. Naming it after the node makes the tree navigable when several
		 * sections are collapsed.
		 *
		 * @return {string} e.g. "Collapse Finance" / "Expand Finance".
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		toggleLabel() {
			return this.expanded
				? this.t('launchpad', 'Collapse {label}', { label: this.node.label })
				: this.t('launchpad', 'Expand {label}', { label: this.node.label })
		},

		/**
		 * Render-ready icon value. Legacy free-text names (from the old
		 * free-text icon input) are mapped to their MDI path so they don't
		 * silently fall back to the default icon; picker-emitted paths and
		 * URLs pass through unchanged.
		 *
		 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
		 * @return {string|null} value for `CnDashboardIcon :name`.
		 */
		iconName() {
			return normaliseIconValue(this.node.icon)
		},

		isActive() {
			return this.urlMatches(this.node.url)
		},

		hasActiveDescendant() {
			return this.descendantMatches(this.node.children)
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		indentPx() {
			// Visual indent — 12px per level beyond root.
			return ((this.level - 1) * 12) + 'px'
		},
	},

	/** @spec openspec/specs/navigation-editor-org/spec.md */
	mounted() {
		// Auto-expand when a descendant is active (REQ-ONAV-009).
		if (this.hasActiveDescendant) {
			this.expanded = true
		}
	},

	methods: {
		/** @spec openspec/specs/navigation-editor-org/spec.md */
		toggle() {
			if (this.hasChildren) {
				this.expanded = !this.expanded
			}
		},

		/** @spec openspec/specs/navigation-editor-org/spec.md */
		onActivate() {
			this.$emit('navigate')
		},

		/**
		 * URL-match helper (REQ-ONAV-009). A node is active when its
		 * URL exactly matches `currentUrl` OR is a path prefix of it.
		 *
		 * @param {string|null} url Candidate URL.
		 * @return {boolean}
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		urlMatches(url) {
			if (!url || !this.currentUrl) {
				return false
			}
			if (url === this.currentUrl) {
				return true
			}
			// Prefix match — but require a path-segment boundary so
			// '/foo' doesn't match '/foobar'.
			if (this.currentUrl.startsWith(url)) {
				const next = this.currentUrl.charAt(url.length)
				return next === '' || next === '/' || next === '?' || next === '#'
			}
			return false
		},

		/**
		 * Recursive descendant scanner used by the auto-expand logic.
		 *
		 * @param {Array<object>|null} children Child nodes.
		 * @return {boolean}
		 * @spec openspec/specs/navigation-editor-org/spec.md
		 */
		descendantMatches(children) {
			if (!Array.isArray(children) || children.length === 0) {
				return false
			}
			for (const child of children) {
				if (this.urlMatches(child.url)) {
					return true
				}
				if (this.descendantMatches(child.children)) {
					return true
				}
			}
			return false
		},
	},
}
</script>

<style scoped>
.org-nav-item {
	list-style: none;
}

/* The row and its disclosure toggle are siblings inside this wrapper (they
   used to be nested, which made the toggle keyboard-unreachable). The
   wrapper owns the horizontal layout the row used to own. */
.org-nav-item__rowwrap {
	display: flex;
	align-items: center;
	gap: 6px;
}

.org-nav-item__row {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 6px 8px;
	color: inherit;
	text-decoration: none;
	border-radius: 4px;
	background: transparent;
	border: none;
	width: 100%;
	text-align: left;
	cursor: pointer;
	font: inherit;
}

.org-nav-item__row:hover,
.org-nav-item__row:focus-visible {
	background: var(--color-background-hover, #f0f0f0);
	outline: none;
}

.org-nav-item--active > .org-nav-item__rowwrap > .org-nav-item__row {
	background: var(--color-primary-element-light, rgba(25, 118, 210, 0.12));
	font-weight: 600;
}

.org-nav-item--has-active-child > .org-nav-item__rowwrap > .org-nav-item__row {
	font-weight: 600;
}

.org-nav-item__toggle {
	display: inline-block;
	width: 12px;
	flex: 0 0 auto;
	text-align: center;
	font-size: 0.8em;
	cursor: pointer;
	/* It is a real <button> now, so the UA's button chrome has to be reset
	   to keep the glyph looking exactly as it did as a <span>. */
	padding: 0;
	background: transparent;
	border: none;
	color: inherit;
	font-family: inherit;
	line-height: 1;
}

.org-nav-item__toggle:focus-visible {
	outline: 2px solid var(--color-primary-element, #0082c9);
	outline-offset: 1px;
	border-radius: 2px;
}

.org-nav-item__toggle--placeholder {
	visibility: hidden;
}

.org-nav-item__icon {
	width: 24px;
	height: 24px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.org-nav-item__label {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.org-nav-item__children {
	list-style: none;
	margin: 0;
	padding: 0;
}
</style>
