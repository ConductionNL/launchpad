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
		     shown in edit mode. Sits top-right over the wrapper's header area.
		     The absolute positioning lives on this wrapper DIV, not on
		     CnWidgetEditCog itself: the cog's root is an NcActions `.action-item`
		     which sets `position: relative` at equal specificity, so positioning
		     the component directly loses the cascade tie and the cog drops into
		     flow (pushing content). Wrapping matches the shared nc-vue
		     CnDashboardPage pattern. -->
		<div v-if="editMode" class="launchpad-widget__cog">
			<CnWidgetEditCog
				:menu-label="t('launchpad', 'Widget menu')"
				:edit-label="t('launchpad', 'Edit widget')"
				:delete-label="t('launchpad', 'Delete widget')"
				@edit="$emit('edit', placement)"
				@remove="$emit('remove', placement.id)" />
		</div>
	</div>
</template>

<script>
import { CnWidgetWrapper, CnWidgetEditCog } from '@conduction/nextcloud-vue'
import WidgetRenderer from './WidgetRenderer.vue'

export default {
	name: 'WidgetWrapper',

	components: {
		CnWidgetWrapper,
		CnWidgetEditCog,
		WidgetRenderer,
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
		 * Widget types that own their entire visual surface — labels, dividers,
		 * header banners, and the registry-driven `tile`. They keep a borderless
		 * / flush wrapper (no frame, no wrapper header) so the renderer paints
		 * edge to edge; the edit cog still overlays on top. (nc-widget is NOT
		 * here: CnNcWidgetWidget renders just a header + content, so it needs the
		 * card frame — see `rendersOwnHeader`.)
		 *
		 * @spec openspec/specs/widgets/spec.md
		 */
		isChromelessType() {
			return ['label', 'divider', 'header', 'tile'].includes(this.placement?.widgetId)
		},

		/**
		 * Types whose renderer paints its own header/title, so the wrapper must
		 * keep the card frame but suppress its own header to avoid a double
		 * title. The NC Dashboard widget (CnNcWidgetWidget) is the case.
		 *
		 * @spec openspec/specs/widgets/spec.md
		 * @return {boolean} true when the renderer owns the header.
		 */
		rendersOwnHeader() {
			return this.placement?.widgetId === 'nc-widget'
		},

		/** @spec openspec/specs/widgets/spec.md */
		isChromelessFrame() {
			return this.isTileWidget || this.isChromelessType
		},

		/** @spec openspec/specs/widgets/spec.md */
		showHeader() {
			if (this.isTileWidget || this.rendersOwnHeader) {
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

		/**
		 * Placement style overrides forwarded to CnWidgetWrapper. Includes
		 * `headerStyle.{backgroundColor,textColor}`, which CnWidgetWrapper now
		 * applies to its header natively (no per-app CSS-var workaround needed).
		 *
		 * @spec openspec/specs/widgets/spec.md
		 * @return {object} the styleConfig blob.
		 */
		styleConfig() {
			return this.placement.styleConfig || {}
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

/* The shared cog overlays the wrapper's top-right (the header's action area
   when a header is shown, otherwise over the content). */
.launchpad-widget__cog {
	position: absolute;
	top: 8px;
	right: 8px;
	z-index: 10;
}
</style>
