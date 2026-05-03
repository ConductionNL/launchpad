<template>
	<div class="image-widget" @click="handleClick">
		<img
			v-if="imageUrl"
			:src="imageUrl"
			:alt="altText"
			:style="imageStyle"
			class="widget-image">
		<div v-else class="placeholder">
			<CameraIcon :size="48" />
			<span>{{ t('sendentworkspace', 'No image') }}</span>
		</div>
	</div>
</template>

<script>
import CameraIcon from 'vue-material-design-icons/Camera.vue'

export default {
	name: 'ImageWidget',

	components: {
		CameraIcon,
	},

	props: {
		widget: {
			type: Object,
			required: true,
		},
		url: {
			type: String,
			default: '',
		},
		alt: {
			type: String,
			default: '',
		},
		link: {
			type: String,
			default: '',
		},
		fit: {
			type: String,
			default: 'cover',
			validator: (value) => ['cover', 'contain', 'fill', 'none'].includes(value),
		},
	},

	computed: {
		imageUrl() {
			return this.url || this.widget?.content?.url || ''
		},

		altText() {
			return this.alt || this.widget?.content?.alt || this.t('sendentworkspace', 'Image')
		},

		linkUrl() {
			return this.link || this.widget?.content?.link || ''
		},

		imageStyle() {
			const fit = this.fit || this.widget?.content?.fit || 'cover'
			return {
				objectFit: fit,
			}
		},
	},

	methods: {
		handleClick() {
			if (this.linkUrl) {
				window.open(this.linkUrl, '_blank', 'noopener,noreferrer')
			}
		},
	},
}
</script>

<style scoped lang="scss">
.image-widget {
  width: 100%;
  height: 100%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-background-dark);
  cursor: pointer;
}

.widget-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: var(--color-text-maxcontrast);
  font-size: 14px;

  .camera-icon {
    color: var(--color-text-maxcontrast);
  }
}
</style>
