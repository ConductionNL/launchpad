/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Login-page redirect to the public start page (sharing-public-start-page,
 * design D2). Added to the login page by `PublicStartRedirectListener` only
 * for a signed-out GET without `redirect_url`, `direct` or `clear`, while the
 * start page is on. The target is a fixed same-origin path, never taken from
 * the request.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */

import { generateUrl } from '@nextcloud/router'
import { startPageUrl } from './utils/publicStart.js'

window.location.replace(startPageUrl(generateUrl))
