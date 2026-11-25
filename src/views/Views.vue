<template>
	<div id="launchpad-app" :class="{ 'launchpad-app--fill': fillsHeight, 'launchpad-app--kiosk': kiosk }">
		<!-- Slide-in sidebar (REQ-SWITCH-001..007). The sidebar keeps the
		     `isOpen` prop / `update:open` event pair mandated by the spec.
		     Vue 3 removed the component-level `model: { prop, event }`
		     option, so a bare `v-model` here would bind `modelValue` /
		     `update:modelValue` — neither of which the sidebar declares —
		     and the panel would never open. Bind both halves explicitly.
		     Once `runtime-shell` ships and replaces this view with
		     `WorkspaceApp.vue`, the same binding shape applies.

		     TELEPORTED TO BODY, and the reason is a stacking context, not a
		     z-index. The sidebar and its backdrop are `position: fixed`, but
		     fixed positioning does not escape a stacking context — it only
		     escapes the scroll flow. Until #551 this view WAS the page, so
		     there was no context to escape. #551 adopted the shared shell, so
		     this view now renders inside `#app-content`, a sibling of
		     `.app-navigation`, and NcAppNavigation carries `z-index: 1400` on
		     ITSELF. A sibling with a z-index paints above the whole of a
		     sibling subtree whose own z-index is auto, so the sidebar's 1500
		     could never win: it was competing inside a box that had already
		     lost.

		     That cost 58 e2e failures across eleven specs, every one of them
		     `locator.click` timing out with the SAME interceptor named in the
		     log, `[data-testid="cn-nav"] .app-navigation`. Raising the number
		     again would not have helped. Teleporting makes the sidebar a
		     sibling of the navigation rather than a descendant of the content,
		     which is the only place its z-index means what it says. -->
		<!-- REQ-KIOSK-001: a kiosk view has no switcher and no top controls. -->
		<Teleport v-if="!kiosk" to="body">
			<DashboardSwitcherSidebar
				:isOpen="sidebarOpen"
				:groupName="primaryGroupName"
				:groupDashboards="sidebarGroupDashboards"
				:userDashboards="sidebarUserDashboards"
				:activeDashboardId="activeDashboard?.id"
				:allowUserDashboards="allowUserDashboards"
				:canEdit="canEdit"
				:defaultUuid="defaultDashboardUuid"
				:dashboardQuotaReached="dashboardQuotaReached"
				:dashboardQuotaTooltip="dashboardQuotaTooltip"
				:isEditMode="isEditMode"
				:filterFields="detailFields"
				@update:open="sidebarOpen = $event"
				@filter="onDetailFilter"
				@switch="onSidebarSwitch"
				@createDashboard="onSidebarCreateDashboard"
				@deleteDashboard="onSidebarDeleteDashboard"
				@toggleEdit="onRowToggleEdit"
				@openConfig="onRowOpenConfig"
				@addWidget="onRowAddWidget"
				@importBookmarks="onRowImportBookmarks"
				@setDefault="onRowSetDefault" />
			<SidebarBackdrop v-if="sidebarOpen" @close="sidebarOpen = false" />
		</Teleport>

		<!-- The page's top-right controls: the "Meer" menu, and the
		     acknowledgement count and hidden-widgets control when they apply. -->
		<!-- TELEPORTED FOR THE SAME REASON AS THE SIDEBAR, and specifically
		     because of it. These controls are `position: fixed` with
		     `z-index: 1000`, chosen to sit just above the backdrop's original
		     999 so that "click the hamburger again to close the sidebar"
		     works — the sidebar is open, the backdrop is up, and the toggle
		     has to stay reachable through it.

		     Teleporting the backdrop to body broke that pairing: the backdrop
		     became a body-level sibling and these controls stayed inside
		     `#app-content`, whose own z-index is auto, so the backdrop painted
		     over them whatever number they carried. `runtime-shell-canEdit`
		     caught it immediately, with the backdrop named as the interceptor
		     over a button the log calls visible, enabled and stable.

		     Both halves have to move for the ordering between them to mean
		     anything. -->
		<!-- On a dashboard with a greeting on the page ground, the controls
		     dock into that row (headerActionsDock.js); in edit mode they dock
		     at the end of the edit bar. -->
		<Teleport v-if="!kiosk" :to="controlsDock || 'body'">
			<div
				class="launchpad-floating-controls"
				:class="{ 'launchpad-floating-controls--docked': controlsDock !== null }"
				data-testid="launchpad-top-controls">
				<!-- Board LpMeer: More is the page header's only control. -->
				<DashboardMoreMenu
					:dashboard="activeDashboard"
					:source="activeDashboardSource"
					:userDashboards="sidebarUserDashboards"
					:groupDashboards="sidebarGroupDashboards"
					:sharedDashboards="sharedWithMeDashboards"
					:defaultUuid="defaultDashboardUuid"
					:canEdit="canEdit"
					:canShare="canShareActiveDashboard"
					:canDuplicate="canDuplicateActiveDashboard"
					:canViewReadReceipts="
						canEdit && acknowledgementAnnouncementKeys.length > 0
					"
					:isEditMode="isEditMode"
					@switch="onSidebarSwitch"
					@openAll="sidebarOpen = true"
					@toggleEdit="
						onRowToggleEdit(activeDashboard, activeDashboardSource)
					"
					@addWidget="openWidgetModal"
					@setDefault="
						onRowSetDefault(activeDashboard, activeDashboardSource)
					"
					@share="openShareDrawer"
					@copyLink="copyDashboardLink"
					@duplicate="onSidebarCreateDashboard"
					@readReceipts="
						openAcknowledgementReport(acknowledgementAnnouncementKeys[0])
					"
					@delete="onSidebarDeleteDashboard(activeDashboard.id)" />
				<!-- dashboard-acknowledgements REQ-ACK-002: dashboard-level count
			     of the user's outstanding mandatory-read items. Hidden entirely
			     when zero so dashboards without acknowledgement requirements are
			     visually unchanged. -->
				<span
					v-if="outstandingAcknowledgementCount > 0"
					class="launchpad-ack-indicator"
					data-testid="acknowledgement-outstanding-count"
					:title="
						t('launchpad', 'You have items awaiting acknowledgement')
					">
					{{
						n(
							'launchpad',
							'%n item to acknowledge',
							'%n items to acknowledge',
							outstandingAcknowledgementCount,
						)
					}}
				</span>
				<!-- dashboards-personal-hide-ui REQ-PERSUI-002: what this
				     person hid, with Show again and Reset my view. -->
				<HiddenWidgetsControl
					v-if="canHideForMe"
					:hiddenPlacements="personalLayer.hiddenPlacements"
					:availableWidgets="availableWidgets"
					@showAgain="onShowAgain"
					@reset="onResetPersonalView" />
			</div>
		</Teleport>

		<!-- Delete confirmation, with the number of dashboards under it. -->
		<DeleteDashboardDialog
			v-if="mountedModals.deleteDialog"
			:open="deleteTarget !== null"
			:name="deleteTarget?.name ?? ''"
			:childCount="deleteChildCount"
			@update:open="onDeleteDialog"
			@confirm="confirmDeleteDashboard" />



		<!-- Admin read-receipt report (REQ-ACK-004/006). -->
		<AcknowledgementReportModal
			v-if="mountedModals.ackReport"
			:open="ackReportOpen"
			:announcementKey="ackReportKey"
			:title="ackReportTitle"
			:dashboardName="activeDashboard?.name || ''"
			@close="ackReportOpen = false" />

		<!-- Main dashboard grid -->
		<!-- platform-dashboard-theme-and-css REQ-THM-001: the dashboard's own
		     look is drawn on this region only, as scoped CSS variables; the
		     header, the navigation and other dashboards keep the theme. -->
		<div
			class="launchpad-container launchpad-dashboard"
			:class="{
				'launchpad-edit-mode': isEditMode,
				'launchpad-container--fill': fillsHeight,
			}"
			:data-dashboard-uuid="activeDashboard?.uuid || null"
			:style="dashboardLookStyle"
			data-testid="launchpad-dashboard-region">
			<!-- Board LpBewerken: the edit bar, with the edit actions. -->
			<DashboardEditBar
				v-if="isEditMode && activeDashboard"
				:dashboardName="activeDashboard.name || ''"
				:columns="activeDashboard.gridColumns || 12"
				:canConfigure="canConfigureActiveDashboard"
				@setColumns="setGridColumns"
				@addWidget="openWidgetModal"
				@openSettings="openConfigModal"
				@done="toggleEditMode"
				@dock="editBarDock = $event" />
			<!-- openregister-leaf-integrations REQ-LEAF-002: the Talk
			     discussion of a shared or group dashboard; the access chain
			     is documented in DashboardDiscussion. -->
			<DashboardDiscussion
				v-if="activeDashboard?.uuid && !isEditMode"
				:dashboard="activeDashboard"
				:sharedWithMe="sharedWithMeDashboards"
				:currentUserId="currentUserId" />
			<!-- Every dashboard without a greeting row opens with its name and
			     the "Meer" menu, its only action (LpWerkplek, LpIntranet). -->
			<DashboardPageHeader
				v-if="activeDashboard && !kiosk && !hasGroundHeader && !isEditMode"
				:dashboard="activeDashboard"
				:source="activeDashboardSource"
				:isDefault="activeDashboard.uuid === defaultDashboardUuid"
				:permissionLevel="permissionLevel"
				:showsPeriod="showsPeriod"
				:period="dashboardPeriod"
				@update:period="onPeriodChange" />
			<DashboardLanguageHeading :dashboard="activeDashboard" />
			<DashboardBreadcrumb
				:dashboard="activeDashboard"
				@navigate="onBreadcrumbNavigate" />
			<!-- drill-down-cross-widget-filter REQ-CWF-006, REQ-CWF-007. -->
			<ActiveFiltersPanel
				v-if="activeDashboard"
				:clauses="filterClauses"
				:labels="filterSession?.labels ?? {}"
				:widgetNames="filterWidgetNames"
				@remove="onFilterRemove"
				@reset="onFilterReset" />
			<FilterBreadcrumb
				v-if="activeDashboard && filterSession"
				:hierarchies="filterSession.bus.hierarchies"
				:clauses="filterClauses"
				:labels="filterSession.labels"
				@pop="onFilterPop" />
			<p
				v-if="canManagePublication && publicationNote"
				class="launchpad-publication-note"
				role="status"
				data-testid="publication-note">
				{{ publicationNote }}
			</p>
			<!-- building-edit-a-dashboard-together: no lock in edit mode; a
				refusal says so, and colleagues editing are shown. -->
			<!-- platform-baseline REQ-PLB-009: on a phone, the offer to put
			     LaunchPad on the home screen (board LpMobiel). -->
			<InstallBanner v-if="!kiosk && !isEditMode" :kiosk="kiosk" />
			<!-- phone-hidden-widgets-named REQ-MRA-007: on a phone, the
			     widgets that need a larger screen are hidden and named. -->
			<PhoneHiddenNotice v-if="!kiosk" :titles="phoneHiddenTitles" />
			<EditLockBanner :status="presence.state.status" />
			<CoEditorsBanner
				v-if="isEditMode"
				:editors="presence.state.editors" />
			<CnDashboardGrid
				v-if="activeDashboard"
				:layout="gridLayout"
				:editable="isEditMode"
				:fillHeight="fillsHeight"
				:columns="activeDashboard.gridColumns || 12"
				:cellHeight="60"
				:margin="8"
				:columnOpts="dashboardColumnOpts"
				cellHeightCssVar="--launchpad-cell-height"
				:itemKey="placementItemKey"
				:itemLabel="placementItemLabel"
				@layoutChange="updatePlacements">
				<template #widget="{ item }">
					<!-- tile-quick-search. Dimming is REACTIVE: the `search`
					     widget writes the current match set into the tileSearch
					     store and this binding renders the consequence, so no
					     component reaches in to toggle classes and nothing reads
					     the placement id back out of the DOM (ADR-004).
					     `data-placement-id` remains as the addressing mechanism
					     for the two things that must stay imperative — scrolling
					     a chosen tile into view and clicking its link — and as
					     the e2e suite's selector. -->
					<div
						class="launchpad-grid-item"
						:class="{
							'launchpad-grid-item--dimmed': isTileDimmed(item),
							'launchpad-grid-item--hidden-for-viewers': item.hiddenForViewers === true,
						}"
						:data-placement-id="item.id"
						@contextmenu="onWidgetRightClick($event, item)">
						<!-- REQ-VIS-013: the visibility rules hide this widget for its
						     viewers; the owner and editors still see it, dimmed. -->
						<span
							v-if="item.hiddenForViewers === true"
							class="launchpad-grid-item__hidden-badge"
							data-testid="hidden-for-viewers-badge">
							{{ t('launchpad', 'Hidden for viewers') }}
						</span>
						<!-- Tile placements render the launcher tile directly. -->
						<TileWidget
							v-if="isTilePlacement(item)"
							:tile="getTileData(item)"
							:editMode="isEditMode"
							:placementId="item.id"
							:content="item.content || null"
							:healthPingEnabled="
								item.content
								&& item.content.healthPingEnabled === true
							"
							:pingInterval="item.content && item.content.pingInterval"
							:linkTarget="(item.content && item.content.linkTarget) || null"
							:defaultLinkTarget="(activeDashboard && activeDashboard.defaultLinkTarget) || null"
							:removeLocked="!isRemovablePlacement(item)"
							@edit="openTileEditorForEdit(item)"
							@remove="removeWidget(item.id)" />
						<!-- All other placements render through the widget wrapper. -->
						<WidgetWrapper
							v-else
							:placement="item"
							:widget="getWidget(item.widgetId)"
							:availableWidgets="availableWidgets"
							:editMode="isEditMode"
							:outstandingAcknowledgement="
								isPlacementOutstanding(item)
							"
							:canHideForMe="canHideForMe"
							:embedHref="placementEmbedHref(item)"
							:removeLocked="!isRemovablePlacement(item)"
							@remove="removeWidget(item.id)"
							@style="openStyleEditor(item)"
							@edit="handleContextMenuEdit(item)"
							@whoSees="handleContextMenuVisibilityRules(item)"
							@acknowledged="onWidgetAcknowledged"
							@hideForMe="onHideForMe" />
					</div>
				</template>
			</CnDashboardGrid>

			<!-- Loading shim. The empty-state below previously rendered
			     during the initial fetch because `activeDashboard` is
			     null until `loadDashboards()` resolves; the spinner
			     keeps the "No dashboard yet" CTA out of view while the
			     user is just waiting on data. -->
			<div v-else-if="loading" class="launchpad-loading">
				<NcLoadingIcon :size="48" />
			</div>

			<!-- Empty-state shell. The "Create dashboard" affordance is gated
			     by the admin `allow_user_dashboards` flag (REQ-ASET-003,
			     extended). When the flag is off the button MUST be hidden
			     and the description swapped for a localised explainer so
			     the workspace never offers an action that would 403. -->
			<div v-else class="launchpad-empty">
				<NcEmptyContent
					:name="t('launchpad', 'No dashboard yet')"
					:description="emptyStateDescription">
					<template #icon>
						<ViewDashboard :size="64" />
					</template>
					<template v-if="allowUserDashboards" #action>
						<NcButton variant="primary" @click="handleCreateDashboard">
							{{ t('launchpad', 'Create dashboard') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</div>
			<!-- reactions-bar-at-the-foot REQ-RXN-010: view mode, at the foot
			     of the dashboard under the last widget, over the full width. -->
			<DashboardReactions
				v-if="activeDashboard?.uuid && !isEditMode"
				:dashboardUuid="activeDashboard.uuid" />
		</div>

		<!-- Widget picker modal -->
		<WidgetPickerModal
			v-if="mountedModals.widgetPicker"
			:open="isWidgetModalOpen"
			:widgets="availableWidgets"
			:placedWidgetIds="placedWidgetIds"
			:discoveredApps="discoveredApps"
			:placedAppIds="placedDiscoveredAppIds"
			:catalogueApps="catalogueApps"
			:customTypes="customWidgetTypes"
			:requestAppFn="requestCatalogueApp"
			@close="onWidgetPickerClose"
			@add="addWidget"
			@addCustom="onPickCustomWidget"
			@addApp="addDiscoveredApp"
			@addCatalogueApp="addCatalogueApp"
			@addOfficePack="addOfficePack" />

		<!-- Custom widget add/edit modal — registry-driven host for label,
		     text, image, link-button, etc. (REQ-WDG-010..014). The modal does
		     no API calls itself; this view persists the emitted payload. -->
		<CnAddWidgetModal
			:show="isCustomWidgetModalOpen"
			:preselectedType="customWidgetPreselectedType"
			:initialType="customWidgetInitialType"
			:editingWidget="customWidgetEditing"
			:notices="customWidgetNotices"
			:uploadFn="iconUploadFn"
			:fileUploadFn="imageFileUpload"
			:calendarsFetcher="fetchCalendars"
			:setReturnFocus="dialogReturnFocus"
			@close="closeCustomWidgetModal"
			@submit="saveCustomWidget" />

		<!-- Dashboard configuration modal (also used for creating a new dashboard) -->
		<DashboardConfigModal
			v-if="mountedModals.config"
			:open="isConfigModalOpen"
			:dashboard="configModalMode === 'create' ? null : activeDashboard"
			:mode="configModalMode"
			:canDelete="dashboards.length > 1"
			:defaultUuid="defaultDashboardUuid"
			:initialTab="configModalInitialTab"
			:parentOptions="dashboards"
			:saveError="configSaveError"
			:canManagePublication="canManagePublication"
			:canScheduleExports="canEdit"
			:canViewVersions="canManagePublication"
			:exportSubjects="exportSubjects"
			@close="closeConfigModal"
			@save="saveDashboardConfig"
			@delete="deleteCurrentDashboard"
			@setDefault="onModalSetDefault"
			@versionRestored="onVersionRestored" />

		<!-- Style editor modal. `extra-icon-options` is gone: CnIconBrowser now
		     ships the NL Design sets itself (RVO lazily), so passing the pack here
		     only forced all ~2.3 MB of it into the eager bundle. -->
		<CnWidgetStyleEditorModal
			v-if="editingPlacement"
			:show="isStyleEditorOpen"
			:widget="styleEditorWidget"
			:deletable="!editingPlacement.isCompulsory"
			@close="closeStyleEditor"
			@save="onStyleSaved"
			@delete="deleteWidget" />

		<!-- Tile editor modal -->
		<BookmarkImportModal
			v-if="mountedModals.bookmarkImport"
			:open="isBookmarkImportOpen"
			:importBookmarks="importChosenBookmarks"
			@close="isBookmarkImportOpen = false" />

		<TileEditor
			v-if="mountedModals.tileEditor"
			:open="isTileEditorOpen"
			:tile="editingTile"
			@close="closeTileEditor"
			@save="saveTile"
			@delete="deleteTile" />

		<!-- Widget right-click context menu (REQ-WDG-015..017). The
		     popover renders only in edit mode, anchored at the cursor.
		     Clicking Edit reuses AddWidgetModal with the placement set
		     as `editingWidget`; Remove calls the placement-delete path
		     of REQ-WDG-005; Cancel is a no-op close. -->
		<WidgetContextMenu
			v-if="contextMenuVisible"
			:top="contextMenuTop"
			:left="contextMenuLeft"
			:removeLocked="!contextMenuCanRemove"
			@edit="grid.triggerEdit()"
			@move="grid.triggerMove()"
			@remove="grid.triggerRemove()"
			@visibilityRules="grid.triggerVisibilityRules()"
			@readConfirmation="openReadConfirmation()"
			@close="grid.closeContextMenu()" />

		<!-- engagement-acknowledgement-toggle REQ-ACK-007: ask readers to
		     confirm they have read a widget. -->
		<ReadConfirmationDialog
			v-if="readConfirmationPlacement"
			:open="true"
			:placement="readConfirmationPlacement"
			@update:open="
				(v) => {
					if (!v) readConfirmationPlacement = null
				}
			"
			@save="saveReadConfirmation" />

		<!-- Keyboard-operable move/resize panel (WCAG 2.1 SC 2.1.1). The
		     pointer-only GridStack drag has no keyboard equivalent, so the
		     context menu's "Move" item opens this panel, which nudges the
		     placement through the same pure `nudgePlacement()` helper the
		     drag path's collision model uses. -->
		<WidgetMovePanel
			v-if="mountedModals.movePanel"
			:open="movePanelOpen"
			:placement="movePanelPlacement"
			:allPlacements="widgetPlacements"
			:gridColumns="activeDashboard?.gridColumns || 12"
			@save="handleMoveSave"
			@close="closeMovePanel" />

		<!-- "Who sees this widget?" (conditional-visibility-editor
		     REQ-CVUI-006/007). Opened from the widget's menu and its
		     right-click menu in edit mode. -->
		<VisibilityRulesModal
			v-if="mountedModals.visibility"
			:open="isVisibilityModalOpen"
			:placementId="visibilityPlacementId"
			:widgetTitle="visibilityWidgetTitle"
			:dashboardName="activeDashboard?.name || ''"
			:ruleMatch="visibilityRuleMatch"
			@close="closeVisibilityRules"
			@ruleAdded="onVisibilityRulesChanged"
			@ruleUpdated="onVisibilityRulesChanged"
			@ruleRemoved="onVisibilityRulesChanged"
			@ruleMatchChanged="onRuleMatchChanged" />
	</div>
</template>

<script>
import {
	CnAddWidgetModal,
	CnDashboardGrid,
	CnWidgetStyleEditorModal,
	NcButton,
	NcEmptyContent,
	NcLoadingIcon,
} from '@conduction/nextcloud-vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import { computed, defineAsyncComponent, nextTick, provide, reactive, ref, shallowRef } from 'vue'
// Icons
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import TileWidget from '../components/TileWidget.vue'
import WidgetContextMenu from '../components/Widgets/WidgetContextMenu.vue'
// Components
import WidgetWrapper from '../components/WidgetWrapper.vue'
import ActiveFiltersPanel from '../components/Workspace/ActiveFiltersPanel.vue'
import CoEditorsBanner from '../components/Workspace/CoEditorsBanner.vue'
import DashboardBreadcrumb from '../components/Workspace/DashboardBreadcrumb.vue'
import DashboardDiscussion from '../components/Workspace/DashboardDiscussion.vue'
import DashboardEditBar from '../components/Workspace/DashboardEditBar.vue'
import DashboardLanguageHeading from '../components/Workspace/DashboardLanguageHeading.vue'
import DashboardMoreMenu from '../components/Workspace/DashboardMoreMenu.vue'
import DashboardPageHeader from '../components/Workspace/DashboardPageHeader.vue'
import DashboardReactions from '../components/Workspace/DashboardReactions.vue'
import { embedAdminHref, isOwnedDashboard } from '../components/Workspace/DashboardRowActions.vue'
import DashboardSwitcherSidebar from '../components/Workspace/DashboardSwitcherSidebar.vue'
import EditLockBanner from '../components/Workspace/EditLockBanner.vue'
import FilterBreadcrumb from '../components/Workspace/FilterBreadcrumb.vue'
import HiddenWidgetsControl from '../components/Workspace/HiddenWidgetsControl.vue'
import InstallBanner from '../components/Workspace/InstallBanner.vue'
import PhoneHiddenNotice from '../components/Workspace/PhoneHiddenNotice.vue'
import SidebarBackdrop from '../components/Workspace/SidebarBackdrop.vue'
// The dialogs and modals load the first time they open, not with the page.
const DeleteDashboardDialog = defineAsyncComponent(() => import('../dialogs/DeleteDashboardDialog.vue'))
const ReadConfirmationDialog = defineAsyncComponent(() => import('../dialogs/ReadConfirmationDialog.vue'))
const AcknowledgementReportModal = defineAsyncComponent(() => import('../modals/AcknowledgementReportModal.vue'))
const BookmarkImportModal = defineAsyncComponent(() => import('../modals/BookmarkImportModal.vue'))
const DashboardConfigModal = defineAsyncComponent(() => import('../modals/DashboardConfigModal.vue'))
const TileEditor = defineAsyncComponent(() => import('../modals/TileEditor.vue'))
const VisibilityRulesModal = defineAsyncComponent(() => import('../modals/VisibilityRulesModal.vue'))
const WidgetMovePanel = defineAsyncComponent(() => import('../modals/WidgetMovePanel.vue'))
const WidgetPickerModal = defineAsyncComponent(() => import('../modals/WidgetPickerModal.vue'))
import { createFilterTelemetry } from '../composables/filterTelemetry.js'
// Composables
import { useDashboardFilterSession } from '../composables/useDashboardFilterSession.js'
import { useDashboardPresence } from '../composables/useDashboardPresence.js'
import { useGridManager } from '../composables/useGridManager.js'
import { getWidgetTypeEntry, listWidgetTypes, offeredForViewer } from '../constants/widgetRegistry.js'
import { api } from '../services/api.js'
import { uploadDataUrl, uploadFile } from '../services/resourceService.js'
import {
	hidesWhenUnavailable,
	isSourceAvailable,
	sourceKey,
} from '../services/sourceAvailability.js'
// Stores
import { useCalculatedFieldsStore } from '../stores/calculatedFields.js'
import { useDashboardStore } from '../stores/dashboard.js'
import { usePersonalLayerStore } from '../stores/personalLayer.js'
import { useTileStore } from '../stores/tiles.js'
import { useTileSearchStore } from '../stores/tileSearch.js'
import { useWidgetStore } from '../stores/widgets.js'
import { appBase, bootHash, bootPath, dashboardUuidFromHash } from '../utils/bootPath.js'
import { applyCustomCss, DASHBOARD_STYLE_ID } from '../utils/customCss.js'
import { lookStyle } from '../utils/dashboardLook.js'
import { addressWithPeriod, namesPeriod, readPeriod } from '../utils/dashboardPeriod.js'
import { appTileFromDiscoveredApp, discoveredAppIdsOnDashboard, tileFromCatalogueApp } from '../utils/discoveredTile.js'
import { gridItemLabel } from '../utils/gridItemLabel.js'
import { resolveHeaderActionsTarget } from '../utils/headerActionsDock.js'
import { isKioskMode, KIOSK_REFRESH_MS, startKioskView } from '../utils/kioskMode.js'
import { loadInitialState } from '../utils/loadInitialState.js'
import { logger } from '../utils/logger.js'
import { dashboardGridColumnOpts } from '../utils/narrowGridLayout.js'
import { newWidgetSize } from '../utils/newWidgetSize.js'
import { officePackContainer } from '../utils/officeTiles.js'
import { hiddenWidgetTitle, isPhoneReady, isPhoneWidth, phoneHiddenPlacements } from '../utils/phoneHiddenWidgets.js'
import { dialogReturnFocus, restoreLostFocus } from '../utils/returnFocus.js'
import { parseServerStamp } from '../utils/serverStamp.js'
import { isLaunchType, launchContent, launchFields } from '../utils/tileLaunch.js'
import { busNotices } from '../utils/widgetBusNotices.js'
import { resolveWidgetTitle } from '../utils/widgetTitle.js'

/** The workspace grid's row height in pixels; matches `:cellHeight` above. */
const GRID_ROW_HEIGHT = 60

/**
 * A pathname without the `/index.php` front controller or a trailing slash,
 * so an address and the canonical deep link compare as the same route
 * whether or not the instance rewrites URLs.
 *
 * @param {string} path The pathname.
 * @return {string} The bare pathname.
 */
function barePath(path) {
	return path.replace(/^\/index\.php(?=\/)/, '').replace(/\/+$/, '')
}

/**
 * Whether a placement is a tile: a launcher tile (`tile`, `tile-{id}`, or an
 * inline tile with a `tileType`) or an app tile.
 *
 * @param {object} placement The placement.
 * @return {boolean}
 * @spec openspec/specs/widgets/spec.md
 */
export function isTilePlacement(placement) {
	const id = String(placement?.widgetId ?? '')
	return id === 'tile' || id === 'app-tile' || id.startsWith('tile-') || Boolean(placement?.tileType)
}

/**
 * A placement with the minimum size its widget type registers, so the grid
 * lets a divider be one row and a tile one column while other widgets keep
 * the grid's minimum.
 *
 * @param {object} placement The placement.
 * @return {object} The placement, or a copy carrying `minHeight` / `minWidth`.
 * @spec openspec/specs/divider-widget/spec.md#requirement-req-div-006-default-widget-sizing
 */
export function withTypeMinHeight(placement) {
	const entry = getWidgetTypeEntry(placement?.widgetId)
	const extra = {}
	if (Number.isFinite(entry?.minHeight) && placement.minHeight === undefined) {
		extra.minHeight = entry.minHeight
	}
	const minWidth = isTilePlacement(placement) ? 1 : entry?.minWidth
	if (Number.isFinite(minWidth) && placement.minWidth === undefined) {
		extra.minWidth = minWidth
	}
	return Object.keys(extra).length === 0 ? placement : { ...placement, ...extra }
}


/**
 * The update body of a dashboard settings save: the fields the modal sent.
 *
 * @param {object} config The modal's save.
 * @param {string} config.name Display name.
 * @param {string} config.description Short description.
 * @param {string} config.icon Icon key or URL.
 * @param {string} [config.parentUuid] Parent uuid, '' for none.
 * @param {string} [config.slug] Web address name.
 * @param {object} [config.look] The changed look fields (REQ-THM-001).
 * @param {string} [config.defaultLinkTarget] How tiles open, '' for the link-type rule.
 * @param {Array<object>} [config.hierarchies] The hierarchy descriptors, only when changed.
 * @return {object} The body for `api.updateDashboard`.
 * @spec openspec/specs/dashboards/spec.md
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */
function dashboardUpdateData({ name, description, icon, parentUuid, slug, look, defaultLinkTarget, hierarchies }) {
	const data = { name, description, icon }
	// dashboard-tree-navigation: '' clears the parent; an
	// empty web address name leaves the stored one alone.
	if (parentUuid !== undefined) {
		data.parentUuid = parentUuid
	}
	if (slug) {
		data.slug = slug
	}
	// REQ-THM-001: only a changed look is sent, so a rename
	// stays a metadata edit.
	if (look) {
		Object.assign(data, look)
	}
	// launcher-tile-open-behaviour D2: '' clears the default.
	if (defaultLinkTarget !== undefined) {
		data.defaultLinkTarget = defaultLinkTarget
	}
	if (hierarchies !== undefined) {
		data.hierarchies = hierarchies
	}
	return data
}


/**
 * The placements this viewer may see, before the phone rule: visible ones,
 * and outside edit mode not those whose source is missing or whose type is
 * not offered to this viewer.
 *
 * @param {object} vm The Views instance.
 * @return {Array<object>} The placements.
 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */
function viewerPlacementsOf(vm) {
	const all = (vm.widgetPlacements || []).filter(
		(p) => p.isVisible !== 0 && p.isVisible !== false,
	)
	if (vm.isEditMode) {
		return all
	}
	const unavailable = vm.unavailableSources || {}
	return all.filter(
		(p) =>
			(!hidesWhenUnavailable(p)
			|| unavailable[sourceKey(p.content)] !== true)
			&& offeredForViewer(getWidgetTypeEntry(p.widgetId), vm.integrationApps),
	)
}

export default {
	// Multi-word per vue/multi-word-component-names. This is the `name` option
	// (devtools / findComponent), NOT the registration key — parents still
	// register and stub it as `Views`.
	name: 'DashboardViews',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		ViewDashboard,
		CnDashboardGrid,
		WidgetWrapper,
		AcknowledgementReportModal,
		TileWidget,
		WidgetPickerModal,
		CnWidgetStyleEditorModal,
		BookmarkImportModal,
		TileEditor,
		DashboardConfigModal,
		CnAddWidgetModal,
		WidgetContextMenu,
		WidgetMovePanel,
		VisibilityRulesModal,
		DashboardSwitcherSidebar,
		DashboardEditBar,
		DashboardMoreMenu,
		SidebarBackdrop,
		DashboardDiscussion,
		DashboardReactions,
		ReadConfirmationDialog,
		DashboardLanguageHeading,
		DashboardPageHeader,
		DashboardBreadcrumb,
		ActiveFiltersPanel,
		FilterBreadcrumb,
		DeleteDashboardDialog,
		CoEditorsBanner,
		EditLockBanner,
		HiddenWidgetsControl,
		InstallBanner,
		PhoneHiddenNotice,
	},

	// REQ-INIT-004 / REQ-ASET-003 / REQ-TMPL-012: pull typed initial-state
	// values down the tree. Defaults keep the UX safe when keys are missing.
	inject: {
		allowUserDashboards: {
			from: 'allowUserDashboards',
			default: false,
		},

		// Which apps behind the integration widgets are enabled for the
		// viewer (wiki-widget REQ-WIKI-004); the picker leaves out the rest.
		integrationApps: {
			from: 'integrationApps',
			default: () => ({}),
		},

		primaryGroupName: {
			from: 'primaryGroupName',
			default: '',
		},

		// Server-computed admin flag (PageController initial state); only
		// decides whether "Take over" is offered. The server checks again.
		isAdmin: {
			from: 'isAdmin',
			default: false,
		},

		// Canonical slug-chain path the server resolved for the active
		// dashboard. Empty string when no dashboard is active OR the
		// active one has no slug. Read once on mount to bring
		// `window.location.pathname` in line with what the server
		// rendered (handles renamed parents / stale bookmarks).
		injectedDeepLinkPath: {
			from: 'deepLinkPath',
			default: '',
		},

		// UUID of the dashboard the server resolved for this request; for a
		// slug address, the dashboard that address names (REQ-DDL-002).
		injectedActiveDashboardId: {
			from: 'activeDashboardId',
			default: '',
		},
	},

	// Inject the typed initial-state snapshot pushed from `src/main.js`
	// (REQ-INIT-003..005). Defaults match the reader contract so the
	// sidebar still mounts when running under tests that don't set a
	// provider (e.g. Vitest harness) — see DashboardSwitcherSidebar specs.
	// (Inject keys are defined once below; this comment block was kept
	// as the historical anchor for the provide/inject contract.)
	/** @spec openspec/specs/dashboards/spec.md */
	setup() {
		// Reactive `canEdit` proxy handed to the grid manager composable.
		// Wrapped in Vue.observable so the composable's
		// `onWidgetRightClick` early-return tracks the live value without
		// re-creating the composable on every edit-mode toggle. When the
		// runtime-shell capability ships, this will be replaced by the
		// typed provide/inject contract and removed from local state.
		const canEditRef = reactive({ value: false })

		// `selectedWidget` from the popover may live in either of two
		// edit paths. The host-side callbacks resolve which one to use
		// (custom-type widgets → AddWidgetModal; nextcloud-widget tiles →
		// CnWidgetStyleEditorModal) and the placement-delete path is the same
		// `removeWidgetFromDashboard` action used by the existing remove
		// flow. The host wires these via methods after instantiation so
		// `this` is bound to the component when the callbacks fire.
		const grid = useGridManager({
			canEdit: canEditRef,
			/**
			 * Context-menu "Edit" — open the content editor for a placement.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			onEdit(widget) {
				// `this` is the Vue instance once we bind in `created()`.
				grid._host?.handleContextMenuEdit(widget)
			},
			/**
			 * Context-menu "Remove" — delete a placement from the dashboard.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			onRemove(widget) {
				grid._host?.handleContextMenuRemove(widget)
			},
			/**
			 * Context-menu "Move" — open the keyboard move/resize panel.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/grid-layout/spec.md
			 */
			onMove(widget) {
				grid._host?.handleContextMenuMove(widget)
			},
			/**
			 * Context-menu "Visibility rules…" — open the conditional
			 * visibility editor for a placement.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/conditional-visibility/spec.md
			 */
			onVisibilityRules(widget) {
				grid._host?.handleContextMenuVisibilityRules(widget)
			},
		})

		// nc-vue's CnNcDashboardWidgetForm (the `nc-widget` sub-form mounted by
		// CnAddWidgetModal) injects a `widgets` catalog to populate its
		// CnNcWidgetGridPicker — the same Nextcloud-native dashboard-widget
		// list already fetched into the widget store (`loadAvailableWidgets`,
		// whose own comment notes it feeds `CnNcWidgetWidget`'s runtime
		// renderer via `widgetBridge.setWidgetMetadata`). Nothing previously
		// provided it down this component's tree, so the picker always saw
		// nc-vue's inject default (`[]`) and rendered permanently empty.
		// `computed` keeps it reactive to the store across the fetch.
		provide(
			'widgets',
			computed(() => useWidgetStore().availableWidgets),
		)

		// dashboards-personal-hide-ui: this person's own layer.
		const personalLayer = usePersonalLayerStore()

		// drill-down-cross-widget-filter: the open dashboard's filter session.
		// Its bus is provided to the widgets below, which publish clicks into
		// it and read the clauses they consume (REQ-CWF-001, REQ-CWF-002).
		const filterSession = shallowRef(null)
		provide('dashboardFilterBus', computed(() => filterSession.value?.bus ?? null))

		// dashboard-period-and-download REQ-DPD-002: the period the dated
		// widgets follow, from the address; read-only, never stored.
		const dashboardPeriod = ref(readPeriod(typeof window === 'undefined' ? '' : window.location.search))
		provide('dashboardPeriod', dashboardPeriod)
		// The widget forms offer "Date field for the period" on their register
		// source here, because this dashboard has a period (nextcloud-vue
		// period-field-on-register-forms; REQ-DPD-002).
		provide('cnOffersPeriodField', true)

		return { canEditRef, grid, personalLayer, filterSession, dashboardPeriod }
	},

	data() {
		return {
			/** REQ-KIOSK-001: opened with `?kiosk=1`. */
			kiosk: isKioskMode(),
			stopKiosk: null,
			kioskRefresh: null,
			isEditMode: false,
			/** The viewport width, for the phone rule (REQ-MRA-007). */
			viewportWidth: typeof window === 'undefined' ? 0 : window.innerWidth,
			/** The dialogs and modals opened at least once, which stay mounted from then on. */
			mountedModals: {},
			/**
			 * The greeting row's actions element the top-right controls are
			 * teleported into, or null while they float (headerActionsDock.js).
			 */
			headerActionsTarget: null,
			// The edit bar's end, where the controls dock in edit mode.
			editBarDock: null,
			/**
			 * Sources (`register/schema`) OpenRegister said are not here,
			 * keyed by source (REQ-TMPL-022).
			 */
			unavailableSources: {},
			isWidgetModalOpen: false,
			discoveredApps: [],
			catalogueApps: [],
			isConfigModalOpen: false,
			configModalMode: 'edit',
			// Tab the config drawer lands on when opened (dashboard-sharing
			// spec). The top-bar share action sets this to 'sharing'.
			configModalInitialTab: 'general',
			isStyleEditorOpen: false,
			editingPlacement: null,
			// Deep clone of editingPlacement handed to CnWidgetStyleEditorModal,
			// which mutates its `widget` prop in place on Save. Editing the clone
			// keeps the live store placement untouched until the patch persists.
			styleEditorWidget: null,
			isTileEditorOpen: false,
			isBookmarkImportOpen: false,
			editingTile: null,
			// Custom widget add/edit modal state. `customWidgetEditing`
			// non-null = edit mode; `customWidgetPreselectedType` non-null =
			// type-specific deep-link from the toolbar.
			isCustomWidgetModalOpen: false,
			customWidgetPreselectedType: null,
			// The type picked in the widget picker: the form opens on it and
			// keeps its type select.
			customWidgetInitialType: null,
			customWidgetEditing: null,
			// The element focused when a widget dialog opened (REQ-WDG-013).
			widgetDialogOpener: null,
			// Per-widget conditional-visibility editor state. Non-null
			// `visibilityPlacementId` + `isVisibilityModalOpen` opens the
			// modal for that placement (conditional-visibility spec).
			isVisibilityModalOpen: false,
			visibilityPlacementId: null,
			// Keyboard move/resize panel state (WCAG 2.1 SC 2.1.1). Opened
			// from the context menu's "Move" item for the placement in
			// `movePanelPlacement`.
			movePanelOpen: false,
			movePanelPlacement: null,
			// `dashboard-switcher` capability state — controlled here, the
			// sidebar emits update:open(boolean) via its v-model rebind.
			sidebarOpen: false,
			// REQ-ANLT-011 — per-uuid debounce ledger. Maps dashboard
			// UUID → last-send millisecond timestamp so two near-
			// simultaneous mounts of the same dashboard (multi-tab,
			// fast-tab-switch) collapse into a single view-event POST.
			// Different uuids are tracked independently per spec
			// scenario "Different dashboards are not debounced".
			viewEventLastSent: Object.create(null),
			// Whether the boot (stored active dashboard, then the deep-linked
			// one) is over; until then a dashboard change replaces the address
			// instead of pushing a history entry.
			historyBooted: false,

			// Wave3.7 — UUID of the user's pinned default dashboard, or
			// '' when no pin is set. Fetched once on mount via
			// `GET /api/dashboards/default` and refreshed locally
			// whenever the user picks a new default via the cog menu.
			defaultDashboardUuid: '',
			// dashboard-acknowledgements REQ-ACK-004: admin read-receipt
			// report modal state.
			ackReportOpen: false,
			ackReportKey: '',
			// engagement-acknowledgement-toggle: the placement whose read
			// confirmation is being set up.
			readConfirmationPlacement: null,
			// dashboard-language-and-details-tabs: detail field definitions
			// for "Filter by detail", and the uuids the filter keeps (null
			// when no filter is set).
			detailFields: [],
			detailFilterUuids: null,
			// dashboard-tree-navigation: the delete confirmation and, once
			// the server has answered 409, how many dashboards go with it.
			deleteTarget: null,
			deleteChildCount: 0,
			// Why the dashboard configuration could not be saved.
			configSaveError: '',
			// building-edit-a-dashboard-together: who else edits the active
			// dashboard; edit mode no longer takes a lock.
			presence: useDashboardPresence({
				onRefused: () => this.onPresenceRefused(),
			}),
		}
	},

	computed: {
		/**
		 * Whether each lazily mounted dialog or modal is open now.
		 *
		 * @spec exclude load-time split of the dialogs, no behaviour of its own
		 * @return {object} Open flags keyed like `mountedModals`.
		 */
		openModals() {
			return {
				deleteDialog: this.deleteTarget !== null,
				ackReport: this.ackReportOpen,
				widgetPicker: this.isWidgetModalOpen,
				config: this.isConfigModalOpen,
				bookmarkImport: this.isBookmarkImportOpen,
				tileEditor: this.isTileEditorOpen,
				movePanel: this.movePanelOpen,
				visibility: this.isVisibilityModalOpen,
			}
		},

		/**
		 * The active dashboard's own look as scoped CSS variables, or no style
		 * for a dashboard that follows the theme (REQ-THM-001).
		 *
		 * @return {object} the inline style for the dashboard region.
		 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
		 */
		dashboardLookStyle() {
			return lookStyle(this.activeDashboard)
		},

		/**
		 * The open dashboard's filter clauses, for the active-filters panel.
		 *
		 * @return {object[]}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-007-filter-stack-ui-shows-every-active-clause-with-its-source
		 */
		/**
		 * What the widget dialog says about the edited widget's filter
		 * declarations on this dashboard (REQ-CWF-008).
		 *
		 * @return {Array<{id: string, type: string, text: string}>}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
		 */
		customWidgetNotices() {
			const content = this.customWidgetEditing?.content ?? null
			const bus = this.filterSession ? this.filterSession.bus : null
			return busNotices(content, bus ? bus.hierarchies : [])
		},

		filterClauses() {
			return this.filterSession ? this.filterSession.clauses.value : []
		},

		/**
		 * What the Exports tab of the settings can export: the active
		 * dashboard and its widgets.
		 *
		 * @return {object[]} `{type, id, label}` per subject.
		 * @spec openspec/specs/scheduled-exports/spec.md#requirement-req-sch-011-scheduled-exports-live-in-the-dashboard-settings
		 */
		exportSubjects() {
			const dashboard = this.activeDashboard
			const dashboards = dashboard?.uuid
				? [{ type: 'dashboard', id: dashboard.uuid, label: dashboard.name || dashboard.uuid }]
				: []
			const widgets = (this.widgetPlacements || []).map((placement) => ({
				type: 'widget',
				id: String(placement.id),
				label: resolveWidgetTitle(placement, this.availableWidgets),
			}))
			return [...dashboards, ...widgets]
		},

		/**
		 * The signed-in user's id, for the dashboard discussion.
		 *
		 * @return {string}
		 * @spec openspec/changes/openregister-leaf-integrations/specs/dashboard-talk-leaf/spec.md#requirement-req-leaf-002-discussion-surface-renders-only-on-shared-dashboards
		 */
		currentUserId() {
			return typeof window.OC?.currentUser === 'string' ? window.OC.currentUser : ''
		},

		/**
		 * Each placement's title by placement id, so a chip names the widget
		 * a clause was clicked in.
		 *
		 * @return {{[key: string]: string}}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-007-filter-stack-ui-shows-every-active-clause-with-its-source
		 */
		filterWidgetNames() {
			const names = {}
			for (const placement of this.widgetPlacements || []) {
				names[placement.id] = resolveWidgetTitle(placement, this.availableWidgets)
			}
			return names
		},

		/**
		 * The placements the grid shows. A placement with `isVisible` 0 never
		 * renders (REQ-WDG-009; the server sets it for a widget the viewer's
		 * role does not permit, REQ-RFP-001). Of the rest: every placement in
		 * edit mode, and outside it every placement but an object-list that
		 * asked to be hidden when its register is not on this instance
		 * (REQ-TMPL-022), and every widget that reads through an app that is
		 * not enabled for the viewer (REQ-CASES-004). In edit mode they stay,
		 * so whoever edits sees they are there.
		 *
		 * @return {object[]} the placements to lay out.
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-is-offered-only-with-dossiq-req-cases-004
		 */
		shownPlacements() {
			const viewer = viewerPlacementsOf(this)
			if (this.isEditMode || !isPhoneWidth(this.viewportWidth)) {
				return viewer
			}
			return viewer.filter((p) => isPhoneReady(getWidgetTypeEntry(p.widgetId)))
		},

		/**
		 * The titles of the widgets a phone hides, for the notice above the
		 * grid. Empty in edit mode and on a larger screen (board LpMobiel).
		 *
		 * @return {Array<string>}
		 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
		 */
		phoneHiddenTitles() {
			if (this.isEditMode || !isPhoneWidth(this.viewportWidth)) {
				return []
			}
			return phoneHiddenPlacements(viewerPlacementsOf(this), getWidgetTypeEntry)
				.map((p) => hiddenWidgetTitle(p, getWidgetTypeEntry(p.widgetId)))
		},

		/**
		 * Whether a shown widget names a period field, so the header offers
		 * the period choice (REQ-DPD-002).
		 *
		 * @return {boolean}
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		showsPeriod() {
			return namesPeriod(this.shownPlacements)
		},

		/**
		 * Whether the dashboard opens with a greeting on the page ground (a
		 * header widget with `ground`), which then carries the page title.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/archive/2026-11-24-start-page-board-sync/specs/lpvandaag-start-page/spec.md#requirement-the-start-page-greets-the-reader
		 */
		hasGroundHeader() {
			return this.shownPlacements.some(
				(p) => p?.widgetId === 'header' && p?.content?.ground === true,
			)
		},

		/**
		 * Whether the dashboard fills the height below the top bar instead
		 * of scrolling (LpVandaag): a placement asks for it with
		 * `content.fillHeight: true`. The grid then divides its height over
		 * the rows, and long widgets scroll inside their card.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-the-start-page-fits-the-screen
		 */
		fillsHeight() {
			return this.shownPlacements.some((p) => p?.content?.fillHeight === true)
		},

		/**
		 * The layout the grid draws: the shown placements, each carrying the
		 * minimum height its widget type registers (a divider may be one row),
		 * and outside edit mode a placement with `content.fitRows: true`
		 * marked so its rows take its content's height on a filled grid.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/lpvandaag-start-page/specs/lpvandaag-start-page/spec.md#requirement-the-start-page-fits-the-screen
		 * @spec openspec/specs/divider-widget/spec.md#requirement-req-div-006-default-widget-sizing
		 */
		gridLayout() {
			const placements = this.shownPlacements.map(withTypeMinHeight)
			if (this.isEditMode || !this.fillsHeight) {
				return placements
			}
			return placements.map((p) =>
				p?.content?.fitRows === true ? { ...p, fitRows: true } : p,
			)
		},

		/**
		 * tile-quick-search REQ-QSEARCH-002 — whether a given placement is
		 * currently de-emphasised by an active quick-search query.
		 *
		 * Returns `false` for every placement when no query is running, which
		 * is why a dashboard with no `search` widget never dims anything.
		 *
		 * A `search` placement NEVER dims itself. It is a placement like any
		 * other, so without this it de-emphasises the very bar the user is
		 * typing into the moment their query stops matching the word
		 * "Search" — the control fades out from under the cursor.
		 *
		 * Keyed on the widget type rather than on a single remembered id so a
		 * dashboard carrying more than one search widget behaves the same.
		 *
		 * @return {(placement: object) => boolean} the predicate.
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		isTileDimmed() {
			const { isDimmed } = useTileSearchStore()
			return (placement) => {
				if (placement?.widgetId === 'search') {
					return false
				}
				return isDimmed(placement?.id)
			}
		},

		/**
		 * dashboard-acknowledgements REQ-ACK-004: distinct announcement keys of
		 * the placements on the active dashboard that require acknowledgement.
		 * Drives the admin "Read receipts" affordance (shown only when at least
		 * one placement carries a requirement and the user can edit).
		 *
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 * @return {string[]} the announcement keys.
		 */
		/**
		 * The title of the announcement whose read confirmations are open
		 * (REQ-ACK-010), '' when none is.
		 *
		 * @return {string} The widget title.
		 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
		 */
		ackReportTitle() {
			const placement = (this.widgetPlacements || []).find((p) => p.announcementKey === this.ackReportKey)
			return placement ? resolveWidgetTitle(placement, this.availableWidgets) : ''
		},

		/**
		 * The placement whose "Who sees this widget?" dialog is open.
		 *
		 * @return {object|null} The placement, null when none is open.
		 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		visibilityPlacement() {
			return (this.widgetPlacements || []).find((p) => p.id === this.visibilityPlacementId) || null
		},

		/**
		 * The title of the widget whose visibility dialog is open.
		 *
		 * @return {string} The widget title, '' when none is open.
		 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		visibilityWidgetTitle() {
			return this.visibilityPlacement ? resolveWidgetTitle(this.visibilityPlacement, this.availableWidgets) : ''
		},

		/**
		 * How the open widget's include rules combine.
		 *
		 * @return {string} `all` or `any`.
		 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
		 */
		visibilityRuleMatch() {
			return this.visibilityPlacement?.ruleMatch === 'all' ? 'all' : 'any'
		},

		acknowledgementAnnouncementKeys() {
			const keys = (this.widgetPlacements || [])
				.filter(
					(p) =>
						Number(p.requiresAcknowledgement) === 1 && p.announcementKey,
				)
				.map((p) => p.announcementKey)
			return [...new Set(keys)]
		},

		/**
		 * Responsive breakpoint options for CnDashboardGrid (REQ-GRID-007):
		 * the shared nc-vue helper's reflow, with a reading-order flow on a
		 * phone so app tiles sit four to a row (board LpMobiel).
		 *
		 * @return {object} the GridStack `columnOpts` bag.
		 * @spec openspec/specs/grid-layout/spec.md#req-grid-007
		 */
		dashboardColumnOpts() {
			return dashboardGridColumnOpts()
		},

		...mapState(useDashboardStore, [
			'dashboards',
			'activeDashboard',
			'widgetPlacements',
			'permissionLevel',
			'loading',
			'userDashboards',
			'groupSharedDashboards',
			'defaultGroupDashboards',
			'sharedWithMeDashboards',
			// dashboard-quota-limits REQ-QUOTA-006: drive the disabled
			// create affordance + tooltip from the store getters.
			'dashboardQuotaReached',
			'dashboardQuotaTooltip',
			// dashboard-acknowledgements REQ-ACK-002: outstanding count +
			// per-placement outstanding predicate for the forced-delivery gate.
			'outstandingAcknowledgementCount',
			'isPlacementOutstanding',
		]),

		...mapState(useWidgetStore, ['availableWidgets']),
		/*
		 * `tiles` is deliberately NOT mapped: `getTileData()` builds a tile
		 * from the placement row itself, so nothing here reads the store list.
		 */

		/** @spec openspec/specs/dashboards/spec.md */
		canEdit() {
			return this.permissionLevel !== 'view_only'
		},

		/**
		 * Whether "Hide for me" and "Hidden (n)" are offered: a dashboard
		 * this person does not own (REQ-PERSUI-001).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		canHideForMe() {
			return !!this.activeDashboard && this.activeDashboard.isOwner !== true
		},

		/**
		 * Whether the top-bar share action should be shown (dashboard-sharing
		 * spec). Requires an active dashboard the user owns (only owners can
		 * manage shares — mirrors `DashboardConfigModal.canManageShares`).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboard-sharing/spec.md
		 */
		canShareActiveDashboard() {
			const dash = this.activeDashboard
			return !!dash && dash.isOwner !== false && (dash.id ?? null) !== null
		},

		/**
		 * Publish, unpublish and schedule are for the owner or an
		 * administrator (REQ-SCHEDUI-001); the server checks again.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		canManagePublication() {
			const dash = this.activeDashboard
			return !!dash?.uuid && (dash.isOwner === true || this.isAdmin === true)
		},

		/**
		 * Where the top-right controls render: in the edit bar while editing,
		 * in the greeting row when there is one, else floating (null).
		 *
		 * @return {HTMLElement|null}
		 * @spec openspec/specs/dashboards/spec.md#requirement-req-dash-038-the-edit-bar
		 */
		controlsDock() {
			return this.isEditMode ? this.editBarDock : this.headerActionsTarget
		},

		/**
		 * Whether the dashboard settings open for this person: the owner, or
		 * whoever may manage the publication (the gate of the former
		 * "Settings" menu item).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboards/spec.md#requirement-req-dash-038-the-edit-bar
		 */
		canConfigureActiveDashboard() {
			return isOwnedDashboard(this.activeDashboard, this.activeDashboardSource)
				|| this.canManagePublication
		},

		/**
		 * Whether More offers "Duplicate as new dashboard": the gates of the
		 * switcher's create, personal dashboards allowed and the quota not
		 * reached (REQ-ASET-003, REQ-QUOTA-006).
		 *
		 * @return {boolean}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		canDuplicateActiveDashboard() {
			return Boolean(this.activeDashboard?.uuid)
				&& (this.allowUserDashboards === true || this.isAdmin === true)
				&& !this.dashboardQuotaReached
		},

		/**
		 * "Goes live on …" and/or "Comes down on …" for the active dashboard.
		 *
		 * @return {string} The note, or empty.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		publicationNote() {
			const dash = this.activeDashboard
			const parts = []
			if (dash?.publicationStatus === 'scheduled' && dash.publishAt) {
				parts.push(
					t('launchpad', 'Goes live on {date}.', {
						date: this.formatStamp(dash.publishAt),
					}),
				)
			}
			if (dash?.unpublishAt) {
				parts.push(
					t('launchpad', 'Comes down on {date}.', {
						date: this.formatStamp(dash.unpublishAt),
					}),
				)
			}
			return parts.join(' ')
		},

		/**
		 * Whether the right-click context menu (REQ-WDG-015) should open
		 * for the current dashboard. Requires both the user permission
		 * gate and the workspace shell's edit-mode toggle. View mode
		 * intentionally falls through to the browser's native menu.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		canEditForContextMenu() {
			return this.canEdit && this.isEditMode
		},

		/*
		 * Reactive bridges to `grid.state.*` (from the `useGridManager`
		 * composable in `setup()`). Vue 2.7's template compiler does NOT
		 * track property reads on plain objects returned from `setup()` —
		 * binding `v-if="grid.state.contextMenuOpen"` directly captures
		 * the *initial* truthy/falsy value but never re-renders when the
		 * composable mutates the state. Wrapping each access in a computed
		 * forces Vue's dependency tracker to subscribe to the
		 * `reactive()` getter, so subsequent state changes (open via
		 * right-click, close via outside-click / Cancel) correctly mount
		 * and unmount the popover.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		contextMenuVisible() {
			return this.grid.state.contextMenuOpen
		},

		/** @spec openspec/specs/dashboards/spec.md */
		contextMenuTop() {
			return this.grid.state.contextMenuPosition.y
		},

		/** @spec openspec/specs/dashboards/spec.md */
		contextMenuLeft() {
			return this.grid.state.contextMenuPosition.x
		},

		/**
		 * Whether the context menu offers Remove for the widget it opened on.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/permissions/spec.md
		 */
		contextMenuCanRemove() {
			return this.isRemovablePlacement(this.grid.state.selectedWidget)
		},

		/**
		 * The widget types Launchpad builds itself, as the picker lists them.
		 *
		 * @return {Array<{type: string, title: string}>}
		 * @spec openspec/specs/widgets/spec.md#requirement-widget-picker-req-wdg-010
		 */
		customWidgetTypes() {
			return listWidgetTypes()
				.filter((type) => offeredForViewer(getWidgetTypeEntry(type), this.integrationApps))
				.map((type) => ({ type, title: getWidgetTypeEntry(type)?.displayName || type }))
				.sort((left, right) => left.title.localeCompare(right.title))
		},

		/** @spec openspec/specs/dashboards/spec.md */
		placedWidgetIds() {
			return this.widgetPlacements.map((p) => p.widgetId)
		},

		/** @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002 */
		placedDiscoveredAppIds() {
			return discoveredAppIdsOnDashboard(this.widgetPlacements, this.discoveredApps)
		},

		/**
		 * Combined input for the sidebar's `groupDashboards` prop —
		 * primary-group + default-group + shared-with-me rows, each carrying
		 * their `source` discriminator from `/api/dashboards/visible`
		 * (REQ-DASH-013, REQ-SHARE-002). The sidebar re-splits them by
		 * `source` into its own sections; anything omitted here is dropped
		 * from the switcher entirely, which is how `shared` rows went
		 * missing once the store refreshed over the server-rendered
		 * initial state.
		 *
		 * @return {Array<object>} Concatenated group + default + shared dashboards.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		sidebarGroupDashboards() {
			return this.applyDetailFilter([
				...this.groupSharedDashboards,
				...this.defaultGroupDashboards,
				...this.sharedWithMeDashboards,
			])
		},

		/**
		 * Section discriminator for the currently active dashboard, so the
		 * start page's "Meer" menu (DashboardMoreMenu) gates its
		 * owner-only entries (Configure / Delete) exactly like the matching
		 * sidebar row. Resolved by locating the active id in the same buckets
		 * the sidebar renders; falls back to the dashboard's own `isOwner`
		 * flag when the record is not in any bucket yet.
		 *
		 * @return {'group'|'default'|'user'|'shared'} The active dashboard's source.
		 * @spec openspec/specs/dashboard-switcher/spec.md
		 */
		activeDashboardSource() {
			const id = this.activeDashboard?.id
			if (id !== null && id !== undefined) {
				if (this.userDashboards?.some((d) => d.id === id)) {
					return 'user'
				}
				if (this.defaultGroupDashboards?.some((d) => d.id === id)) {
					return 'default'
				}
				if (this.groupSharedDashboards?.some((d) => d.id === id)) {
					return 'group'
				}
				// REQ-SHARE-002: a dashboard reached through a share is not
				// the caller's own, so the cog must gate its owner-only
				// entries exactly as it does for a group row.
				if (this.sharedWithMeDashboards?.some((d) => d.id === id)) {
					return 'shared'
				}
			}
			return this.activeDashboard?.isOwner === false ? 'group' : 'user'
		},

		/**
		 * Personal dashboards for the sidebar's `userDashboards` prop.
		 * Aliased so the sidebar's prop name reads naturally in the
		 * template even if the store getter is renamed later.
		 *
		 * @return {Array<object>} Dashboards with `source === 'user'`.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		sidebarUserDashboards() {
			return this.applyDetailFilter(this.userDashboards)
		},

		/**
		 * Empty-state copy. When personal dashboards are disabled by the
		 * admin we swap the friendly "create one" prompt for a localised
		 * explainer (REQ-ASET-003). The translatable English source is
		 * kept short so the layout doesn't wrap awkwardly.
		 *
		 * @return {string}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		emptyStateDescription() {
			if (this.allowUserDashboards) {
				return this.t(
					'launchpad',
					'Create your first dashboard to get started',
				)
			}
			return this.t(
				'launchpad',
				'Personal dashboards are not enabled by your administrator',
			)
		},
	},

	watch: {
		openModals: {
			immediate: true,
			/**
			 * Mount a dialog or modal when it first opens.
			 *
			 * @spec exclude load-time split of the dialogs, no behaviour of its own
			 * @param {object} open The open flags.
			 */
			handler(open) {
				for (const [key, isOpen] of Object.entries(open)) {
					if (isOpen) {
						this.mountedModals[key] = true
					}
				}
			},
		},

		/**
		 * The calculated fields re-evaluate when the dashboard's filters change (REQ-CALC-003).
		 *
		 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-19
		 */
		filterClauses: {
			immediate: true,
			/**
			 * @param {object[]} clauses The active filter clauses.
			 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-19
			 */
			handler(clauses) {
				useCalculatedFieldsStore().setFilters(clauses || [])
			},
		},

		/**
		 * The administrator CSS of the dashboard on screen, already scoped to
		 * its region by the server, inserted as text (REQ-THM-003, D4).
		 *
		 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
		 */
		'activeDashboard.customCss': {
			immediate: true,
			/**
			 * @param {string|null} css The scoped CSS of the dashboard on screen.
			 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
			 */
			handler(css) {
				applyCustomCss(DASHBOARD_STYLE_ID, css)
			},
		},

		/**
		 * Editing makes the greeting row a draggable grid item: the controls
		 * float while editing and dock again after.
		 *
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 */
		isEditMode() {
			this.$nextTick(() => this.dockHeaderActions())
		},

		/**
		 * Ask, once per source, whether the registers the hideable lists read
		 * are on this instance (REQ-TMPL-022), and hand the placements to the
		 * calculated fields (widget-calculated-fields REQ-CALC-003).
		 *
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
		 */
		widgetPlacements: {
			immediate: true,
			/**
			 * @param {object[]} placements The open dashboard's placements.
			 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
			 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-19
			 */
			handler(placements) {
				this.probeHideableSources()
				useCalculatedFieldsStore().setWidgets(placements || [])
			},
		},

		/**
		 * Read this person's layer whenever another shared dashboard
		 * becomes active, so "Hidden (n)" is right from the start.
		 *
		 * @param {number|string|null} id Active dashboard id.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		'activeDashboard.id': {
			immediate: true,
			/**
			 * @param {number|string|null} id Active dashboard id.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			handler(id) {
				if (id !== undefined && id !== null && this.canHideForMe) {
					this.personalLayer.load(id).catch(() => {})
				}
			},
		},

		/**
		 * Mirror the combined edit-mode / permission gate into the
		 * Vue.observable proxy the grid manager composable owns. The
		 * proxy is the only thing the composable reads, so this watcher
		 * is what keeps the right-click guard live.
		 *
		 * @param {boolean} value the new combined edit/permission value
		 */
		canEditForContextMenu: {
			immediate: true,
			/**
			 * Mirror the computed edit permission into the ref the grid
			 * manager closes over.
			 *
			 * @param {boolean} value Whether the user may currently edit.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			handler(value) {
				if (this.canEditRef) {
					this.canEditRef.value = !!value
				}
			},
		},

		/**
		 * REQ-ANLT-011 — fire a view-event whenever the active
		 * dashboard switches (and on initial render once the active
		 * dashboard hydrates). Per-uuid debouncing in
		 * `recordViewEventDebounced` collapses near-simultaneous
		 * mounts of the same dashboard into one network call.
		 *
		 * @param {object|null} dashboard the freshly-active dashboard
		 */
		'activeDashboard.uuid': {
			immediate: true,
			/**
			 * Fire a view-event when the active dashboard changes.
			 *
			 * @param {string|null} uuid UUID of the newly-active dashboard.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			handler(uuid) {
				if (!uuid) {
					return
				}
				// Filters belong to a dashboard: a switch starts a fresh
				// session. Every session of the boot reads a bookmarked
				// `?f=`: the boot can pass through more than one uuid (the
				// stored active dashboard, then the one the address names).
				this.openFilterSession(!this.historyBooted)
				this.recordViewEventDebounced(uuid)
				// Outbound URL sync — every uuid change after the boot pushes
				// a new history entry so back/forward navigates between
				// dashboards. The boot's changes (the stored active
				// dashboard, then the one the address names) are covered by
				// `replaceState` in mounted, so they add no entry.
				if (this.historyBooted) {
					this.pushUrlForActiveDashboard()
				}
			},
		},
	},

	/** @spec openspec/specs/dashboards/spec.md */
	async created() {
		// Bind the host onto the grid composable so its onEdit / onRemove
		// callbacks can delegate to component methods. The composable was
		// instantiated in `setup()` which has no access to `this`.
		this.grid._host = this

		// `?edit=1` opens the start page in edit mode: the "Startpagina
		// aanpassen" button of Mijn werkdag (LpStart) is a link in the
		// greeting, and a link can only carry its wish in the address.
		// Read now, because mounted() rewrites the address.
		const editFromAddress =
			new URLSearchParams(window.location.search).get('edit') === '1'

		const dashboardStore = useDashboardStore()
		const widgetStore = useWidgetStore()
		const tileStore = useTileStore()

		await Promise.all([
			dashboardStore.loadDashboards(),
			widgetStore.loadAvailableWidgets(),
			tileStore.loadTiles(),
		])
		await this.openDeepLinkedDashboard(bootPath())
		await this.openHashLinkedDashboard(bootHash())
		await this.replaceUrlAfterBoot()
		// After the watcher has seen the deep-linked switch.
		await nextTick()
		this.historyBooted = true

		// dashboard-acknowledgements REQ-ACK-002: load the user's outstanding
		// mandatory-read items so the forced-delivery gate and the outstanding
		// count reflect reality on first render. Non-fatal on failure.
		dashboardStore.fetchPendingAcknowledgements()

		// Wave3.7 — fetch the user's pinned default-dashboard UUID
		// once on mount so the per-row cog can render the right "Set
		// as default" / "Default dashboard" state. Failure here is
		// non-fatal: the cog falls back to "Set as default" for every
		// row when the pref can't be read.
		try {
			const res = await api.getDefaultDashboardPreference()
			this.defaultDashboardUuid = res?.data?.uuid ?? ''
		} catch (error) {
			logger.error(
				'[Views] Failed to load default-dashboard preference:',
				error,
			)
		}

		if (editFromAddress && this.canEdit) {
			await this.enterEditMode()
		}
	},

	/** @spec openspec/specs/dashboards/spec.md */
	mounted() {
		if (this.kiosk) {
			this.stopKiosk = startKioskView(window)
			// REQ-KIOSK-004: a kiosk view re-reads its widgets in place.
			this.kioskRefresh = setInterval(
				() => useDashboardStore().reloadPlacements(),
				KIOSK_REFRESH_MS,
			)
		}
		// dashboard-language-and-details-tabs: fields for "Filter by detail".
		this.loadDetailFields()
		// Attach the document-level click listener (REQ-WDG-016 outside-
		// click closes popover). Detached in beforeDestroy so we never
		// leak a listener across mounts.
		this.grid.attach()

		// Deep-link URL sync — replace the URL in-place so the address
		// bar reflects whichever dashboard the server actually rendered
		// (handles renamed parents and stale bookmarked paths). Uses
		// replaceState rather than pushState so the bootstrap entry
		// doesn't pollute the back-button history.
		this.replaceUrlFromInitialState()

		// Browser back / forward → re-resolve the URL and switch.
		window.addEventListener('popstate', this.handleHistoryPopState)
		// A search result opened while LaunchPad is already showing.
		window.addEventListener('hashchange', this.onDashboardHashChange)
		window.addEventListener('resize', this.onViewportResize)

		// The greeting row mounts with the grid, after this view; watch the
		// container so the controls dock once it is there, and move again
		// when another dashboard (with or without a greeting) replaces it.
		this.dockHeaderActions()
		const container = document.querySelector('.launchpad-container')
		if (container && typeof MutationObserver !== 'undefined') {
			this.headerActionsObserver = new MutationObserver(() => this.dockHeaderActions())
			this.headerActionsObserver.observe(container, { childList: true, subtree: true })
		}
	},

	/** @spec openspec/specs/dashboards/spec.md */
	beforeUnmount() {
		this.stopKiosk?.()
		clearInterval(this.kioskRefresh)
		applyCustomCss(DASHBOARD_STYLE_ID, '')
		this.filterSession?.stop()
		this.grid.detach()
		// Drop the host pointer to avoid retaining the Vue instance.
		this.grid._host = null

		window.removeEventListener('popstate', this.handleHistoryPopState)
		window.removeEventListener('hashchange', this.onDashboardHashChange)
		window.removeEventListener('resize', this.onViewportResize)
		this.headerActionsObserver?.disconnect()
		this.headerActionsObserver = null
		// Leaving the page while editing withdraws the presence.
		this.presence.stop()
	},

	methods: {
		/**
		 * Follow the viewport width for the phone rule.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
		 */
		onViewportResize() {
			this.viewportWidth = window.innerWidth
		},

		dialogReturnFocus,

		/**
		 * The Beheer ▸ Embeds link that creates an embed token for one widget
		 * of the active dashboard; '' for a non-administrator, or without a
		 * saved dashboard and placement.
		 *
		 * @param {object} placement The widget placement.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
		 */
		placementEmbedHref(placement) {
			if (this.isAdmin !== true || !this.activeDashboard?.id || !placement?.id) {
				return ''
			}
			return embedAdminHref(this.activeDashboard, placement)
		},

		/**
		 * Dock the top-right controls into the greeting row when the page
		 * has one and is not being edited; float them otherwise.
		 *
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 */
		dockHeaderActions() {
			const container = document.querySelector('.launchpad-container')
			const target = resolveHeaderActionsTarget(container, this.isEditMode)
			if (target !== this.headerActionsTarget) {
				this.headerActionsTarget = target
			}
		},

		/**
		 * For every object-list that asked to be hidden when its source is
		 * not here, ask OpenRegister once and remember a "not here"
		 * (REQ-TMPL-022). A source that is there, or that could not be
		 * asked, is left alone: the list renders.
		 *
		 * @return {Promise<void>} resolves when every source was asked.
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-022
		 */
		async probeHideableSources() {
			const keys = new Set()
			for (const placement of this.widgetPlacements || []) {
				if (hidesWhenUnavailable(placement)) {
					keys.add(sourceKey(placement.content))
				}
			}
			await Promise.all(
				[...keys].map(async (key) => {
					const [register, schema] = key.split('/')
					const available = await isSourceAvailable(register, schema)
					if (available === false) {
						this.unavailableSources = {
							...this.unavailableSources,
							[key]: true,
						}
					}
				}),
			)
		},

		t,

		/**
		 * Per-item render key for CnDashboardGrid.
		 *
		 * The key is the placement id and NOTHING else. It previously also
		 * folded in `updatedAt` and a JSON dump of `styleConfig` to force a
		 * re-render after an edit — but that made the key change on every
		 * persist, so Vue tore down and recreated the grid item's DOM node.
		 * Any element focused inside it lost focus, which breaks keyboard
		 * move/resize outright (WCAG 2.1 SC 2.1.1): after one arrow-key move
		 * the user's focus was thrown back to the document and a second move
		 * was impossible without re-navigating the whole grid.
		 *
		 * Re-rendering on content change does not need a key change — the
		 * placement objects are replaced wholesale by the store, so the
		 * bound props update and the child re-renders normally.
		 *
		 * NOTE: the "Placement key regeneration" scenario at
		 * openspec/specs/grid-layout/spec.md:405 — filed, oddly, under
		 * REQ-GRID-010 (Grid Styling) — still requires the superseded
		 * `updatedAt` + `styleConfig` key via a `getPlacementKey()` that no
		 * longer exists. That spelling is precisely what broke keyboard
		 * move/resize, so the scenario is stale and this method deliberately
		 * does not satisfy it. REQ-GRID-008 below is the requirement this
		 * implementation serves. Tracked for spec correction in launchpad#101.
		 *
		 * @spec openspec/specs/grid-layout/spec.md#req-grid-008
		 * @param {object} placement the placement (CnDashboardGrid layout item).
		 * @return {string} the render key — stable for the placement's lifetime.
		 */
		placementItemKey(placement) {
			return String(placement.id)
		},

		/**
		 * Accessible name of a grid item: "Widget: {title}", plus the position
		 * and size in edit mode.
		 *
		 * @spec openspec/specs/grid-layout/spec.md#req-grid-008
		 * @param {object} placement the placement (CnDashboardGrid layout item).
		 * @return {string} the accessible name.
		 */
		placementItemLabel(placement) {
			return gridItemLabel(placement, {
				editing: this.isEditMode,
				columns: this.activeDashboard?.gridColumns || 12,
				availableWidgets: this.availableWidgets,
			})
		},

		/**
		 * Resolve the available-widget definition for a placement's widgetId.
		 *
		 * @param {string} widgetId the placement's widget id.
		 * @return {object|undefined} the widget definition, if registered.
		 */
		getWidget(widgetId) {
			return this.availableWidgets.find((w) => w.id === widgetId)
		},

		/**
		 * Whether a placement renders as a launcher tile (custom tile type).
		 *
		 * @param {object} placement the placement.
		 * @return {boolean} true for tile placements.
		 */
		isTilePlacement(placement) {
			return placement.tileType === 'custom'
		},

		/**
		 * Project a tile placement's flat `tile*` columns into the tile shape
		 * TileWidget expects.
		 *
		 * @spec openspec/specs/grid-layout/spec.md#req-grid-009
		 * @param {object} placement the tile placement.
		 * @return {object|null} the tile data, or null when not a tile.
		 */
		getTileData(placement) {
			if (!this.isTilePlacement(placement)) return null
			return {
				id: placement.id,
				title: placement.tileTitle,
				icon: placement.tileIcon,
				iconType: placement.tileIconType,
				backgroundColor: placement.tileBackgroundColor,
				textColor: placement.tileTextColor,
				linkType: placement.tileLinkType,
				linkValue: placement.tileLinkValue,
				// launcher-tile-internal-address REQ-TIA-002: the office-network address, in `content`.
				internalUrl: (placement.content && placement.content.internalUrl) || '',
			}
		},

		// dashboard-acknowledgements REQ-ACK-002: after a recipient signs off
		// on a widget, refresh the outstanding set so the dashboard-level
		// count stays accurate. The store already dropped the acknowledged
		// item optimistically; the re-fetch reconciles with the server.
		/** @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md */
		onWidgetAcknowledged() {
			const dashboardStore = useDashboardStore()
			dashboardStore.fetchPendingAcknowledgements()
		},

		/**
		 * Open the admin read-receipt report for an announcement
		 * (REQ-ACK-004).
		 *
		 * @param {string} announcementKey Key identifying the announcement
		 *   whose acknowledgement report should be shown.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		openAcknowledgementReport(announcementKey) {
			this.ackReportKey = announcementKey
			this.ackReportOpen = true
		},

		...mapActions(useDashboardStore, [
			'switchDashboard',
			'createDashboard',
			'forkDashboard',
			'loadDashboards',
			'updatePlacements',
			'setGridColumns',
			'addWidgetToDashboard',
			'addTileToDashboard',
			'importBookmarks',
			'removeWidgetFromDashboard',
			'updateWidgetPlacement',
			'recordViewEvent',
		]),

		/**
		 * REQ-ANLT-011 — per-uuid 1-second debounce wrapping the
		 * fire-and-forget store action. The ledger lives on
		 * `data.viewEventLastSent` (Object.create(null)) so different
		 * dashboards are tracked independently per spec scenario
		 * "Different dashboards are not debounced against each other".
		 * Across the tabs of one browser, the tab that sends holds a Web
		 * Lock named after the dashboard for a second; another tab that
		 * finds it held sends nothing ("Debouncing prevents multi-tab
		 * double-counting"). A reload drops the lock with the page, so it
		 * counts again. Without the Locks API every tab sends.
		 *
		 * @param {string} uuid The dashboard UUID to record.
		 * @spec openspec/specs/dashboard-view-analytics/spec.md
		 */
		recordViewEventDebounced(uuid) {
			if (!uuid) {
				return
			}
			const now = Date.now()
			const last = this.viewEventLastSent[uuid] || 0
			if (now - last < 1000) {
				return
			}
			this.viewEventLastSent[uuid] = now
			const locks = globalThis.navigator?.locks
			if (typeof locks?.request !== 'function') {
				this.recordViewEvent(uuid)
				return
			}
			let sent = false
			locks.request(`launchpad-view-event:${uuid}`, { ifAvailable: true }, (lock) => {
				if (!lock) {
					return undefined
				}
				sent = true
				this.recordViewEvent(uuid)
				return new Promise((resolve) => setTimeout(resolve, 1000))
			}).catch((e) => {
				logger.debug('[Views] view-event lock unavailable:', e)
				if (!sent) {
					this.recordViewEvent(uuid)
				}
			})
		},

		/*
		 * The tile-store actions are deliberately NOT mapped. `createTile` and
		 * `updateTile` had no caller, and the mapped `deleteTile` was SHADOWED
		 * by the local `deleteTile()` defined later in this same object — a
		 * later key wins, so the store action could never run. The local one
		 * (which delegates to `removeWidget`) is what `@delete` invokes.
		 */

		/**
		 * Keep only the dashboards the detail filter kept.
		 *
		 * @param {Array<object>} list Dashboards of one section.
		 * @return {Array<object>} The filtered list, or the list itself.
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		applyDetailFilter(list) {
			if (this.detailFilterUuids === null) {
				return list
			}
			return list.filter((d) => this.detailFilterUuids.includes(d.uuid))
		},

		/**
		 * Read the detail fields once, for the switcher's filter.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		async loadDetailFields() {
			try {
				const { data } = await api.getMetadataFieldDefinitions()
				this.detailFields = Array.isArray(data?.fields) ? data.fields : []
			} catch {
				this.detailFields = []
			}
		},

		/**
		 * Filter the switcher: ask the visible list with the filter and
		 * keep the uuids it returns; null clears the filter.
		 *
		 * @param {object|null} filter `{<key>: <value>}` or null.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		async onDetailFilter(filter) {
			if (!filter) {
				this.detailFilterUuids = null
				return
			}
			try {
				const { data } = await api.getVisibleDashboards(filter)
				const rows = Array.isArray(data) ? data : (data?.items ?? [])
				this.detailFilterUuids = rows.map((d) => d.uuid)
			} catch (error) {
				logger.error('Failed to filter dashboards:', error)
				this.detailFilterUuids = null
			}
		},

		/**
		 * "Hide for me" on a widget: save the layer, then read the dashboard
		 * again so the server applies it (REQ-PERSUI-001).
		 *
		 * @param {object} placement The widget's placement.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onHideForMe(placement) {
			const id = this.activeDashboard?.id
			if (id === undefined || id === null) {
				return
			}
			try {
				await this.personalLayer.hide(id, placement.id)
			} catch (error) {
				showError(
					error?.compulsory
						? t(
								'launchpad',
								'{name} is compulsory on this dashboard and cannot be hidden.',
								{
									name: resolveWidgetTitle(
										placement,
										this.availableWidgets,
									),
								},
							)
						: t('launchpad', 'The widget could not be hidden.'),
				)
				return
			}
			await this.switchDashboard(id)
		},

		/**
		 * "Show again" in the hidden list (REQ-PERSUI-002).
		 *
		 * @param {number} placementId The placement to bring back.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onShowAgain(placementId) {
			const id = this.activeDashboard?.id
			try {
				await this.personalLayer.showAgain(id, placementId)
			} catch {
				showError(t('launchpad', 'The widget could not be shown again.'))
				return
			}
			await this.switchDashboard(id)
		},

		/**
		 * "Reset my view", confirmed (REQ-PERSUI-002).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onResetPersonalView() {
			const id = this.activeDashboard?.id
			try {
				await this.personalLayer.reset(id)
			} catch {
				showError(t('launchpad', 'Your view could not be reset.'))
				return
			}
			await this.switchDashboard(id)
		},

		/**
		 * Show a stored timestamp in the viewer's locale.
		 *
		 * @param {string} value Timestamp from the server.
		 * @return {string} Formatted date and time.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		formatStamp(value) {
			const parsed = parseServerStamp(value)
			return parsed === null
				? String(value)
				: parsed.toLocaleString(undefined, {
						dateStyle: 'medium',
						timeStyle: 'short',
					})
		},

		/**
		 * A version was restored: read the dashboard again from the server
		 * rather than patching local state from the snapshot.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		async onVersionRestored() {
			const id = this.activeDashboard?.id
			if (id !== undefined && id !== null) {
				await this.switchDashboard(id)
			}
		},

		/**
		 * The widget menu's "Read confirmation…": open the dialog for the
		 * widget the menu was opened on.
		 *
		 * @spec openspec/specs/dashboard-acknowledgements/spec.md
		 */
		openReadConfirmation() {
			// The menu emits `close` right after this event, so the selected
			// widget is read first.
			const widget = this.grid.state.selectedWidget
			if (widget) {
				this.readConfirmationPlacement = widget
			}
		},

		/**
		 * Save the read confirmation on the placement; the prompt then
		 * shows for readers who have not confirmed (REQ-ACK-002).
		 *
		 * @param {object} payload `{requiresAcknowledgement, acknowledgementPrompt, acknowledgementDeadline}`.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-acknowledgements/spec.md
		 */
		async saveReadConfirmation(payload) {
			const placement = this.readConfirmationPlacement
			this.readConfirmationPlacement = null
			if (!placement) {
				return
			}
			await this.updateWidgetPlacement(placement.id, payload)
			this.onWidgetAcknowledged()
		},

		/**
		 * Enter or leave edit mode. Entering announces this person as an
		 * editor of the dashboard; leaving withdraws it
		 * (building-edit-a-dashboard-together, task 1.4).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-19-building-edit-a-dashboard-together/tasks.md#task-1.4
		 */
		async toggleEditMode() {
			if (!this.isEditMode) {
				await this.enterEditMode()
				return
			}
			this.leaveEditMode()
			await this.presence.stop()
		},

		/**
		 * Enter edit mode, also while colleagues edit the same dashboard:
		 * every change is saved per widget, so nobody's change is lost. Only a
		 * refusal (no edit rights, or an unknown dashboard) keeps the page
		 * read-only, and the banner says why.
		 *
		 * @return {Promise<boolean>} Whether the page is in edit mode now.
		 * @spec openspec/changes/archive/2026-11-19-building-edit-a-dashboard-together/tasks.md#task-1.4
		 */
		async enterEditMode() {
			if (this.kiosk) {
				return false
			}
			if (this.isEditMode) {
				return true
			}
			const uuid = this.activeDashboard?.uuid ?? this.activeDashboard?.id
			if (!uuid) {
				return false
			}
			const allowed = await this.presence.start(String(uuid))
			if (allowed) {
				this.isEditMode = true
			}
			return allowed
		},

		/**
		 * Drop edit mode and every edit-only surface. The presence itself is
		 * withdrawn by the caller.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		leaveEditMode() {
			this.isEditMode = false
			this.closeWidgetModal()
			this.closeStyleEditor()
			// Leaving edit mode also dismisses any open right-click
			// popover so view mode never carries an edit-only surface.
			this.grid.closeContextMenu()
		},

		/**
		 * A renewal was refused (edit rights gone, or the dashboard deleted):
		 * back to view mode; the banner says why.
		 *
		 * @spec openspec/changes/archive/2026-11-19-building-edit-a-dashboard-together/tasks.md#task-1.4
		 */
		onPresenceRefused() {
			if (this.isEditMode) {
				this.leaveEditMode()
			}
		},

		/**
		 * Before another dashboard becomes active: leave edit mode, withdraw
		 * the presence and clear a banner that was about the previous one.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-19-building-edit-a-dashboard-together/tasks.md#task-1.4
		 */
		async dropEditLock() {
			if (this.isEditMode) {
				this.leaveEditMode()
			}
			await this.presence.stop()
		},

		/**
		 * DashboardGrid forwards every right-click on a placement here
		 * (REQ-WDG-015). The composable owns the early-return + viewport
		 * clamp + state mutation; we just forward the event so view mode
		 * never calls `preventDefault()`.
		 *
		 * @param {MouseEvent} event the contextmenu event
		 * @param {object} placement the placement under the cursor
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onWidgetRightClick(event, placement) {
			if (this.kiosk) {
				return
			}
			this.grid.onWidgetRightClick(event, placement)
		},

		/**
		 * Edit click from the popover (REQ-WDG-015 edit scenario). Custom-
		 * type placements (label, text, image, link-button, …) reuse the
		 * unified AddWidgetModal with `editingWidget` set (REQ-WDG-010);
		 * all other placements fall through to the legacy style editor so
		 * the popover is useful for stock Nextcloud widgets too.
		 *
		 * @param {object} placement the placement to edit
		 * @spec openspec/specs/dashboards/spec.md
		 */
		handleContextMenuEdit(placement) {
			// Placements only carry `widgetId` — never a `type` field. For
			// registry-driven custom widgets (label, text, image, link, …)
			// the `widgetId` IS the registry type key, so resolve the type
			// from the registry and open the content editor (AddWidgetModal)
			// with `type` set so `loadEditingWidget` can pre-fill the form.
			// `tile` now resolves to the shared registry entry (CnDashTileWidget
			// + CnDashTileWidgetForm), so preset/registry tiles open the content
			// editor below; the form reads their flat tile* columns. Legacy
			// `custom` tiles are handled earlier via TileEditor (isTilePlacement).
			// Stock Nextcloud-widget placements still have no registry entry and
			// fall through to the legacy style editor.
			const resolvedType = this.resolveWidgetType(placement)
			if (resolvedType) {
				this.openCustomWidgetEdit({ ...placement, type: resolvedType })
				return
			}
			this.openStyleEditor(placement)
		},

		/**
		 * Resolve the registry type key for a placement from its `widgetId`.
		 * Returns the type string for registry-driven custom widgets (the
		 * `widgetId` is the type key) or `null` when the placement is not a
		 * custom widget (stock NC widget, tile, …) so the caller can fall
		 * back to the style editor.
		 *
		 * @param {object} placement the placement under the cursor
		 * @return {string|null} the resolved widget type, or null
		 * @spec openspec/specs/dashboards/spec.md
		 */
		resolveWidgetType(placement) {
			const widgetId = placement?.widgetId
			if (typeof widgetId !== 'string' || widgetId === '') {
				return null
			}
			const entry = getWidgetTypeEntry(widgetId)
			// Only types with a configurable form can be content-edited; the
			// renderer-only `nc-widget` proxy stays on the style-editor path.
			if (!entry || !entry.form) {
				return null
			}
			return widgetId
		},

		/**
		 * Remove click from the popover (REQ-WDG-015 remove scenario).
		 * Routes through the same store action as the existing remove
		 * flow so the placement-delete path of REQ-WDG-005 (DELETE
		 * `/api/placements/{id}`) remains the single source of truth.
		 *
		 * @param {object} placement the placement to delete
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async handleContextMenuRemove(placement) {
			if (!placement?.id) {
				return
			}
			try {
				await this.removeWidget(placement.id)
			} catch (error) {
				logger.error(
					'[Views] Failed to remove widget via context menu:',
					error,
				)
			}
		},

		/**
		 * "Who sees this widget?" from the widget's menu or its right-click
		 * menu (conditional-visibility spec). Stores the target placement id
		 * and opens the editor modal. Both menus show only in edit mode,
		 * which needs `canEdit`, so no extra gate is needed here.
		 *
		 * @param {object} placement the placement whose rules to edit
		 * @spec openspec/specs/conditional-visibility/spec.md
		 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-dialog-is-in-the-widgets-menu-req-cvui-007
		 */
		handleContextMenuVisibilityRules(placement) {
			if (!placement?.id) {
				return
			}
			this.visibilityPlacementId = placement.id
			this.isVisibilityModalOpen = true
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		closeVisibilityRules() {
			this.isVisibilityModalOpen = false
			this.visibilityPlacementId = null
		},

		/**
		 * Context-menu "Move" — open the keyboard move/resize panel for a
		 * placement. This is the WCAG 2.1 SC 2.1.1 keyboard equivalent of
		 * GridStack's pointer-only drag; reaching the menu already required
		 * `canEdit`, so no extra gate is needed here.
		 *
		 * @param {object} placement the placement to move or resize
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		handleContextMenuMove(placement) {
			if (!placement?.id) {
				return
			}
			this.movePanelPlacement = placement
			this.movePanelOpen = true
		},

		/** @spec openspec/specs/grid-layout/spec.md */
		closeMovePanel() {
			this.movePanelOpen = false
			this.movePanelPlacement = null
		},

		/**
		 * Persist a confirmed keyboard move/resize. The panel hands back the
		 * clamped rectangle plus any placements `nudgePlacement()` had to
		 * push down to avoid overlap; both are folded into one
		 * `updatePlacements` call so the whole layout is written atomically
		 * on the same debounced path the drag handler uses.
		 *
		 * @param {{gridX: number, gridY: number, gridWidth: number, gridHeight: number, pushed: Array<{id: (number|string), gridY: number}>}} rect
		 *   Confirmed geometry emitted by `WidgetMovePanel`.
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		async handleMoveSave(rect) {
			const movedId = this.movePanelPlacement?.id
			if (!movedId || !rect) {
				this.closeMovePanel()
				return
			}
			const pushedById = new Map(
				(rect.pushed || []).map((p) => [p.id, p.gridY]),
			)
			const next = (this.widgetPlacements || []).map((placement) => {
				if (placement.id === movedId) {
					return {
						...placement,
						gridX: rect.gridX,
						gridY: rect.gridY,
						gridWidth: rect.gridWidth,
						gridHeight: rect.gridHeight,
					}
				}
				if (pushedById.has(placement.id)) {
					return { ...placement, gridY: pushedById.get(placement.id) }
				}
				return placement
			})
			this.closeMovePanel()
			try {
				await this.updatePlacements(next)
			} catch (error) {
				logger.error('[Views] Failed to persist keyboard move:', error)
			}
		},

		/**
		 * The dialog saved how the rules combine: keep the placement in step
		 * and ask again which widgets the rules hide for viewers.
		 *
		 * @param {string} mode `all` or `any`.
		 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
		 */
		onRuleMatchChanged(mode) {
			const store = useDashboardStore()
			store.widgetPlacements = (store.widgetPlacements || []).map((p) =>
				p.id === this.visibilityPlacementId ? { ...p, ruleMatch: mode } : p,
			)
			store.refreshPlacementVisibility()
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		onVisibilityRulesChanged() {
			// Rules are applied server-side when the dashboard renders, so
			// ask again which widgets they hide for viewers.
			useDashboardStore().refreshPlacementVisibility()
		},

		/** @spec openspec/specs/dashboards/spec.md */
		async openWidgetModal() {
			if (!(await this.enterEditMode())) {
				return
			}
			this.widgetDialogOpener = document.activeElement
			this.isWidgetModalOpen = true
			this.loadDiscoveredApps()
			this.loadCatalogueApps()
		},

		/**
		 * Read the apps this server runs for the user, for the picker's
		 * "Discovered on this server" section. A failure leaves the section
		 * out; the rest of the picker works as before.
		 *
		 * @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md#requirement-the-add-flow-lists-the-apps-this-server-runs-for-the-user-req-disc-001
		 */
		async loadDiscoveredApps() {
			try {
				const response = await api.discoverApps()
				this.discoveredApps = Array.isArray(response?.data?.apps) ? response.data.apps : []
			} catch (error) {
				this.discoveredApps = []
				logger.warn('[Views] Could not discover the server apps:', error)
			}
		},

		/**
		 * Place a discovered app as a tile through the normal tile path.
		 *
		 * @param {{appId: string, name: string, href: string, icon: string}} app The discovered app.
		 * @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002
		 */
		/**
		 * Place the office tile pack as one container titled "Office" at the
		 * bottom of the dashboard (REQ-OFT-001). A pack with no tiles places
		 * nothing and says so.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
		 */
		async addOfficePack() {
			try {
				const response = await api.officeTilePack()
				const tiles = Array.isArray(response?.data?.tiles) ? response.data.tiles : []
				if (tiles.length === 0) {
					showError(t('launchpad', 'This server has no office apps to make tiles for.'))
					return
				}
				const pack = officePackContainer(tiles, t('launchpad', 'Office'))
				const created = await this.addWidgetToDashboard({ type: 'container', content: pack.content }, pack.size)
				if (created?.id) {
					this.closeWidgetModal()
				}
			} catch (error) {
				logger.error('[Views] Failed to place the office tiles:', error)
				showError(t('launchpad', 'The office tiles could not be added.'))
			}
		},

		/**
		 * Place a discovered app as its own app tile.
		 *
		 * @param {{appId: string}} app The discovered app.
		 * @return {Promise<void>}
		 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-the-add-flow-offers-every-app-as-its-own-tile-req-apptile-001
		 */
		async addDiscoveredApp(app) {
			try {
				await this.addWidgetToDashboard(appTileFromDiscoveredApp(app))
			} catch (error) {
				logger.error('[Views] Failed to place a discovered app:', error)
			}
		},

		/**
		 * Read the app catalogue entries the user's groups may see, for the
		 * picker's "From the app catalogue" section. A failure leaves the
		 * section out; the rest of the picker works as before.
		 *
		 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-add-catalogue-apps-as-tiles-from-the-add-flow-req-acat-002
		 */
		async loadCatalogueApps() {
			try {
				const response = await api.catalogueApps()
				this.catalogueApps = Array.isArray(response?.data?.apps) ? response.data.apps : []
			} catch (error) {
				this.catalogueApps = []
				logger.warn('[Views] Could not load the app catalogue:', error)
			}
		},

		/**
		 * Place a catalogue entry as an ordinary tile through the normal tile path.
		 *
		 * @param {object} app The catalogue entry.
		 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-add-catalogue-apps-as-tiles-from-the-add-flow-req-acat-002
		 */
		async addCatalogueApp(app) {
			try {
				await this.addTileToDashboard(tileFromCatalogueApp(app))
			} catch (error) {
				logger.error('[Views] Failed to place a catalogue app:', error)
			}
		},

		/**
		 * Send a request for a missing app; the picker shows the outcome.
		 *
		 * @param {{appName: string, purpose: string}} request The request.
		 * @return {Promise} The API call.
		 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
		 */
		requestCatalogueApp(request) {
			return api.requestCatalogueApp(request)
		},

		/** @spec openspec/specs/dashboards/spec.md */
		closeWidgetModal() {
			this.isWidgetModalOpen = false
		},

		/**
		 * The picker was dismissed (Escape, its close button or the backdrop):
		 * close it and make sure focus does not fall to the page.
		 *
		 * @return {void}
		 * @spec openspec/specs/widgets/spec.md#requirement-modal-close-discipline-req-wdg-013
		 */
		onWidgetPickerClose() {
			this.closeWidgetModal()
			nextTick(() => restoreLostFocus(this.widgetDialogOpener))
		},

		/**
		 * A Launchpad widget type was picked: close the picker and open the
		 * custom widget form on that type, its type select still changeable.
		 *
		 * @param {string} type The registry key.
		 * @return {Promise<void>}
		 * @spec openspec/specs/widgets/spec.md#requirement-widget-picker-req-wdg-010
		 */
		async onPickCustomWidget(type) {
			this.closeWidgetModal()
			await this.openCustomWidgetModal(null, type)
		},

		/**
		 * Open the registry-driven custom widget modal in create mode.
		 * Pass a `type` to deep-link to a specific sub-form (REQ-WDG-010
		 * preselected-type scenario); omit it for the type-picker flow.
		 *
		 * @param {string|null} type registry key, or null for picker flow
		 * @param {string|null} initialType registry key the form opens on while
		 *   its type select stays visible
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async openCustomWidgetModal(type = null, initialType = null) {
			if (!(await this.enterEditMode())) {
				return
			}
			this.customWidgetPreselectedType = type
			this.customWidgetInitialType = initialType
			this.customWidgetEditing = null
			this.widgetDialogOpener = document.activeElement
			this.isCustomWidgetModalOpen = true
		},

		/**
		 * Open the modal in edit mode for an existing custom-type
		 * placement. The placement's type is immutable in edit mode
		 * (REQ-WDG-010), so the type select is hidden.
		 *
		 * @param {object} placement existing placement record with type+content
		 * @spec openspec/specs/dashboards/spec.md
		 */
		openCustomWidgetEdit(placement) {
			this.customWidgetEditing = placement
			this.customWidgetPreselectedType = null
			this.customWidgetInitialType = null
			this.widgetDialogOpener = document.activeElement
			this.isCustomWidgetModalOpen = true
		},

		/** @spec openspec/specs/dashboards/spec.md */
		closeCustomWidgetModal() {
			this.isCustomWidgetModalOpen = false
			this.customWidgetPreselectedType = null
			this.customWidgetInitialType = null
			this.customWidgetEditing = null
			nextTick(() => restoreLostFocus(this.widgetDialogOpener))
		},

		/**
		 * Persist the `{type, content}` payload emitted by AddWidgetModal.
		 * In create mode we route through `addWidgetToDashboard` (which
		 * the per-widget proposals will extend to accept custom-type
		 * payloads); in edit mode we route through `updateWidgetPlacement`.
		 *
		 * The per-widget capability proposals own the actual API contract
		 * — this view simply forwards the payload, mirroring how the tile
		 * editor and style editor work today.
		 *
		 * @param {{type: string, content: object}} payload the widget add/edit payload from AddWidgetModal
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async saveCustomWidget(payload) {
			try {
				const chrome = payload.chrome || {}
				if (this.customWidgetEditing?.id) {
					await this.updateWidgetPlacement(this.customWidgetEditing.id, {
						content: payload.content,
						...this.chromePatch(
							chrome,
							this.customWidgetEditing.styleConfig,
						),
					})
				} else {
					// Create the placement, then apply the chrome (title /
					// background / icon) from the same modal as a follow-up
					// patch against the new id.
					const created = await this.addWidgetToDashboard(
						{
							type: payload.type,
							content: payload.content,
						},
						newWidgetSize(payload, this.activeDashboard?.gridColumns, GRID_ROW_HEIGHT),
					)
					if (created?.id) {
						await this.updateWidgetPlacement(
							created.id,
							this.chromePatch(chrome, created.styleConfig),
						)
					}
				}
				this.closeCustomWidgetModal()
			} catch (error) {
				logger.error('[Views] Failed to save custom widget:', error)
			}
		},

		/**
		 * Upload transport for the shared CnAddWidgetModal's Appearance icon
		 * picker — rounds a custom icon data URL through LaunchPad's resource
		 * service so it isn't embedded inline.
		 *
		 * @param {File} file the file to upload.
		 * @return {Promise<string>} the resulting URL/data URL.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		iconUploadFn(file) {
			return uploadDataUrl(file)
		},

		/**
		 * Raw-file upload transport for the shared CnAddWidgetModal's image
		 * widget sub-form. Streams the picked file to LaunchPad's resource
		 * service as multipart (no base64) on submit and returns the hosted
		 * URL in the `{ url }` shape the sub-form's uploadFn expects.
		 *
		 * @param {File} file the picked image file.
		 * @return {Promise<{url: string}>} the hosted resource URL.
		 * @spec openspec/specs/resource-uploads/spec.md
		 */
		imageFileUpload(file) {
			return uploadFile(file).then((result) => ({ url: result.url }))
		},

		/**
		 * Calendar list fetcher for the shared CnAddWidgetModal's calendar
		 * widget picker — returns the user's calendars so authors select them
		 * instead of typing principal URIs (REQ-CAL-002).
		 *
		 * @return {Promise<Array<{key: string, name: string, color: string}>>}
		 * @spec openspec/specs/calendar-widget/spec.md
		 */
		async fetchCalendars() {
			const res = await api.getCalendarWidgetCalendars()
			return res?.data?.calendars || []
		},

		/**
		 * Build the chrome patch (title / background / icon) from the unified
		 * add/edit modal payload, preserving any existing styleConfig keys and
		 * folding the background colour into styleConfig.backgroundColor — the
		 * same shape the legacy style editor persisted (see onStyleSaved).
		 *
		 * @param {{showTitle?: boolean, customTitle?: string, customIcon?: string, backgroundColor?: string}} chrome the modal chrome payload
		 * @param {object} [existingStyleConfig] the placement's current styleConfig, if any
		 * @return {object} the placement patch
		 * @spec openspec/specs/dashboards/spec.md
		 */
		chromePatch(chrome, existingStyleConfig) {
			return {
				showTitle: chrome.showTitle,
				customTitle: chrome.customTitle,
				customIcon: chrome.customIcon,
				styleConfig: {
					...(existingStyleConfig || {}),
					backgroundColor: chrome.backgroundColor || '',
				},
			}
		},

		/** @spec openspec/specs/dashboards/spec.md */
		openConfigModal() {
			this.configModalMode = 'edit'
			this.configModalInitialTab = 'general'
			this.isConfigModalOpen = true
		},

		/**
		 * Top-bar share action (dashboard-sharing spec). Opens the config
		 * drawer for the active dashboard pre-selected to the Sharing tab.
		 *
		 * @return {void}
		 * @spec openspec/specs/dashboard-sharing/spec.md
		 */
		openShareDrawer() {
			this.configModalMode = 'edit'
			this.configModalInitialTab = 'sharing'
			this.isConfigModalOpen = true
		},

		/**
		 * More's "Copy link to this dashboard": the open dashboard's own
		 * address (its slug path, else `/dashboards/:id`) on the clipboard.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		async copyDashboardLink() {
			const dash = this.activeDashboard
			if (!dash) {
				return
			}
			let path = ''
			try {
				const res = dash.uuid ? await api.getDashboardPath(dash.uuid) : null
				path = res?.data?.path ?? ''
			} catch (error) {
				logger.warn('[Views] no path for the dashboard link:', error)
			}
			const url = window.location.origin + this.buildDeepLinkUrl(path || `/dashboards/${dash.id}`)
			try {
				await navigator.clipboard.writeText(url)
				showSuccess(t('launchpad', 'Link copied'))
			} catch (error) {
				logger.warn('[Views] could not copy the dashboard link:', error)
				showError(t('launchpad', 'The link could not be copied.'))
			}
		},

		/** @spec openspec/specs/dashboards/spec.md */
		openCreateDashboardModal() {
			this.configModalMode = 'create'
			this.isConfigModalOpen = true
		},

		/** @spec openspec/specs/dashboards/spec.md */
		closeConfigModal() {
			this.isConfigModalOpen = false
			this.configSaveError = ''
		},

		/**
		 * Place a widget on the active dashboard.
		 *
		 * @param {string} widgetId Registry key or Nextcloud widget id to add.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async addWidget(widgetId) {
			await this.addWidgetToDashboard(widgetId)
		},

		/**
		 * Whether the user may remove a placement: not a compulsory widget
		 * on a dashboard the user holds with less than full permission.
		 *
		 * @param {object|null} placement The placement.
		 * @return {boolean} True when it may be removed.
		 * @spec openspec/specs/permissions/spec.md
		 */
		isRemovablePlacement(placement) {
			return !(
				Number(placement?.isCompulsory) === 1
				&& this.permissionLevel !== 'full'
			)
		},

		/**
		 * Remove a placement from the active dashboard.
		 *
		 * @param {number|string} placementId Id of the placement to remove.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async removeWidget(placementId) {
			// A compulsory widget cannot be removed from a copy made from an
			// admin template unless the template allows everything; the
			// server answers 403 and the store does not even ask it. Say so
			// instead of doing nothing (#780, REQ-TMPL-023).
			const placement = (this.widgetPlacements || []).find(
				(p) => p.id === placementId,
			)
			if (!this.isRemovablePlacement(placement)) {
				showError(
					t(
						'launchpad',
						'"{title}" is part of the start page your administrator set for your group and cannot be removed.',
						{
							title:
								placement?.customTitle || placement?.widgetId || '',
						},
					),
				)
				return
			}
			await this.removeWidgetFromDashboard(placementId)
		},

		/**
		 * Open the per-widget style editor for a placement.
		 *
		 * @param {object} placement Placement whose chrome/styling to edit.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		openStyleEditor(placement) {
			this.editingPlacement = placement
			// Hand the modal a deep clone — it mutates `widget` in place on Save;
			// the live store placement only changes once the patch persists.
			this.styleEditorWidget = JSON.parse(JSON.stringify(placement))
			this.isStyleEditorOpen = true
		},

		/** @spec openspec/specs/dashboards/spec.md */
		closeStyleEditor() {
			this.isStyleEditorOpen = false
			this.editingPlacement = null
			this.styleEditorWidget = null
		},

		/**
		 * Bridge CnWidgetStyleEditorModal's mutate-in-place `@save(widget)` to
		 * launchpad's immutable store path: derive the chrome+style patch from
		 * the (mutated) clone and persist it against the placement id.
		 *
		 * @param {object} widget The clone the modal mutated on Save.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onStyleSaved(widget) {
			const id = this.editingPlacement?.id
			if (!id) {
				return
			}
			await this.updateWidgetPlacement(id, {
				showTitle: widget.showTitle,
				customTitle: widget.customTitle,
				customIcon: widget.customIcon,
				styleConfig: widget.styleConfig,
			})
			this.closeStyleEditor()
		},

		/** @spec openspec/specs/dashboards/spec.md */
		async deleteWidget() {
			if (this.editingPlacement?.id) {
				await this.removeWidget(this.editingPlacement.id)
				this.closeStyleEditor()
			}
		},

		/**
		 * Open the launcher-tile editor, entering edit mode if needed.
		 *
		 * @param {object|null} [tile] Existing tile to edit; null opens the
		 *   editor for a brand-new tile.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async openTileEditor(tile = null) {
			if (!(await this.enterEditMode())) {
				return
			}
			this.editingTile = tile
			this.isTileEditorOpen = true
		},

		/**
		 * Open the tile editor pre-filled from an existing tile placement.
		 *
		 * @param {object} placement Tile placement whose `content` blob seeds
		 *   the editor form.
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		openTileEditorForEdit(placement) {
			const content = placement.content || {}
			const tileData = {
				id: placement.id,
				title: placement.tileTitle,
				icon: placement.tileIcon,
				iconType: placement.tileIconType,
				backgroundColor: placement.tileBackgroundColor,
				textColor: placement.tileTextColor,
				linkType: placement.tileLinkType,
				linkValue: placement.tileLinkValue,
				internalUrl: content.internalUrl || '',
				// service-health-ping: config lives in the placement's
				// `content` JSON blob, no schema change (REQ-HPING-001).
				healthPingEnabled: content.healthPingEnabled === true,
				healthUrl: content.healthUrl || '',
				expectedStatus: content.expectedStatus || 200,
				pingInterval: content.pingInterval || 60,
				// Launcher-tile-open-behaviour D1: '' follows the dashboard.
				linkTarget: content.linkTarget || '',
				// launcher-tile-launch-types: remote-desktop and sso settings.
				...(isLaunchType(placement.tileLinkType) ? launchFields(content) : {}),
			}
			this.openTileEditor(tileData)
		},

		/** @spec openspec/specs/dashboards/spec.md */
		closeTileEditor() {
			this.isTileEditorOpen = false
			this.editingTile = null
		},

		/**
		 * Create or update a launcher tile from the editor's form state.
		 *
		 * @param {object} tileData Assembled tile fields (title, icon, link
		 *   target, colours, health-ping config, …).
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		async saveTile(tileData) {
			// service-health-ping: config lives in the placement's `content`
			// JSON blob, no schema change (REQ-HPING-001).
			const healthPingContent = {
				healthPingEnabled: tileData.healthPingEnabled === true,
				healthUrl: tileData.healthUrl || '',
				expectedStatus: tileData.expectedStatus || 200,
				pingInterval: tileData.pingInterval || 60,
				// launcher-tile-internal-address REQ-TIA-002: validated by the server.
				internalUrl: tileData.internalUrl || '',
			}
			// Launcher-tile-open-behaviour D1: the link target rides in the
			// same content blob; absent means "follow the dashboard".
			if (tileData.linkTarget) {
				healthPingContent.linkTarget = tileData.linkTarget
			}
			// launcher-tile-launch-types: a remote-desktop or sso tile keeps its
			// settings in the same blob, checked by the server with the link.
			const content = { ...healthPingContent, ...launchContent(tileData) }
			try {
				if (this.editingTile) {
					await this.updateWidgetPlacement(this.editingTile.id, {
						tileTitle: tileData.title,
						tileIcon: tileData.icon,
						tileIconType: tileData.iconType,
						tileBackgroundColor: tileData.backgroundColor,
						tileTextColor: tileData.textColor,
						tileLinkType: tileData.linkType,
						tileLinkValue: tileData.linkValue,
						content,
					})
				} else {
					await this.createTile(tileData, content)
				}
				this.closeTileEditor()
			} catch (error) {
				this.reportTileSaveError(tileData, error)
			}
		},

		/**
		 * Create a launcher tile from the editor's form state.
		 *
		 * @param {object} tileData Assembled tile fields.
		 * @param {object} content The placement's content blob.
		 * @spec openspec/specs/service-health-ping/spec.md
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
		 */
		async createTile(tileData, content) {
			// A launch tile is checked on create, so it carries its content.
			const newPlacement = await this.addTileToDashboard(
				isLaunchType(tileData.linkType) ? { ...tileData, content } : tileData,
			)
			// `addTile` has no `content` parameter (mirrors the
			// `addWidget` create-then-patch pattern above) — persist
			// the health-ping block (and the office-network address,
			// launcher-tile-internal-address REQ-TIA-002, and the link target)
			// with a follow-up patch only when the author actually set one.
			if (newPlacement?.id && (content.healthPingEnabled || content.internalUrl || content.linkTarget)) {
				await this.updateWidgetPlacement(newPlacement.id, {
					content,
				})
			}
		},

		/**
		 * Log a failed tile save, and tell the author when the server refused a launch tile's settings.
		 *
		 * @param {object} tileData Assembled tile fields.
		 * @param {Error} error The failure.
		 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
		 */
		reportTileSaveError(tileData, error) {
			logger.error('[Views] Failed to save tile:', error)
			if (isLaunchType(tileData.linkType) && error?.response?.status === 400) {
				showError(t('launchpad', 'The tile was not saved: check its address or settings.'))
			}
		},

		/** @spec openspec/specs/dashboards/spec.md */
		async deleteTile() {
			if (this.editingTile?.id) {
				await this.removeWidget(this.editingTile.id)
				this.closeTileEditor()
			}
		},

		/** @spec openspec/specs/dashboards/spec.md */
		handleCreateDashboard() {
			this.openCreateDashboardModal()
		},

		/**
		 * Create or update a dashboard from the config modal's form state.
		 *
		 * @param {object} config Dashboard attributes from the modal.
		 * @param {number|null} config.id Dashboard id; null creates a new one.
		 * @param {string} config.name Display name.
		 * @param {string} config.description Short description.
		 * @param {string} config.icon Icon key or URL.
		 * @param {string} [config.parentUuid] Parent uuid, '' for none (edit only).
		 * @param {string} [config.slug] Web address name (edit only).
		 * @param {object} [config.look] The changed look fields (REQ-THM-001), edit only.
		 * @param {string} [config.defaultLinkTarget] How tiles open, '' for the link-type rule (edit only).
		 * @param {Array<object>} [config.hierarchies] The hierarchy descriptors, only when changed (edit only).
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
		 */
		async saveDashboardConfig(config) {
			const { id, name, description, icon, hierarchies } = config
			this.configSaveError = ''
			try {
				if (id === null || id === undefined) {
					await this.createDashboard({ name, description, icon })
				} else {
					await api.updateDashboard(id, dashboardUpdateData(config))
					await this.loadDashboards()
					// REQ-CWF-011: the open dashboard's bus is rebuilt on what it now stores.
					if (hierarchies !== undefined && this.activeDashboard?.id === id) {
						this.openFilterSession(false)
					}
				}
				this.closeConfigModal()
			} catch (error) {
				logger.error('Failed to save dashboard:', error)
				this.configSaveError = this.treeSaveMessage(error)
			}
		},

		/**
		 * Say why a save was refused, for the tree fields in particular.
		 * The service's messages are English constants
		 * (DashboardTreeService::ERR_*), so they are mapped, not shown.
		 *
		 * @param {Error} error The failed request.
		 * @return {string} A translated message.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		treeSaveMessage(error) {
			const message = error?.response?.data?.message ?? ''
			// REQ-THM-002: the server's measured ratio, in the user's language.
			if (error?.response?.data?.error === 'contrast_too_low') {
				return t(
					'launchpad',
					'Buttons in this colour are hard to see (contrast {ratio} to 1, needs 3 to 1)',
					{ ratio: Number(error.response.data.ratio).toFixed(1) },
				)
			}
			if (message === 'Setting this parent would create a cycle') {
				return t(
					'launchpad',
					'That dashboard sits under this one, so it cannot be its parent.',
				)
			}
			if (message === 'Slug must be unique among siblings') {
				return t(
					'launchpad',
					'Another dashboard under the same parent already uses this web address name.',
				)
			}
			if (message === 'Parent dashboard not found') {
				return t(
					'launchpad',
					'The chosen parent dashboard no longer exists.',
				)
			}
			return t('launchpad', 'The dashboard could not be saved.')
		},

		/**
		 * Delete a dashboard after an explicit user confirmation.
		 *
		 * @param {object} dashboard Dashboard record to delete.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async deleteCurrentDashboard(dashboard) {
			this.deleteChildCount = 0
			this.deleteTarget = dashboard
		},

		/**
		 * Handle a switch emitted by `DashboardSwitcherSidebar`. The sidebar
		 * passes the row's `source` discriminator alongside the id so we
		 * can pick the correct API endpoint per REQ-DASH-013/REQ-DASH-014:
		 *
		 *   - `'user'`    → personal dashboard endpoint (already the
		 *                   default in `dashboardStore.switchDashboard`)
		 *   - `'group'`   → primary group endpoint
		 *   - `'default'` → default group endpoint
		 *
		 * The store currently fetches via `getDashboardById`, which works
		 * for every visible-to-user record regardless of source — the
		 * group/default branches stay identical for now and exist to make
		 * the source contract visible to readers (and to keep the fan-out
		 * easy when source-specific endpoints land).
		 *
		 * @param {string|number} id Dashboard id from the clicked row.
		 * @param {'group'|'default'|'user'} source Section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */

		// REQ-SWITCH-002 contract and is kept in the signature so per-source
		// endpoints can land without re-touching this view.
		async onSidebarSwitch(id, _source) {
			// `source` is currently informational — `switchDashboard`
			// resolves any visible dashboard via /api/dashboard/{id}. The
			// signature is kept explicit so per-source behaviour can land
			// without re-touching this view (and so the load-bearing
			// REQ-SWITCH-002 contract is visible at the call site).
			await this.dropEditLock()
			await this.switchDashboard(id)
		},

		/**
		 * Compute the absolute URL for a slug-chain path, under the app
		 * prefix the page was opened on (`/index.php/apps/launchpad` or
		 * `/apps/launchpad`), which is the prefix the router is mounted on.
		 *
		 * @param {string} path Leading-slash slug-chain (e.g. `/finance/q1`).
		 * @return {string} Absolute pathname for `history.pushState`.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		buildDeepLinkUrl(path) {
			if (!path) {
				return ''
			}
			const prefix = appBase()
			const cleanPath = path.startsWith('/') ? path : `/${path}`
			return `${prefix}${cleanPath}`
		},

		/**
		 * Open the dashboard a slug address names. The store loads the
		 * stored active dashboard, so without this a slug address showed
		 * whichever dashboard was open last. Only an address the server
		 * resolved to its canonical path counts: any other address (a stale
		 * slug, a router path) keeps what the store chose.
		 *
		 * @param {string} bootPath `window.location.pathname` at boot.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-deeplinking/spec.md
		 */
		async openDeepLinkedDashboard(bootPath) {
			const uuid = this.injectedActiveDashboardId
			const canonical = this.buildDeepLinkUrl(this.injectedDeepLinkPath)
			if (!uuid || !canonical || this.activeDashboard?.uuid === uuid) {
				return
			}
			if (barePath(bootPath) !== barePath(canonical)) {
				return
			}
			const target = this.dashboards.find((d) => d.uuid === uuid)
			if (target) {
				await this.switchDashboard(target.id)
			}
		},

		/**
		 * Open the dashboard a `#dashboard/<uuid>` hash names: the link a
		 * unified search result carries. A uuid the user has no dashboard
		 * for keeps what the store chose.
		 *
		 * @param {string} hash The location hash.
		 * @return {Promise<void>}
		 * @spec openspec/specs/nc-unified-search-integration/spec.md#requirement-req-srch-011-nextcloud-search-ui-integration
		 */
		async openHashLinkedDashboard(hash) {
			const uuid = dashboardUuidFromHash(hash)
			if (!uuid || this.activeDashboard?.uuid === uuid) {
				return
			}
			const target = this.dashboards.find((d) => d.uuid === uuid)
			if (target) {
				await this.switchDashboard(target.id)
			}
		},

		/**
		 * Follow a `#dashboard/<uuid>` hash set after the page loaded.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/nc-unified-search-integration/spec.md#requirement-req-srch-011-nextcloud-search-ui-integration
		 */
		async onDashboardHashChange() {
			await this.openHashLinkedDashboard(window.location.hash)
		},

		/**
		 * Take the chosen period and keep it in the address, so a copied
		 * link opens with the same period; the stored dashboard is untouched.
		 *
		 * @param {string} period `month`, `quarter` or `year`.
		 * @return {void}
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		onPeriodChange(period) {
			this.dashboardPeriod = readPeriod('?period=' + encodeURIComponent(period))
			try {
				window.history.replaceState(window.history.state, '', addressWithPeriod(window.location.href, this.dashboardPeriod))
			} catch (e) {
				logger.warn('[Views] history.replaceState failed:', e)
			}
		},

		/**
		 * Bring the browser URL in line with the deep-link path the
		 * server pushed via initial state. Runs once on mount; uses
		 * `replaceState` so the bootstrap entry doesn't pollute the
		 * back-button history.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		replaceUrlFromInitialState() {
			const target = this.buildDeepLinkUrl(this.injectedDeepLinkPath)
			if (!target) {
				return
			}
			if (barePath(window.location.pathname) === barePath(target)) {
				return
			}
			try {
				// The query and hash stay: `?kiosk=1` must survive a reload.
				window.history.replaceState(
					{
						uuid: this.activeDashboard?.uuid ?? null,
						source: 'launchpad-deeplink',
					},
					'',
					target + window.location.search + window.location.hash,
				)
			} catch (e) {
				// SecurityError when running outside the page's origin
				// (jsdom test harnesses, sandboxed iframes). Failure is
				// non-fatal — the URL just stays out of sync.
				logger.warn('[Views] history.replaceState failed:', e)
			}
		},

		/**
		 * Outbound URL sync — fetch the canonical path for the active
		 * dashboard and `pushState` it. Called from the
		 * `activeDashboard.uuid` watcher AFTER the initial hydration
		 * (the bootstrap render uses `replaceUrlFromInitialState`
		 * instead). Failures are non-fatal; the URL just stays at its
		 * previous value while the active dashboard moves on.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		/**
		 * Open the filter session of the active dashboard, stopping the
		 * previous one (drill-down-cross-widget-filter REQ-CWF-003).
		 *
		 * Drill-down performance (Task 35, measured 2026-11-21 by
		 * tests/e2e/cross-widget-filter-performance.spec.ts on a local stack:
		 * 50,000 OpenRegister objects, 12 provinces, 342 gemeenten, 3,073
		 * wijken, OpenRegister 2.2.0-dev, PostgreSQL 16; 12 gemeente-then-wijk
		 * clicks per run, median / p95):
		 * - OpenRegister time of a filtered aggregation: 113 / 125 ms
		 *   (gemeente step), 108 / 118 ms (wijk step); a consumer's request
		 *   leaves 5 / 9 ms after the press.
		 * - Click to every consumer showing the filtered data, `bar-list`
		 *   charts: 176 / 244 ms (wijk step 161 / 191 ms), inside 300 ms.
		 * - The same with ApexCharts bars: 9.8 / 13.6 s, almost all of it the
		 *   chart redraw after the response (ApexCharts measures each SVG text
		 *   with getComputedStyle and getBBox); the bus and the query are not
		 *   the cost. A consumer whose data lands over 200 ms after a filter
		 *   change logs a warning (`refetchClock` in widgetBusConsumption.js).
		 * - Non-consumers sent no request and did not redraw.
		 * - Heap of 110 charts on the bus over the same charts off it:
		 *   +0.32 MB (bound 1 MB).
		 *
		 * @param {boolean} readUrl Whether the URL's `f` seeds the bus.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
		 */
		openFilterSession(readUrl) {
			this.filterSession?.stop()
			this.filterSession = useDashboardFilterSession({
				dashboard: this.activeDashboard,
				readUrl,
				// REQ-CWF-010: rows are written only when the organisation opted in.
				telemetry: createFilterTelemetry({
					enabled: () => loadInitialState('workspace').filterTelemetryEnabled === true,
					dashboardId: this.activeDashboard?.uuid ?? '',
					userId: typeof window.OC?.currentUser === 'string' ? window.OC.currentUser : '',
				}),
			})
		},

		/**
		 * Remove one clause from the active-filters panel.
		 *
		 * @param {string} dimension The clause's dimension.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-007-filter-stack-ui-shows-every-active-clause-with-its-source
		 */
		onFilterRemove(dimension) {
			this.filterSession?.remove(dimension)
		},

		/**
		 * "Reset filters": clear every clause that is not pinned.
		 *
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-006-reset-filters-clears-all-non-pinned-clauses
		 */
		onFilterReset() {
			this.filterSession?.reset()
		},

		/**
		 * A drill-down breadcrumb step was chosen: go back up to that level.
		 *
		 * @param {string} dimension The level's dimension.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
		 */
		onFilterPop(dimension) {
			this.filterSession?.popTo(dimension)
		},

		/**
		 * Write the address of the dashboard the boot ended on when it is not
		 * the one the server rendered. A `/dashboards/:id` route or a
		 * `#dashboard/<uuid>` hash opens another dashboard after mounted()
		 * wrote the server's dashboard's address, so without this the
		 * address named the wrong dashboard. Replaces rather than pushes, so
		 * the boot adds no history entry.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/manifest-routing/spec.md#requirement-req-route-003-a-dashboard-has-an-address
		 */
		async replaceUrlAfterBoot() {
			const uuid = this.activeDashboard?.uuid
			if (!uuid || uuid === this.injectedActiveDashboardId) {
				return
			}
			await this.pushUrlForActiveDashboard(true)
		},

		/**
		 * Write the active dashboard's canonical path to the address bar.
		 *
		 * @param {boolean} [replace] Replace the current entry, keeping the
		 *   query and hash, and fall back to `/dashboards/:id` for a dashboard
		 *   without a path. Otherwise push a new entry.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async pushUrlForActiveDashboard(replace = false) {
			const uuid = this.activeDashboard?.uuid
			if (!uuid) {
				return
			}
			try {
				const res = await api.getDashboardPath(uuid)
				const path = res?.data?.path
					|| (replace && this.activeDashboard.id !== undefined ? `/dashboards/${this.activeDashboard.id}` : '')
				const target = this.buildDeepLinkUrl(path)
				if (!target) {
					return
				}
				if (barePath(window.location.pathname) === barePath(target)) {
					return
				}
				if (replace) {
					window.history.replaceState(
						{ uuid, source: 'launchpad-deeplink' },
						'',
						target + window.location.search + window.location.hash,
					)
					return
				}
				window.history.pushState(
					{ uuid, source: 'launchpad-deeplink' },
					'',
					target,
				)
			} catch (e) {
				logger.warn('[Views] failed to push URL for active dashboard:', e)
			}
		},

		/**
		 * Browser back / forward handler. Strips the launchpad route
		 * prefix off `window.location.pathname` and re-resolves the
		 * remaining slug-chain via the existing by-path API. The state
		 * payload's uuid is preferred when present (avoids the
		 * round-trip), but we fall back to path resolution so external
		 * navigations (manually pasted URLs that hit popstate) still
		 * route correctly.
		 *
		 * @param {PopStateEvent} event The popstate event.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async handleHistoryPopState(event) {
			const targetUuid = event?.state?.uuid ?? null
			if (targetUuid && targetUuid === this.activeDashboard?.uuid) {
				return
			}

			const prefix = appBase()
			const pathname = window.location.pathname
			let suffix = ''
			if (pathname.startsWith(prefix)) {
				suffix = pathname
					.slice(prefix.length)
					.replace(/^\/+/, '')
					.replace(/\/+$/, '')
			}
			if (!suffix) {
				return
			}

			try {
				const res = await api.getDashboardByPath(suffix)
				const dashboard = res?.data?.dashboard
				if (dashboard?.id !== undefined && dashboard?.id !== null) {
					await this.dropEditLock()
					await this.switchDashboard(dashboard.id)
				}
			} catch (e) {
				logger.warn('[Views] popstate path resolution failed:', e)
			}
		},

		/*
		 * Wave3.6 per-row action handlers. Each cog action emits the
		 * row's full dashboard payload (not just the active one), so
		 * we first switch to that dashboard (when not already active)
		 * and then apply the requested action. This lets the user
		 * Edit / Configure / Add-widget on any row without manually
		 * switching first.
		 *
		 * @param {object} dashboard Row payload (`id`, `name`, `isOwner`, …).
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onRowToggleEdit(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.toggleEditMode()
		},

		/**
		 * Row cog "Configure" — switch to the row's dashboard, then open the
		 * dashboard config modal.
		 *
		 * @param {object} dashboard Row payload (`id`, `name`, `isOwner`, …).
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onRowOpenConfig(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.openConfigModal()
		},

		/**
		 * Row cog "Add widget": switch to the row's dashboard, then open the
		 * widget picker.
		 *
		 * @param {object} dashboard Row payload (`id`, `name`, `isOwner`, …).
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/widgets/spec.md#requirement-widget-picker-req-wdg-010
		 */
		async onRowAddWidget(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			await this.openWidgetModal()
		},

		/**
		 * Open the bookmark import for the row's dashboard, switching to it
		 * first so the new containers and tiles land where the user looks
		 * (launcher-bookmark-import REQ-BMI-001).
		 *
		 * @param {object} dashboard Row payload.
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
		 */
		async onRowImportBookmarks(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.isBookmarkImportOpen = true
		},

		/**
		 * Send the chosen bookmarks for the active dashboard through the store.
		 *
		 * @param {{folders: Array<object>, bookmarks: Array<object>}} chosen The chosen items.
		 * @return {Promise<{placements: Array<object>, skipped: Array<object>}>}
		 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
		 */
		importChosenBookmarks(chosen) {
			return this.importBookmarks(chosen)
		},

		/**
		 * Wave3.7 — pin the row's dashboard as the user's default.
		 *
		 * Toggle semantics: clicking the already-pinned row clears the pin
		 * (so the cog shows "Set as default" again on next open); clicking
		 * any other row replaces the pin with that dashboard's UUID. The new
		 * preference takes effect on the next page load — visiting
		 * `/apps/launchpad/` resolves to this dashboard via the resolver's
		 * Step 0.
		 *
		 * @param {object} dashboard Row payload; only `uuid` is read.
		 * @param {'group'|'default'|'user'} source Row section discriminator,
		 *   accepted for signature parity with the other row handlers.
		 * @spec openspec/specs/dashboards/spec.md
		 */

		// but kept so every row-cog handler shares one signature.
		async onRowSetDefault(dashboard, _source) {
			const uuid = dashboard?.uuid ?? ''
			if (uuid === '') {
				return
			}
			try {
				if (this.defaultDashboardUuid === uuid) {
					await api.clearDefaultDashboardPreference()
					this.defaultDashboardUuid = ''
				} else {
					await api.setDefaultDashboardPreference(uuid)
					this.defaultDashboardUuid = uuid
				}
			} catch (error) {
				logger.error(
					'[Views] Failed to update default-dashboard preference:',
					error,
				)
			}
		},

		/*
		 * Wave3.8 — modal `set-default` handler. Honours the explicit
		 * boolean from the toggle (unlike the row-cog toggle which
		 * inverts based on current pin state). Lets the user clear
		 * the pin from the dashboard's own configuration without
		 * having to hunt for the same dashboard's row.
		 */
		/**
		 * @param {object} payload Emitted by the dashboard config modal.
		 * @param {string} payload.uuid UUID of the dashboard in the modal.
		 * @param {boolean} payload.isDefault True to pin it as the user's
		 *   default; false clears the pin when this dashboard held it.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onModalSetDefault({ uuid, isDefault }) {
			if (!uuid) {
				return
			}
			try {
				if (isDefault) {
					await api.setDefaultDashboardPreference(uuid)
					this.defaultDashboardUuid = uuid
				} else if (this.defaultDashboardUuid === uuid) {
					await api.clearDefaultDashboardPreference()
					this.defaultDashboardUuid = ''
				}
			} catch (error) {
				logger.error(
					'[Views] Failed to update default-dashboard preference from modal:',
					error,
				)
			}
		},

		/**
		 * Switch the active dashboard only when the requested id differs
		 * from the current one. Avoids a redundant API round-trip when a
		 * per-row action targets the row that is already active.
		 *
		 * @param {string|number} id Dashboard id the row action targets.
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async maybeSwitchTo(id, source) {
			if (this.activeDashboard?.id === id) {
				return
			}
			await this.onSidebarSwitch(id, source)
		},

		/**
		 * Sidebar `+ New Dashboard` handler — forks the active dashboard
		 * as a personal copy (REQ-DASH-020). The store action handles the
		 * 403 toast when personal dashboards are disabled (REQ-ASET-003)
		 * and pushes the new entry to `dashboards` on success.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onSidebarCreateDashboard() {
			const sourceUuid = this.activeDashboard?.uuid
			if (!sourceUuid) {
				return
			}
			try {
				await this.forkDashboard(sourceUuid, null)
			} catch {
				// Error surfaces via the store's showError toast; nothing
				// additional to do here.
			}
		},

		/**
		 * Sidebar personal-row delete handler. Mirrors the topbar
		 * deletion flow (confirm → API → reload) but operates on an
		 * arbitrary id rather than the active dashboard.
		 *
		 * @param {string|number} id Personal dashboard id to delete.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onSidebarDeleteDashboard(id) {
			this.deleteChildCount = 0
			this.deleteTarget = this.dashboards.find((d) => d.id === id) ?? {
				id,
				name: '',
			}
		},

		/**
		 * The delete dialog closed without confirming.
		 *
		 * @param {boolean} isOpen New open state.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onDeleteDialog(isOpen) {
			if (!isOpen) {
				this.deleteTarget = null
				this.deleteChildCount = 0
			}
		},

		/**
		 * Delete the confirmed dashboard. When it has dashboards under it
		 * the server answers 409 with `childCount`; the dialog then says how
		 * many go with it, and the next confirmation deletes them all.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async confirmDeleteDashboard() {
			const target = this.deleteTarget
			if (!target) {
				return
			}
			try {
				await api.deleteDashboard(target.id, this.deleteChildCount > 0)
			} catch (error) {
				const data = error?.response?.data
				if (
					error?.response?.status === 409
					&& data?.error === 'dashboard_has_children'
				) {
					this.deleteChildCount = Number(data.childCount) || 1
					return
				}
				logger.error('Failed to delete dashboard:', error)
				return
			}
			this.deleteTarget = null
			this.deleteChildCount = 0
			await this.loadDashboards()
			this.closeConfigModal()
		},

		/**
		 * A breadcrumb was chosen: open that dashboard.
		 *
		 * @param {string} uuid The ancestor's uuid.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onBreadcrumbNavigate(uuid) {
			const target = this.dashboards.find((d) => d.uuid === uuid)
			if (target) {
				await this.onSidebarSwitch(target.id, target.source)
			}
		},
	},
}
</script>

<style scoped>
#launchpad-app {
	width: 100%;
	background: transparent;
}

/* A dashboard that fills the height (LpVandaag): the page keeps the height
   below the top bar and the grid divides it, so nothing here scrolls. */
#launchpad-app.launchpad-app--fill {
	height: 100%;
	display: flex;
	flex-direction: column;
}

.launchpad-container.launchpad-container--fill {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	min-height: 0;
	overflow: hidden;
}

.launchpad-container--fill > .cn-dashboard-grid {
	flex: 1 1 auto;
	min-height: 0;
}

/* Nextcloud insets the content area horizontally but not at the top, so the
   grid sat flush under the navbar (8px top gap from the grid margin vs 16px
   on the sides). Add a matching top inset so the dashboard breathes evenly.
   Note: the layout properties (flex, overflow, min-height) are consolidated
   in the single .launchpad-container rule further below. */

/* CnDashboardGrid renders a flat item background; restore launchpad's
   translucent tile look (was DashboardGrid's local style) by overriding
   the grid-item-content surface. :deep penetrates into CnDashboardGrid.
   No backdrop blur: the content area behind is already blurred, and each
   blurred item was blurred again on every repaint, which made a pointer
   move over the grid cost 100 ms or more. */
.launchpad-container :deep(.grid-stack-item-content) {
	background: var(--color-main-background-blur);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

/* In edit mode the whole grid item is a drag surface. Text-heavy chromeless
   widgets (esp. labels) are pure selectable text filling the cell, so a drag
   would start a text selection instead of a gridstack move. Suppress text
   selection + show the move cursor so dragging works anywhere on any widget.
   The edit cog stays clickable (pointer-events unaffected). */
.launchpad-container.launchpad-edit-mode :deep(.grid-stack-item-content) {
	cursor: move;
	user-select: none;
	-webkit-user-select: none;
}

/* Chromeless widgets (label/divider/header/tile) AND nc-dashboard card
   widgets opt out of the grid-item frosted surface: the former paint their
   own surface, and the latter now carry the shared CnWidgetWrapper
   `nc-dashboard` chrome (its own blur panel), so the grid item must be
   transparent to avoid a double background / mismatched radius. */
.launchpad-container
	:deep(.grid-stack-item-content:has(.cn-widget-wrapper--borderless)),
.launchpad-container
	:deep(.grid-stack-item-content:has(.cn-widget-wrapper--nc-dashboard)),
.launchpad-container :deep(.grid-stack-item-content:has(.tile-widget)) {
	background: transparent;
	backdrop-filter: none;
	-webkit-backdrop-filter: none;
}

.launchpad-grid-item {
	width: 100%;
	height: 100%;
	transition: opacity 0.15s ease;
}

/* tile-quick-search REQ-QSEARCH-002 "Typing filters tiles by label":
   non-matching tiles are de-emphasised, NOT removed from the grid layout,
   so the grid never reflows while the user types. The class is bound
   reactively from the tileSearch store in this component's own template
   (see `isTileDimmed`) — no other component reaches in to set it. */
.launchpad-grid-item--dimmed {
	opacity: 0.35;
}

.launchpad-grid-item--hidden-for-viewers {
	position: relative;
}

.launchpad-grid-item--hidden-for-viewers > :not(.launchpad-grid-item__hidden-badge) {
	opacity: 0.5;
}

.launchpad-grid-item__hidden-badge {
	position: absolute;
	top: calc(var(--default-grid-baseline) * 2);
	inset-inline-start: calc(var(--default-grid-baseline) * 2);
	z-index: 2;
	padding: 0 calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius-pill);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border-dark);
	font-size: var(--font-size-small, 13px);
	line-height: 24px;
	pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
	.launchpad-grid-item {
		transition: none;
	}
}

.launchpad-floating-controls {
	position: fixed;
	top: 80px;
	right: 44px;
	display: flex;
	gap: 8px;
	align-items: center;
	/*
	 * Above the sidebar backdrop's 1450, which is what keeps "click the
	 * hamburger again to close" working while the sidebar is open. Only
	 * meaningful together with the Teleport in the template: inside
	 * `#app-content` no value here can beat a body-level sibling.
	 */
	z-index: 1460;
}

/* Docked in the greeting row (headerActionsDock.js): part of the row's
   flow, so nothing floats over the page and the row scrolls with it. */
.launchpad-floating-controls--docked {
	position: static;
	z-index: auto;
	gap: 10px;
}

.launchpad-ack-indicator {
	/* dashboard-acknowledgements REQ-ACK-002 outstanding-count pill. */
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: var(--border-radius-pill, 100px);
	background: var(--color-warning, #d97706);
	color: var(--color-primary-text, #fff);
	font-size: 0.8em;
	font-weight: 600;
	white-space: nowrap;
}

/* Strip the visible text on the menu trigger button — we want icon-only.
   NcActions renders its aria-label as button text in this version. */
.launchpad-floating-controls :deep(.action-item__menutoggle .button-vue__text) {
	display: none;
}

.launchpad-floating-controls :deep(.action-item__menutoggle) {
	width: var(--default-clickable-area, 44px);
	min-width: var(--default-clickable-area, 44px);
	padding: 0;
}

.launchpad-container {
	flex: 1;
	padding: 8px 0 0;
	overflow: auto;
	min-height: calc(
		100vh - var(--header-height, 50px) - var(--body-container-margin, 8px)
	);
}

.launchpad-empty,
.launchpad-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: calc(
		100vh - var(--header-height, 50px) - var(--body-container-margin, 8px)
	);
}

.launchpad-publication-note {
	margin: 0 8px 8px;
	color: var(--color-text-maxcontrast);
}
</style>
