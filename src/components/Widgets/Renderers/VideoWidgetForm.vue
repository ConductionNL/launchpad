<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<CnVideoWidgetForm
		ref="form"
		:editingWidget="editingWidget"
		:value="value"
		:urlError="urlError"
		@update:content="onContent" />
</template>

<script>
import { CnVideoWidgetForm } from '@conduction/nextcloud-vue'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

/** How long the URL must stay unchanged before it is sent to the parse endpoint. */
const PARSE_DELAY_MS = 300

/**
 * The localised text of a parse endpoint error (VideoParsingService).
 *
 * @param {string} error The endpoint's error.
 * @return {string}
 */
function parseErrorLabel(error) {
	if (error === 'Invalid URL format') {
		return t('launchpad', 'Invalid URL format')
	}
	if (error === 'Unsupported video source') {
		return t('launchpad', 'Unsupported video source')
	}
	return error
}

/**
 * The video widget form with the server's verdict on the URL: each URL the
 * user types is sent to `POST /api/widgets/video/parse`, and its error is
 * shown under the field and blocks saving (REQ-VID-005). A Nextcloud file
 * source is not parsed.
 *
 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
 */
export default {
	name: 'VideoWidgetForm',

	components: { CnVideoWidgetForm },

	props: {
		/** The placement being edited, or `null` in create mode. */
		editingWidget: {
			type: Object,
			default: null,
		},

		/** Initial content values. */
		value: {
			type: Object,
			default: () => ({}),
		},
	},

	emits: ['update:content'],

	data() {
		return {
			urlError: '',
			lastContent: null,
		}
	},

	computed: {
		/**
		 * The content the dialog stores, read from the shared form.
		 *
		 * @return {object|undefined}
		 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
		 */
		assembledContent() {
			// Read again on each change: `$refs` alone is not reactive.
			void this.lastContent
			return this.$refs.form?.assembledContent
		},
	},

	beforeUnmount() {
		clearTimeout(this.parseTimer)
	},

	methods: {
		/**
		 * The shared form's errors plus the parse endpoint's.
		 *
		 * @return {string[]}
		 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
		 */
		validate() {
			return this.$refs.form ? this.$refs.form.validate() : []
		},

		/**
		 * Pass a change up, and parse a changed URL once typing pauses.
		 *
		 * @param {object} content The assembled content.
		 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
		 * @return {void}
		 */
		onContent(content) {
			const urlChanged = content?.videoUrl !== this.lastContent?.videoUrl
			this.lastContent = content
			if (urlChanged) {
				this.urlError = ''
				clearTimeout(this.parseTimer)
				const url = String(content?.videoUrl || '').trim()
				if (url !== '' && content?.sourceType !== 'nc-file') {
					this.parseTimer = setTimeout(() => this.parse(url), PARSE_DELAY_MS)
				}
			}
			this.$emit('update:content', content)
		},

		/**
		 * Ask the parse endpoint about a URL. Only the answer for the URL still
		 * in the field counts; a failed request leaves the form's own check.
		 *
		 * @param {string} url The URL.
		 * @return {Promise<void>}
		 * @spec openspec/specs/video-widget/spec.md#requirement-req-vid-005-parse-and-validate-video-urls-server-side
		 */
		async parse(url) {
			let error = ''
			try {
				await axios.post(generateUrl('/apps/launchpad/api/widgets/video/parse'), { videoUrl: url })
			} catch (failure) {
				const data = failure?.response?.data
				error = data && data.isValid === false && typeof data.error === 'string' ? parseErrorLabel(data.error) : ''
			}
			if (String(this.lastContent?.videoUrl || '').trim() !== url) {
				return
			}
			this.urlError = error
			// The dialog re-runs validate() on every content update.
			this.$emit('update:content', this.lastContent)
		},
	},
}
</script>
