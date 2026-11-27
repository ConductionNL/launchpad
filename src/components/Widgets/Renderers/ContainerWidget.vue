<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="container-widget" :class="{ 'container-widget--editing': editMode }" :style="wrapperStyle">
		<div v-if="hasTitle || canForgetUsage" class="container-widget__header">
			<h4 v-if="hasTitle" class="container-widget__title">
				{{ titleText }}
			</h4>
			<NcButton
				v-if="canForgetUsage"
				class="container-widget__forget"
				variant="tertiary"
				data-testid="container-forget-usage"
				@click="forgetUsage">
				{{ t('launchpad', 'Forget my usage') }}
			</NcButton>
		</div>

		<p v-if="sortDescription" :id="descriptionId" class="hidden-visually">
			{{ sortDescription }}
		</p>

		<!-- REQ-CONT-009: an app list's note in edit mode; the tiles are drawn per viewer. -->
		<p v-if="editMode && appList" class="container-widget__app-list-note" data-testid="container-app-list-note">
			{{ t('launchpad', 'Apps from the app menu appear here for each viewer.') }}
		</p>

		<template v-if="flowMode">
			<input
				v-if="appList.showFilter"
				v-model="query"
				type="search"
				class="container-widget__filter"
				data-testid="container-app-filter"
				:aria-label="t('launchpad', 'Filter apps')"
				:placeholder="t('launchpad', 'Filter apps')">
			<!-- A list drawn per viewer is laid out as cells, not on the
			     editable inner grid, so the filter can close it up at once. -->
			<div class="launchpad-container-flow" data-testid="container-flow">
				<div
					v-for="(child, index) in displayedChildren"
					:key="childKey(child, index)"
					class="container-widget__child container-widget__cell"
					:style="cellStyle(child)"
					:data-launchpad-child="index">
					<ContainerChild
						:placement="child"
						:editMode="false"
						:canEditTile="false" />
				</div>
			</div>
		</template>

		<div
			v-else
			ref="innerGrid"
			class="grid-stack launchpad-container-grid"
			:aria-describedby="sortDescription ? descriptionId : null">
			<div
				v-for="(child, index) in displayedChildren"
				:key="childKey(child, index)"
				class="grid-stack-item container-widget__child"
				:gs-x="child.gridX || 0"
				:gs-y="child.gridY || 0"
				:gs-w="child.gridWidth || 2"
				:gs-h="child.gridHeight || 2"
				:data-launchpad-child="index">
				<div class="grid-stack-item-content">
					<ContainerChild
						:placement="child"
						:editMode="editMode"
						:canEditTile="canEditTiles"
						@editTile="editTile(index)"
						@removeTile="editTile(index, true)" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { getLanguage, translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import ContainerChild from './ContainerChild.vue'
import {
	getNestedGridOptions,
	NESTED_COLUMNS,
	useNestedGridManager,
} from '../../../composables/useNestedGridManager.js'
import { useTileClickTracking } from '../../../composables/useTileClickTracking.js'
import { FLAG_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'
import {
	childUseKey,
	forgetTileUse,
	normaliseSortMode,
	readTileUse,
	reflowChildren,
	sortChildren,
	USAGE_MODES,
} from '../../../utils/tileSort.js'
import {
	appTilePlacements,
	childLabel,
	listedApps,
	normaliseAppList,
	openableChildren,
	viewerAppMenu,
} from '../../../utils/wellAppList.js'

const { recordTileUse } = useTileClickTracking()

let descriptionCounter = 0

const PADDING_TOKENS = Object.freeze({
	none: '0',
	small: '4px',
	medium: '8px',
	large: '16px',
})

/**
 * ContainerWidget — the `container` widget type renderer (REQ-CONT-001..005).
 *
 * Renders a wrapper element with optional background colour, padding and
 * title, plus an inner GridStack instance bounded by the container's outer
 * cell. Each child placement in `content.placements[]` is rendered through
 * the ContainerChild dispatcher, which in turn dispatches by widget type
 * via the same widget registry used at the top level — so a container can
 * hold any registered widget type, including another container (subject to
 * the REQ-CONT-006 max-depth=3 server-side guard).
 *
 * View mode (REQ-CONT-004): the wrapper is `pointer-events: none` so
 * clicks fall through to the child widget under the cursor; child wrappers
 * re-enable pointer events. Edit mode (REQ-CONT-005): the inner grid
 * becomes editable independently of the outer grid via GridStack's nested-
 * grid behaviour.
 */
export default {
	name: 'ContainerWidget',

	components: {
		ContainerChild,
		NcButton,
	},

	/**
	 * The dashboard's opener of the tile form for a tile in a group
	 * (REQ-TILE-041); absent where no dashboard hosts the container.
	 */
	inject: {
		openGroupTileEditor: { default: null },
	},

	props: {
		content: OBJECT_PROP,

		/** The container's own placement; its id keys children that have none. */
		placement: OBJECT_PROP,

		editMode: FLAG_PROP,
	},

	emits: ['update:content'],

	data() {
		descriptionCounter += 1
		return {
			gridInstance: null,
			// The viewer's usage as it was when the page loaded: the order
			// changes on the next load, not while tiles are being clicked.
			tileUse: readTileUse(),
			descriptionId: `container-sort-${descriptionCounter}`,
			// The viewer's app menu from the page's boot data (REQ-CONT-008, -009).
			appMenu: viewerAppMenu(),
			query: '',
		}
	},

	computed: {
		/**
		 * The stored children. A child without an id or uuid of its own (a
		 * bookmark import writes them that way) gets `<container id>-<index>`
		 * as its uuid, so its use can be counted and it keeps one key.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		children() {
			const list = this.content?.placements
			if (!Array.isArray(list)) {
				return []
			}
			const owner = this.placement?.id
			if (owner === undefined || owner === null) {
				return list
			}
			// REQ-WDG-050: the key the server stamps on save (ContainerChildKeys):
			// never one another child already holds; such a child stays keyless.
			const taken = new Set(list.map(childUseKey).filter((key) => key !== ''))
			return list.map((child, index) => (childUseKey(child) === '' && !taken.has(`${owner}-${index}`)
				? { ...child, uuid: `${owner}-${index}` }
				: child))
		},

		/**
		 * The well's app list, or null (REQ-CONT-009).
		 *
		 * @return {object|null}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		appList() {
			return normaliseAppList(this.content?.appList)
		},

		/**
		 * Whether the well is drawn as cells: a viewer's app list in view mode.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		flowMode() {
			return !this.editMode && this.appList !== null
		},

		/**
		 * The children this viewer may see: in view mode an app tile for an app
		 * outside the viewer's app menu is left out (REQ-CONT-008).
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-2
		 */
		visibleChildren() {
			return this.editMode ? this.children : openableChildren(this.children, this.appMenu)
		},

		/**
		 * The tiles of the app list for this viewer (REQ-CONT-009).
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		dynamicChildren() {
			if (!this.flowMode) {
				return []
			}
			const owner = this.placement?.id ?? this.placement?.uuid ?? this.descriptionId
			const apps = listedApps(this.appMenu ?? [], this.appList, { use: this.tileUse, owner, query: this.query })
			return appTilePlacements(apps, owner)
		},

		/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
		sortMode() {
			return normaliseSortMode(this.content?.sortBy)
		},

		/**
		 * The children as shown: the stored layout in edit mode or when sorted
		 * by hand, otherwise sorted and reflowed row by row (REQ-TSO-001).
		 * Nothing here is written back.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		displayedChildren() {
			if (this.flowMode) {
				// REQ-CONT-009: the app list's tiles first, the placed ones after.
				const text = this.query.trim().toLocaleLowerCase()
				const placed = this.orderedChildren(this.visibleChildren)
					.filter((child) => text === '' || childLabel(child, this.appMenu).toLocaleLowerCase().includes(text))
				return [...this.dynamicChildren, ...placed]
			}
			if (this.editMode || (this.sortMode === 'manual' && this.visibleChildren.length === this.children.length)) {
				return this.children
			}
			// A sorted view, or a tile left out (REQ-CONT-008): closed up row by row.
			return reflowChildren(this.orderedChildren(this.visibleChildren), NESTED_COLUMNS)
		},

		/**
		 * Whether the tiles in this group open the tile form: in edit mode, on a
		 * dashboard that offers it, for a container that is a placement of its own.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		canEditTiles() {
			const id = this.placement?.id
			return this.editMode
				&& typeof this.openGroupTileEditor === 'function'
				&& id !== undefined
				&& id !== null
		},

		/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
		canForgetUsage() {
			return !this.editMode && USAGE_MODES.includes(this.sortMode)
		},

		/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
		sortDescription() {
			if (this.editMode) {
				return ''
			}
			return {
				alphabetical: t('launchpad', 'Sorted alphabetically'),
				'most-used': t('launchpad', 'Sorted by most used'),
				'last-used': t('launchpad', 'Sorted by last used'),
				random: t('launchpad', 'Sorted at random'),
			}[this.sortMode] ?? ''
		},

		/** @spec openspec/specs/container-widget/spec.md */
		backgroundColor() {
			const value = this.content?.backgroundColor
			return typeof value === 'string' && value !== '' ? value : 'transparent'
		},

		/** @spec openspec/specs/container-widget/spec.md */
		paddingToken() {
			const value = this.content?.padding
			if (typeof value === 'string' && Object.hasOwn(PADDING_TOKENS, value)) {
				return value
			}
			return 'medium'
		},

		/** @spec openspec/specs/container-widget/spec.md */
		paddingPx() {
			return PADDING_TOKENS[this.paddingToken]
		},

		/** @spec openspec/specs/container-widget/spec.md */
		titleText() {
			return typeof this.content?.title === 'string' ? this.content.title : ''
		},

		hasTitle() {
			return this.titleText.trim() !== ''
		},

		/** @spec openspec/specs/container-widget/spec.md */
		wrapperStyle() {
			return {
				width: '100%',
				height: '100%',
				padding: this.paddingPx,
				'background-color': this.backgroundColor,
				// REQ-CONT-004: in view mode, the container itself is
				// non-interactive — clicks fall through to children. The
				// child wrappers re-enable pointer events. In edit mode,
				// the wrapper is interactive so the user can drop new
				// widgets into the inner grid.
				'pointer-events': this.editMode ? 'auto' : 'none',
			}
		},
	},

	watch: {
		/**
		 * Edit mode draws an app list's well on the inner grid, view mode as
		 * cells: start or stop the grid with it.
		 *
		 * @param {boolean} flow Whether the well is now drawn as cells.
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		async flowMode(flow) {
			if (flow) {
				this.destroyInnerGrid()
				return
			}
			await this.$nextTick()
			this.initInnerGrid()
		},
	},

	/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
	mounted() {
		// A click anywhere in a child counts a use of that child; the child's
		// own link or button stays the control (no click handler on the cell).
		this.$refs.innerGrid?.addEventListener('click', this.onChildClick, true)
		// A well drawn as cells counts its uses on the same capture phase.
		this.$el?.addEventListener?.('click', this.onFlowClick, true)
		this.initInnerGrid()
	},

	/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
	beforeUnmount() {
		this.$refs.innerGrid?.removeEventListener('click', this.onChildClick, true)
		this.$el?.removeEventListener?.('click', this.onFlowClick, true)
		this.destroyInnerGrid()
	},

	methods: {
		t,

		/**
		 * Children in the well's sort: the chosen sort, or by hand in their
		 * stored reading order.
		 *
		 * @param {Array<object>} list The children.
		 * @return {Array<object>}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-2
		 */
		orderedChildren(list) {
			if (this.sortMode !== 'manual') {
				return sortChildren(list, this.sortMode, { use: this.tileUse, locale: getLanguage() })
			}
			return [...list].sort((a, b) => (Number(a.gridY) || 0) - (Number(b.gridY) || 0) || (Number(a.gridX) || 0) - (Number(b.gridX) || 0))
		},

		/**
		 * A cell's span on the well's columns and rows when drawn as cells.
		 *
		 * @param {object} child The child placement.
		 * @return {object} The style.
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		cellStyle(child) {
			const width = Math.min(Math.max(1, Number(child.gridWidth) || 2), NESTED_COLUMNS)
			const height = Math.max(1, Number(child.gridHeight) || 2)
			return { 'grid-column': `span ${width}`, 'grid-row': `span ${height}` }
		},

		/**
		 * Open the tile form for the child at `index`. In edit mode the children
		 * are shown in their stored order, so the index is the stored position.
		 *
		 * @param {number} index The child's position in `content.placements`.
		 * @param {boolean} [remove] Remove the tile instead of opening the form.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
		 */
		editTile(index, remove = false) {
			if (!this.canEditTiles || this.displayedChildren !== this.children) {
				return
			}
			this.openGroupTileEditor({ containerId: this.placement.id, index, remove })
		},

		/**
		 * Count a use of the child a click landed in.
		 *
		 * @param {MouseEvent} event The click, in its capture phase.
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		onChildClick(event) {
			const grid = this.$refs.innerGrid
			let cell = event.target?.closest?.('[data-launchpad-child]') ?? null
			// A group inside this group has cells of its own: take this grid's.
			while (cell && cell.parentElement !== grid) {
				cell = cell.parentElement?.closest('[data-launchpad-child]') ?? null
			}
			const child = cell ? this.displayedChildren[Number(cell.dataset.launchpadChild)] : undefined
			if (child) {
				this.countUse(child)
			}
		},

		/**
		 * Count a use of the app list's cell a click landed in (REQ-CONT-009).
		 *
		 * @param {MouseEvent} event The click, in its capture phase.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		onFlowClick(event) {
			if (!this.flowMode) {
				return
			}
			const cell = event.target?.closest?.('.container-widget__cell[data-launchpad-child]') ?? null
			const child = cell ? this.displayedChildren[Number(cell.dataset.launchpadChild)] : undefined
			if (child) {
				this.countUse(child)
			}
		},

		/**
		 * Count a use of a child in this browser; only in view mode.
		 *
		 * @param {object} child The child placement.
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		countUse(child) {
			if (this.editMode) {
				return
			}
			recordTileUse(childUseKey(child))
		},

		/**
		 * Forget this browser's usage of this container's tiles (REQ-TSO-003).
		 *
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		async forgetUsage() {
			forgetTileUse(this.children.map(childUseKey).filter((key) => key !== ''))
			this.tileUse = readTileUse()
			await this.$nextTick()
			this.moveGridItemsToAttributes()
		},

		/**
		 * Move every inner grid item to the position its `gs-*` attributes now
		 * name. GridStack keeps its own copy of each item's position, so a
		 * re-sorted view only shows its new order once the grid is told.
		 *
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		moveGridItemsToAttributes() {
			const grid = this.gridInstance
			if (!grid || typeof grid.update !== 'function' || !this.$refs.innerGrid) {
				return
			}
			const items = this.$refs.innerGrid.querySelectorAll(':scope > .grid-stack-item')
			grid.batchUpdate?.()
			for (const el of items) {
				grid.update(el, {
					x: Number(el.getAttribute('gs-x') ?? 0),
					y: Number(el.getAttribute('gs-y') ?? 0),
					w: Number(el.getAttribute('gs-w') ?? 2),
					h: Number(el.getAttribute('gs-h') ?? 2),
				})
			}
			grid.batchUpdate?.(false)
		},

		/**
		 * Stable key for v-for over child placements. Falls back to the
		 * loop index when a child has neither id nor uuid, which keeps
		 * Vue happy during the brief window between an add and the
		 * server-issued id round-trip.
		 *
		 * @param {object} child the child placement
		 * @param {number} index the loop index
		 * @return {string|number}
		 * @spec openspec/specs/container-widget/spec.md
		 */
		childKey(child, index) {
			if (child && child.id !== undefined && child.id !== null) {
				return `id-${child.id}`
			}
			if (child && typeof child.uuid === 'string' && child.uuid !== '') {
				return `uuid-${child.uuid}`
			}
			return `idx-${index}`
		},

		/**
		 * Initialise the inner GridStack instance once the DOM ref is
		 * available. Defers to the runtime GridStack import so the unit
		 * test surface stays JSDOM-friendly — when GridStack is not
		 * available (test env), the renderer still mounts and renders
		 * children, just without drag/resize affordances.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/container-widget/spec.md
		 */
		async initInnerGrid() {
			if (!this.$refs.innerGrid || this.gridInstance) {
				return
			}
			// No initialiser: the catch below returns, so the binding is only ever
			// read after the try assigned it.
			let GridStackCtor
			try {
				const mod = await import('gridstack')
				GridStackCtor = mod && (mod.GridStack || mod.default)
			} catch {
				// GridStack runtime unavailable — non-fatal in tests.
				return
			}
			if (!GridStackCtor || typeof GridStackCtor.init !== 'function') {
				return
			}
			const opts = getNestedGridOptions()
			this.gridInstance = GridStackCtor.init(opts, this.$refs.innerGrid)

			// Wire the persistence callback (REQ-CONT-005). Movements and
			// resizes inside the inner grid call back to the parent so the
			// container placement's `content.placements[]` array is kept
			// in sync; the parent persists via the existing widget-update
			// API path.
			const manager = useNestedGridManager({
				persistPlacements: this.handlePersist,
			})
			this._nestedManager = manager
			if (this.gridInstance && typeof this.gridInstance.on === 'function') {
				this.gridInstance.on('change', this.onGridChange)
			}
		},

		/**
		 * GridStack 'change' callback — translates inner-grid node
		 * coordinates back into the LaunchPad field-name form and emits
		 * an `update:content` event so the parent placement's content
		 * blob is updated.
		 *
		 * @param {Event} _event the GridStack event (ignored)
		 * @param {Array<object>} nodes the changed nodes
		 * @spec openspec/specs/container-widget/spec.md
		 */
		onGridChange(_event, nodes) {
			// A sorted view reflows the tiles; that order must never be
			// written back (REQ-TSO-001). The stored layout persists as before.
			if (this.displayedChildren !== this.children) {
				return
			}
			if (!Array.isArray(nodes) || nodes.length === 0) {
				return
			}
			const byKey = new Map()
			for (const child of this.children) {
				byKey.set(this.childKey(child, 0), child)
			}
			const updated = this.children.map((child, index) => {
				const node = nodes.find(
					(n) =>
						n.el
						&& n.el.dataset
						&& n.el.dataset.launchpadIndex === String(index),
				)
				if (!node) {
					return child
				}
				return {
					...child,
					gridX: node.x,
					gridY: node.y,
					gridWidth: node.w,
					gridHeight: node.h,
				}
			})
			this.handlePersist(updated)
		},

		/**
		 * Bridge for the nested manager's persist callback — re-emits
		 * `update:content` with the merged placements so the parent
		 * AddWidgetModal / placement-store update path picks up the
		 * change.
		 *
		 * @param {Array<object>} placements the new child placements
		 * @spec openspec/specs/container-widget/spec.md
		 */
		handlePersist(placements) {
			this.$emit('update:content', {
				...(this.content || {}),
				placements: Array.isArray(placements) ? placements : [],
			})
		},

		/** @spec openspec/specs/container-widget/spec.md */
		destroyInnerGrid() {
			if (
				this.gridInstance
				&& typeof this.gridInstance.destroy === 'function'
			) {
				try {
					this.gridInstance.destroy(false)
				} catch {
					// no-op — best-effort teardown
				}
			}
			this.gridInstance = null
			this._nestedManager = null
		},
	},
}
</script>

<style scoped>
.launchpad-container-flow {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	grid-auto-rows: 40px;
	gap: 4px;
	pointer-events: auto;
}

.container-widget__filter {
	width: 100%;
	margin-bottom: 4px;
	pointer-events: auto;
}

.container-widget__app-list-note {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.container-widget {
	width: 100%;
	height: 100%;
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	gap: 4px;
	overflow: hidden;
}

.container-widget__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	pointer-events: auto;
}

.container-widget__title {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-text-maxcontrast, var(--color-main-text));
	pointer-events: auto;
}

.launchpad-container-grid {
	flex: 1;
	min-height: 0;
}

/* REQ-WDG-050: in edit mode a child's cell does not clip, so the edit menu of
   a child one row tall stays whole and clickable above the resize handle. */
.container-widget--editing .launchpad-container-grid > .grid-stack-item > .grid-stack-item-content {
	overflow: visible;
}

.container-widget__child {
	/* REQ-CONT-004: child wrappers re-enable pointer events so clicks
	   on a child fire the child's own handlers — the container itself
	   is `pointer-events: none` in view mode (see wrapperStyle). */
	pointer-events: auto;
}
</style>
