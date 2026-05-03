<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="text-display-widget" :style="wrapperStyle">
		<table
			v-if="tableMode"
			class="text-display-widget__table"
			:style="tableContainerStyle">
			<tbody>
				<tr v-for="(row, rIdx) in tableRows" :key="`r-${rIdx}`">
					<template v-for="(cell, cIdx) in row">
						<th
							v-if="isHeaderCell(rIdx)"
							v-show="!isPlaceholderCell(rIdx, cIdx)"
							:key="`c-${rIdx}-${cIdx}`"
							:rowspan="cell.rowSpan || 1"
							:colspan="cell.colSpan || 1"
							:style="headerCellStyle(cIdx)">
							<span
								v-if="cellHasText(cell)"
								v-html="sanitizeCell(cell.text)" /><!-- eslint-disable-line vue/no-v-html -->
							<span
								v-else
								class="text-display-widget__cell-placeholder">
								{{ emptyCellPlaceholder }}
							</span>
						</th>
						<td
							v-else
							v-show="!isPlaceholderCell(rIdx, cIdx)"
							:key="`c-${rIdx}-${cIdx}`"
							:rowspan="cell.rowSpan || 1"
							:colspan="cell.colSpan || 1"
							:style="dataCellStyle(cIdx)">
							<span
								v-if="cellHasText(cell)"
								v-html="sanitizeCell(cell.text)" /><!-- eslint-disable-line vue/no-v-html -->
							<span
								v-else
								class="text-display-widget__cell-placeholder">
								{{ emptyCellPlaceholder }}
							</span>
						</td>
					</template>
				</tr>
			</tbody>
		</table>
		<div
			v-else-if="hasText"
			class="text-display-widget__content"
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
import { isPlaceholderCell } from '../../../utils/textTable.js'

/**
 * TextDisplayWidget renders user-authored multi-line text inside a dashboard
 * cell. Content is passed through DOMPurify before injection via `v-html` so
 * common formatting tags (`<b>`, `<i>`, `<a>`, `<br>`, `<p>`, `<ul>`, `<li>`)
 * survive while XSS vectors (`<script>`, `on*` attributes, `javascript:`
 * URLs) are stripped.
 *
 * Persisted shape (REQ-TXT-001..005): `{type: 'text', content: {text,
 * fontSize, color, backgroundColor, textAlign}}`. Defaults: `fontSize='14px'`,
 * `color='var(--color-main-text)'`, `backgroundColor='transparent'`,
 * `textAlign='left'`. Empty/whitespace `text` shows a localised italic
 * placeholder so the cell stays a visible drop target.
 *
 * REQ-TBLE-002 / REQ-TBLE-009: When `content.tableMode === true`, the
 * renderer draws an HTML `<table>` from `content.tableData` instead. Cell
 * text is sanitised the same way as the text mode path, header rows promote
 * row 0 to `<th>`, per-column alignment is applied as inline `text-align`,
 * and `colSpan` / `rowSpan` are emitted as native HTML attributes. Cells that
 * have been "swallowed" by a neighbouring merge are hidden via `v-show` so
 * the visual grid stays rectangular.
 */
export default {
	name: 'TextDisplayWidget',

	props: {
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	computed: {
		text() {
			return typeof this.content?.text === 'string' ? this.content.text : ''
		},

		hasText() {
			return this.text.trim() !== ''
		},

		sanitizedHtml() {
			// DOMPurify default config strips <script>, <style>, <link>,
			// on* event attributes and javascript: URLs. We keep the
			// default config explicitly — tighter overrides would block
			// the `<a href>` and `<b>`/`<i>` formatting authors expect.
			return DOMPurify.sanitize(this.text)
		},

		placeholderText() {
			return t('mydash', 'No text content')
		},

		emptyCellPlaceholder() {
			return t('mydash', 'Empty cell')
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

		tableMode() {
			return this.content?.tableMode === true
		},

		tableData() {
			return this.content?.tableData || null
		},

		tableRows() {
			return Array.isArray(this.tableData?.rows) ? this.tableData.rows : []
		},

		headerRow() {
			return this.tableData?.headerRow === true
		},

		columnAlignments() {
			const aligns = this.tableData?.columnAlignments
			return Array.isArray(aligns) ? aligns : []
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

		tableContainerStyle() {
			return {
				'font-size': this.fontSize,
				color: this.color,
				width: '100%',
			}
		},
	},

	methods: {
		/**
		 * Run cell text through DOMPurify so the table-mode render path
		 * has the same XSS-stripping guarantees as the text-mode path.
		 *
		 * @param {string} text raw cell text (may contain HTML)
		 * @return {string} sanitised HTML safe to render via v-html
		 */
		sanitizeCell(text) {
			return DOMPurify.sanitize(typeof text === 'string' ? text : '')
		},

		/**
		 * Whether the given row index renders as `<th>` (header row).
		 *
		 * @param {number} rIdx row index (0-based)
		 * @return {boolean} true when row 0 and headerRow flag is set
		 */
		isHeaderCell(rIdx) {
			return this.headerRow && rIdx === 0
		},

		/**
		 * Hide cells that another cell's `rowSpan` / `colSpan` already
		 * covers. The data structure keeps placeholders to maintain a
		 * rectangular grid, but they MUST NOT render as their own DOM cells
		 * (otherwise the table would be visually wider than intended).
		 *
		 * @param {number} rIdx row index
		 * @param {number} cIdx column index
		 * @return {boolean} true when this cell is covered by another
		 */
		isPlaceholderCell(rIdx, cIdx) {
			return isPlaceholderCell(this.tableRows, rIdx, cIdx)
		},

		/**
		 * Empty-cell detection — used to swap in the localised "Empty cell"
		 * placeholder so freshly-created tables read as "click to type"
		 * rather than as broken empty boxes.
		 *
		 * @param {object} cell the cell object
		 * @return {boolean} true when the cell has any non-whitespace text
		 */
		cellHasText(cell) {
			return typeof cell?.text === 'string' && cell.text.trim() !== ''
		},

		dataCellStyle(cIdx) {
			return {
				'text-align': this.columnAlignments[cIdx] || 'left',
				border: '1px solid var(--color-border)',
				padding: '8px',
			}
		},

		headerCellStyle(cIdx) {
			return {
				...this.dataCellStyle(cIdx),
				'background-color': 'var(--color-background-hover)',
				'font-weight': 'bold',
			}
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

.text-display-widget__table {
	border-collapse: collapse;
	width: 100%;
}

.text-display-widget__table th,
.text-display-widget__table td {
	overflow-wrap: break-word;
	word-wrap: break-word;
	vertical-align: top;
}

.text-display-widget__cell-placeholder {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
