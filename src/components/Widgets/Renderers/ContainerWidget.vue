<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="container-widget" :style="wrapperStyle">
		<h4 v-if="hasTitle" class="container-widget__title">
			{{ titleText }}
		</h4>
		<button
			v-if="canForget"
			type="button"
			class="container-widget__forget"
			@click="forgetUsage">
			{{ t('launchpad', 'Forget my usage') }}
		</button>

		<div
			ref="innerGrid"
			class="grid-stack launchpad-container-grid"
			:aria-description="sortDescription || undefined">
			<div
				v-for="(child, index) in displayChildren"
				:key="childKey(child, index)"
				class="grid-stack-item container-widget__child"
				:gs-x="child.gridX || 0"
				:gs-y="child.gridY || 0"
				:gs-w="child.gridWidth || 2"
				:gs-h="child.gridHeight || 2"
				:data-use-key="useKey(child)">
				<div class="grid-stack-item-content">
					<ContainerChild :placement="child" :editMode="editMode" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import ContainerChild from './ContainerChild.vue'
import {
	getNestedGridOptions,
	NESTED_COLUMNS,
	useNestedGridManager,
} from '../../../composables/useNestedGridManager.js'
import {
	forgetLocalTileUse,
	randomRankFor,
	readLocalTileUse,
	recordLocalTileUse,
} from '../../../composables/useTileClickTracking.js'
import {
	childTitle,
	orderChildren,
	reflowChildren,
	SORT_MODES,
} from '../../../utils/sortContainerTiles.js'

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
	},

	props: {
		content: {
			type: Object,
			default: () => ({}),
		},

		editMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:content'],

	data() {
		return {
			gridInstance: null,
			// The viewer's own tile use, read once per page load (REQ-TSO-002).
			use: readLocalTileUse(),
		}
	},

	computed: {
		/** @spec openspec/specs/container-widget/spec.md */
		children() {
			const list = this.content?.placements
			return Array.isArray(list) ? list : []
		},

		/** @spec openspec/specs/container-widget/spec.md */
		sortBy() {
			const value = this.content?.sortBy
			return SORT_MODES.includes(value) ? value : 'manual'
		},

		/**
		 * The children as shown: the stored layout in edit mode or by hand,
		 * otherwise ordered and reflowed at view time (REQ-TSO-001).
		 *
		 * @spec openspec/specs/container-widget/spec.md
		 */
		displayChildren() {
			if (this.editMode || this.sortBy === 'manual') {
				return this.children
			}
			// REQ-TSO-002: without browser storage there is no use to sort by.
			if (
				this.use === null
				&& ['most-used', 'last-used'].includes(this.sortBy)
			) {
				return this.children
			}
			const ordered = orderChildren(this.children, this.sortBy, {
				use: this.use || {},
				keyOf: this.useKey,
				rankOf: randomRankFor,
				collator: new Intl.Collator(undefined, {
					sensitivity: 'base',
					numeric: true,
				}),
			})
			return reflowChildren(ordered, NESTED_COLUMNS)
		},

		/** @spec openspec/specs/container-widget/spec.md */
		sortDescription() {
			const labels = {
				alphabetical: t('launchpad', 'Sorted alphabetically'),
				'most-used': t('launchpad', 'Sorted by most used'),
				'last-used': t('launchpad', 'Sorted by last used'),
				random: t('launchpad', 'Sorted at random'),
			}
			return this.editMode ? '' : labels[this.sortBy] || ''
		},

		/**
		 * Offer "Forget my usage" when the order reads the viewer's use and
		 * there is use to forget (REQ-TSO-003).
		 *
		 * @spec openspec/specs/container-widget/spec.md
		 */
		canForget() {
			if (this.editMode || !['most-used', 'last-used'].includes(this.sortBy)) {
				return false
			}
			return (
				this.use !== null
				&& this.children.some((child) => this.use[this.useKey(child)])
			)
		},

		/** @spec openspec/specs/container-widget/spec.md */
		layoutSignature() {
			return this.displayChildren
				.map(
					(child) => `${this.useKey(child)}@${child.gridX},${child.gridY}`,
				)
				.join('|')
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
		 * A new view-time layout (after "Forget my usage") rebuilds the
		 * inner grid, which reads positions only when it starts.
		 *
		 * @spec openspec/specs/container-widget/spec.md
		 */
		layoutSignature() {
			if (this.editMode || !this.gridInstance) {
				return
			}
			this.destroyInnerGrid()
			this.$nextTick(() => this.initInnerGrid())
		},
	},

	/** @spec openspec/specs/container-widget/spec.md */
	mounted() {
		// REQ-TSO-002: one delegated listener counts clicks on the tiles'
		// own links and buttons; the wrapper itself is not a control.
		this.$refs.innerGrid?.addEventListener('click', this.onGridClick)
		this.initInnerGrid()
	},

	/** @spec openspec/specs/container-widget/spec.md */
	beforeUnmount() {
		this.$refs.innerGrid?.removeEventListener('click', this.onGridClick)
		this.destroyInnerGrid()
	},

	methods: {
		/**
		 * The key a child's use is stored under: its placement id, else its
		 * uuid, else its title.
		 *
		 * @param {object} child The child placement.
		 * @return {string} The key.
		 * @spec openspec/specs/container-widget/spec.md
		 */
		useKey(child) {
			if (child?.id !== undefined && child?.id !== null) {
				return String(child.id)
			}
			if (typeof child?.uuid === 'string' && child.uuid !== '') {
				return child.uuid
			}
			return `title:${childTitle(child)}`
		},

		/**
		 * Count a click on a tile in this browser (REQ-TSO-002). The order
		 * does not change until the next page load, so tiles never jump
		 * under the pointer.
		 *
		 * @param {MouseEvent} event The click inside the grid.
		 * @spec openspec/specs/container-widget/spec.md
		 */
		onGridClick(event) {
			if (this.editMode) {
				return
			}
			const item = event?.target?.closest?.('[data-use-key]')
			if (item) {
				recordLocalTileUse(item.dataset.useKey)
			}
		},

		/**
		 * Forget this browser's use of this container's tiles (REQ-TSO-003).
		 *
		 * @spec openspec/specs/container-widget/spec.md
		 */
		forgetUsage() {
			forgetLocalTileUse(this.children.map((child) => this.useKey(child)))
			this.use = readLocalTileUse()
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
			// A view-time reflow is never written back (REQ-TSO-001).
			if (!this.editMode || !Array.isArray(nodes) || nodes.length === 0) {
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

.container-widget__forget {
	align-self: flex-start;
	padding: 2px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 12px;
	cursor: pointer;
	pointer-events: auto;
}

.container-widget__child {
	/* REQ-CONT-004: child wrappers re-enable pointer events so clicks
	   on a child fire the child's own handlers — the container itself
	   is `pointer-events: none` in view mode (see wrapperStyle). */
	pointer-events: auto;
}
</style>
