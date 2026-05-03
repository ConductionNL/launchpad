<template>
	<div class="admin-app">
		<!-- Group Management View -->
		<div v-if="viewState === 'groups'" class="admin-section">
			<h3>{{ t('sendentworkspace', 'Group Management') }}</h3>
			<p class="description">
				{{ t('sendentworkspace', 'Manage which groups have access to custom workspaces. Drag groups between lists to activate/deactivate them. The order of active groups determines the priority for users in multiple groups.') }}
			</p>

			<div class="groups-management">
				<div class="group-list">
					<h4>{{ t('sendentworkspace', 'Active Groups (in priority order)') }}</h4>
					<div class="group-filter">
						<input
							v-model="activeFilter"
							type="text"
							:placeholder="t('sendentworkspace', 'Filter groups…')"
							class="filter-input">
					</div>
					<draggable
						v-model="activeGroups"
						class="groups-container active"
						group="groups"
						item-key="id"
						@change="updateGroups">
						<template #item="{ element }">
							<div v-show="matchesFilter(element, activeFilter)" class="group-item">
								<span class="group-name">{{ element.displayName || element.id }}</span>
								<button class="edit-btn" @click="openDashboards(element.id, element.displayName || element.id)">
									<PencilIcon :size="16" /> {{ t('sendentworkspace', 'Dashboards') }}
								</button>
							</div>
						</template>
					</draggable>
				</div>

				<div class="group-list">
					<h4>{{ t('sendentworkspace', 'Inactive Groups') }}</h4>
					<div class="group-filter">
						<input
							v-model="inactiveFilter"
							type="text"
							:placeholder="t('sendentworkspace', 'Filter groups…')"
							class="filter-input">
					</div>
					<draggable
						v-model="inactiveGroups"
						class="groups-container inactive"
						group="groups"
						item-key="id"
						@change="updateGroups">
						<template #item="{ element }">
							<div v-show="matchesFilter(element, inactiveFilter)" class="group-item">
								<span class="group-name">{{ element.displayName || element.id }}</span>
							</div>
						</template>
					</draggable>
				</div>
			</div>

			<div class="default-group-section">
				<h4>{{ t('sendentworkspace', 'Default Workspace') }}</h4>
				<p>{{ t('sendentworkspace', 'This workspace is shown to users who don\'t belong to any active group.') }}</p>
				<button class="edit-btn primary" @click="openDashboards('default', t('sendentworkspace', 'Default'))">
					<PencilIcon :size="16" /> {{ t('sendentworkspace', 'Edit Default Dashboards') }}
				</button>
			</div>

			<div class="settings-section">
				<h4>{{ t('sendentworkspace', 'Settings') }}</h4>
				<label class="toggle-setting">
					<input
						type="checkbox"
						:checked="allowUserDashboards"
						@change="toggleAllowUserDashboards">
					{{ t('sendentworkspace', 'Allow users to create their own dashboards') }}
				</label>
			</div>

			<!-- Group order is auto-saved on drag -->
		</div>

		<!-- Dashboard List View -->
		<div v-if="viewState === 'dashboards'" class="admin-section">
			<div class="section-header">
				<button class="back-btn" @click="backToGroups">
					<ArrowLeftIcon :size="20" /> {{ t('sendentworkspace', 'Back to Groups') }}
				</button>
				<h3>{{ t('sendentworkspace', 'Dashboards for') }} {{ editingGroupName }}</h3>
			</div>

			<div class="dashboards-list">
				<div
					v-for="dash in dashboards"
					:key="dash.id"
					class="dashboard-item"
					:class="{ 'is-default': dash.id === defaultDashboardId }">
					<div class="dashboard-info">
						<img v-if="isCustomIconUrl(dash.icon)"
							:src="dash.icon"
							:alt="dash.name"
							class="dashboard-icon-img">
						<component
							:is="getIconComponent(dash.icon)"
							v-else
							:size="18"
							class="dashboard-icon" />
						<span class="dashboard-name">{{ dash.name }}</span>
						<span v-if="dash.id === defaultDashboardId" class="default-badge">
							{{ t('sendentworkspace', 'Default') }}
						</span>
					</div>
					<div class="dashboard-actions">
						<button
							v-if="dash.id !== defaultDashboardId"
							class="action-btn"
							@click="setDefaultDashboard(dash.id)">
							<StarIcon :size="16" /> {{ t('sendentworkspace', 'Set Default') }}
						</button>
						<button class="action-btn primary" @click="editDashboard(dash)">
							<PencilIcon :size="16" /> {{ t('sendentworkspace', 'Edit') }}
						</button>
						<button
							v-if="dashboards.length > 1"
							class="action-btn danger"
							@click="deleteDashboard(dash.id)">
							<DeleteIcon :size="16" /> {{ t('sendentworkspace', 'Delete') }}
						</button>
					</div>
				</div>
			</div>

			<div class="create-dashboard-section">
				<input
					v-model="newDashboardName"
					type="text"
					:placeholder="t('sendentworkspace', 'New dashboard name…')"
					class="filter-input"
					@keyup.enter="createDashboard">
				<div class="icon-picker">
					<select v-model="newDashboardIcon" class="icon-select">
						<option
							v-for="(_, iconName) in dashboardIconNames"
							:key="iconName"
							:value="iconName">
							{{ iconName }}
						</option>
					</select>
					<span class="icon-or">{{ t('sendentworkspace', 'or') }}</span>
					<label class="icon-upload-btn">
						<input
							ref="dashIconUpload"
							type="file"
							accept="image/*"
							class="hidden-upload"
							@change="handleDashboardIconUpload">
						{{ t('sendentworkspace', 'Upload icon') }}
					</label>
					<img v-if="isCustomIconUrl(newDashboardIcon)"
						:src="newDashboardIcon"
						alt="Icon preview"
						class="icon-preview-small">
				</div>
				<button class="edit-btn primary" :disabled="!newDashboardName.trim()" @click="createDashboard">
					<PlusIcon :size="16" /> {{ t('sendentworkspace', 'Create Dashboard') }}
				</button>
			</div>
		</div>

		<!-- Workspace Editor Full-Screen View -->
		<div v-if="viewState === 'editor'" class="editor-fullscreen">
			<div class="editor-header">
				<button class="back-btn" @click="backToDashboards">
					<ArrowLeftIcon :size="20" /> {{ t('sendentworkspace', 'Back to Dashboards') }}
				</button>
				<h3>{{ editingDashboardName }} <span class="group-label">({{ editingGroupName }})</span></h3>
				<button class="save-btn" :disabled="savingWorkspace" @click="saveWorkspace">
					{{ savingWorkspace ? t('sendentworkspace', 'Saving…') : t('sendentworkspace', 'Save Workspace') }}
				</button>
			</div>
			<WorkspaceEditor
				:group-id="editingGroup"
				:initial-layout="currentLayout"
				:widgets="availableWidgets"
				@layout-changed="onLayoutChanged" />
		</div>
	</div>
</template>

<script>
import { inject, ref } from 'vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import draggable from 'vuedraggable'
import WorkspaceEditor from './components/WorkspaceEditor.vue'
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import { DASHBOARD_ICONS, DEFAULT_ICON, getIconComponent, isCustomIconUrl } from './constants/dashboardIcons.js'

export default {
	name: 'AdminApp',

	components: {
		draggable,
		WorkspaceEditor,
		PencilIcon,
		ArrowLeftIcon,
		PlusIcon,
		StarIcon,
		DeleteIcon,
	},

	setup() {
		const allGroupsData = inject('allGroups', [])
		const configuredGroupsData = inject('configuredGroups', [])
		const availableWidgets = inject('widgets', [])
		const initialAllowUserDashboards = inject('allowUserDashboards', false)

		// View state: 'groups' | 'dashboards' | 'editor'
		const viewState = ref('groups')

		const activeGroups = ref([])
		const inactiveGroups = ref([])
		const activeFilter = ref('')
		const inactiveFilter = ref('')
		const allowUserDashboards = ref(initialAllowUserDashboards)

		// Dashboard list state
		const editingGroup = ref(null)
		const editingGroupName = ref('')
		const dashboards = ref([])
		const defaultDashboardId = ref('')
		const newDashboardName = ref('')
		const newDashboardIcon = ref(DEFAULT_ICON)
		const dashboardIconNames = DASHBOARD_ICONS
		const dashIconUpload = ref(null)

		// Editor state
		const editingDashboardId = ref(null)
		const editingDashboardName = ref('')
		const currentLayout = ref([])
		const savingWorkspace = ref(false)

		// Initialize groups
		const initGroups = () => {
			const configuredIds = configuredGroupsData || []

			activeGroups.value = configuredIds
				.map(id => allGroupsData.find(g => g.id === id))
				.filter(Boolean)

			const activeIds = activeGroups.value.map(g => g.id)
			inactiveGroups.value = allGroupsData.filter(g => !activeIds.includes(g.id))
		}

		initGroups()

		const updateGroups = () => {
			// Auto-save after every drag event (move between columns or reorder)
			saveGroupOrder()
		}

		const matchesFilter = (element, filter) => {
			if (!filter) return true
			return (element.displayName || element.id).toLowerCase().includes(filter.toLowerCase())
		}

		const saveGroupOrder = async () => {
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/groups')
				const groupIds = activeGroups.value.map(g => g.id)

				await axios.post(url, {
					groups: groupIds,
				})

				showSuccess(t('sendentworkspace', 'Group order saved successfully'))
			} catch (error) {
				console.error('Failed to save group order:', error)
				showError(t('sendentworkspace', 'Failed to save group order'))
			}
		}

		const toggleSectionConstraint = (active) => {
			const section = document.getElementById('sendentworkspace-admin')
			if (section) {
				if (active) {
					section.classList.add('editor-active')
				} else {
					section.classList.remove('editor-active')
				}
			}
		}

		// Settings
		const toggleAllowUserDashboards = async (event) => {
			const newValue = event.target.checked
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/settings')
				await axios.post(url, { allow_user_dashboards: newValue })
				allowUserDashboards.value = newValue
				showSuccess(t('sendentworkspace', 'Settings saved'))
			} catch (error) {
				console.error('Failed to save settings:', error)
				showError(t('sendentworkspace', 'Failed to save settings'))
				event.target.checked = !newValue
			}
		}

		// Dashboard list navigation
		const openDashboards = async (groupId, groupName) => {
			editingGroup.value = groupId
			editingGroupName.value = groupName

			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}', { groupId })
				const response = await axios.get(url)
				const data = response.data?.ocs?.data || {}
				dashboards.value = data.dashboards || []
				defaultDashboardId.value = data.defaultDashboardId || ''
			} catch (error) {
				console.error('Failed to load dashboards:', error)
				dashboards.value = []
				defaultDashboardId.value = ''
			}

			viewState.value = 'dashboards'
		}

		const backToGroups = () => {
			viewState.value = 'groups'
			editingGroup.value = null
			editingGroupName.value = ''
			dashboards.value = []
			defaultDashboardId.value = ''
			newDashboardName.value = ''
		}

		const createDashboard = async () => {
			const name = newDashboardName.value.trim()
			if (!name) return

			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}', {
					groupId: editingGroup.value,
				})
				const response = await axios.post(url, { name, icon: newDashboardIcon.value })
				const newDash = response.data?.ocs?.data?.dashboard
				if (newDash) {
					dashboards.value.push(newDash)
				}
				newDashboardName.value = ''
				newDashboardIcon.value = DEFAULT_ICON
				showSuccess(t('sendentworkspace', 'Dashboard created'))
			} catch (error) {
				console.error('Failed to create dashboard:', error)
				showError(t('sendentworkspace', 'Failed to create dashboard'))
			}
		}

		const setDefaultDashboard = async (dashboardId) => {
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}/default', {
					groupId: editingGroup.value,
				})
				await axios.post(url, { dashboardId })
				defaultDashboardId.value = dashboardId
				showSuccess(t('sendentworkspace', 'Default dashboard updated'))
			} catch (error) {
				console.error('Failed to set default:', error)
				showError(t('sendentworkspace', 'Failed to set default dashboard'))
			}
		}

		const deleteDashboard = async (dashboardId) => {
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}/{dashboardId}', {
					groupId: editingGroup.value,
					dashboardId,
				})
				await axios.delete(url)
				dashboards.value = dashboards.value.filter(d => d.id !== dashboardId)

				// If we deleted the default, update
				if (defaultDashboardId.value === dashboardId && dashboards.value.length > 0) {
					defaultDashboardId.value = dashboards.value[0].id
				}

				showSuccess(t('sendentworkspace', 'Dashboard deleted'))
			} catch (error) {
				console.error('Failed to delete dashboard:', error)
				showError(t('sendentworkspace', 'Failed to delete dashboard'))
			}
		}

		// Editor navigation
		const editDashboard = async (dash) => {
			editingDashboardId.value = dash.id
			editingDashboardName.value = dash.name

			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}/{dashboardId}', {
					groupId: editingGroup.value,
					dashboardId: dash.id,
				})
				const response = await axios.get(url)
				currentLayout.value = response.data?.ocs?.data?.dashboard?.layout || []
			} catch (error) {
				console.error('Failed to load dashboard:', error)
				currentLayout.value = []
			}

			viewState.value = 'editor'
			toggleSectionConstraint(true)
		}

		const backToDashboards = () => {
			viewState.value = 'dashboards'
			editingDashboardId.value = null
			editingDashboardName.value = ''
			currentLayout.value = []
			toggleSectionConstraint(false)
		}

		const onLayoutChanged = (newLayout) => {
			currentLayout.value = newLayout
		}

		const saveWorkspace = async () => {
			savingWorkspace.value = true
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/dashboards/{groupId}/{dashboardId}', {
					groupId: editingGroup.value,
					dashboardId: editingDashboardId.value,
				})

				await axios.put(url, {
					layout: currentLayout.value,
				})

				showSuccess(t('sendentworkspace', 'Workspace saved successfully'))
			} catch (error) {
				console.error('Failed to save workspace:', error)
				showError(t('sendentworkspace', 'Failed to save workspace'))
			} finally {
				savingWorkspace.value = false
			}
		}

		const handleDashboardIconUpload = async (event) => {
			const file = event.target.files[0]
			if (!file) return
			try {
				const dataUrl = await new Promise((resolve, reject) => {
					const reader = new FileReader()
					reader.onload = () => resolve(reader.result)
					reader.onerror = () => reject(reader.error)
					reader.readAsDataURL(file)
				})

				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/upload-resource')
				const response = await axios.post(url, { base64: dataUrl })
				if (response.data?.ocs?.data?.url) {
					newDashboardIcon.value = response.data.ocs.data.url
				}
			} catch (error) {
				console.error('Failed to upload icon:', error)
				showError(t('sendentworkspace', 'Failed to upload icon'))
			}
		}

		return {
			t,
			viewState,
			activeGroups,
			inactiveGroups,
			activeFilter,
			inactiveFilter,
			allowUserDashboards,
			editingGroup,
			editingGroupName,
			dashboards,
			defaultDashboardId,
			newDashboardName,
			newDashboardIcon,
			dashboardIconNames,
			getIconComponent,
			isCustomIconUrl,
			dashIconUpload,
			handleDashboardIconUpload,
			editingDashboardId,
			editingDashboardName,
			currentLayout,
			savingWorkspace,
			availableWidgets,
			updateGroups,
			matchesFilter,
			saveGroupOrder,
			toggleAllowUserDashboards,
			openDashboards,
			backToGroups,
			createDashboard,
			setDefaultDashboard,
			deleteDashboard,
			editDashboard,
			backToDashboards,
			onLayoutChanged,
			saveWorkspace,
		}
	},
}
</script>

<style scoped lang="scss">
.admin-app {
  padding: 20px;
}

.admin-section {
  background-color: var(--color-main-background);
  border-radius: 8px;
  padding: 24px;

  h3 {
    margin: 0 0 12px;
    font-size: 24px;
    color: var(--color-main-text);
  }

  h4 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 600;
    color: var(--color-main-text);
  }

  .description {
    color: var(--color-text-maxcontrast);
    margin-bottom: 24px;
  }
}

.section-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;

  h3 {
    margin: 0;
  }
}

.groups-management {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.group-list {
  display: flex;
  flex-direction: column;
}

.group-filter {
  margin-bottom: 12px;

  .filter-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    background-color: var(--color-main-background);
    color: var(--color-main-text);

    &:focus {
      outline: none;
      border-color: var(--color-primary);
    }
  }
}

.groups-container {
  min-height: 200px;
  max-height: 400px;
  overflow-y: auto;
  padding: 12px;
  border: 2px dashed var(--color-border);
  border-radius: 6px;
  background-color: var(--color-background-dark);

  &.active {
    border-color: var(--color-primary);
  }
}

.group-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  margin-bottom: 8px;
  background-color: var(--color-main-background);
  border-radius: 6px;
  border: 1px solid var(--color-border);
  cursor: move;
  transition: all 0.2s;

  &:hover {
    border-color: var(--color-primary);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  &:last-child {
    margin-bottom: 0;
  }
}

.group-name {
  font-weight: 500;
  color: var(--color-main-text);
}

.edit-btn {
  padding: 6px 12px;
  background-color: var(--color-primary);
  color: var(--color-primary-text);
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: opacity 0.2s;

  &:hover {
    opacity: 0.9;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.primary {
    padding: 10px 20px;
    font-size: 14px;
  }
}

.default-group-section {
  padding: 20px;
  background-color: var(--color-background-dark);
  border-radius: 6px;
  margin-bottom: 24px;

  p {
    color: var(--color-text-maxcontrast);
    margin: 8px 0 16px;
  }
}

.settings-section {
  padding: 20px;
  background-color: var(--color-background-dark);
  border-radius: 6px;
  margin-bottom: 24px;
}

.toggle-setting {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-main-text);

  input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
  }
}

.save-btn {
  padding: 10px 24px;
  background-color: var(--color-success);
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: opacity 0.2s;

  &:hover:not(:disabled) {
    opacity: 0.9;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

// Dashboard list styles
.dashboards-list {
  margin-bottom: 24px;
}

.dashboard-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  margin-bottom: 8px;
  background-color: var(--color-background-dark);
  border-radius: 6px;
  border: 1px solid var(--color-border);
  transition: border-color 0.2s;

  &:hover {
    border-color: var(--color-primary);
  }

  &.is-default {
    border-color: var(--color-primary);
    background-color: var(--color-primary-element-light);
  }
}

.dashboard-info {
  display: flex;
  align-items: center;
  gap: 10px;

  .dashboard-icon {
    color: var(--color-primary);
    flex-shrink: 0;
  }
}

.dashboard-name {
  font-weight: 500;
  font-size: 15px;
  color: var(--color-main-text);
}

.default-badge {
  padding: 2px 8px;
  background-color: var(--color-primary);
  color: var(--color-primary-text);
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
}

.dashboard-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  padding: 6px 10px;
  background: none;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--color-main-text);
  transition: all 0.2s;

  &:hover {
    background-color: var(--color-background-hover);
  }

  &.primary {
    background-color: var(--color-primary);
    color: var(--color-primary-text);
    border-color: var(--color-primary);

    &:hover {
      opacity: 0.9;
    }
  }

  &.danger {
    color: var(--color-error);
    border-color: var(--color-error);

    &:hover {
      background-color: var(--color-error);
      color: white;
    }
  }
}

.create-dashboard-section {
  display: flex;
  gap: 12px;
  align-items: center;

  .filter-input {
    flex: 1;
    max-width: 300px;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    background-color: var(--color-main-background);
    color: var(--color-main-text);

    &:focus {
      outline: none;
      border-color: var(--color-primary);
    }
  }
}

.icon-picker {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.icon-or {
  font-size: 13px;
  color: var(--color-text-maxcontrast);
}

.icon-upload-btn {
  padding: 6px 12px;
  background-color: var(--color-background-dark);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  color: var(--color-main-text);
  transition: background-color 0.2s;

  &:hover {
    background-color: var(--color-background-hover);
  }
}

.hidden-upload {
  display: none;
}

.icon-preview-small {
  width: 24px;
  height: 24px;
  object-fit: contain;
  border-radius: 2px;
  border: 1px solid var(--color-border);
}

.dashboard-icon-img {
  width: 18px;
  height: 18px;
  object-fit: contain;
  border-radius: 2px;
  flex-shrink: 0;
}

.icon-select {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-size: 14px;
  background-color: var(--color-main-background);
  color: var(--color-main-text);
  cursor: pointer;

  &:focus {
    outline: none;
    border-color: var(--color-primary);
  }
}

// Editor styles
.editor-fullscreen {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 100px);
}

.editor-header {
  display: flex;
  align-items: center;
  padding: 16px 24px;
  background-color: var(--color-background-dark);
  border-radius: 8px;
  margin-bottom: 16px;
  gap: 16px;

  h3 {
    margin: 0;
    font-size: 18px;
    flex: 1;
  }

  .group-label {
    font-weight: 400;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
  }

  .save-btn {
    padding: 8px 16px;
    font-size: 13px;
  }
}

.back-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: none;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-main-text);
  transition: background-color 0.2s;

  &:hover {
    background-color: var(--color-background-hover);
  }
}
</style>
