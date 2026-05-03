<template>
	<div class="workspace-editor">
		<!-- Toolbar -->
		<div class="editor-toolbar">
			<div class="add-widget-dropdown">
				<button
					class="add-widget-button"
					@click="showAddDropdown = !showAddDropdown">
					<PlusIcon :size="16" /> {{ t('sendentworkspace', 'Add Widget') }}
					<ChevronDownIcon :size="16" class="dropdown-arrow" :class="{ 'rotated': showAddDropdown }" />
				</button>
				<div v-if="showAddDropdown" class="dropdown-menu" @click.stop>
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
		</div>

		<!-- Grid Container -->
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
						:is-admin="true" />
				</div>
			</div>
		</div>

		<!-- Add Widget Modal -->
		<AddWidgetModal
			:show="showModal"
			:widgets="widgets"
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
	</div>
</template>

<script>
import { ref, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'

import TextDisplayWidget from './TextDisplayWidget.vue'
import ImageWidget from './ImageWidget.vue'
import LinkButtonWidget from './LinkButtonWidget.vue'
import LabelWidget from './LabelWidget.vue'
import ApiWidget from './ApiWidget.vue'
import AddWidgetModal from './AddWidgetModal.vue'
import ContextMenu from './ContextMenu.vue'
import { useGridManager } from '../composables/useGridManager.js'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'WorkspaceEditor',

	components: {
		TextDisplayWidget,
		ImageWidget,
		LinkButtonWidget,
		LabelWidget,
		ApiWidget,
		AddWidgetModal,
		ContextMenu,
		PlusIcon,
		ChevronDownIcon,
	},

	props: {
		groupId: {
			type: String,
			required: true,
		},
		initialLayout: {
			type: Array,
			default: () => [],
		},
		widgets: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['layout-changed'],

	setup(props, { emit }) {
		const layout = ref([...props.initialLayout])
		const gridContainer = ref(null)

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
		} = useGridManager({
			layout,
			gridContainer,
			isAdmin: true,
			onLayoutChanged: (newLayout) => emit('layout-changed', newLayout),
		})

		watch(() => props.initialLayout, (newLayout) => {
			layout.value = [...newLayout]
		}, { deep: true })

		onMounted(async () => {
			await nextTick()
			initGrid()
			document.addEventListener('click', handleClickOutside)
		})

		onBeforeUnmount(() => {
			document.removeEventListener('click', handleClickOutside)
			destroyGrid()
		})

		const getItemContentClass = (widget) => ({
			'widget-text': widget.type === 'text',
			'widget-image': widget.type === 'image',
			'widget-link': widget.type === 'link',
			'widget-label': widget.type === 'label',
			'widget-api': widget.type === 'widget',
		})

		return {
			layout,
			gridContainer,
			getItemContentClass,
			showModal,
			showAddDropdown,
			preselectedType,
			showContextMenu,
			contextMenuX,
			contextMenuY,
			selectedWidget,
			editingWidgetData,
			getWidgetComponent,
			getWidgetProps,
			openAddWidgetModal,
			closeModal,
			handleWidgetSubmit,
			onWidgetRightClick,
			closeContextMenu,
			editWidget,
			removeWidget,
		}
	},
}
</script>

<style scoped lang="scss">
.workspace-editor {
  flex: 1;
  display: flex;
  flex-direction: column;
  background-color: var(--color-main-background);
}

.editor-toolbar {
  padding: 12px;
  border-bottom: 1px solid var(--color-border);
  background-color: var(--color-background-dark);
}

.add-widget-dropdown {
  position: relative;
  display: inline-block;
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
  inset-inline-start: 0;
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

.grid-stack {
  flex: 1;
  padding: 16px;
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
</style>
