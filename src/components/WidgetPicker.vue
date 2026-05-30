<!--
  - SPDX-FileCopyrightText: 2024 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="launchpad-picker" :class="{ 'launchpad-picker--open': open }">
		<div class="launchpad-picker__header">
			<h2 class="launchpad-picker__title">
				{{ activeTab === 'widgets' ? t('launchpad', 'Add to dashboard') : t('launchpad', 'Manage dashboards') }}
			</h2>
			<NcButton
				type="tertiary"
				:aria-label="t('launchpad', 'Close')"
				@click="$emit('close')">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
		</div>

		<!-- Tabs -->
		<div class="launchpad-picker__tabs">
			<button
				class="launchpad-picker__tab"
				:class="{ 'launchpad-picker__tab--active': activeTab === 'widgets' }"
				@click="activeTab = 'widgets'">
				<ViewModule :size="20" />
				{{ t('launchpad', 'Widgets') }}
			</button>
			<button
				class="launchpad-picker__tab"
				:class="{ 'launchpad-picker__tab--active': activeTab === 'dashboards' }"
				@click="activeTab = 'dashboards'">
				<ViewDashboard :size="20" />
				{{ t('launchpad', 'Dashboards') }}
			</button>
		</div>

		<!-- Widgets Tab -->
		<div v-if="activeTab === 'widgets'" class="launchpad-picker__content">
			<!-- Add Tile Button -->
			<div class="launchpad-picker__add-tile">
				<NcButton
					type="primary"
					@click="$emit('add-tile')">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('launchpad', 'Create tile') }}
				</NcButton>
			</div>
			<div class="launchpad-picker__search">
				<NcTextField
					v-model="searchQuery"
					:label="t('launchpad', 'Search widgets')"
					:placeholder="t('launchpad', 'Search widgets...')"
					:show-trailing-button="searchQuery !== ''"
					trailing-button-icon="close"
					@trailing-button-click="searchQuery = ''">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>

			<div class="launchpad-picker__list">
				<div
					v-for="widget in filteredWidgets"
					:key="widget.id"
					class="launchpad-picker__widget"
					:class="{ 'launchpad-picker__widget--placed': isPlaced(widget.id) }"
					@click="addWidget(widget)">
					<img
						v-if="widget.iconUrl"
						:src="widget.iconUrl"
						:alt="widget.title"
						class="launchpad-picker__widget-icon">
					<span v-else-if="widget.iconClass" :class="widget.iconClass" class="launchpad-picker__widget-icon" />
					<div class="launchpad-picker__widget-info">
						<span class="launchpad-picker__widget-title">{{ widget.title }}</span>
						<span v-if="isPlaced(widget.id)" class="launchpad-picker__widget-badge">
							{{ t('launchpad', 'Already added') }}
						</span>
					</div>
					<Plus v-if="!isPlaced(widget.id)" :size="20" class="launchpad-picker__widget-add" />
					<Check v-else :size="20" class="launchpad-picker__widget-check" />
				</div>

				<NcEmptyContent
					v-if="filteredWidgets.length === 0"
					:description="t('launchpad', 'No widgets found')">
					<template #icon>
						<Magnify :size="48" />
					</template>
				</NcEmptyContent>
			</div>
		</div>

		<!-- Dashboards Tab -->
		<div v-if="activeTab === 'dashboards'" class="launchpad-picker__content">
			<div class="launchpad-picker__add-tile">
				<NcButton
					type="primary"
					@click="createDashboard">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('launchpad', 'Create dashboard') }}
				</NcButton>
			</div>

			<div class="launchpad-picker__list">
				<div
					v-for="dashboard in dashboards"
					:key="dashboard.id"
					class="launchpad-picker__dashboard">
					<div class="launchpad-picker__dashboard-content">
						<ViewDashboard :size="20" class="launchpad-picker__dashboard-icon" />
						<div class="launchpad-picker__dashboard-info">
							<span class="launchpad-picker__dashboard-title">{{ dashboard.name }}</span>
							<span v-if="dashboard.id === activeDashboardId" class="launchpad-picker__dashboard-badge">
								{{ t('launchpad', 'Active') }}
							</span>
						</div>
					</div>
					<div class="launchpad-picker__dashboard-actions">
						<NcButton
							v-if="dashboard.id !== activeDashboardId"
							type="tertiary"
							:aria-label="t('launchpad', 'Switch to this dashboard')"
							@click="$emit('switch-dashboard', dashboard.id)">
							<template #icon>
								<SwapHorizontal :size="20" />
							</template>
						</NcButton>
						<NcButton
							type="tertiary"
							:aria-label="t('launchpad', 'Edit dashboard')"
							@click="editDashboard(dashboard)">
							<template #icon>
								<Pencil :size="20" />
							</template>
						</NcButton>
						<NcButton
							v-if="dashboards.length > 1"
							type="tertiary"
							:aria-label="t('launchpad', 'Delete dashboard')"
							@click="deleteDashboard(dashboard)">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</div>
				</div>

				<NcEmptyContent
					v-if="dashboards.length === 0"
					:description="t('launchpad', 'No dashboards yet')">
					<template #icon>
						<ViewDashboard :size="48" />
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcTextField, NcEmptyContent } from '@conduction/nextcloud-vue'
import Close from 'vue-material-design-icons/Close.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Check from 'vue-material-design-icons/Check.vue'
import ViewModule from 'vue-material-design-icons/ViewModule.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import SwapHorizontal from 'vue-material-design-icons/SwapHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'WidgetPicker',

	components: {
		NcButton,
		NcTextField,
		NcEmptyContent,
		Close,
		Magnify,
		Plus,
		Check,
		ViewModule,
		ViewDashboard,
		SwapHorizontal,
		Pencil,
		Delete,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		widgets: {
			type: Array,
			required: true,
		},
		placedWidgetIds: {
			type: Array,
			default: () => [],
		},
		dashboards: {
			type: Array,
			default: () => [],
		},
		activeDashboardId: {
			type: Number,
			default: null,
		},
	},

	emits: ['close', 'add', 'add-tile', 'switch-dashboard', 'create-dashboard', 'edit-dashboard', 'delete-dashboard'],

	data() {
		return {
			searchQuery: '',
			activeTab: 'widgets',
		}
	},

	computed: {
		/** @spec openspec/specs/widgets/spec.md */
		filteredWidgets() {
			if (!this.searchQuery) {
				return this.sortedWidgets
			}

			const query = this.searchQuery.toLowerCase()
			return this.sortedWidgets.filter(
				w => w.title.toLowerCase().includes(query),
			)
		},

		/** @spec openspec/specs/widgets/spec.md */
		sortedWidgets() {
			return [...this.widgets].sort((a, b) => {
				// Show not-placed widgets first.
				const aPlaced = this.isPlaced(a.id)
				const bPlaced = this.isPlaced(b.id)
				if (aPlaced !== bPlaced) {
					return aPlaced ? 1 : -1
				}
				// Then sort by order.
				return (a.order || 0) - (b.order || 0)
			})
		},
	},

	methods: {
		isPlaced(widgetId) {
			return this.placedWidgetIds.includes(widgetId)
		},

		/** @spec openspec/specs/widgets/spec.md */
		addWidget(widget) {
			// Allow adding the same widget multiple times.
			this.$emit('add', widget.id)
		},

		/** @spec openspec/specs/widgets/spec.md */
		createDashboard() {
			this.$emit('create-dashboard')
		},

		/** @spec openspec/specs/widgets/spec.md */
		editDashboard(dashboard) {
			this.$emit('edit-dashboard', dashboard)
		},

		/** @spec openspec/specs/widgets/spec.md */
		deleteDashboard(dashboard) {
			this.$emit('delete-dashboard', dashboard)
		},
	},
}
</script>

<style scoped>
.launchpad-picker {
	position: fixed;
	right: 0;
	top: 50px;
	bottom: 0;
	width: 320px;
	background: var(--color-main-background);
	border-left: 1px solid var(--color-border);
	transform: translateX(100%);
	transition: transform var(--animation-quick) ease;
	z-index: 1000;
	display: flex;
	flex-direction: column;
}

.launchpad-picker--open {
	transform: translateX(0);
}

.launchpad-picker__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px;
}

.launchpad-picker__title {
	font-size: 18px;
	font-weight: 600;
	margin: 0;
}

.launchpad-picker__tabs {
	display: flex;
	border-bottom: 1px solid var(--color-border);
}

.launchpad-picker__tab {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 12px 16px;
	background: none;
	border: none;
	cursor: pointer;
	font-size: 14px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
	transition: all var(--animation-quick) ease;
	border-bottom: 2px solid transparent;
}

.launchpad-picker__tab:hover {
	color: var(--color-main-text);
	background: var(--color-background-hover);
}

.launchpad-picker__tab--active {
	color: var(--color-primary-element);
	border-bottom-color: var(--color-primary-element);
}

.launchpad-picker__add-tile {
	padding: 16px;
}

.launchpad-picker__content {
	flex: 1;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
}

.launchpad-picker__search {
	padding: 16px;
}

.launchpad-picker__list {
	flex: 1;
	overflow-y: auto;
	padding: 0 16px 16px;
}

.launchpad-picker__widget {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: background var(--animation-quick) ease;
}

.launchpad-picker__widget:hover {
	background: var(--color-background-hover);
}

.launchpad-picker__widget--placed {
	opacity: 0.6;
}

.launchpad-picker__widget-icon {
	width: 32px;
	height: 32px;
	flex-shrink: 0;
}

.launchpad-picker__widget-info {
	flex: 1;
	min-width: 0;
}

.launchpad-picker__widget-title {
	display: block;
	font-weight: 500;
}

.launchpad-picker__widget-badge {
	display: block;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.launchpad-picker__widget-add,
.launchpad-picker__widget-check {
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
}

.launchpad-picker__widget-check {
	color: var(--color-success);
}

.tiles-list {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
	gap: 8px;
	padding: 16px;
}

.tile-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 6px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	cursor: pointer;
	transition: background var(--animation-quick) ease;
	position: relative;
}

.tile-item:hover {
	background: var(--color-background-hover);
}

.tile-item__icon {
	font-size: 32px;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.tile-item__icon:not(.tile-item__emoji):not(svg) {
	filter: brightness(0) invert(1);
}

.tile-item__icon img {
	width: 100%;
	height: 100%;
	object-fit: contain;
}

.tile-item__title {
	font-size: 11px;
	font-weight: 600;
	text-align: center;
	word-break: break-word;
	line-height: 1.2;
}

.tile-item__add {
	position: absolute;
	top: 4px;
	right: 4px;
	opacity: 0;
	transition: opacity var(--animation-quick) ease;
}

.tile-item:hover .tile-item__add {
	opacity: 1;
}

.launchpad-picker__dashboard {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	transition: background var(--animation-quick) ease;
}

.launchpad-picker__dashboard:hover {
	background: var(--color-background-hover);
}

.launchpad-picker__dashboard-content {
	display: flex;
	align-items: center;
	gap: 12px;
	flex: 1;
	min-width: 0;
}

.launchpad-picker__dashboard-icon {
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
}

.launchpad-picker__dashboard-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1;
	min-width: 0;
}

.launchpad-picker__dashboard-title {
	font-size: 14px;
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.launchpad-picker__dashboard-badge {
	display: inline-block;
	padding: 2px 8px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-radius: var(--border-radius-pill);
	font-size: 11px;
	font-weight: 600;
	width: fit-content;
}

.launchpad-picker__dashboard-actions {
	display: flex;
	gap: 4px;
	flex-shrink: 0;
}
</style>
