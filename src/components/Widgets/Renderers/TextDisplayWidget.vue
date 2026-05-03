<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="text-display-widget" :style="wrapperStyle">
		<div
			v-if="hasText"
			class="text-display-widget__content"
			:class="contentClass"
			:style="contentStyle"
			v-html="sanitizedHtml" /><!-- eslint-disable-line vue/no-v-html -->
		<span
			v-else
			class="text-display-widget__placeholder"
			:style="contentStyle">
			{{ placeholderText }}
		</span>
	</div>
</template>

<script>
import DOMPurify from 'dompurify'
import { marked } from 'marked'

/**
 * TextDisplayWidget renders user-authored multi-line text inside a dashboard
 * cell. Content is passed through DOMPurify before injection via `v-html` so
 * common formatting tags (`<b>`, `<i>`, `<a>`, `<br>`, `<p>`, `<ul>`, `<li>`)
 * survive while XSS vectors (`<script>`, `on*` attributes, `javascript:`
 * URLs) are stripped.
 *
 * Persisted shape (REQ-TXT-001..005, REQ-TXMD-001..007):
 * `{type: 'text', content: {text, fontSize, color, backgroundColor,
 * textAlign, contentMode}}`. Defaults: `fontSize='14px'`,
 * `color='var(--color-main-text)'`, `backgroundColor='transparent'`,
 * `textAlign='left'`, `contentMode='html'` (existing widgets) /
 * `'markdown'` (new widgets via registry default).
 *
 * When `contentMode === 'markdown'`, the text is first parsed via marked
 * (CommonMark-compliant, GFM tables enabled) and the resulting HTML is then
 * sanitised by DOMPurify through the same allow-list as the HTML path —
 * a single trust boundary protects both modes (REQ-TXMD-003).
 *
 * Empty/whitespace `text` shows a localised italic placeholder so the cell
 * stays a visible drop target.
 */

// Configure marked once at module-load time; `gfm: true` enables tables, and
// `breaks: false` keeps CommonMark-strict newline behaviour. `headerIds` is
// disabled to avoid leaking unsanitised slugged ids into the DOM.
marked.setOptions({
	gfm: true,
	breaks: false,
	headerIds: false,
	mangle: false,
})

// DOMPurify allow-list extension: the markdown parser may emit table tags
// which DOMPurify already permits in the default profile, so we rely on the
// default allow-list. We explicitly add `target` to ALLOWED_ATTR so author-
// supplied target=_blank links survive sanitisation, then we add the
// `rel="noopener noreferrer"` attribute via an afterSanitizeAttributes hook
// to mitigate reverse-tabnabbing (REQ-TXMD-003 scenario "target=_blank
// links get rel attribute"). The hook is idempotent and registered once.
let hookRegistered = false
function ensureDomPurifyHook() {
	if (hookRegistered) {
		return
	}
	DOMPurify.addHook('afterSanitizeAttributes', (node) => {
		if (node.tagName === 'A' && node.getAttribute('target') === '_blank') {
			node.setAttribute('rel', 'noopener noreferrer')
		}
	})
	hookRegistered = true
}

export default {
	name: 'TextDisplayWidget',

	props: {
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	created() {
		ensureDomPurifyHook()
	},

	computed: {
		text() {
			return typeof this.content?.text === 'string' ? this.content.text : ''
		},

		hasText() {
			return this.text.trim() !== ''
		},

		contentMode() {
			// REQ-TXMD-001: absent contentMode means legacy HTML mode for
			// backward compatibility. Only the literal 'markdown' switches
			// the renderer to the markdown branch; any other value (or
			// none) is treated as 'html'.
			return this.content?.contentMode === 'markdown' ? 'markdown' : 'html'
		},

		contentClass() {
			return this.contentMode === 'markdown'
				? 'text-display-widget__content--markdown'
				: 'text-display-widget__content--html'
		},

		/**
		 * The HTML to render: either the raw author HTML (sanitised) or
		 * the markdown rendering of the author text (sanitised). Both
		 * branches go through DOMPurify, which strips <script>, <style>,
		 * <link>, on* attributes, and javascript: URLs.
		 *
		 * @return {string} sanitised HTML safe for v-html injection
		 */
		sanitizedHtml() {
			let html
			if (this.contentMode === 'markdown') {
				try {
					// `parse` returns sync output for our config (no async
					// extensions). The cast guards against a future build
					// where async parsing is enabled.
					html = marked.parse(this.text)
					if (typeof html !== 'string') {
						html = String(html)
					}
				} catch (err) {
					// Defensive: if the parser throws, fall back to the
					// raw text so the widget still renders something
					// readable instead of crashing the dashboard.
					html = this.text
				}
			} else {
				html = this.text
			}
			// DOMPurify default config strips <script>, <style>, <link>,
			// on* event attributes and javascript: URLs. We add `target`
			// to ALLOWED_ATTR so explicit target=_blank survives, paired
			// with the afterSanitizeAttributes hook above which appends
			// rel="noopener noreferrer" to those anchors.
			return DOMPurify.sanitize(html, {
				ADD_ATTR: ['target'],
			})
		},

		placeholderText() {
			return t('mydash', 'No text content')
		},

		fontSize() {
			return this.content?.fontSize || '14px'
		},

		color() {
			return this.content?.color || 'var(--color-main-text)'
		},

		backgroundColor() {
			return this.content?.backgroundColor || 'transparent'
		},

		textAlign() {
			return this.content?.textAlign || 'left'
		},

		wrapperStyle() {
			return {
				width: '100%',
				height: '100%',
				padding: '16px',
				display: 'flex',
				'align-items': 'center',
				'justify-content': 'center',
				overflow: 'auto',
				'background-color': this.backgroundColor,
			}
		},

		contentStyle() {
			const base = {
				'font-size': this.fontSize,
				'text-align': this.textAlign,
				color: this.color,
				width: '100%',
				'overflow-wrap': 'break-word',
			}
			if (!this.hasText) {
				base['font-style'] = 'italic'
				base.color = 'var(--color-text-maxcontrast)'
			}
			return base
		},
	},
}
</script>

<style scoped>
.text-display-widget {
	width: 100%;
	height: 100%;
}

.text-display-widget__content,
.text-display-widget__placeholder {
	/* Safety net (REQ-TXT-005) — ensures long URLs / words inside the
	   sanitised HTML wrap rather than overflowing horizontally. */
	overflow-wrap: break-word;
	word-wrap: break-word;
	max-width: 100%;
}

/* Markdown-mode rendering normalises spacing for headings, lists, blockquotes
   and tables so the parsed output sits naturally inside the cell rather
   than inheriting browser defaults that look out of place in a small grid
   tile (REQ-TXMD-002). */
.text-display-widget__content--markdown :deep(h1),
.text-display-widget__content--markdown :deep(h2),
.text-display-widget__content--markdown :deep(h3),
.text-display-widget__content--markdown :deep(h4),
.text-display-widget__content--markdown :deep(h5),
.text-display-widget__content--markdown :deep(h6) {
	margin: 8px 0 4px;
	font-weight: 600;
	line-height: 1.3;
}

.text-display-widget__content--markdown :deep(p),
.text-display-widget__content--markdown :deep(ul),
.text-display-widget__content--markdown :deep(ol),
.text-display-widget__content--markdown :deep(blockquote) {
	margin: 4px 0;
}

.text-display-widget__content--markdown :deep(code) {
	padding: 1px 4px;
	border-radius: 3px;
	background: var(--color-background-hover);
	font-family: var(--font-monospace, monospace);
	font-size: 0.95em;
}

.text-display-widget__content--markdown :deep(pre) {
	padding: 8px;
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
	overflow: auto;
}

.text-display-widget__content--markdown :deep(pre) :deep(code) {
	padding: 0;
	background: transparent;
}

.text-display-widget__content--markdown :deep(blockquote) {
	padding-left: 8px;
	border-left: 3px solid var(--color-border);
	color: var(--color-text-maxcontrast);
}

.text-display-widget__content--markdown :deep(table) {
	border-collapse: collapse;
	margin: 4px 0;
}

.text-display-widget__content--markdown :deep(th),
.text-display-widget__content--markdown :deep(td) {
	padding: 4px 8px;
	border: 1px solid var(--color-border);
}
</style>
