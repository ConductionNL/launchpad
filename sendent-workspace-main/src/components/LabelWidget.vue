<template>
	<div class="label-widget" :style="labelStyle">
		<span class="label-text">{{ labelText }}</span>
	</div>
</template>

<script>
export default {
	name: 'LabelWidget',

	props: {
		widget: {
			type: Object,
			required: true,
		},
		text: {
			type: String,
			default: '',
		},
		fontSize: {
			type: String,
			default: '16px',
		},
		color: {
			type: String,
			default: '',
		},
		backgroundColor: {
			type: String,
			default: '',
		},
		fontWeight: {
			type: String,
			default: 'bold',
		},
		textAlign: {
			type: String,
			default: 'center',
		},
	},

	computed: {
		labelText() {
			return this.text || this.widget?.content?.text || this.t('sendentworkspace', 'Label')
		},

		labelStyle() {
			return {
				fontSize: this.fontSize || this.widget?.content?.fontSize || '16px',
				color: this.color || this.widget?.content?.color || 'var(--color-main-text)',
				backgroundColor: this.backgroundColor || this.widget?.content?.backgroundColor || 'transparent',
				fontWeight: this.fontWeight || this.widget?.content?.fontWeight || 'bold',
				textAlign: this.textAlign || this.widget?.content?.textAlign || 'center',
			}
		},
	},
}
</script>

<style scoped lang="scss">
.label-widget {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
}

.label-text {
  overflow-wrap: break-word;
  text-align: inherit;
}
</style>
