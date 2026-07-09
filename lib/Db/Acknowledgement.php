<?php

/**
 * Acknowledgement Entity
 *
 * Represents one row in the `oc_launchpad_acknowledgements` table — a single
 * `(announcementKey, userId, contentVersion, acknowledgedAt)` read-receipt
 * recording that a user attested they read a mandatory-read announcement.
 * REQ-ACK-003.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Dashboard acknowledgement receipt entity.
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 *
 * @method string|null getAnnouncementKey()
 * @method void setAnnouncementKey(?string $announcementKey)
 * @method string|null getUserId()
 * @method void setUserId(?string $userId)
 * @method int getContentVersion()
 * @method void setContentVersion(int $contentVersion)
 * @method \DateTime|null getAcknowledgedAt()
 * @method void setAcknowledgedAt(?\DateTime $acknowledgedAt)
 */
class Acknowledgement extends Entity implements JsonSerializable
{

    /**
     * The stable announcement identity this receipt attests to.
     *
     * @var string|null
     */
    protected ?string $announcementKey = null;

    /**
     * The Nextcloud user ID of the acknowledging user.
     *
     * @var string|null
     */
    protected ?string $userId = null;

    /**
     * The content version that was acknowledged (REQ-ACK-005).
     *
     * @var integer
     */
    protected int $contentVersion = 1;

    /**
     * The instant the acknowledgement was recorded.
     *
     * @var \DateTime|null
     */
    protected ?DateTime $acknowledgedAt = null;

    /**
     * Constructor — registers column types.
     *
     * @return void
     */
    public function __construct()
    {
        $this->addType(fieldName: 'id', type: 'integer');
        $this->addType(fieldName: 'contentVersion', type: 'integer');
        $this->addType(fieldName: 'acknowledgedAt', type: 'datetime');
    }//end __construct()

    /**
     * Format the acknowledgement timestamp the same way other LaunchPad
     * timestamps are exposed in the JSON envelope.
     *
     * @return string|null `Y-m-d H:i:s` format or null when unset.
     *
     * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
     */
    public function getAcknowledgedAtFormatted(): ?string
    {
        if ($this->acknowledgedAt === null) {
            return null;
        }

        return $this->acknowledgedAt->format(format: 'Y-m-d H:i:s');
    }//end getAcknowledgedAtFormatted()

    /**
     * Serialize to JSON.
     *
     * @return array The serialized acknowledgement.
     *
     * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
     */
    public function jsonSerialize(): array
    {
        return [
            'id'              => $this->getId(),
            'announcementKey' => $this->announcementKey,
            'userId'          => $this->userId,
            'contentVersion'  => $this->contentVersion,
            'acknowledgedAt'  => $this->getAcknowledgedAtFormatted(),
        ];
    }//end jsonSerialize()
}//end class
