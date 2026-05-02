<?php

/**
 * Application
 *
 * Main application bootstrap class for MyDash.
 *
 * @category  AppInfo
 * @package   OCA\MyDash\AppInfo
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\MyDash\AppInfo;

use OCA\MyDash\BackgroundJob\PurgeViewsJob;
use OCA\MyDash\BackgroundJob\SaltRotationJob;
use OCA\MyDash\Listener\UserDeletedListener;
use OCA\MyDash\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'mydash';

    /**
     * Constructor
     *
     * @param array $urlParams The URL parameters.
     */
    public function __construct(array $urlParams=[])
    {
        parent::__construct(appName: self::APP_ID, urlParams: $urlParams);
    }//end __construct()

    /**
     * Register services, event listeners, etc.
     *
     * @param IRegistrationContext $context The registration context.
     *
     * @return void
     */
    public function register(IRegistrationContext $context): void
    {
        // Render `dashboard_shared` and `dashboard_ownership_transferred`
        // notifications via our INotifier. REQ-SHARE-011.
        $context->registerNotifierService(notifierClass: Notifier::class);

        // Cascade share cleanup + admin-retention transfer on user deletion.
        // REQ-SHARE-012, REQ-SHARE-013.
        $context->registerEventListener(
            event: UserDeletedEvent::class,
            listener: UserDeletedListener::class
        );
    }//end register()

    /**
     * App initialization after all apps are registered.
     *
     * @param IBootContext $context The boot context.
     *
     * @return void
     */
    public function boot(IBootContext $context): void
    {
        // App initialization after all apps are registered.
        \OCP\Util::addStyle(application: self::APP_ID, file: 'mydash');

        // Register the dashboard view-analytics background jobs
        // (REQ-ANLT-003 design D2 + REQ-ANLT-009). Both are
        // idempotent: `IJobList::add()` is a no-op when the job is
        // already registered.
        $serverContainer = $context->getServerContainer();
        $jobList         = $serverContainer->get(IJobList::class);
        if ($jobList instanceof IJobList === true) {
            $jobList->add(job: PurgeViewsJob::class);
            $jobList->add(job: SaltRotationJob::class);
        }
    }//end boot()
}//end class
