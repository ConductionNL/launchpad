<template>
	<div class="api-widget">
		<div class="widget-header">
			<img
				v-if="widgetIconUrl"
				:src="widgetIconUrl"
				:alt="widgetTitle"
				class="widget-icon">
			<span v-else-if="widgetIconClass" class="widget-icon-class" :class="widgetIconClass" />
			<h4>
				{{ widgetTitle }}
			</h4>
		</div>
		<div class="widget-content">
			<!-- Container for app-rendered widget (via OCA.Dashboard.register callback) -->
			<div v-if="usesRegisteredCallback" ref="appContainer" class="app-container" />

			<!-- Fallback: API-based item list rendering -->
			<template v-else>
				<div v-if="loading" class="loading">
					{{ t('sendentworkspace', 'Loading…') }}
				</div>
				<div v-else-if="items.length === 0" class="empty">
					{{ t('sendentworkspace', 'No items available') }}
				</div>
				<div v-else class="items-list" :class="'mode-' + displayMode">
					<a
						v-for="(item, index) in items"
						:key="index"
						:href="item.link"
						target="_blank"
						rel="noopener noreferrer"
						class="item">
						<img v-if="item.iconUrl"
							:src="item.iconUrl"
							:alt="item.title"
							class="item-icon">
						<div class="item-content">
							<div class="item-title">
								{{ item.title }}
							</div>
							<div v-if="item.subtitle" class="item-subtitle">
								{{ item.subtitle }}
							</div>
						</div>
					</a>
				</div>
			</template>
		</div>
	</div>
</template>

<script>
import { inject } from 'vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

const CALLBACK_POLL_INTERVAL = 200
const CALLBACK_MAX_RETRIES = 15

export default {
	name: 'ApiWidget',

	props: {
		widget: {
			type: Object,
			required: true,
		},
		widgetId: {
			type: String,
			default: '',
		},
		displayMode: {
			type: String,
			default: 'vertical',
		},
	},

	setup() {
		const injected = inject('widgets', [])
		// Ensure it's always an array (PHP may encode as object if keys are non-sequential)
		const availableWidgets = Array.isArray(injected) ? injected : Object.values(injected)
		return { t, availableWidgets }
	},

	data() {
		return {
			loading: true,
			items: [],
			usesRegisteredCallback: false,
			pollTimer: null,
		}
	},

	computed: {
		actualWidgetId() {
			return this.widgetId || this.widget?.content?.widgetId || ''
		},

		widgetMeta() {
			return this.availableWidgets.find(w => w.id === this.actualWidgetId)
		},

		widgetTitle() {
			return this.widgetMeta?.title || this.actualWidgetId || this.t('sendentworkspace', 'Widget')
		},

		widgetIconUrl() {
			return this.widgetMeta?.iconUrl || ''
		},

		widgetIconClass() {
			return this.widgetMeta?.iconClass || ''
		},
	},

	mounted() {
		if (!this.actualWidgetId) {
			this.loading = false
			return
		}

		// Try registered callback immediately (instant check)
		if (this.tryUseCallback()) return

		// No callback yet — start API loading immediately
		this.loadWidgetItems()

		// Brief background poll: if a callback registers later (widget script
		// still loading), switch to the widget's native UI automatically.
		let retries = 0
		this.pollTimer = setInterval(() => {
			retries++
			if (this.tryUseCallback() || retries >= CALLBACK_MAX_RETRIES) {
				clearInterval(this.pollTimer)
				this.pollTimer = null
			}
		}, CALLBACK_POLL_INTERVAL)
	},

	beforeUnmount() {
		if (this.pollTimer) {
			clearInterval(this.pollTimer)
			this.pollTimer = null
		}
	},

	methods: {
		tryUseCallback() {
			const registry = window._sendentDashboardRegistry || {}
			const registration = registry[this.actualWidgetId]
			if (registration?.callback) {
				this.usesRegisteredCallback = true
				this.loading = false
				this.$nextTick(() => {
					if (this.$refs.appContainer) {
						registration.callback(this.$refs.appContainer)
					}
				})
				return true
			}
			return false
		},

		async loadWidgetItems() {
			this.loading = true
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/widget-items')
				const response = await axios.get(url, {
					params: {
						widgets: [this.actualWidgetId],
						limit: 7,
					},
				})

				if (response.data?.ocs?.data?.items?.[this.actualWidgetId]) {
					this.items = response.data.ocs.data.items[this.actualWidgetId]
				}
			} catch (error) {
				console.error('Failed to load widget items:', error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.api-widget {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.widget-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid var(--color-border);

  h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--color-main-text);
  }
}

.widget-icon {
  width: 20px;
  height: 20px;
  object-fit: contain;
  flex-shrink: 0;
}

.widget-icon-class {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
}

.widget-content {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
}

.app-container {
  width: 100%;
  height: 100%;
}

.loading,
.empty {
  text-align: center;
  color: var(--color-text-maxcontrast);
  padding: 20px;
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: 8px;

  // Horizontal card layout
  &.mode-horizontal {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 12px;

    .item {
      flex-direction: column;
      align-items: center;
      text-align: center;
      width: 120px;
      padding: 12px 8px;
      border: 1px solid var(--color-border);
      background-color: var(--color-main-background);
    }

    .item-icon {
      width: 44px;
      height: 44px;
      margin-bottom: 4px;
    }

    .item-content {
      width: 100%;
    }

    .item-title {
      font-size: 12px;
    }

    .item-subtitle {
      font-size: 11px;
    }
  }
}

.item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  border-radius: 6px;
  text-decoration: none;
  color: var(--color-main-text);
  transition: background-color 0.2s;

  &:hover {
    background-color: var(--color-background-hover);
  }
}

.item-icon {
  width: 32px;
  height: 32px;
  border-radius: 4px;
  object-fit: cover;
}

.item-content {
  flex: 1;
  min-width: 0;
}

.item-title {
  font-weight: 500;
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-subtitle {
  font-size: 12px;
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
}
</style>
