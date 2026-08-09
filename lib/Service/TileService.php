<?php

/**
 * TileService
 *
 * Read-only access to the legacy `oc_launchpad_tiles` reusable-entity table.
 *
 * This service used to carry createTile/updateTile/deleteTile as well. All
 * three were removed: the endpoints above them — TileApiController's create,
 * update and destroy — return HTTP 410 Gone unconditionally and never called
 * them, and nothing else in the app, the CLI commands or the migrations did
 * either. Their docblocks claimed they were "preserved for legacy callers and
 * migration tooling", and there were none of either.
 *
 * That is not merely dead code. A write path with no caller, sitting under a
 * permanently-410 endpoint, is a way to put rows back into a table the app has
 * deliberately stopped writing to (REQ-WDG-022 / REQ-TILE-PLACEMENT moved tile
 * creation onto widget placements) — the deprecation would be bypassed by
 * whoever wired it up next, with nothing to warn them.
 *
 * The table is still READ: getUserTiles backs `GET /api/tiles`, so existing
 * rows stay visible. Read-only is the intended end state, and it is now the
 * only state this class can express.
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

use OCA\LaunchPad\Db\Tile;
use OCA\LaunchPad\Db\TileMapper;

class TileService
{
    /**
     * Constructor
     *
     * @param TileMapper $tileMapper The tile mapper.
     */
    public function __construct(
        private readonly TileMapper $tileMapper,
    ) {
    }//end __construct()

    /**
     * Get all tiles for a user.
     *
     * @param string $userId The user ID.
     *
     * @return Tile[] Array of tiles.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-29
     */
    public function getUserTiles(string $userId): array
    {
        return $this->tileMapper->findByUserId(userId: $userId);
    }//end getUserTiles()

}//end class
