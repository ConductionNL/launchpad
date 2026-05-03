<template>
	<div class="text-display-widget" :style="textStyle">
		<!-- eslint-disable-next-line vue/no-v-html -- content is sanitized with DOMPurify -->
		<div v-if="content" v-html="sanitizedContent" />
		<div v-else class="placeholder">
			{{ t('sendentworkspace', 'No text content') }}
		</div>
	</div>
</template>

<script>
import DOMPurify from 'dompurify'

export default {
	name: 'TextDisplayWidget',

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
			default: '14px',
		},
		color: {
			type: String,
			default: '',
		},
		backgroundColor: {
			type: String,
			default: '',
		},
		textAlign: {
			type: String,
			default: 'left',
		},
	},

	computed: {
		content() {
			return this.text || this.widget?.content?.text || ''
		},

		sanitizedContent() {
			return DOMPurify.sanitize(this.content)
		},

		textStyle() {
			return {
				fontSize: this.fontSize || this.widget?.content?.fontSize || '14px',
				color: this.color || this.widget?.content?.color || 'var(--color-main-text)',
				backgroundColor: this.backgroundColor || this.widget?.content?.backgroundColor || 'transparent',
				textAlign: this.textAlign || this.widget?.content?.textAlign || 'left',
			}
		},
	},
}
</script>

<style scoped lang="scss">
.text-display-widget {
  padding: 16px;
  height: 100%;
  width: 100%;
  overflow: auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.placeholder {
  color: var(--color-text-maxcontrast);
  font-style: italic;
}
</style>
