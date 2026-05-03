/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ViewDashboardIcon from 'vue-material-design-icons/ViewDashboard.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import ChartBarIcon from 'vue-material-design-icons/ChartBar.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import BellIcon from 'vue-material-design-icons/Bell.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import HeartIcon from 'vue-material-design-icons/Heart.vue'
import BookOpenVariantIcon from 'vue-material-design-icons/BookOpenVariant.vue'
import LightbulbIcon from 'vue-material-design-icons/Lightbulb.vue'
import RocketLaunchIcon from 'vue-material-design-icons/RocketLaunch.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import BriefcaseIcon from 'vue-material-design-icons/Briefcase.vue'

export const DASHBOARD_ICONS = {
	ViewDashboard: ViewDashboardIcon,
	Home: HomeIcon,
	ChartBar: ChartBarIcon,
	Cog: CogIcon,
	AccountGroup: AccountGroupIcon,
	Calendar: CalendarIcon,
	FileDocument: FileDocumentIcon,
	Bell: BellIcon,
	Star: StarIcon,
	Heart: HeartIcon,
	BookOpenVariant: BookOpenVariantIcon,
	Lightbulb: LightbulbIcon,
	RocketLaunch: RocketLaunchIcon,
	Earth: EarthIcon,
	Briefcase: BriefcaseIcon,
}

export const DEFAULT_ICON = 'ViewDashboard'

export function isCustomIconUrl(name) {
	return name && (name.startsWith('/') || name.startsWith('http'))
}

export function getIconComponent(name) {
	if (isCustomIconUrl(name)) {
		return null
	}
	return DASHBOARD_ICONS[name] || DASHBOARD_ICONS[DEFAULT_ICON]
}
