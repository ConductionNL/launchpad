<?php

/**
 * EmbedController
 *
 * The public embed render route (embedded-analytics tasks 9, 26, 28):
 * `GET /apps/launchpad/embed/{subjectType}/{subjectId}` serves the page an
 * iframe shows, `.../data` the subject it renders. EmbedTokenMiddleware has
 * already admitted the token; here the token must name this very subject (a
 * 403 that does not say whether another subject exists), the framing page
 * must be one of its host origins, and the request must fit its rate limit.
 * Every answer, refusals included, is written as a usage event unless
 * telemetry is switched off.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
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

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Exception\EmbedRefusalException;
use OCA\LaunchPad\Service\Embed\EmbedGate;
use OCA\LaunchPad\Service\Embed\EmbedRenderService;
use OCA\LaunchPad\Service\Embed\EmbedRequestContext;
use OCA\LaunchPad\Service\Embed\RateLimitService;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

/**
 * Serves embeds to the token that names them.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
 */
class EmbedController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest              $request   The request.
	 * @param EmbedRequestContext   $context   The admitted token.
	 * @param EmbedGate             $gate      The subject and origin checks.
	 * @param EmbedRenderService    $renderer  The subject and page.
	 * @param RateLimitService      $limits    The token buckets.
	 * @param UsageTelemetryService $telemetry The usage events.
	 * @param ITimeFactory          $time      The clock.
	 */
	public function __construct(
		IRequest $request,
		private readonly EmbedRequestContext $context,
		private readonly EmbedGate $gate,
		private readonly EmbedRenderService $renderer,
		private readonly RateLimitService $limits,
		private readonly UsageTelemetryService $telemetry,
		private readonly ITimeFactory $time,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The page an iframe shows for this subject.
	 *
	 * @param string $subjectType `widget` or `dashboard`.
	 * @param string $subjectId   Its id.
	 *
	 * @return Response
	 *
	 * @throws EmbedRefusalException When the token does not name this subject, the
	 *                               framing origin is not allowed or the rate limit is spent.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function render(string $subjectType, string $subjectId): Response {
		$token    = $this->admitted(subjectType: $subjectType, subjectId: $subjectId);
		$verdict  = $this->spend(token: $token);
		$response = $this->renderer->page(subjectType: $subjectType, subjectId: $subjectId, token: $token);
		$this->limitHeaders(response: $response, verdict: $verdict);
		$this->record(token: $token, eventType: 'pageView', status: Http::STATUS_OK);

		return $response;
	}//end render()

	/**
	 * The subject itself, as the embed page loads it: the widget or dashboard
	 * with the token's scope, the filters in force and the theme.
	 *
	 * @param string $subjectType `widget` or `dashboard`.
	 * @param string $subjectId   Its id.
	 *
	 * @return Response
	 *
	 * @throws EmbedRefusalException When the token does not name this subject, the
	 *                               framing origin is not allowed or the rate limit is spent.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function data(string $subjectType, string $subjectId): Response {
		$token   = $this->admitted(subjectType: $subjectType, subjectId: $subjectId);
		$verdict = $this->spend(token: $token);
		$filters = $this->request->getParam('filters', []);
		if (is_array($filters) === false) {
			$filters = [];
		}

		$payload = $this->renderer->render(subjectType: $subjectType, subjectId: $subjectId, token: $token, requestFilters: $filters);
		if ($payload === null) {
			$this->record(token: $token, eventType: 'widgetRendered', status: Http::STATUS_NOT_FOUND);
			return new JSONResponse(data: ['error' => 'not_found', 'message' => 'The embedded resource no longer exists'], statusCode: Http::STATUS_NOT_FOUND);
		}

		$response = new JSONResponse(data: $payload);
		$this->limitHeaders(response: $response, verdict: $verdict);
		$this->record(token: $token, eventType: 'widgetRendered', status: Http::STATUS_OK);

		return $response;
	}//end data()

	/**
	 * The admitted token, once it is shown to name this subject and the page
	 * framing it is one of its host origins.
	 *
	 * @param string $subjectType The subject type in the URL.
	 * @param string $subjectId   The subject id in the URL.
	 *
	 * @return EmbedToken
	 *
	 * @throws EmbedRefusalException When either check fails.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	private function admitted(string $subjectType, string $subjectId): EmbedToken {
		$token = $this->context->token();
		if ($token === null || $token->grants(type: $subjectType, id: $subjectId) === false) {
			throw $this->gate->subjectMismatch();
		}

		$refusal = $this->gate->originRefusal(request: $this->request, token: $token, form: $this->context->form());
		if ($refusal !== null) {
			throw $refusal;
		}

		return $token;
	}//end admitted()

	/**
	 * Take one request from the token's bucket, or refuse with 429.
	 *
	 * @param EmbedToken $token The token.
	 *
	 * @return array{allowed: bool, limit: int, remaining: int, retryAfter: int, resetAt: int}
	 *
	 * @throws EmbedRefusalException When the bucket is empty.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-007--rate-limiting-and-request-budgeting-per-token
	 */
	private function spend(EmbedToken $token): array {
		$verdict = $this->limits->checkRateLimit(tokenId: $token->id, policy: $token->rateLimitPolicy, now: (float)$this->time->getTime());
		if ($verdict['allowed'] === true) {
			return $verdict;
		}

		throw new EmbedRefusalException(
			status: Http::STATUS_TOO_MANY_REQUESTS,
			body: $this->limits->refusalBody(verdict: $verdict),
			headers: [
				'Retry-After'         => (string)$verdict['retryAfter'],
				'RateLimit-Limit'     => (string)$verdict['limit'],
				'RateLimit-Remaining' => '0',
				'RateLimit-Reset'     => (string)$verdict['retryAfter'],
			]
		);
	}//end spend()

	/**
	 * The RateLimit headers of an answered request; the reset is the seconds
	 * until the bucket is full again.
	 *
	 * @param Response                                                                  $response The response.
	 * @param array{allowed: bool, limit: int, remaining: int, retryAfter: int, resetAt: int} $verdict  The bucket verdict.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-007--rate-limiting-and-request-budgeting-per-token
	 */
	private function limitHeaders(Response $response, array $verdict): void {
		$response->addHeader('RateLimit-Limit', (string)$verdict['limit']);
		$response->addHeader('RateLimit-Remaining', (string)$verdict['remaining']);
		$response->addHeader('RateLimit-Reset', (string)max(0, ($verdict['resetAt'] - $this->time->getTime())));
	}//end limitHeaders()

	/**
	 * Write the answer as a usage event.
	 *
	 * @param EmbedToken $token     The token.
	 * @param string     $eventType The event type.
	 * @param int        $status    The status answered.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	private function record(EmbedToken $token, string $eventType, int $status): void {
		$viewport = $this->request->getParam('viewport');
		if (is_string($viewport) === false || $viewport === '') {
			$viewport = null;
		}

		$this->telemetry->record(
			tokenId: $token->id,
			eventType: $eventType,
			origin: $this->request->getHeader('Origin'),
			referer: $this->request->getHeader('Referer'),
			userAgent: $this->request->getHeader('User-Agent'),
			viewport: $viewport,
			status: $status,
			latencyMs: $this->context->latencyMs(now: (microtime(true) * 1000))
		);
	}//end record()

}//end class
