<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	MyActivitySettings: a person reads their own activity, always, without the
	administrator switch and without an audit entry
	(dashboards-and-who-may-see-them REQ-DWMS-005).
-->

<template>
	<NcSettingsSection
		:name="t('launchpad', 'My activity')"
		:description="t('launchpad', 'What you did across your apps, counted per day. Counted as events, never as hours.')">
		<ActivityReport />
	</NcSettingsSection>
</template>

<script>
import { NcSettingsSection } from '@nextcloud/vue'
import ActivityReport from '../../components/activity/ActivityReport.vue'

export default {
	name: 'MyActivitySettings',

	components: { ActivityReport, NcSettingsSection },
}
</script>
