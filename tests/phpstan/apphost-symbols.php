<?php

/**
 * PHPStan stub for the OpenRegister AppHost engine classes (ADR-040).
 *
 * launchpad's HealthController / MetricsController extend these engine base
 * classes, which live in the sibling `openregister` app and are therefore not
 * present in launchpad's vendor/ during static analysis. PHPStan refuses to
 * suppress "extends unknown class" via ignoreErrors, so these skeletons give it
 * just enough to resolve the inheritance (and the named-argument factory wiring
 * in Application::registerObservability()) without pulling in OpenRegister.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

namespace OCA\OpenRegister\AppHost\Observability;

class ManifestLoader {
}

class HealthCheckExecutor {
}

class MetricsEngine {
}

namespace OCA\OpenRegister\AppHost\Controller;

use OCA\OpenRegister\AppHost\Observability\HealthCheckExecutor;
use OCA\OpenRegister\AppHost\Observability\ManifestLoader;
use OCA\OpenRegister\AppHost\Observability\MetricsEngine;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IRequest;

class GenericHealthController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		ManifestLoader $manifestLoader,
		HealthCheckExecutor $executor,
	) {
		parent::__construct($appName, $request);
	}

	public function index(): JSONResponse {
		return new JSONResponse();
	}
}

class GenericMetricsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		ManifestLoader $manifestLoader,
		MetricsEngine $engine,
	) {
		parent::__construct($appName, $request);
	}

	public function index(): TextPlainResponse {
		return new TextPlainResponse('');
	}
}
