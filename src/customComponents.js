// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * The `customComponents` map App.vue hands to CnAppRoot.
 *
 * LaunchPad resolves its pages and components through the v2 `registry`
 * (src/registry.js). This map exists for the one thing the registry cannot
 * answer: CnIndexPage resolves a header action's `handler` name against
 * `customComponents` only. CnAppRoot logs a one-time deprecation warning for a
 * non-empty map next to a v2 manifest; that warning is the price of a handler
 * that leaves the app, and stackiq and buildiq pay it the same way.
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-004-an-admin-reads-the-connections-on-an-integrations-page
 */

import { generateUrl } from '@nextcloud/router'
import { createConnectionHandlers } from './services/connectionRegistry.js'

export default {
	// Header-action handler: the Integrations page's Add integration
	// (adopt-connection-registry). A FUNCTION, because it leaves the app for
	// integriq's Connections overview and a header action's `navigate` only
	// pushes a route inside this app.
	...createConnectionHandlers({
		generateUrl,
		assign: (url) => window.location.assign(url),
	}),
}
