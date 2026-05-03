<template>
	<div
		v-if="show"
		class="context-menu"
		:style="{ top: y + 'px', left: x + 'px' }"
		@click.stop>
		<button class="context-menu-item" @click="$emit('edit', widget)">
			<PencilIcon :size="16" /> {{ t('sendentworkspace', 'Edit') }}
		</button>
		<button class="context-menu-item danger" @click="$emit('remove', widget)">
			<DeleteIcon :size="16" /> {{ t('sendentworkspace', 'Remove') }}
		</button>
		<button class="context-menu-item" @click="$emit('close')">
			{{ t('sendentworkspace', 'Cancel') }}
		</button>
	</div>
</template>

<script>
import PencilIcon from 'vue-material-design-icons/Pencil.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'ContextMenu',

	components: {
		PencilIcon,
		DeleteIcon,
	},

	props: {
		show: {
			type: Boolean,
			default: false,
		},
		x: {
			type: Number,
			default: 0,
		},
		y: {
			type: Number,
			default: 0,
		},
		widget: {
			type: Object,
			default: null,
		},
	},

	emits: ['edit', 'remove', 'close'],
}
</script>

<style scoped lang="scss">
.context-menu {
  position: fixed;
  background-color: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  min-width: 150px;
  z-index: 10000;
  overflow: hidden;
}

.context-menu-item {
  display: block;
  width: 100%;
  padding: 10px 16px;
  background: none;
  border: none;
  text-align: start;
  cursor: pointer;
  transition: background-color 0.2s;
  font-size: 14px;
  color: var(--color-main-text);

  &:hover {
    background-color: var(--color-background-hover);
  }

  &.danger {
    color: var(--color-error);

    &:hover {
      background-color: var(--color-error);
      color: white;
    }
  }
}
</style>
