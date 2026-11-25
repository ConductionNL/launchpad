<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="image-widget-form">
		<fieldset class="image-widget-form__choices">
			<legend class="image-widget-form__legend">
				{{ t('launchpad', 'Where does the image come from?') }}
			</legend>
			<label
				v-for="card in sourceCards"
				:key="card.value"
				class="image-widget-form__choice"
				:class="{ 'image-widget-form__choice--selected': source === card.value }">
				<input
					type="radio"
					name="image-source"
					class="image-widget-form__choice-input"
					:value="card.value"
					:checked="source === card.value"
					:data-testid="'image-source-' + card.value"
					@change="onSourceChange(card.value)">
				<span class="image-widget-form__choice-title">{{ card.title }}</span>
				<span class="image-widget-form__choice-text">{{ card.text }}</span>
			</label>
		</fieldset>

		<template v-if="source === 'upload'">
			<div v-if="chosen" class="image-widget-form__chosen" data-testid="image-chosen">
				<img
					v-if="previewSrc"
					class="image-widget-form__thumb"
					:src="previewSrc"
					alt="">
				<div class="image-widget-form__chosen-text">
					<span class="image-widget-form__chosen-name">{{ chosen.name }}</span>
					<span class="image-widget-form__chosen-meta">{{ chosenMeta }}</span>
				</div>
				<NcButton variant="tertiary" :disabled="uploading" @click="chooseAnother">
					{{ t('launchpad', 'Choose another') }}
				</NcButton>
			</div>
			<div v-else class="image-widget-form__upload-row">
				<NcButton :disabled="uploading" @click="pickFromFiles">
					{{ t('launchpad', 'Pick from Files') }}
				</NcButton>
				<label class="image-widget-form__upload-label">
					<input
						type="file"
						accept="image/*"
						class="image-widget-form__file-input"
						data-testid="image-file-input"
						:disabled="uploading"
						@change="onFileInput">
					<span class="image-widget-form__upload-button">{{ t('launchpad', 'Upload') }}</span>
				</label>
			</div>
		</template>

		<NcTextField
			v-else
			:modelValue="url"
			:label="t('launchpad', 'Image URL')"
			placeholder="https://"
			@update:modelValue="updateField('url', $event)" />

		<p v-if="fileError" class="image-widget-form__error" role="alert">
			{{ fileError }}
		</p>

		<NcTextField
			:modelValue="alt"
			:label="t('launchpad', 'Text for who does not see the image')"
			:required="true"
			:error="altMissing"
			:helperText="altMissing ? t('launchpad', 'Alternative text is required') : ''"
			@update:modelValue="updateField('alt', $event)" />

		<NcTextField
			:modelValue="link"
			:label="t('launchpad', 'Link (optional)')"
			placeholder="https://"
			@update:modelValue="updateField('link', $event)" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { FilePickerClosed, getFilePickerBuilder } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'
import { describeImageFile, isUploadableImage, MAX_IMAGE_BYTES } from '../../../utils/imageFile.js'

const ALLOWED_FITS = Object.freeze(['cover', 'contain', 'fill', 'none'])

const DEFAULT_CONTENT = Object.freeze({ url: '', alt: '', link: '', fit: 'cover' })

/**
 * A stored image is an upload when it lives on this server (a relative or
 * data URL), else a web address.
 *
 * @param {string} url The stored URL.
 * @return {string} `upload` or `url`.
 */
function sourceOf(url) {
	return typeof url === 'string' && /^https?:\/\//i.test(url) ? 'url' : 'upload'
}

/**
 * ImageWidgetForm — the content part of an Image widget as board
 * LpWidgetInstellen draws it (REQ-IMG-020): Upload file (from Files or the
 * computer, up to 5 MB) or Web address, the chosen file with its name, size
 * and dimensions, the required text for who does not see the image, and an
 * optional link. Stores `{url, alt, link, fit}` as before, so the renderer is
 * unchanged. A picked file is uploaded only when the modal calls `commit()`.
 *
 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
 */
export default {
	name: 'ImageWidgetForm',

	components: { NcButton, NcTextField },

	props: {
		/** The placement being edited, or `null` in create mode. */
		editingWidget: NULLABLE_OBJECT_PROP,

		/** Initial content values, used when not editing. */
		value: objectProp(DEFAULT_CONTENT),

		/**
		 * Upload transport `async (file) => ({ url })`, passed by the modal.
		 * Without one a picked file is embedded as a data URL.
		 */
		fileUploadFn: {
			type: Function,
			default: null,
		},
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		const url = typeof initial.url === 'string' ? initial.url : ''
		return {
			url,
			alt: typeof initial.alt === 'string' ? initial.alt : '',
			link: typeof initial.link === 'string' ? initial.link : '',
			fit: ALLOWED_FITS.includes(initial.fit) ? initial.fit : DEFAULT_CONTENT.fit,
			source: sourceOf(url),
			// The chosen file: {name, size, width, height}; a stored upload
			// shows its file name only.
			chosen: url !== '' && sourceOf(url) === 'upload' ? { name: url.split('/').pop().split('?')[0] || url, size: null, width: null, height: null } : null,
			pendingFile: null,
			pendingPreviewUrl: '',
			fileError: '',
			uploading: false,
			altTouched: false,
		}
	},

	computed: {
		/**
		 * The two choice cards.
		 *
		 * @return {Array<{value: string, title: string, text: string}>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		sourceCards() {
			return [
				{
					value: 'upload',
					title: t('launchpad', 'Upload file'),
					text: t('launchpad', 'From Files or your computer, up to 5 MB'),
				},
				{
					value: 'url',
					title: t('launchpad', 'Web address'),
					text: t('launchpad', 'An image on another site'),
				},
			]
		},

		/**
		 * The thumbnail: the picked file, else the stored upload.
		 *
		 * @return {string}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		previewSrc() {
			return this.pendingPreviewUrl || this.url
		},

		/**
		 * Size and dimensions of the chosen file.
		 *
		 * @return {string}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		chosenMeta() {
			return describeImageFile(this.chosen)
		},

		/**
		 * Whether the required alternative text was emptied by the editor.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		altMissing() {
			return this.altTouched && this.alt.trim() === ''
		},

		/**
		 * The stored content.
		 *
		 * @return {{url: string, alt: string, link: string, fit: string}}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		assembledContent() {
			return { url: this.url, alt: this.alt, link: this.link, fit: this.fit }
		},
	},

	beforeUnmount() {
		this.revokePreview()
	},

	methods: {
		t,

		/**
		 * Set one field and emit the content.
		 *
		 * @param {string} field url, alt or link.
		 * @param {string} val The value.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		updateField(field, val) {
			if (field === 'alt') {
				this.altTouched = true
			}
			this[field] = typeof val === 'string' ? val : ''
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Switch between upload and web address. Switching drops what the
		 * other source held, so a stale upload never saves under a web address.
		 *
		 * @param {string} val `upload` or `url`.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		onSourceChange(val) {
			if (val === this.source) {
				return
			}
			this.source = val === 'url' ? 'url' : 'upload'
			this.chooseAnother()
			this.updateField('url', '')
		},

		/**
		 * A file from the computer.
		 *
		 * @param {Event} event The input's change event.
		 * @return {Promise<void>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		async onFileInput(event) {
			const file = event?.target?.files?.[0]
			if (event?.target) {
				event.target.value = ''
			}
			if (file) {
				await this.holdFile(file)
			}
		},

		/**
		 * A file from Files: read with the editor's session, then held like a
		 * file from the computer, so readers never need access to it.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		async pickFromFiles() {
			const picker = getFilePickerBuilder(t('launchpad', 'Pick from Files'))
				.setMultiSelect(false)
				.setMimeTypeFilter(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'])
				.addButton({ label: t('launchpad', 'Choose'), type: 'primary', callback: () => {} })
				.build()
			let node
			try {
				const nodes = await picker.pickNodes()
				node = Array.isArray(nodes) ? nodes[0] : nodes
			} catch (e) {
				if (!(e instanceof FilePickerClosed)) {
					this.fileError = t('launchpad', 'The file could not be read')
				}
				return
			}
			if (!node?.source) {
				return
			}
			try {
				const response = await axios.get(node.source, { responseType: 'blob' })
				const file = new File([response.data], node.basename || 'image', { type: node.mime || response.data?.type || '' })
				await this.holdFile(file)
			} catch {
				this.fileError = t('launchpad', 'The file could not be read')
			}
		},

		/**
		 * Hold a picked file for upload on save, after the type and size check.
		 *
		 * @param {File} file The file.
		 * @return {Promise<void>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		async holdFile(file) {
			const problem = isUploadableImage(file)
			if (problem === 'type') {
				this.fileError = t('launchpad', 'Choose an image file')
				return
			}
			if (problem === 'size') {
				this.fileError = t('launchpad', 'The file is larger than 5 MB')
				return
			}
			this.fileError = ''
			this.revokePreview()
			this.pendingFile = file
			this.pendingPreviewUrl = typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : ''
			this.chosen = { name: file.name, size: file.size, width: null, height: null }
			this.measure(this.pendingPreviewUrl)
		},

		/**
		 * Read the dimensions of the held image.
		 *
		 * @param {string} src The object URL.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		measure(src) {
			if (!src || typeof Image === 'undefined') {
				return
			}
			const image = new Image()
			image.onload = () => {
				if (this.chosen && this.pendingPreviewUrl === src) {
					this.chosen = { ...this.chosen, width: image.naturalWidth, height: image.naturalHeight }
				}
			}
			image.src = src
		},

		/**
		 * Drop the chosen file so another can be picked.
		 *
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		chooseAnother() {
			this.revokePreview()
			this.pendingFile = null
			this.chosen = null
			this.fileError = ''
			if (this.source === 'upload' && this.url !== '') {
				this.updateField('url', '')
			}
		},

		/** Free the object URL of the held file. */
		revokePreview() {
			if (this.pendingPreviewUrl && typeof URL.revokeObjectURL === 'function') {
				URL.revokeObjectURL(this.pendingPreviewUrl)
			}
			this.pendingPreviewUrl = ''
		},

		/**
		 * Upload the held file and store its URL; called by the modal on submit.
		 *
		 * @return {Promise<void>}
		 * @throws {Error} when the upload fails, so the modal keeps the dialog open.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		async commit() {
			if (this.pendingFile === null) {
				return
			}
			this.uploading = true
			try {
				let url
				if (typeof this.fileUploadFn === 'function') {
					const result = await this.fileUploadFn(this.pendingFile)
					url = typeof result === 'string' ? result : result?.url
				} else {
					url = await readAsDataUrl(this.pendingFile)
				}
				if (typeof url !== 'string' || url === '') {
					throw new Error(t('launchpad', 'The image could not be uploaded'))
				}
				this.pendingFile = null
				this.updateField('url', url)
			} catch (err) {
				this.fileError = t('launchpad', 'The image could not be uploaded')
				throw err
			} finally {
				this.uploading = false
			}
		},

		/**
		 * The form's errors; the alternative text is named when missing. Pure:
		 * the modal calls it from a computed on every input.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md#requirement-the-image-content-form-req-img-020
		 */
		validate() {
			const errors = []
			if (this.url.trim() === '' && this.pendingFile === null) {
				errors.push(t('launchpad', 'Choose an image or enter its web address'))
			}
			if (this.alt.trim() === '') {
				errors.push(t('launchpad', 'Alternative text is required'))
			}
			return errors
		},
	},
}

/**
 * Read a file as a data URL, for hosts that pass no upload transport.
 *
 * @param {File} file The file.
 * @return {Promise<string>} the data URL.
 */
function readAsDataUrl(file) {
	if (file.size > MAX_IMAGE_BYTES) {
		return Promise.reject(new Error('too large'))
	}
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.onload = () => resolve(String(reader.result))
		reader.onerror = () => reject(reader.error)
		reader.readAsDataURL(file)
	})
}
</script>

<style scoped>
.image-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.image-widget-form__choices {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	border: 0;
	margin: 0;
	padding: 0;
}

.image-widget-form__legend {
	width: 100%;
	margin-bottom: 4px;
	font-weight: 600;
}

.image-widget-form__choice {
	position: relative;
	display: flex;
	flex: 1 1 200px;
	flex-direction: column;
	gap: 2px;
	padding: 10px 12px 10px 36px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	cursor: pointer;
}

.image-widget-form__choice--selected {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.image-widget-form__choice-input {
	position: absolute;
	top: 12px;
	left: 12px;
	margin: 0;
}

.image-widget-form__choice-title {
	font-weight: 600;
}

.image-widget-form__choice-text,
.image-widget-form__chosen-meta {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.image-widget-form__upload-row,
.image-widget-form__chosen {
	display: flex;
	gap: 12px;
	align-items: center;
}

.image-widget-form__chosen {
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.image-widget-form__chosen-text {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-width: 0;
}

.image-widget-form__chosen-name {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.image-widget-form__thumb {
	width: 48px;
	height: 48px;
	object-fit: cover;
	border-radius: var(--border-radius);
}

.image-widget-form__file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
}

.image-widget-form__upload-label {
	position: relative;
	cursor: pointer;
}

.image-widget-form__upload-button {
	display: inline-block;
	padding: 8px 16px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-element, var(--border-radius-pill));
}

.image-widget-form__upload-label:focus-within .image-widget-form__upload-button {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.image-widget-form__error {
	margin: 0;
	color: var(--color-error-text, var(--color-error));
}
</style>
