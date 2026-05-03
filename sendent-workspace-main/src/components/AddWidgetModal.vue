<template>
	<div v-if="show" class="modal">
		<div class="modal-backdrop" @click="$emit('close')" />
		<div class="modal-content">
			<h3>{{ editMode ? t('sendentworkspace', 'Edit Widget') : t('sendentworkspace', 'Add Widget') }}</h3>

			<!-- Type Selection -->
			<div v-if="!preselectedType" class="input-block">
				<label>{{ t('sendentworkspace', 'Type') }}:</label>
				<select v-model="form.type">
					<option value="text">
						{{ t('sendentworkspace', 'Text') }}
					</option>
					<option value="image">
						{{ t('sendentworkspace', 'Image') }}
					</option>
					<option value="link">
						{{ t('sendentworkspace', 'Link Button') }}
					</option>
					<option value="label">
						{{ t('sendentworkspace', 'Label') }}
					</option>
					<option value="widget">
						{{ t('sendentworkspace', 'Nextcloud Widget') }}
					</option>
				</select>
			</div>

			<!-- Text Widget Form -->
			<div v-if="form.type === 'text'" class="form-section">
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Content') }}:</label>
					<textarea v-model="form.text" :placeholder="t('sendentworkspace', 'Enter text content')" rows="4" />
				</div>
				<div class="input-row">
					<div class="input-block">
						<label>{{ t('sendentworkspace', 'Font Size') }}:</label>
						<input v-model="form.fontSize" type="text" placeholder="14px">
					</div>
					<div class="input-block">
						<label>{{ t('sendentworkspace', 'Text Color') }}:</label>
						<input v-model="form.color" type="color">
					</div>
				</div>
			</div>

			<!-- Image Widget Form -->
			<div v-else-if="form.type === 'image'" class="form-section">
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Upload Image') }}:</label>
					<input ref="imageUpload"
						type="file"
						accept="image/*"
						@change="handleImageUpload">
					<div v-if="uploading" class="upload-status">
						{{ t('sendentworkspace', 'Uploading…') }}
					</div>
					<div v-if="uploadError" class="error-message">
						{{ uploadError }}
					</div>
				</div>
				<div v-if="form.url" class="image-preview">
					<img :src="form.url" alt="Preview">
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Or enter Image URL') }}:</label>
					<input v-model="form.url" type="text" placeholder="https://...">
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Alt Text') }}:</label>
					<input v-model="form.alt" type="text" :placeholder="t('sendentworkspace', 'Image description')">
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Link (optional)') }}:</label>
					<input v-model="form.link" type="text" placeholder="https://...">
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Fit') }}:</label>
					<select v-model="form.fit">
						<option value="cover">
							{{ t('sendentworkspace', 'Cover') }}
						</option>
						<option value="contain">
							{{ t('sendentworkspace', 'Contain') }}
						</option>
						<option value="fill">
							{{ t('sendentworkspace', 'Fill') }}
						</option>
					</select>
				</div>
			</div>

			<!-- Link Button Form -->
			<div v-else-if="form.type === 'link'" class="form-section">
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Label') }}:</label>
					<input v-model="form.label" type="text" :placeholder="t('sendentworkspace', 'Button text')">
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Action Type') }}:</label>
					<select v-model="form.actionType">
						<option value="external">
							{{ t('sendentworkspace', 'External Link') }}
						</option>
						<option value="internal">
							{{ t('sendentworkspace', 'Internal Function') }}
						</option>
						<option value="createFile">
							{{ t('sendentworkspace', 'Create File') }}
						</option>
					</select>
				</div>
				<div class="input-block">
					<label>{{ form.actionType === 'createFile' ? t('sendentworkspace', 'File Extension:') : t('sendentworkspace', 'URL:') }}</label>
					<input v-model="form.url" type="text" :placeholder="form.actionType === 'createFile' ? 'docx' : 'https://...'">
				</div>
				<div class="input-row">
					<div class="input-block">
						<label>{{ t('sendentworkspace', 'Background Color') }}:</label>
						<input v-model="form.backgroundColor" type="color">
					</div>
					<div class="input-block">
						<label>{{ t('sendentworkspace', 'Text Color') }}:</label>
						<input v-model="form.textColor" type="color">
					</div>
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Upload Icon (optional)') }}:</label>
					<input ref="iconUpload"
						type="file"
						accept="image/*"
						@change="handleIconUpload">
					<div v-if="uploadingIcon" class="upload-status">
						{{ t('sendentworkspace', 'Uploading…') }}
					</div>
				</div>
				<div v-if="form.icon" class="icon-preview">
					<img :src="form.icon" alt="Icon preview">
				</div>
			</div>

			<!-- Label Widget Form -->
			<div v-else-if="form.type === 'label'" class="form-section">
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Text') }}:</label>
					<input v-model="form.text" type="text" :placeholder="t('sendentworkspace', 'Enter label text')">
				</div>
				<div class="input-row">
					<div class="input-block">
						<label>{{ t('sendentworkspace', 'Font Size') }}:</label>
						<input v-model="form.fontSize" type="text" placeholder="16px">
					</div>
					<div class="input-block">
						<label>{{ t('sendentworkspace', 'Text Color') }}:</label>
						<input v-model="form.color" type="color">
					</div>
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Background Color') }}:</label>
					<input v-model="form.backgroundColor" type="color">
				</div>
			</div>

			<!-- Nextcloud Widget Form -->
			<div v-else-if="form.type === 'widget'" class="form-section">
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Select Widget') }}:</label>
					<select v-model="form.widgetId">
						<option value="">
							{{ t('sendentworkspace', 'Choose a widget…') }}
						</option>
						<option v-for="widget in widgets" :key="widget.id" :value="widget.id">
							{{ widget.title }}
						</option>
					</select>
				</div>
				<div class="input-block">
					<label>{{ t('sendentworkspace', 'Display Mode') }}:</label>
					<select v-model="form.displayMode">
						<option value="vertical">
							{{ t('sendentworkspace', 'Vertical (list)') }}
						</option>
						<option value="horizontal">
							{{ t('sendentworkspace', 'Horizontal (cards)') }}
						</option>
					</select>
				</div>
			</div>

			<!-- Actions -->
			<div class="actions">
				<button class="btn-secondary" @click="$emit('close')">
					{{ t('sendentworkspace', 'Cancel') }}
				</button>
				<button class="btn-primary" :disabled="!isFormValid" @click="submit">
					{{ editMode ? t('sendentworkspace', 'Save') : t('sendentworkspace', 'Add') }}
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'AddWidgetModal',

	props: {
		show: {
			type: Boolean,
			default: false,
		},
		widgets: {
			type: Array,
			default: () => [],
		},
		preselectedType: {
			type: String,
			default: null,
		},
		editingWidget: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'submit'],

	data() {
		return {
			uploading: false,
			uploadingIcon: false,
			uploadError: null,
			form: {
				type: this.preselectedType || 'text',
				// Text/Label fields
				text: '',
				fontSize: '14px',
				color: '#000000',
				backgroundColor: '',
				fontWeight: 'normal',
				textAlign: 'left',
				// Image fields
				url: '',
				alt: '',
				link: '',
				fit: 'cover',
				// Link button fields
				label: '',
				actionType: 'external',
				icon: '',
				textColor: '#ffffff',
				// Widget fields
				widgetId: '',
				displayMode: 'vertical',
			},
		}
	},

	computed: {
		editMode() {
			return !!this.editingWidget
		},

		isFormValid() {
			if (this.form.type === 'text') {
				return !!this.form.text
			} else if (this.form.type === 'image') {
				return !!this.form.url
			} else if (this.form.type === 'link') {
				return !!this.form.label && !!this.form.url
			} else if (this.form.type === 'label') {
				return !!this.form.text
			} else if (this.form.type === 'widget') {
				return !!this.form.widgetId
			}
			return false
		},
	},

	watch: {
		show(newVal) {
			if (newVal) {
				this.resetForm()
				if (this.preselectedType) {
					this.form.type = this.preselectedType
				}
				if (this.editingWidget) {
					this.loadEditingWidget()
				}
			}
		},
	},

	methods: {
		resetForm() {
			this.form = {
				type: this.preselectedType || 'text',
				text: '',
				fontSize: '14px',
				color: '#000000',
				backgroundColor: '',
				fontWeight: 'normal',
				textAlign: 'left',
				url: '',
				alt: '',
				link: '',
				fit: 'cover',
				label: '',
				actionType: 'external',
				icon: '',
				textColor: '#ffffff',
				widgetId: '',
				displayMode: 'vertical',
			}
		},

		loadEditingWidget() {
			if (!this.editingWidget) return

			this.form.type = this.editingWidget.type

			// Load content based on type
			if (this.editingWidget.content) {
				Object.assign(this.form, this.editingWidget.content)
			}
		},

		async handleImageUpload(event) {
			const file = event.target.files[0]
			if (!file) return

			this.uploading = true
			this.uploadError = null

			try {
				const url = await this.uploadFile(file)
				this.form.url = url
			} catch (error) {
				console.error('Failed to upload image:', error)
				this.uploadError = this.t('sendentworkspace', 'Failed to upload image. Please try again.')
			} finally {
				this.uploading = false
			}
		},

		async handleIconUpload(event) {
			const file = event.target.files[0]
			if (!file) return

			this.uploadingIcon = true

			try {
				const url = await this.uploadFile(file)
				this.form.icon = url
			} catch (error) {
				console.error('Failed to upload icon:', error)
			} finally {
				this.uploadingIcon = false
			}
		},

		async uploadFile(file) {
			const dataUrl = await new Promise((resolve, reject) => {
				const reader = new FileReader()
				reader.onload = () => resolve(reader.result)
				reader.onerror = () => reject(reader.error)
				reader.readAsDataURL(file)
			})

			const url = generateOcsUrl('/apps/sendentworkspace/api/v1/upload-resource')
			const response = await axios.post(url, { base64: dataUrl })

			if (response.data?.ocs?.data?.url) {
				return response.data.ocs.data.url
			}

			throw new Error('Upload failed')
		},

		submit() {
			if (!this.isFormValid) return

			const content = {}

			if (this.form.type === 'text') {
				content.text = this.form.text
				content.fontSize = this.form.fontSize
				content.color = this.form.color
				content.backgroundColor = this.form.backgroundColor
				content.textAlign = this.form.textAlign
			} else if (this.form.type === 'image') {
				content.url = this.form.url
				content.alt = this.form.alt
				content.link = this.form.link
				content.fit = this.form.fit
			} else if (this.form.type === 'link') {
				content.label = this.form.label
				content.url = this.form.url
				content.actionType = this.form.actionType
				content.icon = this.form.icon
				content.backgroundColor = this.form.backgroundColor
				content.textColor = this.form.textColor
			} else if (this.form.type === 'label') {
				content.text = this.form.text
				content.fontSize = this.form.fontSize
				content.color = this.form.color
				content.backgroundColor = this.form.backgroundColor
				content.fontWeight = this.form.fontWeight
				content.textAlign = this.form.textAlign
			} else if (this.form.type === 'widget') {
				content.widgetId = this.form.widgetId
				content.displayMode = this.form.displayMode
			}

			this.$emit('submit', {
				type: this.form.type,
				content,
			})
		},
	},
}
</script>

<style scoped lang="scss">
.modal {
  position: fixed;
  top: 0;
  inset-inline: 0;
  bottom: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  top: 0;
  inset-inline: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  position: relative;
  background-color: var(--color-main-background);
  border-radius: 8px;
  padding: 24px;
  min-width: 500px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);

  h3 {
    margin: 0 0 20px;
    font-size: 20px;
    color: var(--color-main-text);
  }

  h4 {
    margin: 16px 0 12px;
    font-size: 16px;
    color: var(--color-main-text);
  }
}

.form-section {
  margin-bottom: 20px;
}

.input-block {
  margin-bottom: 16px;

  label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--color-main-text);
    font-size: 14px;
  }

  input, select, textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 14px;
    background-color: var(--color-main-background);
    color: var(--color-main-text);

    &:focus {
      outline: none;
      border-color: var(--color-primary);
    }
  }

  input[type="color"] {
    height: 40px;
    padding: 4px;
  }

  textarea {
    resize: vertical;
    font-family: inherit;
  }
}

.input-row {
  display: flex;
  gap: 12px;

  .input-block {
    flex: 1;
  }
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border);

  button {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: opacity 0.2s;

    &:hover:not(:disabled) {
      opacity: 0.9;
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }

  .btn-secondary {
    background-color: var(--color-background-dark);
    color: var(--color-main-text);
  }

  .btn-primary {
    background-color: var(--color-primary);
    color: var(--color-primary-text);
  }
}

.upload-status {
  margin-top: 8px;
  color: var(--color-primary);
  font-size: 13px;
  font-style: italic;
}

.error-message {
  margin-top: 8px;
  color: var(--color-error);
  font-size: 13px;
}

.image-preview,
.icon-preview {
  margin: 12px 0;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background-color: var(--color-background-dark);
  display: flex;
  justify-content: center;
  align-items: center;

  img {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
  }
}

.icon-preview {
  img {
    max-height: 64px;
  }
}
</style>
