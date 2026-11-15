<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div v-if="isCreateTile" class="tile-host">
		<TileWidget :tile="createTile" :editMode="editMode" />
	</div>
	<CnDashTileWidget
		v-else
		:content="content"
		:placement="placement"
		v-bind="$attrs" />
</template>

<script>
import { CnDashTileWidget } from '@conduction/nextcloud-vue'
import TileWidget from '../../TileWidget.vue'
import { FLAG_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'

/**
 * TileHost — the `tile` widget type inside LaunchPad. A tile whose link type
 * is `create` (an office "New ..." tile, REQ-OFT-002) renders through
 * LaunchPad's TileWidget, which asks for a name and creates the file; every
 * other tile renders through the shared CnDashTileWidget exactly as before.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
 */
export default {
	name: 'TileHost',

	components: {
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
</style>
