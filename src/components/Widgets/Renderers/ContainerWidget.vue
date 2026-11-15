<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="container-widget" :style="wrapperStyle">
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

		<div
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
				@click.capture="countUse(child)">
				<div class="grid-stack-item-content">
					<ContainerChild :placement="child" :editMode="editMode" />
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

	props: {
		content: OBJECT_PROP,

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
		}
	},

	computed: {
		/** @spec openspec/specs/container-widget/spec.md */
		children() {
			const list = this.content?.placements
			return Array.isArray(list) ? list : []
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
			if (this.editMode || this.sortMode === 'manual') {
				return this.children
			}
			const ordered = sortChildren(this.children, this.sortMode, { use: this.tileUse, locale: getLanguage() })
			return reflowChildren(ordered, NESTED_COLUMNS)
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

	mounted() {
		this.initInnerGrid()
	},

	beforeUnmount() {
		this.destroyInnerGrid()
	},

	methods: {
		t,

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
		forgetUsage() {
			forgetTileUse(this.children.map(childUseKey).filter((key) => key !== ''))
			this.tileUse = readTileUse()
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
			if (!this.$refs.innerGrid) {
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

.container-widget__child {
	/* REQ-CONT-004: child wrappers re-enable pointer events so clicks
	   on a child fire the child's own handlers — the container itself
	   is `pointer-events: none` in view mode (see wrapperStyle). */
	pointer-events: auto;
}
</style>
