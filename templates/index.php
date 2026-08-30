<?php
/**
 * Workspace page template (REQ-SHELL-001).
 *
 * Renders the chrome wrapper `<div id="app-workspace">` and, inside it,
 * the Vue mount point `<div id="workspace-vue">` consumed by `src/main.js`.
 * The chrome wrapper gives `runtime-shell` a stable id Nextcloud's chrome
 * treats as the main content slot (PageController passes
 * `'id-app-content' => '#app-workspace'` and `'id-app-navigation' => null`
 * — the runtime shell renders its own slide-in sidebar instead of using
 * Nextcloud's left navigation panel).
 *
 * The mount id matches `src/main.js#el` (`#workspace-vue`); renaming
 * either side would break the workspace boot.
 *
 * @category Template
 * @package  OCA\LaunchPad
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */
?>

<div id="app-workspace" class="launchpad-workspace">
    <div id="workspace-vue"></div>
</div>
