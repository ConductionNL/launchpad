<template>
	<aside class="sidebar-panel" :class="{ open: isOpen }">
		<div class="sidebar-header">
			<h3 class="sidebar-title">
				{{ t('sendentworkspace', 'Dashboards') }}
			</h3>
			<button class="close-btn" :title="t('sendentworkspace', 'Close')" @click="$emit('update:open', false)">
				<CloseIcon :size="20" />
			</button>
		</div>

		<nav class="sidebar-nav">
			<!-- Matched group dashboards section -->
			<div v-if="matchedGroupDashboards.length > 0" class="nav-section">
				<div class="section-label">
					{{ groupName || t('sendentworkspace', 'Dashboards') }}
				</div>
				<button
					v-for="dash in matchedGroupDashboards"
					:key="dash.id"
					class="nav-item"
					:class="{ active: dash.id === activeDashboardId }"
					@click="selectDashboard(dash.id, dash.source || 'group')">
					<img v-if="isCustomIconUrl(dash.icon)"
						:src="dash.icon"
						:alt="dash.name"
						class="nav-icon-img">
					<component
						:is="getIconComponent(dash.icon)"
						v-else
						:size="20"
						class="nav-icon" />
					<span class="nav-label">{{ dash.name }}</span>
				</button>
			</div>

			<!-- Default group dashboards section -->
			<div v-if="defaultGroupDashboards.length > 0" class="nav-section">
				<div v-if="matchedGroupDashboards.length > 0" class="nav-divider" />
				<div class="section-label">
					{{ t('sendentworkspace', 'Default') }}
				</div>
				<button
					v-for="dash in defaultGroupDashboards"
					:key="dash.id"
					class="nav-item"
					:class="{ active: dash.id === activeDashboardId }"
					@click="selectDashboard(dash.id, 'default')">
					<img v-if="isCustomIconUrl(dash.icon)"
						:src="dash.icon"
						:alt="dash.name"
						class="nav-icon-img">
					<component
						:is="getIconComponent(dash.icon)"
						v-else
						:size="20"
						class="nav-icon" />
					<span class="nav-label">{{ dash.name }}</span>
				</button>
			</div>

			<!-- User dashboards section -->
			<template v-if="userDashboards.length > 0 || allowUserDashboards">
				<div class="nav-divider" />
				<div class="nav-section">
					<div class="section-label">
						{{ t('sendentworkspace', 'My Dashboards') }}
					</div>
					<div
						v-for="dash in userDashboards"
						:key="dash.id"
						class="nav-item-wrapper">
						<button
							class="nav-item"
							:class="{ active: dash.id === activeDashboardId }"
							@click="selectDashboard(dash.id, 'user')">
							<img v-if="isCustomIconUrl(dash.icon)"
								:src="dash.icon"
								:alt="dash.name"
								class="nav-icon-img">
							<component
								:is="getIconComponent(dash.icon)"
								v-else
								:size="20"
								class="nav-icon" />
							<span class="nav-label">{{ dash.name }}</span>
						</button>
						<button
							class="delete-btn"
							:title="t('sendentworkspace', 'Delete dashboard')"
							@click.stop="$emit('delete-dashboard', dash.id)">
							<CloseIcon :size="14" />
						</button>
					</div>
					<button
						v-if="allowUserDashboards"
						class="nav-item add-item"
						@click="onCreateDashboard">
						<PlusIcon :size="20" class="nav-icon" />
						<span class="nav-label">{{ t('sendentworkspace', 'New Dashboard') }}</span>
					</button>
				</div>
			</template>
		</nav>
	</aside>
</template>

<script>
import { computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import { getIconComponent, isCustomIconUrl } from '../constants/dashboardIcons.js'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

export default {
	name: 'DashboardSwitcher',

	components: {
		PlusIcon,
		CloseIcon,
	},

	props: {
		isOpen: {
			type: Boolean,
			default: false,
		},
		groupName: {
			type: String,
			default: '',
		},
		groupDashboards: {
			type: Array,
			default: () => [],
		},
		userDashboards: {
			type: Array,
			default: () => [],
		},
		activeDashboardId: {
			type: String,
			default: '',
		},
		allowUserDashboards: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['switch', 'create-dashboard', 'delete-dashboard', 'update:open'],

	setup(props, { emit }) {
		const matchedGroupDashboards = computed(() => {
			return props.groupDashboards.filter(d => d.source !== 'default')
		})

		const defaultGroupDashboards = computed(() => {
			return props.groupDashboards.filter(d => d.source === 'default')
		})

		const selectDashboard = (id, source) => {
			emit('update:open', false)
			emit('switch', id, source)
		}

		const onCreateDashboard = () => {
			emit('update:open', false)
			emit('create-dashboard')
		}

		return {
			t,
			matchedGroupDashboards,
			defaultGroupDashboards,
			getIconComponent,
			isCustomIconUrl,
			selectDashboard,
			onCreateDashboard,
		}
	},
}
</script>

<style scoped lang="scss">
.sidebar-panel {
  position: fixed;
  top: 50px; // Nextcloud header height
  inset-inline-start: 0;
  bottom: 0;
  width: 280px;
  background-color: var(--color-main-background);
  border-inline-end: 1px solid var(--color-border);
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
  transform: translateX(-100%);
  transition: transform 0.25s ease;
  z-index: 1500;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  &.open {
    transform: translateX(0);
  }
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}

.sidebar-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--color-main-text);
}

.close-btn {
  padding: 4px;
  background: none;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  color: var(--color-text-maxcontrast);
  line-height: 0;
  transition: all 0.15s;

  &:hover {
    color: var(--color-main-text);
    background-color: var(--color-background-hover);
  }
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 8px 0;
}

.nav-section {
  padding: 4px 0;
}

.section-label {
  padding: 8px 16px 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-maxcontrast);
}

.nav-divider {
  height: 1px;
  background-color: var(--color-border);
  margin: 8px 12px;
}

.nav-item-wrapper {
  display: flex;
  align-items: center;

  .nav-item {
    flex: 1;
  }

  .delete-btn {
    display: none;
    padding: 4px;
    margin-inline-end: 12px;
    background: none;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    color: var(--color-text-maxcontrast);
    line-height: 0;

    &:hover {
      color: var(--color-error);
      background-color: var(--color-error-hover);
    }
  }

  &:hover .delete-btn {
    display: inline-flex;
  }
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 16px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-main-text);
  text-align: start;
  transition: background-color 0.15s;
  border-radius: 0;

  &:hover {
    background-color: var(--color-background-hover);
  }

  &.active {
    background-color: var(--color-primary-element-light);
    font-weight: 500;

    .nav-icon {
      color: var(--color-primary);
    }
  }

  &.add-item {
    color: var(--color-primary);
    font-weight: 500;
  }
}

.nav-icon {
  flex-shrink: 0;
  color: var(--color-text-maxcontrast);
}

.nav-icon-img {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  object-fit: contain;
  border-radius: 2px;
}

.nav-label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
