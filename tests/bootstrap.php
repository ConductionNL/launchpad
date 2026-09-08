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
//
// "Pre-configured" means INSTALLED, not merely checked out. `lib/base.php`
// from a source tree that was never installed (the workspace checkout above
// apps-extra/ has a 0-byte config/config.php) still declares `OC` and builds
// `\OC::$server` before it throws "Not installed". That server cannot be
// undone (`OC::$server` is a typed static), so from then on every
// `\OC::$server->get()` in the code under test hits a container that knows
// none of this app's registrations and autowires from scratch; constructor
// cycles then recurse until memory runs out (19 GB and 6 GB of swap in one
// openregister run on 2026-09-08). The decision therefore has to be made
// BEFORE base.php is loaded, on the `installed` flag in config/config.php.

/**
 * Tell whether a Nextcloud root is an INSTALLED instance, not just a source tree.
 *
 * @param string $ncRoot Candidate Nextcloud root.
 *
 * @return bool True when config/config.php declares `installed => true`.
 */
function launchpad_nc_root_is_installed(string $ncRoot): bool
{
	$configFile = $ncRoot . '/config/config.php';
	if (is_file($configFile) === false || filesize($configFile) === 0) {
		return false;
	}

	// The config file is a plain `$CONFIG = [...]` script; including it in a
	// closure keeps `$CONFIG` out of the global scope.
	$config = (static function () use ($configFile): array {
		$CONFIG = [];
		try {
			include $configFile;
		} catch (\Throwable) {
			return [];
		}

		if (is_array($CONFIG) === false) {
			return [];
		}

		return $CONFIG;
	})();

	return ($config['installed'] ?? false) === true;
}//end launchpad_nc_root_is_installed()

$ncRoot = realpath(__DIR__ . '/../../..');
$ncBasePhp = __DIR__ . '/../../../lib/base.php';
$ncRequested = (file_exists($ncBasePhp) === true)
	&& getenv('PHPUNIT_USE_NC_BOOTSTRAP') === '1';
$ncUseFull = $ncRequested === true
	&& $ncRoot !== false
	&& launchpad_nc_root_is_installed($ncRoot) === true;
if ($ncRequested === true && $ncUseFull === false) {
	fwrite(
		STDERR,
		sprintf(
			"[launchpad/tests/bootstrap] Nextcloud root at %s is not an installed instance (config/config.php lacks installed => true); "
			. "skipping lib/base.php and running with composer autoload and OCP stubs only (pure-unit mode).\n",
			(string) $ncRoot
		)
	);
}

if ($ncUseFull === true) {
	try {
		include_once $ncBasePhp;
	} catch (\Throwable $e) {
		// The tree IS installed, so the dangerous case this guard exists for
		// (loading a bare source tree) did not happen. base.php still failed
		// part-way.
		//
		// This does NOT abort. `OC::$server` is a typed static, so a half-built
		// container cannot be unset, and aborting was tried: it turned all six
		// PHPUnit legs red on a suite that passes (humaniq, 2026-09-08). The
		// runaway this guard exists for needs an autowiring lookup to reach the
		// poisoned container, this app has none in lib, and phpunit.xml's 2G cap
		// bounds one anyway.
		//
		// So: say plainly that the container is unreliable, and let the pure unit
		// tests run. A container-bound test failing loudly is the intended outcome.
		fwrite(
			STDERR,
			sprintf(
				"[launchpad/tests/bootstrap] Nextcloud at %s could not finish booting (%s).\n"
				. "  \\OC::\$server now holds a HALF-BUILT container and cannot be unset. Pure unit tests\n"
				. "  continue; anything resolving a service from that container is UNVERIFIED by this run.\n",
				(string) $ncRoot,
				$e->getMessage()
			)
		);
	}
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
