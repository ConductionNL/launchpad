<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- REQ-TILE-041 / REQ-WDG-050: in edit mode every child has the widget
	     edit menu; a tile in a group also opens the tile form from it. -->
	<div v-if="childRenderer && showsMenu" class="container-child container-child--menu">
		<component
			:is="childRenderer"
			:content="childContent"
			:placement="placement"
			:editMode="editMode"
			v-bind="extraProps" />
		<div class="container-child__edit" :data-testid="showsTileEdit ? 'container-child-tile-menu' : 'container-child-menu'">
			<WidgetEditMenu
				:menuLabel="showsTileEdit ? tileMenuLabel : widgetMenuLabel"
				:editLabel="showsTileEdit ? editTileLabel : ''"
				:deleteLabel="showsTileEdit ? deleteTileLabel : ''"
				:hideEdit="!showsTileEdit && !canConfigure"
				:showWhoSees="false"
				:embedHref="embedHref"
				:embedDisabled="embedHref === ''"
				:embedAsksFirst="embedAsksFirst"
				@edit="edit"
				@duplicate="duplicate"
				@embed="openEmbed"
				@remove="remove" />
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
import WidgetEditMenu from '../WidgetEditMenu.vue'
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
import { childUseKey } from '../../../utils/tileSort.js'

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
		WidgetEditMenu,
	},

	inject: {
		// REQ-WDG-050: the widget on the dashboard that holds this child, and
		// the dashboard's child actions (embed link, duplicate, remove).
		launchpadChildOwner: { default: () => () => null },
		launchpadChildActions: { default: null },
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

		/**
		 * REQ-WDG-050: whether this child shows the edit menu: in edit mode,
		 * on a dashboard that offers the child actions, or as a group tile.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		showsMenu() {
			return this.showsTileEdit || (this.editMode && this.launchpadChildActions !== null && this.owner !== null)
		},

		/**
		 * The widget on the dashboard that holds this child, or null.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		owner() {
			return this.launchpadChildOwner() ?? null
		},

		/**
		 * The child's embed link; '' when it cannot be named yet or the
		 * person may not create embed tokens (the entry is then disabled or
		 * left out by the menu).
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		embedHref() {
			return this.launchpadChildActions?.embedHref(this.owner, this.placement) ?? ''
		},

		/**
		 * The draft has unsaved changes, so Create embed token asks first.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		embedAsksFirst() {
			return this.launchpadChildActions?.asksFirst() === true
		},

		/**
		 * Whether the widget form can configure this child (a registry
		 * widget with a form), when it is not a group tile.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		canConfigure() {
			return childUseKey(this.placement) !== '' && this.launchpadChildActions?.canConfigure?.(this.placement) === true
		},

		/** @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050 */
		widgetMenuLabel() {
			return t('launchpad', 'Widget menu')
		},

		/** @spec openspec/specs/container-widget/spec.md */
		unknownLabel() {
			const type = this.placement?.type || ''
			return t('launchpad', 'Unknown widget type: {type}', { type })
		},
	},

	methods: {
		/**
		 * Edit: a group tile opens the tile form; any other child the widget
		 * form (REQ-WDG-050 Configure).
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		edit() {
			if (this.showsTileEdit) {
				this.$emit('editTile')
				return
			}
			this.launchpadChildActions?.configure(this.owner.id, childUseKey(this.placement))
		},

		/**
		 * REQ-WDG-050: Duplicate places a copy after this child, in its list.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		duplicate() {
			const key = childUseKey(this.placement)
			if (key !== '' && this.owner) {
				this.launchpadChildActions?.duplicate(this.owner.id, key)
			}
		},

		/**
		 * Remove: a group tile goes through the tile form's remove; any other
		 * child is taken out of its list (REQ-WDG-050).
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		remove() {
			if (this.showsTileEdit) {
				this.$emit('removeTile')
				return
			}
			const key = childUseKey(this.placement)
			if (key !== '' && this.owner) {
				this.launchpadChildActions?.remove(this.owner.id, key)
			}
		},

		/**
		 * Create embed token with unsaved changes: the dashboard asks first.
		 *
		 * @param {string} href The embed token form.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
		 */
		openEmbed(href) {
			this.launchpadChildActions?.openEmbed(href)
		},
	},
}
</script>

<style scoped>
.container-child {
	width: 100%;
	height: 100%;
}

.container-child--menu {
	position: relative;
}

/* Above the inner grid's resize handle (GridStack and the dashboard's grid
   styles draw it at z-index 100-101): on a small child it sits on the menu. */
.container-child__edit {
	position: absolute;
	top: 2px;
	inset-inline-end: 2px;
	z-index: 110;
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
