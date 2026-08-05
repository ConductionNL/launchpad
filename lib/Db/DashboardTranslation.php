<?php

/**
 * DashboardTranslation Entity
 *
 * Represents a per-language content variant for a dashboard — a single row
 * in the oc_launchpad_dash_translations table holding the localised
 * widget tree, name, and description for one (dashboard, languageCode)
 * pair. REQ-DASH-038..044 (dashboard-language-content).
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Dashboard translation entity.
 *
 * @method string|null getDashboardUuid()
 * @method void setDashboardUuid(?string $dashboardUuid)
 * @method string|null getLanguageCode()
 * @method void setLanguageCode(?string $languageCode)
 * @method string|null getName()
 * @method void setName(?string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string|null getWidgetTreeJson()
 * @method void setWidgetTreeJson(?string $widgetTreeJson)
 * @method int getIsPrimary()
 * @method void setIsPrimary(int $isPrimary)
 * @method string|null getCreatedAt()
 * @method void setCreatedAt(?string $createdAt)
 * @method string|null getUpdatedAt()
 * @method void setUpdatedAt(?string $updatedAt)
 */
class DashboardTranslation extends Entity implements JsonSerializable
{

    /**
     * Default fallback locale when the dashboard owner has no Nextcloud
     * `core/lang` user preference set. REQ-DASH-039 (locale resolution).
     *
     * @var string
     */
    public const DEFAULT_LANGUAGE = 'en';

    /**
     * The dashboard UUID this translation belongs to.
     *
     * @var string|null
     */
    protected ?string $dashboardUuid = null;

    /**
     * The 2-character ISO 639-1 base language code (e.g. 'nl', 'en',
     * 'de', 'fr'). Stored normalised — see
     * {@see DashboardTranslationMapper::normaliseLanguageCode()} for the
     * normalisation rule. REQ-DASH-038, design D2.
     *
     * @var string|null
     */
    protected ?string $languageCode = null;

    /**
     * The localised dashboard name.
     *
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * The localised dashboard description.
     *
     * @var string|null
     */
    protected ?string $description = null;

    /**
     * The localised widget tree JSON blob. Mirrors the existing
     * `oc_launchpad_dashboards.widget_tree_json` column shape so the
     * serializer is interchangeable.
     *
     * @var string|null
     */
    protected ?string $widgetTreeJson = null;

    /**
     * Whether this variant is the primary fallback for the dashboard
     * (SMALLINT 0/1). Exactly one row per dashboard MUST be primary;
     * invariant enforced by service layer. REQ-DASH-038.
     *
     * @var integer
     */
    protected int $isPrimary = 0;

    /**
     * The creation timestamp.
     *
     * @var string|null
     */
    protected ?string $createdAt = null;

    /**
     * The update timestamp.
     *
     * @var string|null
     */
    protected ?string $updatedAt = null;

    /**
     * Constructor — registers column types.
     *
     * @return void
     */
    public function __construct()
    {
        $this->addType(fieldName: 'id', type: 'integer');
        $this->addType(fieldName: 'isPrimary', type: 'integer');
    }//end __construct()

    /**
     * Serialize to JSON.
     *
     * @return array The serialized translation.
     */
    public function jsonSerialize(): array
    {
        return [
            'id'             => $this->getId(),
            'dashboardUuid'  => $this->dashboardUuid,
            'languageCode'   => $this->languageCode,
            'name'           => $this->name,
            'description'    => $this->description,
            'widgetTreeJson' => $this->widgetTreeJson,
            'isPrimary'      => $this->isPrimary,
            'createdAt'      => $this->createdAt,
            'updatedAt'      => $this->updatedAt,
        ];
    }//end jsonSerialize()
}//end class
