<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div v-if="resolving" class="video-host__loading">
		<NcLoadingIcon :size="32" />
	</div>
	<CnVideoWidget v-else :content="resolvedContent" />
</template>

<script>
import { CnVideoWidget, NcLoadingIcon } from '@conduction/nextcloud-vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'

/**
 * VideoHost — LaunchPad wrapper around CnVideoWidget. A Nextcloud Files
 * source (`sourceType: 'nc-file'`) is resolved at render time through the
 * ACL-checked `GET /api/widgets/video/{placementId}/file`, for the viewer:
 * a readable video gets its streaming URL, a deleted file and a non-video
 * get their message, and anything else leaves the URL empty so the widget
 * says "Video not accessible". Hosted players pass straight through.
 *
 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-006-check-file-access-control-for-nextcloud-files
 */
export default {
	name: 'VideoHost',

	components: { CnVideoWidget, NcLoadingIcon },

	props: {
		/** The placement's content blob. */
		content: OBJECT_PROP,

		/** The placement record; its id addresses the file endpoint. */
		placement: OBJECT_PROP,
	},

	data() {
		return {
			resolving: false,
			resolved: null,
		}
	},

	computed: {
		/**
		 * Whether this placement plays a Nextcloud file.
		 *
		 * @return {boolean}
		 */
		isFileSource() {
			return this.content.sourceType === 'nc-file'
				&& this.content.fileId !== null
				&& this.content.fileId !== undefined
				&& this.content.fileId !== ''
		},

		/**
		 * The content handed to CnVideoWidget: the stored content, plus the
		 * streaming URL or the message for a file source.
		 *
		 * @return {object}
		 */
		resolvedContent() {
			if (!this.isFileSource) {
				return this.content
			}
			return { ...this.content, fileStreamingUrl: '', ...(this.resolved || {}) }
		},
	},

	watch: {
		'content.fileId': function() {
			this.resolve()
		},
	},

	mounted() {
		this.resolve()
	},

	methods: {
		/**
		 * Ask the file endpoint what this viewer may play.
		 *
		 * @return {Promise<void>}
		 */
		async resolve() {
			this.resolved = null
			if (!this.isFileSource || !this.placement?.id) {
				return
			}
			this.resolving = true
			try {
				const url = generateUrl('/apps/launchpad/api/widgets/video/{placementId}/file', {
					placementId: this.placement.id,
				})
				const response = await axios.get(url)
				this.resolved = { fileStreamingUrl: String(response?.data?.streamingUrl || '') }
			} catch (error) {
				this.resolved = this.refusalOf(error?.response?.data?.error)
			} finally {
				this.resolving = false
			}
		},

		/**
		 * The content patch for a refused file: its message, or an empty URL
		 * (CnVideoWidget's "Video not accessible").
		 *
		 * @param {string|undefined} code The endpoint's error code.
		 * @return {object}
		 */
		refusalOf(code) {
			if (code === 'not_found') {
				return { error: t('launchpad', 'Video file not found') }
			}
			if (code === 'not_video') {
				return { error: t('launchpad', 'File is not a video') }
			}
			return { fileStreamingUrl: '' }
		},
	},
}
</script>

<style scoped>
.video-host__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
}
</style>
