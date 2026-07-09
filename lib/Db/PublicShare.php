<?php

/**
 * PublicShare Entity
 *
 * Represents a row in launchpad_public_shares — an anonymous read-only share
 * of a dashboard identified by a URL-safe token.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-2
 *
 * @method string|null getDashboardUuid()
 * @method void        setDashboardUuid(?string $dashboardUuid)
 * @method string|null getToken()
 * @method void        setToken(?string $token)
 * @method string|null getPasswordHash()
 * @method void        setPasswordHash(?string $passwordHash)
 * @method string|null getExpiresAt()
 * @method void        setExpiresAt(?string $expiresAt)
 * @method string|null getCreatedBy()
 * @method void        setCreatedBy(?string $createdBy)
 * @method string|null getCreatedAt()
 * @method void        setCreatedAt(?string $createdAt)
 * @method string|null getRevokedAt()
 * @method void        setRevokedAt(?string $revokedAt)
 * @method int|null    getViewCount()
 * @method void        setViewCount(?int $viewCount)
 * @method string|null getLastViewedAt()
 * @method void        setLastViewedAt(?string $lastViewedAt)
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Public share entity. jsonSerialize() omits passwordHash for security.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-2
 *
 * @method string|null getDashboardUuid()
 * @method void        setDashboardUuid(?string $dashboardUuid)
 * @method string|null getToken()
 * @method void        setToken(?string $token)
 * @method string|null getPasswordHash()
 * @method void        setPasswordHash(?string $passwordHash)
 * @method string|null getExpiresAt()
 * @method void        setExpiresAt(?string $expiresAt)
 * @method string|null getCreatedBy()
 * @method void        setCreatedBy(?string $createdBy)
 * @method string|null getCreatedAt()
 * @method void        setCreatedAt(?string $createdAt)
 * @method string|null getRevokedAt()
 * @method void        setRevokedAt(?string $revokedAt)
 * @method int|null    getViewCount()
 * @method void        setViewCount(?int $viewCount)
 * @method string|null getLastViewedAt()
 * @method void        setLastViewedAt(?string $lastViewedAt)
 */
class PublicShare extends Entity implements JsonSerializable
{

    /**
     * Dashboard UUID this share belongs to.
     *
     * @var string|null
     */
    protected ?string $dashboardUuid = null;

    /**
     * URL-safe 64-byte random access token.
     *
     * @var string|null
     */
    protected ?string $token = null;

    /**
     * BCrypt hash of the password; null means no password required.
     *
     * @var string|null
     */
    protected ?string $passwordHash = null;

    /**
     * Optional expiry timestamp (ISO 8601 string from the DB).
     *
     * @var string|null
     */
    protected ?string $expiresAt = null;

    /**
     * Nextcloud user ID who created this share.
     *
     * @var string|null
     */
    protected ?string $createdBy = null;

    /**
     * Creation timestamp.
     *
     * @var string|null
     */
    protected ?string $createdAt = null;

    /**
     * Soft-delete timestamp; null means the share is still active.
     *
     * @var string|null
     */
    protected ?string $revokedAt = null;

    /**
     * Debounced view count.
     *
     * @var integer|null
     */
    protected ?int $viewCount = null;

    /**
     * Timestamp of the last debounced view.
     *
     * @var string|null
     */
    protected ?string $lastViewedAt = null;

    /**
     * Computed public URL — populated by PublicShareMapper after load, not a DB column.
     *
     * @var string|null
     */
    private ?string $url = null;

    /**
     * Constructor — registers column types.
     *
     * @return void
     */
    public function __construct()
    {
        $this->addType(fieldName: 'id', type: 'integer');
        $this->addType(fieldName: 'viewCount', type: 'integer');
    }//end __construct()

    /**
     * Set the computed public URL (not stored in DB).
     *
     * @param string $url The absolute share URL.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-2
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }//end setUrl()

    /**
     * Get the computed public URL.
     *
     * @return string|null
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-2
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }//end getUrl()

    /**
     * Serialize to JSON — passwordHash is excluded.
     *
     * @return array
     *
     * @spec openspec/changes/dashboard-public-share/tasks.md#task-2
     */
    public function jsonSerialize(): array
    {
        return [
            'id'               => $this->getId(),
            'dashboardUuid'    => $this->dashboardUuid,
            'token'            => $this->token,
            'url'              => $this->url,
            'passwordRequired' => ($this->passwordHash !== null),
            'expiresAt'        => $this->expiresAt,
            'createdBy'        => $this->createdBy,
            'createdAt'        => $this->createdAt,
            'revokedAt'        => $this->revokedAt,
            'viewCount'        => $this->viewCount ?? 0,
            'lastViewedAt'     => $this->lastViewedAt,
        ];
    }//end jsonSerialize()
}//end class
