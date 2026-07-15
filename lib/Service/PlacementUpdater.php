<?php

/**
 * PlacementUpdater
 *
 * Service for applying grid and display updates to widget placements.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Service for applying grid and display updates to widget placements.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class PlacementUpdater
{
    /**
     * Apply grid position and size updates to a placement.
     *
     * @param WidgetPlacement $placement The placement entity.
     * @param array           $data      The update data.
     *
     * @return void
     *
     * @spec openspec/specs/widgets/spec.md
     */
    public function applyGridUpdates(
        WidgetPlacement $placement,
        array $data
    ): void {
        if (isset($data['gridX']) === true) {
            $placement->setGridX($data['gridX']);
        }

        if (isset($data['gridY']) === true) {
            $placement->setGridY($data['gridY']);
        }

        if (isset($data['gridWidth']) === true) {
            $placement->setGridWidth($data['gridWidth']);
        }

        if (isset($data['gridHeight']) === true) {
            $placement->setGridHeight(
                $data['gridHeight']
            );
        }
    }//end applyGridUpdates()

    /**
     * Apply display and style updates to a placement.
     *
     * @param WidgetPlacement $placement The placement entity.
     * @param array           $data      The update data.
     *
     * @return void
     *
     * @spec openspec/specs/widgets/spec.md
     */
    public function applyDisplayUpdates(
        WidgetPlacement $placement,
        array $data
    ): void {
        if (isset($data['isVisible']) === true) {
            $placement->setIsVisible($data['isVisible']);
        }

        if (isset($data['showTitle']) === true) {
            $placement->setShowTitle($data['showTitle']);
        }

        if (isset($data['customTitle']) === true) {
            $placement->setCustomTitle(
                $data['customTitle']
            );
        }

        if (isset($data['customIcon']) === true) {
            $placement->setCustomIcon(
                $data['customIcon']
            );
        }

        if (isset($data['styleConfig']) === true) {
            $placement->setStyleConfigArray(
                $data['styleConfig']
            );
        }

        if (isset($data['content']) === true && is_array($data['content']) === true) {
            $placement->setContentArray(
                $data['content']
            );
        }
    }//end applyDisplayUpdates()

    /**
     * Apply mandatory-read acknowledgement updates to a placement and mint
     * the stable `announcementKey` the first time acknowledgement is
     * required. REQ-ACK-001.
     *
     * When `requiresAcknowledgement` is set to `1` and the placement does
     * not yet carry an `announcementKey`, a fresh v4 UUID is minted so all
     * recipients cloned from this (blueprint) placement share one identity
     * (design D2). Clearing the requirement (`requiresAcknowledgement = 0`)
     * does not delete the key or any receipts — receipts are retained as
     * history (REQ-ACK-001 scenario "Clearing the requirement...").
     *
     * @param WidgetPlacement $placement The placement entity.
     * @param array           $data      The update data.
     *
     * @return void
     *
     * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
     */
    public function applyAcknowledgementUpdates(
        WidgetPlacement $placement,
        array $data
    ): void {
        if (isset($data['requiresAcknowledgement']) === true) {
            $placement->setRequiresAcknowledgement(
                (int) ((bool) $data['requiresAcknowledgement'])
            );
        }

        if (array_key_exists('acknowledgementPrompt', $data) === true) {
            $prompt      = $data['acknowledgementPrompt'];
            $promptValue = null;
            if ($prompt !== null) {
                $promptValue = (string) $prompt;
            }

            $placement->setAcknowledgementPrompt($promptValue);
        }

        if (array_key_exists('acknowledgementDeadline', $data) === true) {
            $deadline      = $data['acknowledgementDeadline'];
            $deadlineValue = null;
            if ($deadline !== null && $deadline !== '') {
                $deadlineValue = (string) $deadline;
            }

            $placement->setAcknowledgementDeadline($deadlineValue);
        }

        if (isset($data['reacknowledgeOnChange']) === true) {
            $placement->setReacknowledgeOnChange(
                (int) ((bool) $data['reacknowledgeOnChange'])
            );
        }

        if (isset($data['acknowledgementContentVersion']) === true) {
            $version = (int) $data['acknowledgementContentVersion'];
            if ($version >= 1) {
                $placement->setAcknowledgementContentVersion($version);
            }
        }

        // Mint the stable announcement identity the first time the
        // requirement is enabled (REQ-ACK-001 / design D2).
        if ($placement->getRequiresAcknowledgement() === 1
            && ($placement->getAnnouncementKey() === null
            || $placement->getAnnouncementKey() === '')
        ) {
            $placement->setAnnouncementKey($this->generateUuid());
        }
    }//end applyAcknowledgementUpdates()

    /**
     * Generate a v4 UUID using random_bytes (no external dependency).
     * Mirrors `TemplateService::generateUuid()`.
     *
     * @return string A v4 UUID.
     */
    private function generateUuid(): string
    {
        $data    = random_bytes(length: 16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);
        return vsprintf(
            format: '%s%s-%s-%s-%s-%s%s%s',
            values: str_split(string: bin2hex(string: $data), length: 4)
        );
    }//end generateUuid()
}//end class
