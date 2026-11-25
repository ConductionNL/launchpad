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
			:headerLink="headerLink"
			:headerActions="headerActions"
			:subtitle="headStatus.subtitle"
			:borderless="isChromelessFrame"
			:flush="isChromelessFrame || rendersOwnHeader"
			:showRefresh="false"
			:showRequestFeature="false"
			:showActions="showActionsMenu">
			<!-- REQ-IFRAME-005: the "Outage" tag in the card head, by icon-free
			     text so it never relies on colour alone. -->
			<template v-if="headStatus.outage" #title-meta>
				<span class="launchpad-widget__outage-tag" data-testid="widget-outage-tag">
					{{ t('launchpad', 'Outage') }}
				</span>
			</template>
			<!-- REQ-WDG-044: the content below the fold mounts when it comes
			     into view (or once the page has settled); the card shows at once. -->
			<LazyMount :eager="mountsAtOnce">
				<WidgetRenderer
					:widget="widget"
					:placement="placement"
					:editMode="editMode"
					@status="onRendererStatus"
					@editRequest="$emit('edit', placement)" />
			</LazyMount>
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
		     the menu itself: the cog's root is an NcActions `.action-item`
		     which sets `position: relative` at equal specificity, so positioning
		     the component directly loses the cascade tie and the cog drops into
		     flow (pushing content). Wrapping matches the shared nc-vue
		     CnDashboardPage pattern. -->
		<!-- dashboards-personal-hide-ui REQ-PERSUI-001: in view mode a widget
		     on a shared dashboard can be hidden for this person only. Not
		     offered for a compulsory widget (the server refuses it anyway). -->
		<!-- embedded-analytics task 40: an administrator can create an embed
		     token for this one widget, in Beheer ▸ Embeds with it pre-selected. -->
		<div v-if="showHideForMe || showEmbed" class="launchpad-widget__cog">
			<NcActions
				:aria-label="t('launchpad', 'Widget options')"
				:forceMenu="true">
				<NcActionButton
					v-if="showHideForMe"
					:closeAfterClick="true"
					data-testid="widget-hide-for-me"
					@click="$emit('hideForMe', placement)">
					<template #icon>
						<EyeOff :size="20" />
					</template>
					{{ t('launchpad', 'Hide for me') }}
				</NcActionButton>
				<NcActionLink
					v-if="showEmbed"
					:href="embedHref"
					data-testid="widget-create-embed-token">
					<template #icon>
						<CodeTags :size="20" />
					</template>
					{{ t('launchpad', 'Create embed token') }}
				</NcActionLink>
			</NcActions>
		</div>
		<!-- LpBewerken: in edit mode every widget shows a drag grip at its top
		     left and the three-dot menu at its top right. -->
		<span
			v-if="editMode"
			class="launchpad-widget__grip"
			aria-hidden="true"
			data-testid="widget-drag-grip">
			<DragVertical :size="16" />
		</span>
		<div v-if="editMode" class="launchpad-widget__cog">
			<!-- REQ-CVUI-007: the menu also holds "Who sees this widget?". -->
			<WidgetEditMenu
				:menuLabel="t('launchpad', 'Widget menu')"
				:removeLocked="!canRemove"
				@edit="$emit('edit', placement)"
				@whoSees="$emit('whoSees', placement)"
				@remove="$emit('remove', placement.id)" />
		</div>
	</div>
</template>

<script>
import {
	CnWidgetWrapper,
	NcActionButton,
	NcActions,
} from '@conduction/nextcloud-vue'
import { NcActionLink } from '@nextcloud/vue'
import CodeTags from 'vue-material-design-icons/CodeTags.vue'
import DragVertical from 'vue-material-design-icons/DragVertical.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import AcknowledgementPrompt from './AcknowledgementPrompt.vue'
import LazyMount from './LazyMount.vue'
import WidgetRenderer from './WidgetRenderer.vue'
import WidgetEditMenu from './Widgets/WidgetEditMenu.vue'
import {
	FLAG_PROP,
	LIST_PROP,
	NULLABLE_OBJECT_PROP,
	REQUIRED_OBJECT_PROP,
	TEXT_PROP,
} from '../constants/propDefinitions.js'
import { resolveWidgetTitle } from '../utils/widgetTitle.js'

/**
 * Whether two links lead to the same page, ignoring the host, `/index.php`
 * and a trailing slash.
 *
 * @param {string} a A link.
 * @param {string} b Another link.
 * @return {boolean}
 */
export function sameDestination(a, b) {
	const path = (link) =>
		String(link ?? '')
			.replace(/^[a-z]+:\/\/[^/]+/i, '')
			.replace(/^\/index\.php/, '')
			.replace(/\/+$/, '')
	return a !== undefined && a !== null && path(a) === path(b)
}

/**
 * Whether this page is the headless export render (`?export=1`).
 *
 * @param {Location} [location] Injected for tests.
 * @return {boolean}
 * @spec openspec/changes/archive/2026-11-25-widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
 */
export function isExportRender(location = typeof window !== 'undefined' ? window.location : null) {
	if (!location || typeof location.search !== 'string') {
		return false
	}
	return new URLSearchParams(location.search).get('export') === '1'
}

export default {
	name: 'WidgetWrapper',

	components: {
		CnWidgetWrapper,
		CodeTags,
		DragVertical,
		EyeOff,
		NcActionButton,
		NcActionLink,
		NcActions,
		WidgetRenderer,
		WidgetEditMenu,
		AcknowledgementPrompt,
		LazyMount,
	},

	props: {
		placement: REQUIRED_OBJECT_PROP,

		widget: NULLABLE_OBJECT_PROP,

		/**
		 * The Nextcloud Dashboard widget catalog.
		 *
		 * Needed in addition to `widget` because an `nc-widget` placement
		 * proxies a DIFFERENT widget than its own `widgetId` names, and
		 * resolving that title needs the whole catalog rather than the single
		 * pre-resolved entry. Passed as a prop rather than read from the store
		 * so this stays a presentational component that mounts without pinia.
		 */
		availableWidgets: LIST_PROP,

		editMode: FLAG_PROP,

		// REQ-ACK-002: whether this placement is an outstanding mandatory-read
		// item for the current user. Resolved by the parent from the pending
		// set; defaults false so widgets without a requirement are unchanged.
		outstandingAcknowledgement: FLAG_PROP,

		// dashboards-personal-hide-ui: the host allows "Hide for me" here
		// (a shared dashboard the viewer does not own).
		canHideForMe: FLAG_PROP,

		// REQ-PERM: the host refuses removal (a compulsory widget on a dashboard
		// held with less than full permission), so the cog offers no Delete.
		removeLocked: FLAG_PROP,

		// embedded-analytics task 40: the Beheer ▸ Embeds link that creates an
		// embed token for this widget; '' when the viewer may not create one.
		embedHref: TEXT_PROP,
	},

	emits: ['remove', 'style', 'edit', 'whoSees', 'acknowledged', 'hideForMe'],

	data() {
		return {
			// What the renderer reports for the card head (REQ-IFRAME-005):
			// the source line under the title and the outage tag.
			headStatus: { subtitle: '', outage: false },
		}
	},

	computed: {
		/**
		 * Whether the content mounts at once instead of when it comes into
		 * view: in edit mode, and in the headless export render (`?export=1`),
		 * which prints the whole dashboard.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-25-widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
		 */
		mountsAtOnce() {
			return this.editMode || isExportRender()
		},

		/**
		 * Whether "Hide for me" is offered: view mode, allowed by the host,
		 * and not a compulsory widget.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		showHideForMe() {
			return (
				this.canHideForMe
				&& !this.editMode
				&& Number(this.placement?.isCompulsory) !== 1
			)
		},

		/**
		 * Whether "Create embed token" is offered: view mode, with a link
		 * from the host (only an administrator gets one).
		 *
		 * @return {boolean}
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
		 */
		showEmbed() {
			return this.embedHref !== '' && !this.editMode
		},

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
		 * header banners, the registry-driven `tile` and the `app-tile`. They keep a borderless
		 * / flush wrapper (no frame, no wrapper header) so the renderer paints
		 * edge to edge; the edit cog still overlays on top. (nc-widget is NOT
		 * here: CnNcWidgetWidget renders just a header + content, so it needs the
		 * card frame — see `rendersOwnHeader`.)
		 *
		 * @spec openspec/specs/widgets/spec.md
		 */
		isChromelessType() {
			return ['label', 'section-heading', 'divider', 'header', 'tile', 'app-tile'].includes(
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
			return (
				this.placement?.widgetId === 'nc-widget'
				&& this.placement?.content?.showHeader !== false
			)
		},

		/**
		 * Whether the card's overflow menu shows. On by default outside edit
		 * mode; a placement whose content says `showActions: false` hides it.
		 * In edit mode the widget's own three-dot menu takes its place
		 * (LpBewerken).
		 *
		 * @spec openspec/specs/widgets/spec.md
		 * @return {boolean} false only for an opted-out placement in view mode.
		 */
		showActionsMenu() {
			return !this.editMode && this.placement?.content?.showActions !== false
		},

		/**
		 * Buttons in the card's title row. The compact Files list draws its
		 * Upload there (LpWerkplek "Bestanden") and asks the widget to open
		 * its file picker.
		 *
		 * @return {Array<object>}
		 * @spec openspec/specs/widgets/spec.md
		 */
		headerActions() {
			const content = this.placement?.content || {}
			if (this.editMode || this.placement?.widgetId !== 'files' || content.layout !== 'compact' || content.allowUpload !== true) {
				return []
			}
			const placementId = this.placement.id
			return [{
				key: 'upload',
				label: this.t('launchpad', 'Upload'),
				icon: 'Upload',
				variant: 'secondary',
				run: () => window.dispatchEvent(new CustomEvent('cn-files-widget:upload', { detail: { placementId } })),
			}]
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
		/**
		 * The header title. Shares one resolver with quick search so the name
		 * shown on the tile and the name it is findable by cannot drift — see
		 * `utils/widgetTitle.js`.
		 *
		 * `availableWidgets` rather than the single `widget` prop, because an
		 * `nc-widget` placement proxies a DIFFERENT widget than its own
		 * `widgetId` names, and resolving that needs the whole catalog.
		 *
		 * @return {string} the title to render.
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		widgetTitle() {
			return resolveWidgetTitle(this.placement, this.availableWidgets)
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetIconUrl() {
			return this.widget?.iconUrl || null
		},

		/** @spec openspec/specs/widgets/spec.md */
		widgetButtons() {
			const buttons = this.widget?.buttons || []
			const headerHref = this.headerLink?.href
			if (!headerHref) {
				return buttons
			}
			return buttons.filter(
				(button) => !sameDestination(button?.link, headerHref),
			)
		},

		/**
		 * The text link in the card's header (`content.headerLink`,
		 * `{ label, href }`), as LpVandaag's "Open agenda". Null without a
		 * label and a link.
		 *
		 * @return {{label: string, href: string}|null}
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-the-start-page-fits-the-screen
		 */
		headerLink() {
			const link = this.placement?.content?.headerLink
			if (
				!link
				|| typeof link.label !== 'string'
				|| link.label === ''
				|| typeof link.href !== 'string'
				|| link.href === ''
			) {
				return null
			}
			return { label: link.label, href: link.href }
		},

		/** @spec openspec/specs/permissions/spec.md */
		canRemove() {
			return !this.removeLocked
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

	methods: {
		/**
		 * Keep the card-head state a renderer reports (REQ-IFRAME-005): the
		 * source line under the title and whether the "Outage" tag shows.
		 *
		 * @param {{subtitle?: string, outage?: boolean}|null} status The renderer's head status.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-24-embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
		 */
		onRendererStatus(status) {
			this.headStatus = {
				subtitle: typeof status?.subtitle === 'string' ? status.subtitle : '',
				outage: status?.outage === true,
			}
		},
	},
}
</script>

<style scoped>
.launchpad-widget__outage-tag {
	display: inline-flex;
	align-items: center;
	padding: 0 8px;
	border-radius: var(--border-radius-pill, 12px);
	border: 1px solid var(--color-error);
	color: var(--color-error-text, var(--color-error));
	font-size: 0.75rem;
	font-weight: 600;
	line-height: 20px;
	white-space: nowrap;
}

.launchpad-widget {
	position: relative;
	height: 100%;
}

.launchpad-widget__wrapper {
	height: 100%;
}

/* The drag grip sits at the top left of a widget in edit mode; the card's
   title moves right to make room for it (LpBewerken). On a tile both the
   grip and the menu are small white squares. */
.launchpad-widget__grip {
	position: absolute;
	top: 18px;
	inset-inline-start: 14px;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 20px;
	height: 20px;
	border-radius: 4px;
	color: var(--color-text-maxcontrast);
	pointer-events: none;
}

.launchpad-widget--editing :deep(.cn-widget-wrapper__header) {
	padding-inline-start: 42px;
}

.launchpad-widget--editing:has(.tile-widget, .cn-app-tile-widget) .launchpad-widget__grip {
	top: 6px;
	inset-inline-start: 6px;
	background: var(--color-main-background);
	color: var(--color-main-text);
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
