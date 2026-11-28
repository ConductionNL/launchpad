/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The page's start page header prints the same greeting line as the
 * first-paint shell (lib/Service/FirstPaintGreeting.php), in Dutch and in
 * English, on the one fixture both sides read
 * (tests/fixtures/first-paint-greeting.json), so nothing changes when the page
 * takes over from the shell.
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-2
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import fixture from '../../../../tests/fixtures/first-paint-greeting.json'

const reader = vi.hoisted(() => ({ language: 'en', canonical: 'en-US', words: {} }))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) =>
		(reader.words[text] ?? text).replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	translatePlural: (_app, one, many, count) =>
		(count === 1 ? one : many).replace('%n', String(count)),
	getCanonicalLocale: () => reader.canonical,
	getLanguage: () => reader.language,
	getLocale: () => reader.canonical.replace('-', '_'),
}))
vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => ({ uid: 'pieter', displayName: 'Pieter Jansen', isAdmin: false }),
}))

const { default: CnHeaderWidget } = await import('@conduction/nextcloud-vue/src/components/CnHeaderWidget/CnHeaderWidget.vue')

beforeEach(() => {
	vi.useFakeTimers()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('Start page greeting against the first-paint fixture', () => {
	for (const testCase of fixture.cases) {
		it(`prints the shell's line: ${testCase.name}`, () => {
			reader.language = testCase.language
			reader.canonical = testCase.canonical
			reader.words = fixture.words[testCase.language] ?? {}
			// The reader's local time; the shell reads the same moment in their zone.
			vi.setSystemTime(new Date(testCase.at))
			const wrapper = mount(CnHeaderWidget, {
				props: { content: { title: 'Vandaag', greetingLine: true, ...fixture.content } },
			})
			expect(wrapper.find('.cn-header-widget__line').text()).toBe(testCase.line)
		})
	}
})
