/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Set webpack's runtime public path so dynamically-imported chunks load from
 * the directory Nextcloud actually serves the app's JS from — e.g.
 * `/custom_apps/launchpad/js/` in dev, `/apps/launchpad/js/` in a standard
 * install. Without this, webpack's default `'auto'` publicPath resolves lazy
 * chunks (e.g. `launchpad-cn-manifest-validator.js`) to a path NC answers with
 * the app-shell HTML, producing "Refused to execute script (MIME text/html)".
 *
 * `generateFilePath(app, type, file)` resolves against Nextcloud's per-app web
 * root (`OC.appswebroots`), so it is correct for both `apps/` and `custom_apps/`
 * layouts. This module MUST be imported before any `import()` that triggers
 * chunk loading, so it is listed as the first import in each webpack entry
 * (main.js, admin.js).
 */

import { generateFilePath } from '@nextcloud/router'

// eslint-disable-next-line camelcase, no-undef
__webpack_public_path__ = generateFilePath('launchpad', 'js', '')
