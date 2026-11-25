<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- REQ-TILE-041: in edit mode a tile in a group opens the tile form. -->
	<div v-if="childRenderer && showsTileEdit" class="container-child container-child--tile">
		<component
			:is="childRenderer"
			:content="childContent"
			:placement="placement"
			:editMode="editMode"
			v-bind="extraProps" />
		<!-- The same tile menu as a tile on the dashboard (REQ-TILE-010). -->
		<div class="container-child__edit" data-testid="container-child-tile-menu">
			<CnWidgetEditCog
				icon="kebab"
				:menuLabel="tileMenuLabel"
				:editLabel="editTileLabel"
				:deleteLabel="deleteTileLabel"
				@edit="$emit('editTile')"
				@remove="$emit('removeTile')" />
		</div>
	</div>
	<component
		:is="childRenderer"
		v-else-if="childRenderer"
		:content="childContent"
		:placement="placement"
		:editMode="editMode"
		v-bind="extraProps" />
	<div v-else class="container-child container-child--unknown">
		<span class="container-child__missing">{{ unknownLabel }}</span>
	</div>
</template>

<script>
import { CnWidgetEditCog } from '@conduction/nextcloud-vue'
import {
	FLAG_PROP,
	REQUIRED_OBJECT_PROP,
} from '../../../constants/propDefinitions.js'
import { getWidgetTypeEntry } from '../../../constants/widgetRegistry.js'
import {
	buildRendererExtraProps,
	buildWidgetDataProvide,
} from '../../../services/widgetDataAdapters.js'
import { isGroupTileChild } from '../../../utils/groupTiles.js'

/**
 * ContainerChild — registry-driven dispatcher for a single child placement
 * inside a ContainerWidget (REQ-CONT-003).
 *
 * Looks up the placement's `type` in the shared widget registry and
 * mounts the matching `renderer` component, forwarding the placement's
 * `content` blob. Because the `container` widget type is itself a
 * registry entry whose renderer is `ContainerWidget`, this component is
 * naturally recursive: nesting is bounded only by the server-side
 * REQ-CONT-006 max-depth=3 invariant enforced in
 * `WidgetPlacementService::validateContainerDepth`.
 *
 * The dispatcher deliberately stays tiny — no header chrome, no edit
 * affordances of its own — so child widgets render exactly as they
 * would on the top-level grid. It does, however, provide() the same
 * nc-vue data-source adapters as WidgetRenderer (scoped to this child's
 * placement id) so nested data widgets (people/calendar/spend/files) work
 * identically inside a container. News reads `placement.id` off its own
 * prop instead of an injected source, so it isn't part of this seam.
 */
export default {
	name: 'ContainerChild',

	components: {
		CnWidgetEditCog,
	},

	/**
	 * Bridge the nc-vue data widgets' injected sources to launchpad's
	 * endpoints, scoped to this child's placement. Without this a nested
	 * data widget would inherit the container's placement id (or none) and
	 * fetch the wrong rows, so "child widgets render via the registry
	 * dispatcher" would hold structurally but not behaviourally.
	 *
	 * @spec openspec/specs/container-widget/spec.md#req-cont-003
	 * @return {object} the injected data-source adapters.
	 */
	provide() {
		return buildWidgetDataProvide(() => this.placement?.id, () => this.placement?.content)
	},

	props: {
		placement: REQUIRED_OBJECT_PROP,

		editMode: FLAG_PROP,

		/** Whether the dashboard can open the tile form for this child. */
		canEditTile: FLAG_PROP,
	},

	emits: ['editTile', 'removeTile'],

	computed: {
		/**
		 * Per-type extra props for nc-vue renderers that take a prop (files
		 * `apiBase`). The container child placement carries the type in
		 * `placement.type`.
		 *
		 * @return {object} extra props to v-bind onto the child renderer.
		 */
		extraProps() {
			return buildRendererExtraProps(this.placement?.type)
		},

		/** @spec openspec/specs/container-widget/spec.md */
		registryEntry() {
			const type = this.placement?.type
			if (typeof type !== 'string' || type === '') {
				return null
			}
			return getWidgetTypeEntry(type)
		},

		/** @spec openspec/specs/container-widget/spec.md */
		childRenderer() {
			return this.registryEntry?.renderer || null
		},

		/** @spec openspec/specs/container-widget/spec.md */
		childContent() {
			return this.placement?.content || {}
		},

		/**
		 * Whether this child shows the edit cog of a tile: in edit mode, for a
		 * tile the tile form can edit, when the dashboard offers the form.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		showsTileEdit() {
			return this.editMode && this.canEditTile && isGroupTileChild(this.placement)
		},

		/** @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041 */
		tileMenuLabel() {
			return t('launchpad', 'Tile menu')
		},

		/** @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041 */
		editTileLabel() {
			return t('launchpad', 'Edit tile')
		},

		/** @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041 */
		deleteTileLabel() {
			return t('launchpad', 'Delete tile')
		},

		/** @spec openspec/specs/container-widget/spec.md */
		unknownLabel() {
			const type = this.placement?.type || ''
			return t('launchpad', 'Unknown widget type: {type}', { type })
		},
	},
}
</script>

<style scoped>
.container-child {
	width: 100%;
	height: 100%;
}

.container-child--tile {
	position: relative;
}

.container-child__edit {
	position: absolute;
	top: 2px;
	inset-inline-end: 2px;
	z-index: 1;
	pointer-events: auto;
}

.container-child--unknown {
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
