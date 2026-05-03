<template>
	<main class="workspace-content">
		<!-- Sidebar Dashboard Switcher -->
		<DashboardSwitcher
			v-if="showSwitcher"
			:is-open="sidebarOpen"
			:group-name="primaryGroupName"
			:group-dashboards="groupDashboards"
			:user-dashboards="userDashboards"
			:active-dashboard-id="activeDashboardId"
			:allow-user-dashboards="allowUserDashboards"
			@update:open="sidebarOpen = $event"
			@switch="switchDashboard"
			@create-dashboard="createUserDashboard"
			@delete-dashboard="deleteUserDashboard" />

		<!-- Sidebar backdrop overlay -->
		<div
			v-if="sidebarOpen"
			class="sidebar-backdrop"
			@click="sidebarOpen = false" />

		<!-- Hamburger toggle + dashboard name -->
		<div v-if="showSwitcher" class="dashboard-toggle">
			<button
				class="hamburger-btn"
				:title="t('sendentworkspace', 'Toggle sidebar')"
				@click="sidebarOpen = !sidebarOpen">
				<MenuIcon :size="22" />
			</button>
			<span v-if="activeDashName" class="active-dashboard-name">{{ activeDashName }}</span>
		</div>

		<!-- Admin Toolbar (only when user can edit) -->
		<div v-if="canEdit" class="workspace-toolbar">
			<div class="toolbar-actions">
				<div class="add-widget-dropdown">
					<button
						class="add-widget-button"
						:class="{ 'dropdown-open': showAddDropdown }"
						@click="showAddDropdown = !showAddDropdown">
						<PlusIcon :size="16" /> {{ t('sendentworkspace', 'Add Widget') }}
						<ChevronDownIcon :size="16" class="dropdown-arrow" :class="{ 'rotated': showAddDropdown }" />
					</button>
					<div
						v-if="showAddDropdown"
						class="dropdown-menu"
						@click.stop>
						<button class="dropdown-item" @click="openAddWidgetModal('text')">
							{{ t('sendentworkspace', 'Text Widget') }}
						</button>
						<button class="dropdown-item" @click="openAddWidgetModal('image')">
							{{ t('sendentworkspace', 'Image Widget') }}
						</button>
						<button class="dropdown-item" @click="openAddWidgetModal('link')">
							{{ t('sendentworkspace', 'Link Button') }}
						</button>
						<button class="dropdown-item" @click="openAddWidgetModal('label')">
							{{ t('sendentworkspace', 'Label') }}
						</button>
						<button class="dropdown-item" @click="openAddWidgetModal('widget')">
							{{ t('sendentworkspace', 'Nextcloud Widget') }}
						</button>
					</div>
				</div>

				<button class="save-button" :disabled="saving" @click="saveLayout">
					{{ saving ? t('sendentworkspace', 'Saving…') : t('sendentworkspace', 'Save Layout') }}
				</button>
			</div>
		</div>

		<!-- GridStack Container -->
		<div ref="gridContainer" class="grid-stack">
			<div
				v-for="widget in layout"
				:key="widget.id"
				class="grid-stack-item"
				:gs-x="parseInt(widget.x)"
				:gs-y="parseInt(widget.y)"
				:gs-w="parseInt(widget.w)"
				:gs-h="parseInt(widget.h)"
				:data-id="widget.id"
				@contextmenu.prevent="onWidgetRightClick($event, widget)">
				<div class="grid-stack-item-content" :class="getItemContentClass(widget)">
					<component
						:is="getWidgetComponent(widget)"
						v-bind="getWidgetProps(widget)"
						:id="widget.id"
						:is-admin="canEdit" />
				</div>
			</div>
		</div>

		<!-- Add Widget Modal -->
		<AddWidgetModal
			:show="showModal"
			:widgets="availableWidgets"
			:preselected-type="preselectedType"
			:editing-widget="editingWidgetData"
			@close="closeModal"
			@submit="handleWidgetSubmit" />

		<!-- Context Menu -->
		<ContextMenu
			v-if="showContextMenu"
			:show="showContextMenu"
			:x="contextMenuX"
			:y="contextMenuY"
			:widget="selectedWidget"
			@edit="editWidget"
			@remove="removeWidget"
			@close="closeContextMenu" />
	</main>
</template>

<script>
import { inject, ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import TextDisplayWidget from './components/TextDisplayWidget.vue'
import ImageWidget from './components/ImageWidget.vue'
import LinkButtonWidget from './components/LinkButtonWidget.vue'
import LabelWidget from './components/LabelWidget.vue'
import ApiWidget from './components/ApiWidget.vue'
import AddWidgetModal from './components/AddWidgetModal.vue'
import ContextMenu from './components/ContextMenu.vue'
import DashboardSwitcher from './components/DashboardSwitcher.vue'
import { useGridManager } from './composables/useGridManager.js'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'

export default {
	name: 'WorkspaceApp',

	components: {
		TextDisplayWidget,
		ImageWidget,
		LinkButtonWidget,
		LabelWidget,
		ApiWidget,
		AddWidgetModal,
		ContextMenu,
		DashboardSwitcher,
		PlusIcon,
		ChevronDownIcon,
		MenuIcon,
	},

	setup() {
		const availableWidgets = inject('widgets', [])
		const initialLayout = inject('layout', [])
		const primaryGroup = inject('primaryGroup', 'default')
		const primaryGroupName = inject('primaryGroupName', '')
		const isAdmin = inject('isAdmin', false)
		const initialActiveDashboardId = inject('activeDashboardId', '')
		const initialDashboardSource = inject('dashboardSource', 'group')
		const initialGroupDashboards = inject('groupDashboards', [])
		const initialUserDashboards = inject('userDashboards', [])
		const allowUserDashboards = inject('allowUserDashboards', false)

		const layout = ref([...initialLayout])
		const gridContainer = ref(null)
		const saving = ref(false)
		const activeDashboardId = ref(initialActiveDashboardId)
		const dashboardSource = ref(initialDashboardSource)
		const groupDashboards = ref([...initialGroupDashboards])
		const userDashboards = ref([...initialUserDashboards])
		const sidebarOpen = ref(false)

		// User can edit if admin OR if viewing their own user dashboard
		const canEdit = computed(() => {
			return isAdmin || dashboardSource.value === 'user'
		})

		const showSwitcher = computed(() => {
			return groupDashboards.value.length > 0
				|| userDashboards.value.length > 0
				|| allowUserDashboards
		})

		const activeDashName = computed(() => {
			const all = [...groupDashboards.value, ...userDashboards.value]
			const found = all.find(d => d.id === activeDashboardId.value)
			return found?.name || ''
		})

		const {
			showModal,
			showAddDropdown,
			preselectedType,
			showContextMenu,
			contextMenuX,
			contextMenuY,
			selectedWidget,
			editingWidgetData,
			initGrid,
			destroyGrid,
			getWidgetComponent,
			getWidgetProps,
			openAddWidgetModal,
			closeModal,
			handleWidgetSubmit,
			onWidgetRightClick,
			closeContextMenu,
			editWidget,
			removeWidget,
			handleClickOutside,
		} = useGridManager({ layout, gridContainer, isAdmin: canEdit.value })

		const getItemContentClass = (widget) => {
			return {
				'widget-text': widget.type === 'text',
				'widget-image': widget.type === 'image',
				'widget-link': widget.type === 'link',
				'widget-label': widget.type === 'label',
				'widget-api': widget.type === 'widget',
			}
		}

		// Reinitialize grid with new layout
		const reinitGrid = async (newLayout) => {
			destroyGrid()
			layout.value = [...newLayout]
			await nextTick()
			initGrid()
		}

		// Switch dashboard
		const switchDashboard = async (dashboardId, source) => {
			if (dashboardId === activeDashboardId.value) return

			try {
				let newLayout = []
				if (source === 'user') {
					// Load from user dashboards endpoint
					const url = generateOcsUrl('/apps/sendentworkspace/api/v1/user-dashboards')
					const response = await axios.get(url)
					const data = response.data?.ocs?.data || {}
					const dash = (data.dashboards || []).find(d => d.id === dashboardId)
					newLayout = dash?.layout || []
				} else {
					// Load from group dashboards endpoint — use 'default' group for default-sourced dashboards
					const groupId = source === 'default' ? 'default' : primaryGroup
					const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}/{dashboardId}', {
						groupId,
						dashboardId,
					})
					const response = await axios.get(url)
					newLayout = response.data?.ocs?.data?.dashboard?.layout || []
				}

				activeDashboardId.value = dashboardId
				dashboardSource.value = source
				await reinitGrid(newLayout)

				// Persist active dashboard preference
				const prefUrl = generateOcsUrl('/apps/sendentworkspace/api/v1/active-dashboard')
				axios.post(prefUrl, { dashboardId }).catch(() => {})
			} catch (error) {
				console.error('Failed to switch dashboard:', error)
				showError(t('sendentworkspace', 'Failed to load dashboard'))
			}
		}

		// Create user dashboard (fork from current group dashboard)
		const createUserDashboard = async () => {
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/user-dashboards')
				const response = await axios.post(url, {
					name: t('sendentworkspace', 'My Dashboard'),
					layout: layout.value,
				})
				const newDash = response.data?.ocs?.data
				if (newDash) {
					userDashboards.value.push({ id: newDash.id, name: newDash.name })
					// Switch to the new dashboard
					activeDashboardId.value = newDash.id
					dashboardSource.value = 'user'
					await reinitGrid(newDash.layout || layout.value)

					const prefUrl = generateOcsUrl('/apps/sendentworkspace/api/v1/active-dashboard')
					axios.post(prefUrl, { dashboardId: newDash.id }).catch(() => {})

					showSuccess(t('sendentworkspace', 'Dashboard created'))
				}
			} catch (error) {
				console.error('Failed to create dashboard:', error)
				showError(t('sendentworkspace', 'Failed to create dashboard'))
			}
		}

		// Delete user dashboard
		const deleteUserDashboard = async (dashboardId) => {
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/user-dashboards/{dashboardId}', {
					dashboardId,
				})
				await axios.delete(url)
				userDashboards.value = userDashboards.value.filter(d => d.id !== dashboardId)

				// If we deleted the active one, switch to group default
				if (activeDashboardId.value === dashboardId) {
					const firstGroup = groupDashboards.value[0]
					if (firstGroup) {
						await switchDashboard(firstGroup.id, 'group')
					}
				}

				showSuccess(t('sendentworkspace', 'Dashboard deleted'))
			} catch (error) {
				console.error('Failed to delete dashboard:', error)
				showError(t('sendentworkspace', 'Failed to delete dashboard'))
			}
		}

		const saveLayout = async () => {
			saving.value = true
			try {
				let url
				if (dashboardSource.value === 'user') {
					url = generateOcsUrl('/apps/sendentworkspace/api/v1/user-dashboards/{dashboardId}', {
						dashboardId: activeDashboardId.value,
					})
					await axios.put(url, { layout: layout.value })
				} else {
					url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}/{dashboardId}', {
						groupId: primaryGroup,
						dashboardId: activeDashboardId.value,
					})
					await axios.put(url, { layout: layout.value })
				}

				showSuccess(t('sendentworkspace', 'Layout saved successfully'))
			} catch (error) {
				console.error('Failed to save layout:', error)
				showError(t('sendentworkspace', 'Failed to save layout'))
			} finally {
				saving.value = false
			}
		}

		onMounted(async () => {
			await nextTick()
			initGrid()
			document.addEventListener('click', handleClickOutside)
		})

		onBeforeUnmount(() => {
			document.removeEventListener('click', handleClickOutside)
			destroyGrid()
		})

		return {
			t,
			availableWidgets,
			layout,
			primaryGroup,
			primaryGroupName,
			isAdmin,
			canEdit,
			showSwitcher,
			gridContainer,
			showModal,
			showAddDropdown,
			preselectedType,
			showContextMenu,
			contextMenuX,
			contextMenuY,
			selectedWidget,
			editingWidgetData,
			saving,
			sidebarOpen,
			activeDashboardId,
			activeDashName,
			dashboardSource,
			groupDashboards,
			userDashboards,
			allowUserDashboards,
			getWidgetComponent,
			getWidgetProps,
			getItemContentClass,
			openAddWidgetModal,
			closeModal,
			handleWidgetSubmit,
			onWidgetRightClick,
			closeContextMenu,
			editWidget,
			removeWidget,
			switchDashboard,
			createUserDashboard,
			deleteUserDashboard,
			saveLayout,
		}
	},
}
</script>

<style scoped lang="scss">
.workspace-content {
  padding: 20px;
}

.sidebar-backdrop {
  position: fixed;
  top: 50px;
  inset-inline: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.3);
  z-index: 1400;
}

.dashboard-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px 6px 6px;
  margin-bottom: 12px;
  background-color: var(--color-background-dark);
  border-radius: 8px;
}

.hamburger-btn {
  padding: 6px;
  background: none;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  color: var(--color-main-text);
  line-height: 0;
  transition: background-color 0.15s;

  &:hover {
    background-color: var(--color-background-hover);
  }
}

.active-dashboard-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-main-text);
}

.workspace-toolbar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin-bottom: 16px;
}

.toolbar-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.add-widget-dropdown {
  position: relative;
}

.add-widget-button {
  padding: 8px 16px;
  background-color: var(--color-primary);
  color: var(--color-primary-text);
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background-color 0.2s;

  &:hover {
    background-color: var(--color-primary-hover);
  }

  &.dropdown-open {
    background-color: var(--color-primary-hover);
  }
}

.dropdown-arrow {
  transition: transform 0.2s;
  font-size: 10px;

  &.rotated {
    transform: rotate(180deg);
  }
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  inset-inline-end: 0;
  margin-top: 4px;
  background-color: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  min-width: 180px;
  z-index: 1000;
  overflow: hidden;
}

.dropdown-item {
  display: block;
  width: 100%;
  padding: 10px 16px;
  background: none;
  border: none;
  text-align: start;
  cursor: pointer;
  transition: background-color 0.2s;

  &:hover {
    background-color: var(--color-background-hover);
  }
}

.save-button {
  padding: 8px 16px;
  background-color: var(--color-success);
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: opacity 0.2s;

  &:hover:not(:disabled) {
    opacity: 0.9;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.grid-stack {
  min-height: 500px;
}

.grid-stack-item-content {
  background-color: var(--color-background-dark);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: box-shadow 0.2s;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }

  &.widget-link {
    background-color: transparent;
    box-shadow: none;

    &:hover {
      box-shadow: none;
    }
  }
}

.widget-wrapper {
  width: 100%;
  height: 100%;
}
</style>
