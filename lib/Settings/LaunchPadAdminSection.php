<?php

/**
 * LaunchPadAdminSection
 *
 * Admin settings section for LaunchPad.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Settings;

use OCA\LaunchPad\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class LaunchPadAdminSection implements IIconSection
{
    /**
     * Constructor
     *
     * @param IL10N         $l            The localization service.
     * @param IURLGenerator $urlGenerator The URL generator.
     */
    public function __construct(
        private readonly IL10N $l,
        private readonly IURLGenerator $urlGenerator,
    ) {
    }//end __construct()

    /**
     * Get the section ID.
     *
     * NOTE: Nextcloud admin-UI registration boilerplate; behaviour defined
     * by the OCP\Settings\IIconSection contract, not a LaunchPad spec.
     * Intentionally left without an `@spec` tag (see
     * `openspec/changes/archive/2026-05-03-spec-annotation-pass/design.md`).
     *
     * @return string The section ID.
     */
    public function getID(): string
    {
        return 'launchpad';
    }//end getID()

    /**
     * Get the section name.
     *
     * @return string The section name.
     */
    public function getName(): string
    {
        return $this->l->t(text: 'LaunchPad');
    }//end getName()

    /**
     * Get the section priority.
     *
     * @return int The priority.
     */
    public function getPriority(): int
    {
        return 80;
    }//end getPriority()

    /**
     * Get the section icon URL.
     *
     * @return string The icon URL.
     */
    public function getIcon(): string
    {
        return $this->urlGenerator->imagePath(
            appName: Application::APP_ID,
            file: 'app-dark.svg'
        );
    }//end getIcon()
}//end class
