<?php

/**
 * ResponseDefinitions
 *
 * The response shapes of LaunchPad's public API operations, as Psalm type
 * aliases. Controllers import them in their `@psalm-return` annotations;
 * `composer openapi:generate` writes each alias as a schema under
 * `components.schemas` of openapi.json (governance-rest-api-reference task 2).
 *
 * The shapes are unsealed (`...`): they name the fields an integrator may rely
 * on, and a later field is an addition, not a break.
 *
 * @category  Api
 * @package   OCA\LaunchPad
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad;

/**
 * Holds the response type aliases; never instantiated.
 *
 * @psalm-type LaunchPadDashboard = array{
 *     id: int,
 *     uuid: string,
 *     name: string,
 *     description: string|null,
 *     icon: string|null,
 *     type: string,
 *     userId?: string|null,
 *     groupId?: string|null,
 *     gridColumns: int,
 *     permissionLevel: string|null,
 *     isDefault: int,
 *     isActive: int,
 *     parentUuid: string|null,
 *     slug: string|null,
 *     sortOrder: int,
 *     publicationStatus: string|null,
 *     createdAt: string|null,
 *     updatedAt: string|null,
 *     ...
 * }
 *
 * @psalm-type LaunchPadPlacement = array{
 *     id: int,
 *     dashboardId: int,
 *     widgetId: string|null,
 *     gridX: int,
 *     gridY: int,
 *     gridWidth: int,
 *     gridHeight: int,
 *     isCompulsory: int,
 *     isVisible: int,
 *     customTitle: string|null,
 *     customIcon: string|null,
 *     showTitle: int,
 *     sortOrder: int,
 *     version: int,
 *     createdAt: string|null,
 *     updatedAt: string|null,
 *     ...
 * }
 *
 * @psalm-type LaunchPadQuota = array{
 *     maxDashboards: int,
 *     dashboardsUsed: int,
 *     maxWidgetsPerDashboard: int
 * }
 *
 * @psalm-type LaunchPadDashboardList = array{
 *     items: list<LaunchPadDashboard>,
 *     quota: LaunchPadQuota
 * }
 *
 * @psalm-type LaunchPadDashboardEnvelope = array{
 *     dashboard: LaunchPadDashboard,
 *     placements: list<LaunchPadPlacement>,
 *     permissionLevel: string,
 *     isOwner: bool,
 *     sharedBy: string|null,
 *     hasVariants: bool,
 *     breadcrumbs?: list<array<string, mixed>>
 * }
 *
 * @psalm-type LaunchPadCreatedDashboard = array{
 *     dashboard: LaunchPadDashboard,
 *     placements: list<LaunchPadPlacement>
 * }
 *
 * @psalm-type LaunchPadShare = array{
 *     id: int,
 *     dashboardId: int,
 *     shareType: string,
 *     shareWith: string,
 *     permissionLevel: string,
 *     createdAt: string|null,
 *     updatedAt: string|null,
 *     ...
 * }
 *
 * @psalm-type LaunchPadPublicShare = array{
 *     id: int,
 *     dashboardUuid: string,
 *     token: string,
 *     url: string,
 *     passwordRequired: bool,
 *     expiresAt: string|null,
 *     createdBy: string,
 *     createdAt: string|null,
 *     revokedAt: string|null,
 *     viewCount: int,
 *     lastViewedAt: string|null,
 *     ...
 * }
 *
 * @psalm-type LaunchPadTile = array{
 *     id: int,
 *     userId: string,
 *     title: string,
 *     icon: string|null,
 *     iconType: string|null,
 *     backgroundColor: string|null,
 *     textColor: string|null,
 *     linkType: string,
 *     linkValue: string,
 *     createdAt: string|null,
 *     updatedAt: string|null,
 *     ...
 * }
 *
 * @psalm-type LaunchPadGone = array{
 *     status: 'gone',
 *     message: string,
 *     replacement: string
 * }
 *
 * @psalm-type LaunchPadImportSummary = array{
 *     importedDashboardCount: int,
 *     skippedDashboardCount: int,
 *     errors: list<mixed>
 * }
 *
 * @psalm-type LaunchPadStatusOk = array{
 *     status: 'ok',
 *     ...
 * }
 *
 * @spec openspec/changes/governance-rest-api-reference/specs/rest-api-reference/spec.md
 *
 * @psalm-suppress UnusedClass The class only carries the type aliases.
 */
final class ResponseDefinitions {
}//end class
