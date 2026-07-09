---
sidebar_position: 10
---

# OR-backed widgets

Some LaunchPad widgets surface data from [OpenRegister](https://github.com/ConductionNL/openregister)
(OR). OR is an **optional** dependency: LaunchPad MUST work on a plain
Nextcloud installation with no OR present.

This page documents the canonical pattern every OR-backed widget MUST
follow.

## The three rules

1. **Feature-detect at runtime.** Never assume OR is installed. Always
   call `useOrFeatureDetect()` and check `enabled.value` before any OR
   API call.
2. **Pass `?_lang=` when fetching translatable data.** OR stores
   translatable content in multiple languages. Pass the current user
   locale so OR returns the correct language variant.
3. **Render a documented empty state when OR is absent or returns 5xx.**
   A widget that silently fails is worse than one that shows a clear
   "OpenRegister not available" notice.

## Canonical pattern

```vue
<template>
  <NcEmptyContent
    v-if="!or.enabled.value"
    icon="icon-openregister"
    :name="t('launchpad', 'OpenRegister not available')"
    :description="t('launchpad', 'Enable OpenRegister to surface this data.')"
  />
  <div v-else-if="loading">
    <NcLoadingIcon />
  </div>
  <ul v-else>
    <li v-for="item in items" :key="item.id">{{ item.name }}</li>
  </ul>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { useOrFeatureDetect } from '../composables/useOrFeatureDetect.js'

export default {
  name: 'MyOrWidget',
  setup() {
    const or = useOrFeatureDetect()
    const items = ref([])
    const loading = ref(false)

    onMounted(async () => {
      if (!or.enabled.value) return   // graceful empty state

      loading.value = true
      try {
        // Pass ?_lang= for translatable OR data (ADR-025).
        const lang = document.documentElement.lang || 'en'
        const url = generateUrl('/apps/openregister/api/objects')
        const { data } = await axios.get(url, {
          params: {
            register: 'my-register',
            schema: 'my-schema',
            _lang: lang,
          },
        })
        items.value = data.results ?? []
      } catch {
        // 5xx / network failure: leave items empty, widget stays visible
        // but shows no rows (acceptable degraded state per spec REQ-OR-005).
      } finally {
        loading.value = false
      }
    })

    return { or, items, loading, t }
  },
}
</script>
```

## `useTenantContext()` (pending `multi-tenancy-context`)

When OR surfaces tenant-scoped data, widgets SHOULD additionally use
`useTenantContext()` from `@conduction/nextcloud-vue` to pass the active
tenant scope. This composable is in nc-vue PR #113; once released as a
versioned package, OR-backed widgets that are tenant-aware MUST adopt it.

Until then, widgets MAY omit tenant filtering — OR's server-side ACL
still enforces per-session access.

## Migration checklist for existing widgets

1. Import `useOrFeatureDetect` instead of calling `useAppStatus('openregister')` directly.
2. Guard every `axios.get('/apps/openregister/...')` with `if (!or.enabled.value) return`.
3. Add `params: { _lang: document.documentElement.lang }` to every OR
   fetch that requests translatable fields.
4. Add an `NcEmptyContent` fallback for the OR-absent case.

See `openspec/specs/runtime-or-consumption/spec.md` for the full
requirements and acceptance criteria.
