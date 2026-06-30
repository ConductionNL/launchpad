<!--
  - SPDX-FileCopyrightText: 2024 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="tile"
		class="tile-widget"
		:data-tile-id="tile.id"
		:style="{
			'--tile-bg-color': tile.backgroundColor || '#0082c9',
			'--tile-text-color': tile.textColor || '#ffffff'
		}">
		<!-- Shared edit cog in edit mode (Edit / Delete), matching the
		     OpenBuild widget chrome used across the dashboard. The absolute
		     positioning lives on this wrapper DIV, not on CnWidgetEditCog
		     itself: the cog's root is an NcActions `.action-item` which sets
		     `position: relative` at equal specificity, so positioning the
		     component directly loses the cascade tie and the cog drops into
		     flow. Wrapping matches the shared nc-vue CnDashboardPage pattern. -->
		<div v-if="editMode" class="tile-widget__edit">
			<CnWidgetEditCog
				:menu-label="t('launchpad', 'Tile menu')"
				:edit-label="t('launchpad', 'Edit tile')"
				:delete-label="t('launchpad', 'Delete tile')"
				@edit="$emit('edit')"
				@remove="$emit('remove')" />
		</div>

		<a
			:href="tileUrl"
			class="tile-widget__link"
			:target="tile.linkType === 'url' ? '_blank' : '_self'"
			rel="noopener noreferrer">
			<!-- SVG icon -->
			<svg
				v-if="tile.iconType === 'svg'"
				class="tile-widget__icon"
				:style="{ fill: tile.textColor || '#ffffff' }"
				viewBox="0 0 24 24">
				<path :d="tile.icon" />
			</svg>
			<!-- Icon class or emoji or URL or MDI registry name -->
			<div v-else class="tile-widget__icon">
				<span v-if="tile.iconType === 'class'" :class="['icon', tile.icon]" />
				<img v-else-if="tile.iconType === 'url'" :src="tile.icon" alt="Icon">
				<span v-else-if="tile.iconType === 'emoji'" class="tile-widget__emoji">{{ tile.icon }}</span>
				<!-- MDI registry name (export/import + demo-showcase tiles). The
				     MDI component fills with currentColor, so tint via color. -->
				<CnDashboardIcon
					v-else-if="tile.iconType === 'mdi'"
					:name="tile.icon"
					:size="64"
					:style="{ color: tile.textColor || '#ffffff' }" />
			</div>
			<div
				class="tile-widget__title"
				:style="{
					color: tile.textColor || '#ffffff',
					'--title-color': tile.textColor || '#ffffff'
				}">
				{{ tile.title }}
			</div>
		</a>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { CnWidgetEditCog, CnDashboardIcon } from '@conduction/nextcloud-vue'

export default {
	name: 'TileWidget',

	components: {
		CnWidgetEditCog,
		CnDashboardIcon,
	},

	props: {
		tile: {
			type: Object,
			required: true,
		},
		editMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['edit', 'remove'],

	computed: {
		/** @spec openspec/specs/tiles/spec.md */
		tileUrl() {
			const value = this.tile.linkValue
			if (this.tile.linkType === 'app') {
				return generateUrl('/apps/' + value)
			}
			// Internal absolute paths (e.g. /apps/deck, /apps/text) MUST go
			// through generateUrl so instances served under a sub-directory or
			// requiring an /index.php prefix route correctly. External URLs
			// (http(s)://, protocol-relative //, mailto:, tel:) pass through.
			if (typeof value === 'string' && value.startsWith('/') && value.startsWith('//') === false) {
				return generateUrl(value)
			}
			return value
		},
	},

	/** @spec openspec/specs/tiles/spec.md */
	mounted() {
		console.log('[TileWidget] Mounted with tile:', JSON.stringify({
			id: this.tile?.id,
			title: this.tile?.title,
			backgroundColor: this.tile?.backgroundColor,
			textColor: this.tile?.textColor,
			icon: this.tile?.icon?.substring(0, 30),
			iconType: this.tile?.iconType,
		}, null, 2))
		console.log('[TileWidget] Full tile object keys:', this.tile ? Object.keys(this.tile) : 'tile is null')

		// Add dynamic style to override nldesign's aggressive CSS.
		const styleId = `tile-${this.tile.id}-style`
		if (!document.getElementById(styleId)) {
			const style = document.createElement('style')
			style.id = styleId
			style.textContent = `
				.tile-widget[data-tile-id="${this.tile.id}"] .tile-widget__title {
					color: ${this.tile.textColor || '#ffffff'} !important;
				}
			`
			document.head.appendChild(style)
		}
	},
}
</script>

<style scoped>
.tile-widget {
	height: 100%;
	width: 100%;
	position: absolute;
	top: 0;
	left: 0;
	border-radius: 0;
	border: none;
	overflow: hidden;
	background-color: var(--tile-bg-color) !important;
}

.tile-widget__link {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;
	width: 100%;
	text-decoration: none;
	border-radius: 0;
	padding: 20px;
	gap: 12px;
	transition: transform 0.2s ease, opacity 0.2s ease;
	box-shadow: none;
	background-color: var(--tile-bg-color) !important;
	color: var(--tile-text-color) !important;
}

.tile-widget__link:hover {
	transform: scale(1.02);
	opacity: 0.95;
	box-shadow: none;
}

/* WCAG 2.2 SC 2.3.3 — honour the user's reduced-motion preference (hydra gate-45) */
@media (prefers-reduced-motion: reduce) {
	.tile-widget__link {
		transition: none;
	}
}

.tile-widget__icon {
	font-size: 64px;
	width: auto;
	min-width: 64px;
	max-width: 80%;
	height: 64px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	background: transparent !important;
}

/* Nextcloud icon classes need the icon class wrapper and white filter */
.tile-widget__icon span.icon {
	display: inline-block;
	width: 64px;
	height: 64px;
	background-size: 64px;
	background-color: transparent !important;
	filter: brightness(0) invert(1);
}

.tile-widget__icon img {
	height: 100%;
	width: auto;
	max-width: 100%;
	object-fit: contain;
	filter: none;
	background: transparent !important;
}

.tile-widget__emoji {
	filter: none !important;
	font-size: 64px;
	background: transparent !important;
}

.tile-widget__title {
	font-size: 18px;
	font-weight: 700;
	text-align: center;
	word-break: break-word;
	line-height: 1.3;
	background: transparent !important;
}

/* Very specific selector to override nldesign CSS */
.tile-widget .tile-widget__link .tile-widget__title {
	color: var(--tile-text-color) !important;
}

/* Position the shared white cog over the tile's top-right corner. The
   button's own white styling lives in WidgetEditCog. */
.tile-widget__edit {
	position: absolute;
	top: 8px;
	right: 8px;
	z-index: 10;
}
</style>
