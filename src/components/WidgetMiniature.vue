<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- add-widget-dialog-with-miniatures REQ-WDG-048: the widget itself,
	     drawn by its own renderer with sample content and scaled down. The
	     frame is decoration: hidden from assistive technology, no focus or
	     clicks of its own; the card's name and line say what it is. -->
	<div
		ref="frame"
		class="widget-miniature"
		:class="{ 'widget-miniature--fallback': showFallback }"
		aria-hidden="true"
		inert
		:data-testid="'widget-miniature-' + (type || 'widget')"
		:data-state="state">
		<div
			v-if="ncWidget"
			class="widget-miniature__nc"
			data-testid="widget-miniature-nc">
			<div class="widget-miniature__nc-head">
				<img
					v-if="ncWidget.iconUrl"
					:src="ncWidget.iconUrl"
					alt=""
					class="widget-miniature__nc-icon">
				<span
					v-else-if="ncWidget.iconClass"
					:class="ncWidget.iconClass"
					class="widget-miniature__nc-icon" />
				<span class="widget-miniature__nc-title" />
			</div>
			<span v-for="row in 3" :key="row" class="widget-miniature__nc-row" />
		</div>
		<div
			v-else-if="renderer && !showFallback"
			class="widget-miniature__card">
			<div class="widget-miniature__stage">
				<component
					:is="renderer"
					v-bind="sampleProps"
					:content="sample"
					:placement="samplePlacement"
					:preview="true"
					:editMode="false" />
			</div>
		</div>
		<span v-else class="widget-miniature__icon" data-testid="widget-miniature-icon">
			<slot name="icon" />
		</span>
	</div>
</template>

<script>
import { loadLazyComponent } from '@conduction/nextcloud-vue'
import { markRaw } from 'vue'
import { SAMPLE_PROPS, sampleContentOf } from '../constants/widgetSamples.js'

/** A renderer chunk that has not loaded after this long shows the icon. */
const LOAD_TIMEOUT = 10000

/**
 * WidgetMiniature — the miniature of a widget type on its card in Add widget
 * (REQ-WDG-048, board LpWidgetKiezen). It mounts the type's renderer with the
 * type's sample content and the `preview` flag (no request, poll or record)
 * once the card scrolls into view, scaled down inside a fixed frame. A type
 * without sample content, or one whose renderer fails, shows the type's icon
 * (the `icon` slot) in the frame instead. A Nextcloud dashboard widget of
 * another app shows the frame of such a widget: its app's icon and three
 * neutral rows.
 *
 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
 */
export default {
	name: 'WidgetMiniature',

	props: {
		/** The widget type, or '' for a card that is no Launchpad type. */
		type: { type: String, default: '' },

		/** The type's registry entry (renderer, sampleContent). */
		entry: { type: Object, default: null },

		/** Sample content that replaces the type's own, e.g. for the office pack. */
		content: { type: Object, default: null },

		/** A Nextcloud dashboard widget: `{iconUrl, iconClass}`. */
		ncWidget: { type: Object, default: null },
	},

	data() {
		return {
			visible: false,
			renderer: null,
			failed: false,
			observer: null,
		}
	},

	computed: {
		/**
		 * @return {object|null} The content the miniature draws.
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
		 */
		sample() {
			return this.content || (this.type ? sampleContentOf(this.type, this.entry) : null)
		},

		/**
		 * @return {object} Props besides the content the type's renderer needs to draw its sample without a request.
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
		 */
		sampleProps() {
			return (!this.content && SAMPLE_PROPS[this.type]) || {}
		},

		/**
		 * @return {object} A placement that exists nowhere: id 0, never saved.
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
		 */
		samplePlacement() {
			return { id: 0, widgetId: this.type, content: this.sample, showTitle: 0 }
		},

		/**
		 * @return {boolean} Whether the frame shows the type's icon.
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
		 */
		showFallback() {
			return !this.ncWidget && (this.failed || !this.sample || !this.entry?.renderer)
		},

		/**
		 * @return {string} loading, ready, nc or fallback; for tests and styling.
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
		 */
		state() {
			if (this.ncWidget) {
				return 'nc'
			}
			if (this.showFallback) {
				return 'fallback'
			}
			return this.renderer ? 'ready' : 'loading'
		},
	},

	/** @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048 */
	mounted() {
		if (this.ncWidget || this.showFallback) {
			return
		}
		if (typeof IntersectionObserver !== 'function') {
			this.show()
			return
		}
		this.observer = new IntersectionObserver((entries) => {
			if (entries.some((entry) => entry.isIntersecting)) {
				this.show()
			}
		})
		this.observer.observe(this.$refs.frame)
	},

	/** @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048 */
	beforeUnmount() {
		this.observer?.disconnect()
	},

	/**
	 * A renderer that throws while drawing its sample shows the icon instead.
	 *
	 * @return {boolean} False: the error goes no further than the card.
	 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
	 */
	errorCaptured() {
		this.failed = true
		return false
	},

	methods: {
		/**
		 * The card came into view: load the renderer's chunk and draw it; a
		 * chunk that does not load in time shows the icon.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
		 */
		async show() {
			if (this.visible) {
				return
			}
			this.visible = true
			this.observer?.disconnect()
			try {
				const renderer = await Promise.race([
					loadLazyComponent(this.entry.renderer),
					new Promise((resolve) => setTimeout(() => resolve(null), LOAD_TIMEOUT)),
				])
				if (!renderer) {
					this.failed = true
					return
				}
				this.renderer = markRaw(renderer)
			} catch {
				this.failed = true
			}
		},
	},
}
</script>

<style scoped>
.widget-miniature {
	position: relative;
	box-sizing: border-box;
	width: 100%;
	aspect-ratio: 200 / 112;
	overflow: hidden;
	padding: 10px 12px;
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-background-hover);
	pointer-events: none;
	user-select: none;
}

/* Board LpWidgetKiezen: the widget sits as a small white card on the grey
   band. The renderer draws at twice that card's size and is scaled down. */
.widget-miniature__card {
	position: relative;
	width: 100%;
	height: 100%;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.widget-miniature__stage {
	position: absolute;
	inset: 0 auto auto 0;
	width: 200%;
	height: 200%;
	transform: scale(0.5);
	transform-origin: top left;
	padding: 8px;
	box-sizing: border-box;
}

.widget-miniature__icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
	color: var(--color-text-maxcontrast);
}

.widget-miniature__nc {
	display: flex;
	flex-direction: column;
	gap: 8px;
	box-sizing: border-box;
	height: 100%;
	padding: 10px 12px;
	background: var(--color-main-background);
}

.widget-miniature__nc-head {
	display: flex;
	align-items: center;
	gap: 8px;
}

.widget-miniature__nc-icon {
	display: inline-block;
	width: 18px;
	height: 18px;
	background-size: contain;
}

.widget-miniature__nc-title,
.widget-miniature__nc-row {
	display: block;
	height: 8px;
	border-radius: 4px;
	background: var(--color-border);
}

.widget-miniature__nc-title {
	width: 45%;
}

.widget-miniature__nc-row {
	width: 85%;
	height: 10px;
}
</style>
