<template>
	<div class="link-button-widget">
		<button
			class="link-button"
			:style="buttonStyle"
			:disabled="isExecuting"
			@click="handleClick">
			<img v-if="iconUrl"
				:src="iconUrl"
				:alt="buttonLabel"
				class="button-icon">
			<span class="button-label">{{ buttonLabel }}</span>
		</button>

		<!-- Modal for document creation -->
		<div v-if="showDocModal" class="doc-modal">
			<div class="doc-modal-backdrop" @click="closeDocModal" />
			<div class="doc-modal-content">
				<h3>{{ t('sendentworkspace', 'Create Document') }}</h3>
				<div class="doc-info">
					<label>{{ t('sendentworkspace', 'File Name') }}:</label>
					<div class="doc-name-block">
						<input v-model="docName" class="doc-name" :placeholder="t('sendentworkspace', 'Enter filename')">
						<span class="doc-extension">.{{ docType }}</span>
					</div>
				</div>
				<div class="doc-modal-actions">
					<button @click="closeDocModal">
						{{ t('sendentworkspace', 'Cancel') }}
					</button>
					<button class="create-doc-btn" :disabled="creatingDoc" @click="confirmCreateDocument">
						{{ creatingDoc ? t('sendentworkspace', 'Creating…') : t('sendentworkspace', 'Create') }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showWarning, showError } from '@nextcloud/dialogs'

export default {
	name: 'LinkButtonWidget',

	props: {
		widget: {
			type: Object,
			required: true,
		},
		label: {
			type: String,
			default: '',
		},
		url: {
			type: String,
			default: '',
		},
		icon: {
			type: String,
			default: '',
		},
		actionType: {
			type: String,
			default: 'external', // 'external', 'internal', 'createFile'
		},
		backgroundColor: {
			type: String,
			default: '',
		},
		textColor: {
			type: String,
			default: '',
		},
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			showDocModal: false,
			docName: '',
			docType: 'docx',
			creatingDoc: false,
			isExecuting: false,
		}
	},

	computed: {
		buttonLabel() {
			return this.label || this.widget?.content?.label || this.t('sendentworkspace', 'Button')
		},

		linkUrl() {
			return this.url || this.widget?.content?.url || '#'
		},

		iconUrl() {
			const icon = this.icon || this.widget?.content?.icon
			if (!icon) return ''

			if (icon.startsWith('http') || icon.startsWith('/')) {
				return icon
			}

			return `/apps/sendentworkspace/img/${icon}`
		},

		action() {
			return this.actionType || this.widget?.content?.actionType || 'external'
		},

		buttonStyle() {
			const bgColor = this.backgroundColor || this.widget?.content?.backgroundColor || 'var(--color-primary)'
			const txtColor = this.textColor || this.widget?.content?.textColor || 'var(--color-primary-text)'

			return {
				backgroundColor: bgColor,
				color: txtColor,
			}
		},

		isDocumentAction() {
			return this.action === 'createFile' || ['docx', 'odt', 'xlsx', 'txt'].includes(this.linkUrl.toLowerCase())
		},
	},

	methods: {
		handleClick() {
			if (this.isAdmin) return // Don't trigger actions in admin mode

			if (this.isDocumentAction) {
				this.openDocModal()
			} else if (this.action === 'external') {
				this.openExternalLink()
			} else if (this.action === 'internal') {
				this.executeInternalFunction()
			}
		},

		openExternalLink() {
			if (this.linkUrl && this.linkUrl !== '#') {
				window.open(this.linkUrl, '_blank', 'noopener,noreferrer')
			}
		},

		async executeInternalFunction() {
			// Execute internal JavaScript function
			// This could be extended to support various internal actions
			// Execute the configured internal function

			try {
				this.isExecuting = true
				// Example: create a file via API
				await this.createFileAndOpen('example.txt', '/', 'Sample content')
			} catch (error) {
				console.error('Error executing internal function:', error)
			} finally {
				this.isExecuting = false
			}
		},

		openDocModal() {
			this.docType = this.linkUrl.replace('.', '').toLowerCase()
			this.docName = `document_${Date.now()}`
			this.showDocModal = true
		},

		closeDocModal() {
			this.showDocModal = false
			this.docName = ''
		},

		async confirmCreateDocument() {
			if (!this.docName) {
				showWarning(this.t('sendentworkspace', 'Please enter a file name'))
				return
			}

			this.creatingDoc = true
			try {
				const filename = `${this.docName}.${this.docType}`
				await this.createFileAndOpen(filename, '/', '')
				this.closeDocModal()
			} catch (error) {
				console.error('Error creating document:', error)
				showError(this.t('sendentworkspace', 'Failed to create document'))
			} finally {
				this.creatingDoc = false
			}
		},

		async createFileAndOpen(filename, dir, content) {
			try {
				const url = generateOcsUrl('/apps/sendentworkspace/api/v1/create-file')
				const response = await axios.post(url, {
					filename,
					dir,
					content,
				})

				if (response.data.ocs?.data?.url) {
					window.open(response.data.ocs.data.url, '_blank')
				}
			} catch (error) {
				console.error('Error creating file:', error)
				throw error
			}
		},
	},
}
</script>

<style scoped lang="scss">
.link-button-widget {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
}

.link-button {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 100%;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  font-weight: 500;

  &:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.button-icon {
  width: 48px;
  height: 48px;
  object-fit: contain;
}

.button-label {
  text-align: center;
  overflow-wrap: break-word;
}

.doc-modal {
  position: fixed;
  top: 0;
  inset-inline: 0;
  bottom: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.doc-modal-backdrop {
  position: absolute;
  top: 0;
  inset-inline: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
}

.doc-modal-content {
  position: relative;
  background-color: var(--color-main-background);
  border-radius: 8px;
  padding: 24px;
  min-width: 400px;
  max-width: 90vw;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);

  h3 {
    margin: 0 0 20px;
    font-size: 20px;
    color: var(--color-main-text);
  }
}

.doc-info {
  margin-bottom: 20px;

  label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--color-main-text);
  }
}

.doc-name-block {
  display: flex;
  align-items: center;
  gap: 4px;
}

.doc-name {
  flex: 1;
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

.doc-extension {
  color: var(--color-text-maxcontrast);
  font-size: 14px;
}

.doc-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;

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

  button:first-child {
    background-color: var(--color-background-dark);
    color: var(--color-main-text);
  }

  .create-doc-btn {
    background-color: var(--color-primary);
    color: var(--color-primary-text);
  }
}
</style>
