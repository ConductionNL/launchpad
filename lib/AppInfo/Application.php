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

use OCA\MyDash\Activity\DebounceHelper;
use OCA\MyDash\Listener\GroupDeletedListener;
use OCA\MyDash\Listener\UserDeletedListener;
use OCA\MyDash\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
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
        // Also fires the role-assignment cleanup. REQ-SHARE-012,
        // REQ-SHARE-013, REQ-ROLE-010.
        $context->registerEventListener(
            event: UserDeletedEvent::class,
            listener: UserDeletedListener::class
        );

        // REQ-ACT-007: register DebounceHelper as a shared singleton
        // so the in-memory fallback store (used when APCu is absent in
        // CLI / test runs) survives across all callers within a single
        // request. ActivityPublisher autowires from the app namespace
        // — no explicit binding needed (referenced here in this
        // docblock for the cross-capability discoverability contract:
        // {@see ActivityPublisher}).
        $context->registerService(
            name: DebounceHelper::class,
            factory: static fn(): DebounceHelper => new DebounceHelper(),
            shared: true
        );

        // Role-assignment cascade on group deletion. REQ-ROLE-011.
        $context->registerEventListener(
            event: GroupDeletedEvent::class,
            listener: GroupDeletedListener::class
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
    }//end boot()
}//end class
