/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Loading a widget's reading from LaunchPad's per-placement endpoint, shared
 * by the widgets that show one server-side value (weather, live data tile).
 */

/**
 * Load (or reload) the reading for a widget's placement into the widget's
 * `loading`, `errorMessage` and `reading`. A placement without an id is not
 * saved yet; an answer carrying `error` and a failed request each show their
 * message. It never throws into the caller.
 *
 * @param {{loading: boolean, errorMessage: string, reading: unknown}} widget The widget.
 * @param {{placementId: (number|string|null|undefined), fetch: function((number|string)): Promise<object>, unsaved: string, unavailable: string, failed: string}} source The placement id, the client call and the three messages.
 * @return {Promise<void>}
 * @spec openspec/specs/clock-weather-widgets/spec.md#req-weather-001
 */
export async function loadPlacementReading(widget, source) {
	const placementId = source.placementId
	if (placementId === undefined || placementId === null) {
		widget.loading = false
		widget.errorMessage = source.unsaved
		return
	}

	widget.loading = true
	widget.errorMessage = ''
	try {
		const data = await source.fetch(placementId)
		if (data && data.error) {
			widget.reading = null
			widget.errorMessage = source.unavailable
			return
		}
		widget.reading = data
	} catch {
		widget.reading = null
		widget.errorMessage = source.failed
	} finally {
		widget.loading = false
	}
}
