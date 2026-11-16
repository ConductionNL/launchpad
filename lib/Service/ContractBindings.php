<?php

/**
 * Bindings of the Kernel contracts to their Service implementations
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec exclude Composition root wiring, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AnalyticsServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementCommentServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementFollowNotifierInterface;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementFollowServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\AttentionSourceServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\CalendarWidgetServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\CommandServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ConfluenceImportServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\DashboardLockServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\DemoDataServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ExportServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\FeedRefreshServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\FooterServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\OrphanedDataCleanupServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\PersonalLayerServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ProfileFieldServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\PublicStartPageInterface;
use OCA\LaunchPad\Kernel\Contracts\QuotaServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\RdpFileWriterInterface;
use OCA\LaunchPad\Kernel\Contracts\ResourceServeServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\SearchShortcutServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ServiceDiscoveryServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\SetupWizardServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ShippedTemplateServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ShippedTemplateUpdateServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\TemplateResyncServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\TileAnalyticsServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\WeatherServiceInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Nextcloud autowires concrete classes but not interfaces, so every contract
 * in OCA\LaunchPad\Kernel\Contracts that a caller type-hints needs an alias to
 * the class that implements it. An alias resolves to the same shared instance
 * as the implementation itself.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Composition root wiring, no functional requirement.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class ContractBindings {

	/**
	 * Alias every Kernel contract to its implementation.
	 *
	 * @param IRegistrationContext $context The registration context.
	 *
	 * @return void
	 *
	 * @spec exclude Composition root wiring, no functional requirement.
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(AcknowledgementServiceInterface::class, AcknowledgementService::class);
		$context->registerServiceAlias(AnalyticsServiceInterface::class, AnalyticsService::class);
		$context->registerServiceAlias(AnnouncementCommentServiceInterface::class, AnnouncementCommentService::class);
		$context->registerServiceAlias(AnnouncementFollowNotifierInterface::class, AnnouncementFollowNotifier::class);
		$context->registerServiceAlias(AnnouncementFollowServiceInterface::class, AnnouncementFollowService::class);
		$context->registerServiceAlias(AnnouncementServiceInterface::class, AnnouncementService::class);
		$context->registerServiceAlias(AttentionSourceServiceInterface::class, AttentionSourceService::class);
		$context->registerServiceAlias(CalendarWidgetServiceInterface::class, CalendarWidgetService::class);
		$context->registerServiceAlias(CommandServiceInterface::class, CommandService::class);
		$context->registerServiceAlias(ConditionalServiceInterface::class, ConditionalService::class);
		$context->registerServiceAlias(ConfluenceImportServiceInterface::class, ConfluenceImportService::class);
		$context->registerServiceAlias(DashboardLockServiceInterface::class, DashboardLockService::class);
		$context->registerServiceAlias(DemoDataServiceInterface::class, DemoDataService::class);
		$context->registerServiceAlias(ExportServiceInterface::class, ExportService::class);
		$context->registerServiceAlias(FeedRefreshServiceInterface::class, FeedRefreshService::class);
		$context->registerServiceAlias(FooterServiceInterface::class, FooterService::class);
		$context->registerServiceAlias(OrphanedDataCleanupServiceInterface::class, OrphanedDataCleanupService::class);
		$context->registerServiceAlias(PersonalLayerServiceInterface::class, PersonalLayerService::class);
		$context->registerServiceAlias(ProfileFieldServiceInterface::class, ProfileFieldService::class);
		$context->registerServiceAlias(PublicStartPageInterface::class, PublicStartPage::class);
		$context->registerServiceAlias(QuotaServiceInterface::class, QuotaService::class);
		$context->registerServiceAlias(RdpFileWriterInterface::class, RdpFileWriter::class);
		$context->registerServiceAlias(ResourceServeServiceInterface::class, ResourceServeService::class);
		$context->registerServiceAlias(SearchShortcutServiceInterface::class, SearchShortcutService::class);
		$context->registerServiceAlias(ServiceDiscoveryServiceInterface::class, ServiceDiscoveryService::class);
		$context->registerServiceAlias(SetupWizardServiceInterface::class, SetupWizardService::class);
		$context->registerServiceAlias(ShippedTemplateServiceInterface::class, ShippedTemplateService::class);
		$context->registerServiceAlias(ShippedTemplateUpdateServiceInterface::class, ShippedTemplateUpdateService::class);
		$context->registerServiceAlias(TemplateResyncServiceInterface::class, TemplateResyncService::class);
		$context->registerServiceAlias(TileAnalyticsServiceInterface::class, TileAnalyticsService::class);
		$context->registerServiceAlias(WeatherServiceInterface::class, WeatherService::class);
	}//end register()
}//end class
