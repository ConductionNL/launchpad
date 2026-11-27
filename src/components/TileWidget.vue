<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="tile"
		class="tile-widget"
		:class="{ 'tile-widget--icon-only': iconOnly }"
		:data-tile-id="tile.id"
		:style="{
			'--tile-bg-color': backgroundCss,
			'--tile-text-color': tile.textColor || '#ffffff',
		}">
		<!-- Shared edit cog in edit mode (Edit / Delete), matching the
		     OpenBuild widget chrome used across the dashboard. The absolute
		     positioning lives on this wrapper DIV, not on CnWidgetEditCog
		     itself: the cog's root is an NcActions `.action-item` which sets
		     `position: relative` at equal specificity, so positioning the
		     component directly loses the cascade tie and the cog drops into
		     flow. Wrapping matches the shared nc-vue CnDashboardPage pattern. -->
		<span
			v-if="editMode"
			class="tile-widget__grip"
			aria-hidden="true"
			data-testid="tile-drag-grip">
			<DragVertical :size="16" />
		</span>
		<div v-if="editMode" class="tile-widget__edit">
			<!-- REQ-WDG-047: the shared edit menu, with Create embed token. -->
			<WidgetEditMenu
				:menuLabel="t('launchpad', 'Tile menu')"
				:editLabel="t('launchpad', 'Edit tile')"
				:deleteLabel="t('launchpad', 'Delete tile')"
				:showWhoSees="false"
				:removeLocked="removeLocked"
				:embedHref="embedHref"
				:embedDisabled="placementId === null || placementId === undefined || placementId === ''"
				@edit="$emit('edit')"
				@remove="$emit('remove')" />
		</div>

		<!-- REQ-HPING-004: health badge overlay. Fetches nothing (and renders
		     nothing) unless a ping is actually configured for this tile. -->
		<HealthPingBadge
			v-if="healthPingEnabled"
			:placementId="placementId"
			:interval="pingInterval"
			:hidden="iconOnly"
			@reading="onPingReading" />

		<a
			:href="tileUrl"
			class="tile-widget__link"
			:target="anchorTarget"
			:title="iconOnly ? tile.title : null"
			:aria-label="iconOnly ? accessibleName : null"
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
			<!-- An icon-only tile marks itself as the app tiles do (LpVandaag):
			     a pin at the top left, a count or an outage dot at the top right. -->
			<template v-if="iconOnly">
				<Pin
					v-if="content?.pinned === true"
					class="tile-widget__pin"
					:size="14"
					aria-hidden="true"
					data-testid="tile-pin" />
				<span
					v-if="pingDown"
					class="tile-widget__outage"
					aria-hidden="true"
					data-testid="tile-outage" />
				<span
					v-else-if="badgeText !== ''"
					class="tile-widget__badge"
					aria-hidden="true"
					data-testid="tile-badge">{{ badgeText }}</span>
			</template>
			<div
				v-if="!iconOnly"
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
import { CnDashboardIcon } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineAsyncComponent } from 'vue'
import DragVertical from 'vue-material-design-icons/DragVertical.vue'
import Pin from 'vue-material-design-icons/Pin.vue'
import HealthPingBadge from './HealthPingBadge.vue'
import WidgetEditMenu from './Widgets/WidgetEditMenu.vue'
import { useTileClickTracking } from '../composables/useTileClickTracking.js'
import { FLAG_PROP, REQUIRED_OBJECT_PROP } from '../constants/propDefinitions.js'
import { api } from '../services/api.js'
import { COUNT_TTL_MS, countLabel, resolveTileCount, sourceType } from '../services/tileCount.js'
import { useTilePanelStore } from '../stores/tilePanel.js'
import { isOnOfficeNetwork } from '../utils/loadInitialState.js'
import { logger } from '../utils/logger.js'
import { tileBackground } from '../utils/tileColour.js'
import { launchHref, launchTarget, LINK_PROGRAM } from '../utils/tileLaunch.js'
import { isSameOrigin, NEW_TAB, resolveLinkTarget, SIDE_PANEL } from '../utils/tileLinkTarget.js'

const { recordTileClick } = useTileClickTracking()

export default {
	name: 'TileWidget',

	components: {
		Pin,
		DragVertical,
		WidgetEditMenu,
		CnDashboardIcon,
		HealthPingBadge,
		// Loaded on first use: only a `create` office tile renders it.
		OfficeFileDialog: defineAsyncComponent(() => import('../dialogs/OfficeFileDialog.vue').then((module) => module.default)),
	},

	props: {
		tile: REQUIRED_OBJECT_PROP,

		editMode: FLAG_PROP,

		/** The host refuses removing this tile, so the cog offers no Delete. */
		removeLocked: FLAG_PROP,
		// REQ-WDG-047: the admin link that creates an embed token for this
		// tile, offered in the edit menu only; '' when the viewer may not.
		embedHref: {
			type: String,
			default: '',
		},

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

		/**
		 * The tile's own link target (`content.linkTarget`): `same-tab`,
		 * `new-tab` or `side-panel`, or null to follow the dashboard.
		 *
		 * @type {string|null}
		 */
		linkTarget: {
			type: String,
			default: null,
		},

		/**
		 * The dashboard's default link target, or null for the link-type rule.
		 *
		 * @type {string|null}
		 */
		defaultLinkTarget: {
			type: String,
			default: null,
		},
	},

	emits: ['edit', 'remove'],

	data() {
		return {
			showProgramHint: false,
			createDialogOpen: false,
			creating: false,
			// REQ-ICT-002: the live count; null until the source answers.
			count: null,
			countTimer: null,
			// REQ-ICT-003: the health ping's last reading.
			pingDown: false,
			pingDownSince: null,
		}
	},

	computed: {
		/**
		 * Whether the tile shows only its glyph, with the name as its tooltip
		 * and accessible name (the app tiles of LpVandaag and LpWerkplek).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-a-tile-can-be-an-app-tile-with-only-its-icon-req-ict-001
		 */
		iconOnly() {
			return this.content?.iconOnly === true
		},

		/**
		 * The count on an icon-only tile, '' for none; above 99 it reads 99+.
		 *
		 * @return {string}
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-a-tile-can-be-an-app-tile-with-only-its-icon-req-ict-001
		 */
		badgeText() {
			return countLabel(this.count)
		},

		/**
		 * The tile's background: its theme colour token, else its hex colour.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
		 */
		backgroundCss() {
			return tileBackground(this.tile, this.content)
		},

		/**
		 * The time the outage began, in the viewer's clock and in the
		 * viewer's Nextcloud locale (not the browser's, which wrote
		 * "storing sinds 10:28 PM" for a Dutch user), or ''.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-an-outage-req-ict-003
		 */
		downSinceText() {
			const date = this.pingDownSince ? new Date(this.pingDownSince) : null
			if (!date || Number.isNaN(date.getTime())) {
				return ''
			}
			return date.toLocaleTimeString(getCanonicalLocale(), { hour: '2-digit', minute: '2-digit' })
		},

		/**
		 * The tile's name with its outage, count and pin, for a reader that
		 * cannot see the marks.
		 *
		 * @return {string}
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-a-tile-can-be-an-app-tile-with-only-its-icon-req-ict-001
		 */
		accessibleName() {
			let name = this.tile.title
			if (this.pingDown) {
				name = this.downSinceText === ''
					? t('launchpad', '{app}, outage', { app: name })
					: t('launchpad', '{app}, outage since {time}', { app: name, time: this.downSinceText })
			} else if (this.badgeText !== '') {
				name = t('launchpad', '{app}, {count} open', { app: name, count: this.badgeText })
			}
			return this.content?.pinned === true ? t('launchpad', '{app}, pinned', { app: name }) : name
		},

		/**
		 * How this tile opens: its own target, the dashboard default, or the
		 * link-type rule (launcher-tile-open-behaviour D2).
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		resolvedTarget() {
			return resolveLinkTarget(this.linkTarget, this.defaultLinkTarget, this.tile.linkType)
		},

		/**
		 * The anchor's `target`: a launch type's own target first
		 * (launcher-tile-launch-types D2), else the resolved open-in target. A
		 * `side-panel` tile keeps `_self` so that, if
		 * the panel cannot take it, a plain click still navigates somewhere;
		 * handleActivate() opens the panel instead.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
		 */
		anchorTarget() {
			return launchTarget(this.tile, this.content) ?? (this.resolvedTarget === NEW_TAB ? '_blank' : '_self')
		},

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
		 * Whether this tile opens its office-network address: only when the
		 * server saw the request come from an office network and the tile has one.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
		 */
		opensInternalUrl() {
			return typeof this.tile.internalUrl === 'string'
				&& this.tile.internalUrl !== ''
				&& isOnOfficeNetwork()
		},

		/**
		 * @spec openspec/specs/tiles/spec.md
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-5
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
		 */
		tileUrl() {
			const launch = launchHref(this.tile, this.content, this.placementId, generateUrl)
			if (launch !== null) {
				return launch
			}
			const value = this.opensInternalUrl ? this.tile.internalUrl : this.tile.linkValue
			if (this.isCreateTile) {
				return '#'
			}
			if (this.opensInternalUrl === false && this.tile.linkType === 'app') {
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

		this.startCount()

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

	beforeUnmount() {
		this.stopCount()
	},

	methods: {
		/**
		 * Read the live count of an icon-only tile with a count source, and
		 * again each minute while the tile is shown (REQ-ICT-002).
		 *
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
		 */
		startCount() {
			if (!this.iconOnly || sourceType(this.content?.countSource) === null) {
				return
			}
			this.loadCount()
			this.countTimer = setInterval(() => this.loadCount(), COUNT_TTL_MS)
		},

		/**
		 * Stop reading the count.
		 *
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
		 */
		stopCount() {
			if (this.countTimer !== null) {
				clearInterval(this.countTimer)
				this.countTimer = null
			}
		},

		/**
		 * Ask the count source for the count; no answer is no count.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
		 */
		async loadCount() {
			this.count = await resolveTileCount(this.content?.countSource, getCurrentUser()?.uid || '')
		},

		/**
		 * The health ping's reading: an offline service shows the outage dot
		 * in place of the count (REQ-ICT-003); a reading that clears it brings
		 * the count back.
		 *
		 * @param {{state: (string|null), downSince: (string|null)}} reading The reading.
		 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-an-outage-req-ict-003
		 */
		onPingReading(reading) {
			this.pingDown = reading?.state === 'offline'
			this.pingDownSince = this.pingDown ? (reading?.downSince ?? null) : null
		},

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
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
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
				return
			}

			// Launcher-tile-open-behaviour D3: a side-panel tile opens beside
			// the grid instead of navigating. In edit mode the tile is being
			// arranged, so it never opens anything new.
			if (this.resolvedTarget !== SIDE_PANEL || this.editMode || !this.tileUrl) {
				return
			}
			event?.preventDefault?.()
			useTilePanelStore().open({
				url: this.tileUrl,
				title: this.tile?.title || '',
				sameOrigin: isSameOrigin(this.tileUrl),
				opener: event?.currentTarget || null,
			})
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

/* An icon-only tile is a square of colour with a 48px white glyph and no
   label, as the boards draw the app tiles. The theme underlines and colours
   every link with !important, hence the weight here. */
.tile-widget.tile-widget--icon-only {
	border-radius: 8px;
	/* REQ-ICT-001: an app tile is a square. A grid row (60 px) is lower than
	   a desktop column is wide, so the square takes the cell's height and its
	   width follows; a column narrower than the row caps it. */
	width: auto;
	max-width: 100%;
	aspect-ratio: 1 / 1;
}

.tile-widget--icon-only .tile-widget__link {
	padding: 0;
	gap: 0;
	text-decoration: none !important;
}

/* REQ-ICT-001: the white icon fills about 45% of the square, whatever
   the column width; a container query unit keeps glyph and emoji in step. */
.tile-widget.tile-widget--icon-only {
	container-type: size;
}

.tile-widget--icon-only .tile-widget__icon {
	min-width: 0;
	width: 45cqmin;
	height: 45cqmin;
	font-size: 45cqmin;
}

.tile-widget--icon-only .tile-widget__icon :deep(svg) {
	width: 100%;
	height: 100%;
}

.tile-widget--icon-only .tile-widget__icon span.icon {
	width: 100%;
	height: 100%;
	background-size: 100%;
}

/* The marks of an icon-only tile, as CnAppTile draws them. */
.tile-widget__pin {
	position: absolute;
	top: 6px;
	inset-inline-start: 6px;
	display: flex;
	opacity: 0.85;
}

.tile-widget__badge {
	position: absolute;
	top: 6px;
	inset-inline-end: 6px;
	box-sizing: border-box;
	min-width: 20px;
	height: 20px;
	padding: 0 6px;
	border-radius: 10px;
	background: #fff;
	color: #1b1c1d;
	font-size: 12px;
	font-weight: 700;
	line-height: 20px;
	text-align: center;
}

.tile-widget__outage {
	position: absolute;
	top: 6px;
	inset-inline-end: 6px;
	box-sizing: border-box;
	width: 12px;
	height: 12px;
	border: 2px solid #fff;
	border-radius: 50%;
	background: var(--color-error, #d91f11);
}

/* In edit mode a tile shows a drag grip at its top left, a small white
   square as LpBewerken draws it. */
.tile-widget__grip {
	position: absolute;
	top: 6px;
	inset-inline-start: 6px;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	border-radius: 4px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	pointer-events: none;
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
