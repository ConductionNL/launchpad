<!--
  - SPDX-FileCopyrightText: 2024 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	WidgetWrapper — per-placement widget chrome.

	Renders the shared nc-vue `CnWidgetWrapper` (header, content, footer,
	styleConfig) so launchpad widgets match the OpenBuild widget surface, and
	overlays the single shared `WidgetEditCog` (Edit / Delete) in edit mode.
	The wrapper's own overflow actions menu is suppressed (`:show-refresh` /
	`:show-request-feature` false, no action-items) so the gear cog is the only
	affordance.
-->

<template>
	<div
		class="launchpad-widget"
		:class="{ 'launchpad-widget--editing': editMode }"
		:data-testid="placement?.id ? `widget-placement-${placement.id}` : 'widget-placement'"
		:data-widget-id="placement?.widgetId">
		<CnWidgetWrapper
			class="launchpad-widget__wrapper"
			:class="{ 'launchpad-widget__wrapper--custom-header': hasCustomHeaderStyle }"
			:style="headerStyleVars"
			:title="widgetTitle"
			:show-title="showHeader"
			:icon-url="widgetIconUrl"
			:icon-class="widget && widget.iconClass ? widget.iconClass : null"
			:style-config="styleConfig"
			:buttons="widgetButtons"
			:borderless="isChromelessFrame"
			:flush="isChromelessFrame"
			:show-refresh="false"
			:show-request-feature="false">
			<WidgetRenderer
				:widget="widget"
				:placement="placement" />
		</CnWidgetWrapper>

		<!-- One shared edit cog for every widget type (data, NC, chrome-less),
		     shown in edit mode. Sits top-right over the wrapper's header area. -->
		<WidgetEditCog
			v-if="editMode"
			class="launchpad-widget__cog"
			@edit="$emit('edit', placement)"
			@remove="$emit('remove', placement.id)" />
	</div>
</template>

<script>
import { CnWidgetWrapper } from '@conduction/nextcloud-vue'
import WidgetRenderer from './WidgetRenderer.vue'
import WidgetEditCog from './WidgetEditCog.vue'

export default {
	name: 'WidgetWrapper',

	components: {
		CnWidgetWrapper,
		WidgetRenderer,
		WidgetEditCog,
	},

	props: {
		placement: {
			type: Object,
			required: true,
		},
		widget: {
			type: Object,
			default: null,
		},
		editMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['remove', 'style', 'edit'],

	computed: {
		isTileWidget() {
			return this.placement.widgetId && this.placement.widgetId.startsWith('tile-')
		},

		/**
		 * Widget types that own their entire visual surface — labels,
		 * dividers, header banners, the registry-driven `tile`, and the NC
		 * Dashboard API widget (which renders its own header + title). They
		 * keep a borderless / flush wrapper (no frame, no wrapper header) so
		 * the renderer paints edge to edge; the edit cog still overlays on top.
		 *
		 * @spec openspec/specs/widgets/spec.md
		 */
		isChromelessType() {
			return ['label', 'divider', 'header', 'tile', 'nc-widget'].includes(this.placement?.widgetId)
		},

		/** @spec openspec/specs/widgets/spec.md */
		isChromelessFrame() {
			return this.isTileWidget || this.isChromelessType
		},

		/** @spec openspec/specs/widgets/spec.md */
		showHeader() {
			if (this.isTileWidget) {
				return false
			}
			if (this.isChromelessType && !this.placement.customTitle) {
				return false
			}
			return this.placement.showTitle !== false
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetTitle() {
			return this.placement.customTitle || this.widget?.title || this.t('launchpad', 'Widget')
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetIconUrl() {
			return this.widget?.iconUrl || null
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetButtons() {
			return this.widget?.buttons || []
		},

		/** @spec openspec/specs/widgets/spec.md */
		canRemove() {
			// Can't remove compulsory widgets unless full permission
			return !this.placement.isCompulsory
		},

		/** @spec openspec/specs/widgets/spec.md */
		styleConfig() {
			return this.placement.styleConfig || {}
		},

		/**
		 * Per-widget custom header colours (`styleConfig.headerStyle`).
		 * CnWidgetWrapper's own `styleConfig` doesn't carry these, so we
		 * forward them as CSS variables and re-apply them to its header below.
		 *
		 * @spec openspec/specs/widgets/spec.md
		 */
		headerStyle() {
			return this.styleConfig.headerStyle || {}
		},

		/** @spec openspec/specs/widgets/spec.md */
		hasCustomHeaderStyle() {
			return Boolean(this.headerStyle.backgroundColor || this.headerStyle.textColor)
		},

		/** @spec openspec/specs/widgets/spec.md */
		headerStyleVars() {
			const vars = {}
			if (this.headerStyle.backgroundColor) {
				vars['--lp-header-bg'] = this.headerStyle.backgroundColor
			}
			if (this.headerStyle.textColor) {
				vars['--lp-header-color'] = this.headerStyle.textColor
			}
			return vars
		},
	},
}
</script>

<style scoped>
.launchpad-widget {
	position: relative;
	height: 100%;
}

.launchpad-widget__wrapper {
	height: 100%;
}

/* Restore per-widget custom header colours (styleConfig.headerStyle), which
   CnWidgetWrapper's styleConfig doesn't carry. Driven by the CSS vars set on
   the wrapper root; the title inherits the header colour. */
.launchpad-widget__wrapper--custom-header :deep(.cn-widget-wrapper__header) {
	background-color: var(--lp-header-bg, transparent);
	color: var(--lp-header-color, inherit);
}

/* The shared cog overlays the wrapper's top-right (the header's action area
   when a header is shown, otherwise over the content). */
.launchpad-widget__cog {
	position: absolute;
	top: 8px;
	right: 8px;
	z-index: 10;
}
</style>
