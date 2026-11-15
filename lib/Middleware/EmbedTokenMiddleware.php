<?php

/**
 * EmbedTokenMiddleware
 *
 * Lets embed requests in only with a valid token and only within its scope
 * (embedded-analytics task 6, REQ-EMB-002/004). On the render route a token
 * is required, as `?token=` or a Bearer header; on any other route a Bearer
 * JWT issued by LaunchPad marks the request as an embed request. Either way
 * the token is validated and, for every method but GET and HEAD, the scope is
 * checked before the controller runs, so a read token cannot change state
 * whatever the frontend does (4.7, 4.10). A refusal on the render route is
 * also written as a usage event.
 *
 * @category  Middleware
 * @package   OCA\LaunchPad\Middleware
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

namespace OCA\LaunchPad\Middleware;

use Exception;
use OCA\LaunchPad\Controller\EmbedController;
use OCA\LaunchPad\Exception\EmbedRefusalException;
use OCA\LaunchPad\Service\Embed\EmbedGate;
use OCA\LaunchPad\Service\Embed\EmbedRequestContext;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;

/**
 * Validates embed tokens and enforces their scope at the API tier.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
 */
class EmbedTokenMiddleware extends Middleware {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request   The request.
	 * @param EmbedGate             $gate      The token and scope checks.
	 * @param EmbedRequestContext   $context   Where the admitted token is kept.
	 * @param UsageTelemetryService $telemetry The usage events.
	 */
	public function __construct(
		private readonly IRequest $request,
		private readonly EmbedGate $gate,
		private readonly EmbedRequestContext $context,
		private readonly UsageTelemetryService $telemetry,
	) {
	}//end __construct()

	/**
	 * Admit the request's embed token and check its scope.
	 *
	 * @param object $controller The controller.
	 * @param string $methodName The method.
	 *
	 * @return void
	 *
	 * @throws EmbedRefusalException When the token is missing, invalid or out of scope.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function beforeController($controller, $methodName): void {
		$renderRoute = ($controller instanceof EmbedController);
		if ($renderRoute === false && $this->gate->carriesEmbedToken(request: $this->request) === false) {
			return;
		}

		$startedAt = (microtime(true) * 1000);
		$admitted  = $this->gate->authenticate(request: $this->request, allowQuery: $renderRoute);
		$this->context->admit(token: $admitted['token'], form: $admitted['form'], startedAt: $startedAt);

		$refusal = $this->gate->scopeRefusal(
			method: (string)$this->request->getMethod(),
			path: (string)$this->request->getPathInfo(),
			token: $admitted['token']
		);
		if ($refusal !== null) {
			throw $refusal;
		}
	}//end beforeController()

	/**
	 * Answer a refusal with its status, body and headers; on the render route
	 * also write it as a usage event. Any other exception is not ours.
	 *
	 * @param object    $controller The controller.
	 * @param string    $methodName The method.
	 * @param Exception $exception  The exception.
	 *
	 * @return Response
	 *
	 * @throws Exception When the exception is not an embed refusal.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function afterException($controller, $methodName, Exception $exception): Response {
		if (($exception instanceof EmbedRefusalException) === false) {
			throw $exception;
		}

		if ($controller instanceof EmbedController) {
			$this->recordRefusal(refusal: $exception);
		}

		$response = new JSONResponse(data: $exception->body, statusCode: $exception->status);
		foreach ($exception->headers as $name => $value) {
			$response->addHeader($name, $value);
		}

		return $response;
	}//end afterException()

	/**
	 * Write a refused render as a page view, when the token behind it is known.
	 *
	 * @param EmbedRefusalException $refusal The refusal.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	private function recordRefusal(EmbedRefusalException $refusal): void {
		$tokenId = ($this->context->token()->id ?? $refusal->tokenId);
		if ($tokenId === '') {
			return;
		}

		$this->telemetry->record(
			tokenId: $tokenId,
			eventType: 'pageView',
			origin: $this->request->getHeader('Origin'),
			referer: $this->request->getHeader('Referer'),
			userAgent: $this->request->getHeader('User-Agent'),
			viewport: $this->viewport(),
			status: $refusal->status,
			latencyMs: $this->context->latencyMs(now: (microtime(true) * 1000))
		);
	}//end recordRefusal()

	/**
	 * The viewport the SDK reported, null without one.
	 *
	 * @return string|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	private function viewport(): ?string {
		$viewport = $this->request->getParam('viewport');
		if (is_string($viewport) === false || $viewport === '') {
			return null;
		}

		return $viewport;
	}//end viewport()

}//end class
