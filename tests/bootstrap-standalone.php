<?php

/**
 * Standalone bootstrap for unit tests without a running NC instance.
 *
 * Uses vendor/nextcloud/ocp stubs so PHPUnit can mock OCP interfaces.
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-10
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

define('PHPUNIT_RUN', 1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load Doctrine stubs first — IQueryBuilder references DBAL constants at parse time.
require_once __DIR__ . '/Stubs/DoctrineStubs.php';

$ocpLoader = new \Composer\Autoload\ClassLoader();
$ocpLoader->addPsr4('OCP\\', __DIR__ . '/../vendor/nextcloud/ocp/OCP/');
$ocpLoader->register();
