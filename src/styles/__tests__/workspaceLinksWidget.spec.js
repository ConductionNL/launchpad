/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace stylesheet leaves the links widget's own layouts alone:
 * card, inline and icon-only are drawn by CnLinksWidget, with their icons
 * (openspec/specs/links-widget, REQ-LNKS-004 and -005). A page-wide rule on
 * `.cn-links-widget` restyles every links widget on every dashboard.
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const css = readFileSync(resolve(__dirname, '../workspace.css'), 'utf8')

describe('workspace.css and the links widget', () => {
	it('holds no rule that targets the links widget', () => {
		expect(css).not.toMatch(/\.cn-links-widget/)
	})
})
