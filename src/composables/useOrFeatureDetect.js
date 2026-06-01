/**
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * useOrFeatureDetect — thin façade over nc-vue's useAppStatus composable.
 *
 * Every OR-backed widget MUST call this before issuing any
 * axios.get('/index.php/apps/openregister/...') request. When
 * `enabled.value` is false the widget MUST render its documented empty
 * state and MUST NOT fire the OR API call.
 *
 * Pattern (see docs/widgets/or-data.md for full canonical example):
 *
 *   const { enabled } = useOrFeatureDetect()
 *   if (!enabled.value) return   // render graceful empty state
 *   const data = await axios.get('/index.php/apps/openregister/...')
 *
 * @spec openspec/changes/launchpad-adopt-or-abstractions/tasks.md#task-4
 */

import { computed, ref } from 'vue'

/**
 * Detect whether OpenRegister is present and enabled at runtime.
 *
 * Returns refs that are safe to use in computed properties and templates.
 * Does NOT throw — any OCS fetch failure sets enabled.value = false and
 * populates error.value.
 *
 * @returns {{ enabled: import('vue').ComputedRef<boolean>, version: import('vue').Ref<string|null>, error: import('vue').Ref<Error|null> }}
 */
export function useOrFeatureDetect() {
	// Try to use nc-vue's useAppStatus if available; fall back to a one-shot
	// OCS fetch so the composable works regardless of the nc-vue version.
	try {
		const { useAppStatus } = require('@conduction/nextcloud-vue/composables')
		const status = useAppStatus('openregister')
		return {
			enabled: computed(() => status.enabled?.value ?? false),
			version: computed(() => status.version?.value ?? null),
			error: computed(() => status.error?.value ?? null),
		}
	} catch {
		// useAppStatus not present in this nc-vue build; fall back to local fetch.
	}

	const version = ref(null)
	const error = ref(null)
	const enabled = ref(false)

	// One-shot OCS capability check — fires once per component mount.
	;(async () => {
		try {
			const { default: axios } = await import('@nextcloud/axios')
			const { generateUrl } = await import('@nextcloud/router')
			const url = generateUrl('/ocs/v2.php/cloud/apps/openregister')
			const response = await axios.get(url, {
				headers: { 'OCS-APIREQUEST': 'true' },
			})
			if (response?.data?.ocs?.data?.id === 'openregister'
				&& response?.data?.ocs?.data?.enabled === true) {
				version.value = response.data.ocs.data.version ?? null
				enabled.value = true
			}
		} catch (err) {
			error.value = err instanceof Error ? err : new Error(String(err))
			enabled.value = false
		}
	})()

	return {
		enabled: computed(() => enabled.value),
		version: computed(() => version.value),
		error: computed(() => error.value),
	}
}
