/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The miniature frame (add-widget-dialog-with-miniatures REQ-WDG-048):
 * hidden from assistive technology, mounted when it comes into view, the
 * renderer drawn with the sample and `preview`, the icon when there is no
 * sample or the renderer fails, and the Nextcloud widget frame.
 *
 * @spec openspec/changes/archive/2026-11-27-add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import WidgetMiniature from '../WidgetMiniature.vue'

const Renderer = {
	name: 'SampleRenderer',
	props: ['content', 'placement', 'preview', 'editMode'],
	template: '<div class="sample-renderer">{{ content.text }}</div>',
}
const Broken = {
	name: 'Broken',
	props: ['content'],
	setup() {
		throw new Error('cannot draw')
	},
	template: '<div />',
}

let observers = []

/** A stand-in IntersectionObserver the test can trigger. */
class FakeObserver {
	constructor(callback) {
		this.callback = callback
		this.disconnect = vi.fn()
		observers.push(this)
	}

	observe(element) {
		this.element = element
	}

	trigger(isIntersecting) {
		this.callback([{ isIntersecting }])
	}
}

afterEach(() => {
	observers = []
	vi.unstubAllGlobals()
})

const mountMiniature = (props) => mount(WidgetMiniature, { props, slots: { icon: '<i class="type-icon" />' } })

describe('WidgetMiniature', () => {
	it('is hidden from assistive technology and takes no focus', () => {
		vi.stubGlobal('IntersectionObserver', undefined)
		const frame = mountMiniature({ type: 'label', entry: { renderer: Renderer }, content: { text: 'Hi' } }).find('.widget-miniature')
		expect(frame.attributes('aria-hidden')).toBe('true')
		expect(frame.attributes('inert')).toBeDefined()
	})

	it('mounts the renderer only when the card comes into view, with the sample and preview', async () => {
		vi.stubGlobal('IntersectionObserver', FakeObserver)
		const wrapper = mountMiniature({ type: 'label', entry: { renderer: Renderer }, content: { text: 'Welcome' } })
		await flushPromises()
		expect(wrapper.find('.sample-renderer').exists()).toBe(false)
		expect(wrapper.find('.widget-miniature').attributes('data-state')).toBe('loading')

		observers[0].trigger(false)
		await flushPromises()
		expect(wrapper.find('.sample-renderer').exists()).toBe(false)

		observers[0].trigger(true)
		await flushPromises()
		const renderer = wrapper.findComponent({ name: 'SampleRenderer' })
		expect(renderer.props()).toMatchObject({ content: { text: 'Welcome' }, preview: true, editMode: false, placement: { id: 0, widgetId: 'label' } })
		expect(wrapper.find('.widget-miniature').attributes('data-state')).toBe('ready')
		expect(observers[0].disconnect).toHaveBeenCalled()
	})

	it('uses the registry sample when no content is given', async () => {
		vi.stubGlobal('IntersectionObserver', undefined)
		const wrapper = mountMiniature({ type: 'label', entry: { renderer: Renderer, sampleContent: { text: 'From the registry' } } })
		await flushPromises()
		expect(wrapper.text()).toContain('From the registry')
	})

	it('shows the icon for a type without sample content', () => {
		vi.stubGlobal('IntersectionObserver', FakeObserver)
		const wrapper = mountMiniature({ type: 'no-such-type', entry: { renderer: Renderer } })
		expect(wrapper.find('.type-icon').exists()).toBe(true)
		expect(wrapper.find('.widget-miniature').attributes('data-state')).toBe('fallback')
		expect(observers).toHaveLength(0)
	})

	it('shows the icon when the renderer fails to draw', async () => {
		vi.stubGlobal('IntersectionObserver', undefined)
		const wrapper = mountMiniature({ type: 'label', entry: { renderer: Broken }, content: { text: 'x' } })
		await flushPromises()
		expect(wrapper.find('.type-icon').exists()).toBe(true)
		expect(wrapper.find('.widget-miniature').attributes('data-state')).toBe('fallback')
	})

	it('draws another app\'s Nextcloud widget as its frame with the app icon and three rows', () => {
		const wrapper = mountMiniature({ ncWidget: { iconUrl: '/apps/deck/img/deck.svg' } })
		expect(wrapper.find('[data-testid="widget-miniature-nc"] img').attributes('src')).toBe('/apps/deck/img/deck.svg')
		expect(wrapper.findAll('.widget-miniature__nc-row')).toHaveLength(3)
		expect(wrapper.find('.widget-miniature').attributes('data-state')).toBe('nc')
	})

	it('gives the people widget a sample data source, so it reads no server', async () => {
		vi.stubGlobal('IntersectionObserver', undefined)
		const People = { name: 'People', props: ['content', 'placement', 'preview', 'editMode', 'dataSource'], template: '<div />' }
		const wrapper = mountMiniature({ type: 'people', entry: { renderer: People } })
		await flushPromises()
		const source = wrapper.findComponent({ name: 'People' }).props('dataSource')
		const page = await source.fetchPeople({ offset: 0 })
		expect(page.users.map((user) => user.displayName)).toEqual(['Anna de Vries', 'Pieter Jansen', 'Fatima El Amrani'])
		expect(page.users.every((user) => !user.avatarUrl)).toBe(true)
	})
})
