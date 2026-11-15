<?php

/**
 * PermissionServiceAccessGolden
 *
 * The outcomes PermissionServiceAccessGoldenTest pins, captured from the
 * code before PermissionService was split. One row per user, one cell per
 * dashboard (1 2 3 4 5 6 8 99) or placement (10 11 12 13 14 20 22 98):
 * Y allowed, n refused, F/A/V the full / add_only / view_only level,
 * - no access.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

return array (
  'resolveAccessLevel(id)' => 
  array (
    'alice' => 'F A - V - V A -',
    'viewer' => '- - V V - - - -',
    'admin' => '- - F F - - - -',
    'ncadmin' => '- - F F F - - -',
    'editor' => '- - F F - - - -',
    'member' => '- - V V - - - -',
    'sharee' => 'V A - V - F - -',
    'stranger' => '- - - V - - - -',
  ),
  'resolveAccessLevel(entity)' => 
  array (
    'alice' => 'F A - V - V A -',
    'viewer' => '- - V V - - - -',
    'admin' => '- - F F - - - -',
    'ncadmin' => '- - F F F - - -',
    'editor' => '- - F F - - - -',
    'member' => '- - V V - - - -',
    'sharee' => 'V A - V - F - -',
    'stranger' => '- - - V - - - -',
  ),
  'getEffectivePermissionLevel' => 
  array (
    'alice' => 'F A V V F V A -',
    'viewer' => 'F A V V F V A -',
    'admin' => 'F A F F F V A -',
    'ncadmin' => 'F A F F F V A -',
    'editor' => 'F A F F F V A -',
    'member' => 'F A V V F V A -',
    'sharee' => 'F A V V F V A -',
    'stranger' => 'F A V V F V A -',
  ),
  'canViewDashboard' => 
  array (
    'alice' => 'Y Y n Y n Y Y n',
    'viewer' => 'n n Y Y n n n n',
    'admin' => 'n n Y Y n n n n',
    'ncadmin' => 'n n Y Y Y n n n',
    'editor' => 'n n Y Y n n n n',
    'member' => 'n n Y Y n n n n',
    'sharee' => 'Y Y n Y n Y n n',
    'stranger' => 'n n n Y n n n n',
  ),
  'canEditDashboard' => 
  array (
    'alice' => 'Y Y n n n n Y n',
    'viewer' => 'n n n n n n n n',
    'admin' => 'Y Y Y Y n Y Y n',
    'ncadmin' => 'n n Y Y n n n n',
    'editor' => 'n n Y Y n n n n',
    'member' => 'n n n n n n n n',
    'sharee' => 'n Y n n n Y n n',
    'stranger' => 'n n n n n n n n',
  ),
  'canEditDashboardMetadata' => 
  array (
    'alice' => 'Y Y n n n Y Y n',
    'viewer' => 'n n n n n n n n',
    'admin' => 'n n Y Y n n n n',
    'ncadmin' => 'n n n n Y n n n',
    'editor' => 'n n n n n n n n',
    'member' => 'n n n n n n n n',
    'sharee' => 'n n n n n n n n',
    'stranger' => 'n n n n n n n n',
  ),
  'canAddWidget' => 
  array (
    'alice' => 'Y Y n n n n Y n',
    'viewer' => 'n n n n n n n n',
    'admin' => 'n n Y Y n n n n',
    'ncadmin' => 'n n Y Y Y n n n',
    'editor' => 'n n Y Y n n n n',
    'member' => 'n n n n n n n n',
    'sharee' => 'n Y n n n Y n n',
    'stranger' => 'n n n n n n n n',
  ),
  'canRemoveWidget' => 
  array (
    'alice' => 'Y n Y n n Y Y n',
    'viewer' => 'n n n n n n n n',
    'admin' => 'n n n n Y n n n',
    'ncadmin' => 'n n n n Y n n n',
    'editor' => 'n n n n Y n n n',
    'member' => 'n n n n n n n n',
    'sharee' => 'n n Y n n n Y n',
    'stranger' => 'n n n n n n n n',
  ),
  'canViewPlacement' => 
  array (
    'alice' => 'Y Y Y n n Y Y n',
    'viewer' => 'n n n n Y n n n',
    'admin' => 'n n n n Y n n n',
    'ncadmin' => 'n n n n Y n n n',
    'editor' => 'n n n n Y n n n',
    'member' => 'n n n n Y n n n',
    'sharee' => 'Y Y Y n n Y Y n',
    'stranger' => 'n n n n n n n n',
  ),
  'canStyleWidget' => 
  array (
    'alice' => 'Y Y Y n n Y Y n',
    'viewer' => 'n n n n n n n n',
    'admin' => 'n n n n Y n n n',
    'ncadmin' => 'n n n n Y n n n',
    'editor' => 'n n n n Y n n n',
    'member' => 'n n n n n n n n',
    'sharee' => 'n Y Y n n n Y n',
    'stranger' => 'n n n n n n n n',
  ),
  'canManageAcknowledgement' => 
  array (
    'alice' => 'Y Y Y n n Y n n',
    'viewer' => 'n n n n n n n n',
    'admin' => 'Y Y Y Y Y Y Y Y',
    'ncadmin' => 'Y Y Y Y Y Y Y Y',
    'editor' => 'n n n n n n n n',
    'member' => 'n n n n n n n n',
    'sharee' => 'n n n n n n n n',
    'stranger' => 'n n n n n n n n',
  ),
  'canCreateDashboard' => 
  array (
    'alice' => 'n',
    'viewer' => 'n',
    'admin' => 'Y',
    'ncadmin' => 'n',
    'editor' => 'Y',
    'member' => 'n',
    'sharee' => 'n',
    'stranger' => 'n',
  ),
);
