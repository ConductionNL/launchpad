<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="tile"
		class="tile-widget"
		:data-tile-id="tile.id"
		:style="{
			'--tile-bg-color': tile.backgroundColor || '#0082c9',
			'--tile-text-color': tile.textColor || '#ffffff',
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
				:menuLabel="t('launchpad', 'Tile menu')"
				:editLabel="t('launchpad', 'Edit tile')"
				:deleteLabel="t('launchpad', 'Delete tile')"
				@edit="$emit('edit')"
				@remove="$emit('remove')" />
		</div>

		<!-- REQ-HPING-004: health badge overlay. Fetches nothing (and renders
		     nothing) unless a ping is actually configured for this tile. -->
		<HealthPingBadge
			v-if="healthPingEnabled"
			:placementId="placementId"
			:interval="pingInterval" />

		<a
			:href="tileUrl"
			class="tile-widget__link"
			:target="tileTarget"
			rel="noopener noreferrer"
			@click="handleActivate">
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
				<span
					v-if="tile.iconType === 'class'"
					class="icon"
					:class="[tile.icon]" />
				<img
					v-else-if="tile.iconType === 'url'"
					:src="tile.icon"
					alt="Icon" />
				<span
					v-else-if="tile.iconType === 'emoji'"
					class="tile-widget__emoji"
					>{{ tile.icon }}</span
				>
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
					'--title-color': tile.textColor || '#ffffff',
				}">
				{{ tile.title }}
			</div>
		</a>
		<!-- launcher-tile-launch-types D2: the browser cannot tell whether a
		     program started, so say what to check after the click. -->
		<p
			v-if="showProgramHint"
			class="tile-widget__hint"
			role="status">
			{{ t('launchpad', 'Nothing happened? The program may not be installed on this computer.') }}
		</p>

		<!-- integrations-office-tiles REQ-OFT-002: a `create` tile asks for a
		     name and folder, then creates the file from the office app's own
		     blank template and opens it. -->
		<OfficeFileDialog
			v-if="isCreateTile"
			:open="createDialogOpen"
			:title="tile.title"
			:defaultName="tile.title"
			:busy="creating"
			@update:open="createDialogOpen = $event"
			@confirm="createFile" />
	</div>
</template>

<script>
import { CnDashboardIcon, CnWidgetEditCog } from '@conduction/nextcloud-vue'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineAsyncComponent } from 'vue'
import HealthPingBadge from './HealthPingBadge.vue'
import { useTileClickTracking } from '../composables/useTileClickTracking.js'
import { FLAG_PROP, REQUIRED_OBJECT_PROP } from '../constants/propDefinitions.js'
import { api } from '../services/api.js'
import { logger } from '../utils/logger.js'
import { launchHref, launchTarget, LINK_PROGRAM } from '../utils/tileLaunch.js'

const { recordTileClick } = useTileClickTracking()

export default {
	name: 'TileWidget',

	components: {
		CnWidgetEditCog,
		CnDashboardIcon,
		HealthPingBadge,
		// Loaded on first use: only a `create` office tile renders it.
		OfficeFileDialog: defineAsyncComponent(() => import('../dialogs/OfficeFileDialog.vue').then((module) => module.default)),
	},

	props: {
		tile: REQUIRED_OBJECT_PROP,

		editMode: FLAG_PROP,

		/**
		 * The widget placement id — forwarded to {@link HealthPingBadge}
		 * so it can call the per-placement health-ping endpoint.
		 *
		 * @type {number|string|null}
		 */
		placementId: {
			type: [Number, String],
			default: null,
		},

		/**
		 * The placement's content blob; a remote-desktop tile keeps its
		 * gateway or RDP settings there (launcher-tile-launch-types).
		 *
		 * @type {object|null}
		 */
		content: {
			type: Object,
			default: null,
		},

		/**
		 * Whether this tile has a health ping configured
		 * (REQ-HPING-004 "Ping disabled shows no badge").
		 *
		 * @type {boolean}
		 */
		healthPingEnabled: FLAG_PROP,

		/**
		 * The tile's configured ping interval in seconds.
		 *
		 * @type {number}
		 */
		pingInterval: {
			type: Number,
			default: 60,
		},
	},

	emits: ['edit', 'remove'],

	data() {
		return {
			showProgramHint: false,
			createDialogOpen: false,
			creating: false,
		}
	},

	computed: {
		/**
		 * Whether the tile creates a file instead of opening a link (REQ-OFT-002).
		 *
		 * @return {boolean} True for a `create` tile.
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		isCreateTile() {
			return this.tile.linkType === 'create'
		},

		/**
		 * @spec openspec/specs/tiles/spec.md
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-5
		 */
		tileUrl() {
			const launch = launchHref(this.tile, this.content, this.placementId, generateUrl)
			if (launch !== null) {
				return launch
			}
			const value = this.tile.linkValue
			if (this.isCreateTile) {
				return '#'
			}
			if (this.tile.linkType === 'app') {
				return generateUrl('/apps/' + value)
			}
			// Internal absolute paths (e.g. /apps/deck, /apps/text) MUST go
			// through generateUrl so instances served under a sub-directory or
			// requiring an /index.php prefix route correctly. External URLs
			// (http(s)://, protocol-relative //, mailto:, tel:) pass through.
			if (
				typeof value === 'string'
				&& value.startsWith('/')
				&& value.startsWith('//') === false
			) {
				return generateUrl(value)
			}
			return value
		},

		/**
		 * Where the link opens: a web address in a new tab, a Nextcloud page
		 * in place, and per launch type as design D2 says.
		 *
		 * @return {string}
		 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-5
		 */
		tileTarget() {
			return launchTarget(this.tile, this.content) ?? (this.tile.linkType === 'url' ? '_blank' : '_self')
		},
	},

	/** @spec openspec/specs/tiles/spec.md */
	mounted() {
		logger.debug(
			'[TileWidget] Mounted with tile:',
			JSON.stringify(
				{
					id: this.tile?.id,
					title: this.tile?.title,
					backgroundColor: this.tile?.backgroundColor,
					textColor: this.tile?.textColor,
					icon: this.tile?.icon?.substring(0, 30),
					iconType: this.tile?.iconType,
				},
				null,
				2,
			),
		)
		logger.debug(
			'[TileWidget] Full tile object keys:',
			this.tile ? Object.keys(this.tile) : 'tile is null',
		)

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

	methods: {
		/**
		 * REQ-TANLT-002 — fire a fire-and-forget tile-click record call
		 * on activation. Never blocks or interferes with the anchor's own
		 * navigation (the browser proceeds to `tileUrl` regardless of
		 * whether the tracking call succeeds, fails, or is suppressed). A
		 * single `@click` handler covers both mouse clicks and keyboard
		 * (Enter) activation — native `<a>` elements fire a `click` event
		 * for both. A `create` tile opens its name dialog instead of
		 * following the link (REQ-OFT-002).
		 *
		 * @param {MouseEvent} [event] The click.
		 * @return {void}
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		handleActivate(event) {
			recordTileClick(this.tile?.id)
			if (this.tile?.linkType === LINK_PROGRAM) {
				this.showProgramHint = true
			}
			if (this.isCreateTile) {
				event?.preventDefault()
				if (!this.editMode) {
					this.createDialogOpen = true
				}
			}
		},

		/**
		 * Create the file the dialog asked for and open it in its editor. A
		 * refusal keeps the dialog open with the server's message.
		 *
		 * @param {{name: string, folder: string}} choice The name and folder.
		 * @return {Promise<void>}
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		async createFile(choice) {
			this.creating = true
			try {
				const response = await api.createOfficeFile({
					creatorId: this.tile.linkValue,
					name: choice.name,
					folder: choice.folder,
				})
				this.createDialogOpen = false
				window.location.assign(response.data.url)
			} catch (error) {
				logger.warn('[TileWidget] The office file could not be created:', error)
				showError(error?.response?.data?.message || t('launchpad', 'The file could not be created.'))
			} finally {
				this.creating = false
			}
		},
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

.tile-widget__hint {
	position: absolute;
	inset-inline: 0;
	bottom: 0;
	margin: 0;
	padding: 4px 8px;
	font-size: 0.85em;
	color: var(--color-main-text);
	background-color: var(--color-main-background);
	text-align: center;
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
	transition:
		transform 0.2s ease,
		opacity 0.2s ease;
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
	overflow-wrap: break-word;
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
