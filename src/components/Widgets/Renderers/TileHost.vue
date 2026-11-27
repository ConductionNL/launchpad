<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div v-if="isCreateTile" class="tile-host">
		<TileWidget :tile="createTile" :editMode="editMode" />
	</div>
	<!-- REQ-APPTILE-002: an App or link tile in app mode is the shared app
	     tile, drawn in the tile's text colour (REQ-APPTILE-005). -->
	<div
		v-else-if="isAppMode"
		class="tile-host tile-host--app"
		data-testid="tile-host-app"
		:style="{ '--lp-tile-text': textCss }">
		<CnAppTileWidget
			:content="appContent"
			:placement="placement"
			v-bind="$attrs" />
	</div>
	<CnDashTileWidget
		v-else
		:content="linkContent"
		:placement="placement"
		v-bind="$attrs" />
</template>

<script>
import { CnAppTileWidget, CnDashTileWidget } from '@conduction/nextcloud-vue'
import TileWidget from '../../TileWidget.vue'
import { FLAG_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { appTileAsTileContent, appTileColourKey, RETIRED_APP_TILE, tileMode } from '../../../utils/tileMode.js'
import { isTextColour, textColourValue, tileTextColour } from '../../../utils/tileTextColour.js'

/**
 * TileHost — the App or link tile (`tile`) inside LaunchPad, and the render
 * alias of the retired `app-tile`. In app mode the tile is the shared
 * CnAppTileWidget; a link tile whose link type is `create` (an office "New
 * ..." tile, REQ-OFT-002) renders through LaunchPad's TileWidget, which asks
 * for a name and creates the file; every other link tile renders through the
 * shared CnDashTileWidget. Both modes draw in the tile's text colour.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-an-app-tile-shows-one-app-the-viewer-may-open-req-apptile-002
 */
export default {
	name: 'TileHost',

	components: {
		CnAppTileWidget,
		CnDashTileWidget,
		TileWidget,
	},

	inheritAttrs: false,

	props: {
		content: OBJECT_PROP,

		placement: OBJECT_PROP,

		editMode: FLAG_PROP,
	},

	computed: {
		/**
		 * The content as an App or link tile: a retired app tile reads as a
		 * tile in app mode.
		 *
		 * @return {object} The content.
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
		 */
		tileContent() {
			const type = this.placement?.widgetId ?? this.placement?.type
			return type === RETIRED_APP_TILE ? appTileAsTileContent(this.content) : (this.content || {})
		},

		/**
		 * Whether the tile opens an app.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-an-app-tile-shows-one-app-the-viewer-may-open-req-apptile-002
		 */
		isAppMode() {
			return tileMode(this.tileContent) === 'app'
		},

		/**
		 * The text colour as CSS (white when none is stored).
		 *
		 * @return {string}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		textCss() {
			return textColourValue(tileTextColour(this.tileContent))
		},

		/**
		 * The content the shared app tile reads: its colour as a palette key.
		 *
		 * @return {object}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-an-app-tile-shows-one-app-the-viewer-may-open-req-apptile-002
		 */
		appContent() {
			return { ...this.tileContent, colour: appTileColourKey(this.tileContent) }
		},

		/**
		 * The content the shared link tile reads: a stored text colour wins.
		 *
		 * @return {object}
		 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
		 */
		linkContent() {
			const content = this.tileContent
			return isTextColour(content.textColour) ? { ...content, textColor: textColourValue(content.textColour) } : content
		},

		/**
		 * Whether this tile creates a file.
		 *
		 * @return {boolean} True for a `create` tile.
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		isCreateTile() {
			return (this.content?.linkType ?? this.placement?.tileLinkType) === 'create'
		},

		/**
		 * The tile in the shape TileWidget reads, from the child's content.
		 *
		 * @return {object} The tile.
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		createTile() {
			const c = this.content || {}
			return {
				id: this.placement?.id ?? ('create-' + (c.linkValue || '')),
				title: c.title || '',
				icon: c.icon || 'icon-filetype-file',
				iconType: c.iconType || 'class',
				backgroundColor: c.backgroundColor || '',
				textColor: c.textColor || '',
				linkType: 'create',
				linkValue: c.linkValue || '',
			}
		},
	},
}
</script>

<style scoped>
.tile-host {
	position: relative;
	height: 100%;
	width: 100%;
}

/* REQ-APPTILE-005: the shared app tile paints in white; the host repaints
   its title, icon and count in the tile's text colour. */
.tile-host--app :deep(.cn-app-tile),
.tile-host--app :deep(.cn-app-tile__glyph),
.tile-host--app :deep(.cn-app-tile__fallback) {
	color: var(--lp-tile-text, #fff);
}

.tile-host--app :deep(.cn-app-tile__icon) {
	background-color: var(--lp-tile-text, #fff);
}

.tile-host--app :deep(.cn-app-tile__count) {
	background: var(--lp-tile-text, #fff);
	color: var(--cn-app-tile-background, #1b1c1d);
}
</style>
