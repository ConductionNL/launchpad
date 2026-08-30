<?php

/**
 * Bootstrap file for Unit Tests
 *
 * This bootstrap loads the full Nextcloud environment since tests run inside
 * the Nextcloud Docker container. This gives access to \OC::$server and the
 * full DI container, enabling tests to cover code that depends on Nextcloud services.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

// Define that we're running PHPUnit.
define('PHPUNIT_RUN', 1);

// Include Composer's autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Nextcloud — use full NC environment when explicitly requested
// (PHPUNIT_USE_NC_BOOTSTRAP=1) or when the environment is pre-configured
// (e.g. a running NC container). Fall back to OCP stubs otherwise so that
// unit tests can run in any CI builder container without a live NC install.
$ncBasePhp = __DIR__ . '/../../../lib/base.php';
$ncUseFull = (file_exists($ncBasePhp) === true)
	&& getenv('PHPUNIT_USE_NC_BOOTSTRAP') === '1';
if ($ncUseFull === true) {
	include_once $ncBasePhp;
} elseif (is_dir(__DIR__ . '/../vendor/nextcloud/ocp/OCP') === true) {
	// Outside the container we register the OCP stubs from
	// vendor/nextcloud/ocp so unit tests that mock OCP interfaces
	// (e.g. IInitialState) can still run. These are signature-only
	// stubs and are sufficient for PHPUnit's createMock().
	// Doctrine DBAL placeholders MUST be loaded before the OCP PSR-4
	// loader is registered. IQueryBuilder.php contains class constants
	// that reference Doctrine\DBAL\ParameterType directly
	// (e.g. `PARAM_NULL = ParameterType::NULL`). PHP evaluates these
	// constant expressions as soon as the interface file is parsed —
	// before any mock creation code runs. Loading the stubs first
	// ensures the placeholder classes are in the class table before
	// the autoloader first touches IQueryBuilder.php.
	include_once __DIR__ . '/Stubs/DoctrineStubs.php';

	$ocpLoader = new \Composer\Autoload\ClassLoader();
	$ocpLoader->addPsr4('OCP\\', __DIR__ . '/../vendor/nextcloud/ocp/OCP/');
	$ocpLoader->register();
}//end if

// Register Test\ namespace for NC test classes.
$serverTestsLib = __DIR__ . '/../../../tests/lib/';
if (is_dir($serverTestsLib) === true) {
	$loader = new \Composer\Autoload\ClassLoader();
	$loader->addPsr4('Test\\', $serverTestsLib);
	$loader->register(true);
}

// Bootstrap complete.
