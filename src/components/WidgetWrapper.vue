<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
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
		:data-testid="
			placement?.id ? `widget-placement-${placement.id}` : 'widget-placement'
		"
		:data-widget-id="placement?.widgetId">
		<CnWidgetWrapper
			class="launchpad-widget__wrapper"
			:title="widgetTitle"
			:showTitle="showHeader"
			:chrome="wrapperChrome"
			:iconUrl="widgetIconUrl"
			:iconClass="widget && widget.iconClass ? widget.iconClass : null"
			:styleConfig="styleConfig"
			:buttons="widgetButtons"
			:borderless="isChromelessFrame"
			:flush="isChromelessFrame || rendersOwnHeader"
			:showRefresh="false"
			:showRequestFeature="false">
			<WidgetRenderer :widget="widget" :placement="placement" />
		</CnWidgetWrapper>

		<!-- REQ-ACK-002: forced-delivery read-gate. Overlays the widget with a
		     blocking sign-off prompt when the placement requires an
		     acknowledgement the current user still owes. Suppressed in edit
		     mode so an author can still configure the widget. -->
		<AcknowledgementPrompt
			v-if="showAcknowledgementGate"
			:placement="placement"
			@acknowledged="$emit('acknowledged', placement)" />

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
				:menuLabel="t('launchpad', 'Widget menu')"
				:editLabel="t('launchpad', 'Edit widget')"
				:deleteLabel="t('launchpad', 'Delete widget')"
				@edit="$emit('edit', placement)"
				@remove="$emit('remove', placement.id)" />
		</div>
	</div>
</template>

<script>
import { CnWidgetEditCog, CnWidgetWrapper } from '@conduction/nextcloud-vue'
import AcknowledgementPrompt from './AcknowledgementPrompt.vue'
import WidgetRenderer from './WidgetRenderer.vue'

export default {
	name: 'WidgetWrapper',

	components: {
		CnWidgetWrapper,
		CnWidgetEditCog,
		WidgetRenderer,
		AcknowledgementPrompt,
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

		// REQ-ACK-002: whether this placement is an outstanding mandatory-read
		// item for the current user. Resolved by the parent from the pending
		// set; defaults false so widgets without a requirement are unchanged.
		outstandingAcknowledgement: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['remove', 'style', 'edit', 'acknowledged'],

	computed: {
		/**
		 * Whether this placement renders a reusable tile, identified by the
		 * `tile-` widgetId prefix.
		 *
		 * @spec openspec/specs/tiles/spec.md#requirement-list-user-tiles-req-tile-002
		 * @return {boolean} True for tile placements.
		 */
		isTileWidget() {
			return (
				this.placement.widgetId
				&& this.placement.widgetId.startsWith('tile-')
			)
		},

		/**
		 * REQ-ACK-002: whether the forced-delivery read-gate should overlay
		 * this widget — the placement requires an acknowledgement the current
		 * user still owes, and we are NOT in edit mode (an author configuring
		 * the widget is not a recipient sign-off context).
		 *
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 * @return {boolean} true when the sign-off prompt must block the widget.
		 */
		showAcknowledgementGate() {
			if (this.editMode) {
				return false
			}
			return (
				Number(this.placement?.requiresAcknowledgement) === 1
				&& this.outstandingAcknowledgement
			)
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
			return ['label', 'divider', 'header', 'tile'].includes(
				this.placement?.widgetId,
			)
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

		/**
		 * Card chrome variant forwarded to CnWidgetWrapper. Card widgets (NC
		 * dashboard widgets + data widgets) use the shared `nc-dashboard`
		 * variant so they are visually identical to the native Nextcloud
		 * dashboard (apps/dashboard) by default — same tokens, user-overridable
		 * via styleConfig. Chromeless surfaces (tiles, labels, dividers,
		 * headers) keep the default chrome since they paint their own surface.
		 *
		 * @spec openspec/specs/widgets/spec.md
		 * @return {string} 'nc-dashboard' for card widgets, else 'default'.
		 */
		wrapperChrome() {
			return this.isChromelessFrame ? 'default' : 'nc-dashboard'
		},

		/** @spec openspec/specs/widgets/spec.md */
		showHeader() {
			if (this.isTileWidget || this.rendersOwnHeader) {
				return false
			}
			if (this.isChromelessType && !this.placement.customTitle) {
				return false
			}
			// `showTitle` round-trips through the DB as an integer (0/1) or a
			// string, so a strict `!== false` check wrongly keeps the header
			// for a stored 0. Treat 0 / '0' / false as "off"; an absent flag
			// (legacy placements) still defaults to shown.
			const flag = this.placement.showTitle
			if (flag === undefined || flag === null) {
				return true
			}
			return flag !== false && flag !== 0 && flag !== '0' && flag !== ''
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetTitle() {
			return (
				this.placement.customTitle
				|| this.widget?.title
				|| this.t('launchpad', 'Widget')
			)
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
			// The backend (`WidgetPlacement::jsonSerialize()`) emits `{}` for an
			// empty styleConfig, so a plain `|| {}` fallback satisfies
			// CnWidgetWrapper's Object-typed prop.
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
