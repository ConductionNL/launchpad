<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Sendent B.V.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\SendentWorkspace;

/**
 * @psalm-type WorkspaceWidget = array{
 *     id: string,
 *     type: string,
 *     x: int,
 *     y: int,
 *     w: int,
 *     h: int,
 *     content?: mixed,
 * }
 *
 * @psalm-type WorkspaceLayout = array{
 *     widgets: list<WorkspaceWidget>,
 * }
 */
class ResponseDefinitions {
}
