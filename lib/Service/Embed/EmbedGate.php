<?php

/**
 * EmbedGate
 *
 * The checks an embed request passes before anything renders or runs
 * (embedded-analytics tasks 6, 7): where the token is (?token= or the
 * Authorization Bearer header), whether it is valid, whether the request's
 * method and action fit its scope, and whether the page framing it is one of
 * its host origins. Each refusal carries the exact body and headers the spec
 * prescribes.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

use OCA\LaunchPad\Entity\EmbedToken;
use OCA\LaunchPad\Exception\EmbedRefusalException;
use OCA\LaunchPad\Exception\InvalidTokenException;
use OCA\LaunchPad\Exception\TokenExpiredException;
use OCA\LaunchPad\Exception\TokenRevokedException;
use OCP\IRequest;

/**
 * Admits an embed request or says why not.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
 */
class EmbedGate {

	/**
	 * The token came as `?token=`.
	 *
	 * @var string
	 */
	public const FORM_QUERY = 'query';

	/**
	 * The token came in the Authorization Bearer header.
	 *
	 * @var string
	 */
	public const FORM_BEARER = 'bearer';

	/**
	 * Constructor.
	 *
	 * @param EmbedTokenService       $tokens The token store and validator.
	 * @param ScopeEnforcementService $scopes The scope decisions.
	 * @param JoseEncoding            $jose   The JWS segment encoding.
	 */
	public function __construct(
		private readonly EmbedTokenService $tokens,
		private readonly ScopeEnforcementService $scopes,
		private readonly JoseEncoding $jose,
	) {
	}//end __construct()

	/**
	 * The token a request carries and how: the Bearer header first, else
	 * `?token=` when the caller allows the query form. Null without one.
	 *
	 * @param IRequest $request    The request.
	 * @param bool     $allowQuery Whether `?token=` counts (only on the render route).
	 *
	 * @return array{jwt: string, form: string}|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function credentials(IRequest $request, bool $allowQuery): ?array {
		$header = trim((string)$request->getHeader('Authorization'));
		if (preg_match('/^Bearer\s+(\S+)$/i', $header, $match) === 1) {
			return ['jwt' => $match[1], 'form' => self::FORM_BEARER];
		}

		if ($allowQuery === false) {
			return null;
		}

		$query = $request->getParam('token');
		if (is_string($query) === true && trim($query) !== '') {
			return ['jwt' => trim($query), 'form' => self::FORM_QUERY];
		}

		return null;
	}//end credentials()

	/**
	 * Whether a request outside the render route carries a LaunchPad embed
	 * token: a Bearer JWT whose (still unverified) issuer is LaunchPad. Other
	 * Bearer credentials, such as app passwords, are left to Nextcloud.
	 *
	 * @param IRequest $request The request.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function carriesEmbedToken(IRequest $request): bool {
		$credentials = $this->credentials(request: $request, allowQuery: false);
		if ($credentials === null) {
			return false;
		}

		return ($this->claimsOf(jwt: $credentials['jwt'])['iss'] ?? null) === EmbedTokenService::ISSUER;
	}//end carriesEmbedToken()

	/**
	 * The valid token of a request and how it came, or the 401 refusal the
	 * spec gives for a missing, invalid, expired or revoked one.
	 *
	 * @param IRequest $request    The request.
	 * @param bool     $allowQuery Whether `?token=` counts.
	 *
	 * @return array{token: EmbedToken, form: string}
	 *
	 * @throws EmbedRefusalException When there is no valid token.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function authenticate(IRequest $request, bool $allowQuery): array {
		$credentials = $this->credentials(request: $request, allowQuery: $allowQuery);
		if ($credentials === null) {
			throw new EmbedRefusalException(
				status: 401,
				body: [
					'error' => 'missing_token',
					'message' => 'No token provided. Include ?token=<jwt> or use the Authorization: Bearer <jwt> header',
				],
				headers: ['WWW-Authenticate' => 'Bearer']
			);
		}

		try {
			$token = $this->tokens->validateToken(jwt: $credentials['jwt']);
		} catch (TokenExpiredException) {
			// Expiry and revocation are decided after the signature verified,
			// so the token id is genuine and its usage event can be written.
			throw self::unauthorized(
				error: 'token_expired',
				message: 'The provided token has expired',
				description: 'The token has expired',
				tokenId: $this->tokenIdOf(jwt: $credentials['jwt'])
			);
		} catch (TokenRevokedException) {
			throw self::unauthorized(
				error: 'token_revoked',
				message: 'The provided token has been revoked',
				description: 'The token has been revoked',
				tokenId: $this->tokenIdOf(jwt: $credentials['jwt'])
			);
		} catch (InvalidTokenException) {
			throw self::unauthorized(error: 'invalid_token', message: 'The provided token is invalid or malformed', description: 'The token is invalid');
		}

		return ['token' => $token, 'form' => $credentials['form']];
	}//end authenticate()

	/**
	 * The 403 for a request the token's scope does not allow, or null. GET and
	 * HEAD always pass; a write needs an interactions token naming its action.
	 *
	 * @param string     $method The HTTP method.
	 * @param string     $path   The request path.
	 * @param EmbedToken $token  The token.
	 *
	 * @return EmbedRefusalException|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function scopeRefusal(string $method, string $path, EmbedToken $token): ?EmbedRefusalException {
		$refusal = $this->scopes->refusalFor(method: $method, action: $this->scopes->actionOf(path: $path), scope: $token->scope);
		if ($refusal === null) {
			return null;
		}

		return new EmbedRefusalException(status: $refusal['status'], body: $refusal['body']);
	}//end scopeRefusal()

	/**
	 * The 403 for a page that frames the embed from an origin the token does
	 * not list, or null. The Referer's origin is checked when the browser says
	 * the request is cross-site, and always for a Bearer token; without a
	 * Referer (a privacy-preserving browser) the request passes and CSP
	 * frame-ancestors still holds.
	 *
	 * @param IRequest   $request The request.
	 * @param EmbedToken $token   The token.
	 * @param string     $form    How the token came.
	 *
	 * @return EmbedRefusalException|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
	 */
	public function originRefusal(IRequest $request, EmbedToken $token, string $form): ?EmbedRefusalException {
		$origin = self::originOf(url: (string)$request->getHeader('Referer'));
		if ($origin === '') {
			return null;
		}

		$crossSite = strtolower(trim((string)$request->getHeader('Sec-Fetch-Site'))) === 'cross-site';
		if ($crossSite === false && $form !== self::FORM_BEARER) {
			return null;
		}

		$allowed = array_map(static fn (mixed $host): string => rtrim(strtolower((string)$host), '/'), $token->hostOrigins);
		if (in_array($origin, $allowed, true) === true) {
			return null;
		}

		return new EmbedRefusalException(
			status: 403,
			body: ['error' => 'origin_mismatch', 'message' => 'This iframe cannot be embedded on this origin']
		);
	}//end originRefusal()

	/**
	 * The 403 for a token used on another subject; it does not say whether
	 * that subject exists.
	 *
	 * @return EmbedRefusalException
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function subjectMismatch(): EmbedRefusalException {
		return new EmbedRefusalException(
			status: 403,
			body: ['error' => 'scope_mismatch', 'message' => 'The provided token does not grant access to this resource']
		);
	}//end subjectMismatch()

	/**
	 * A 401 with its WWW-Authenticate challenge.
	 *
	 * @param string $error       The error code.
	 * @param string $message     The body message.
	 * @param string $description The challenge's error_description.
	 * @param string $tokenId     The genuine token id, or ''.
	 *
	 * @return EmbedRefusalException
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	private static function unauthorized(string $error, string $message, string $description, string $tokenId=''): EmbedRefusalException {
		return new EmbedRefusalException(
			status: 401,
			body: ['error' => $error, 'message' => $message],
			headers: ['WWW-Authenticate' => sprintf('Bearer error="invalid_token", error_description="%s"', $description)],
			tokenId: $tokenId
		);
	}//end unauthorized()

	/**
	 * The unverified claims of a JWT, [] when it is not one.
	 *
	 * @param string $jwt The token.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	private function claimsOf(string $jwt): array {
		$segments = explode('.', $jwt);
		if (count($segments) !== 3) {
			return [];
		}

		try {
			$claims = json_decode($this->jose->base64UrlDecode(segment: $segments[1]), true);
		} catch (InvalidTokenException) {
			return [];
		}

		if (is_array($claims) === false) {
			return [];
		}

		return $claims;
	}//end claimsOf()

	/**
	 * The `jti` of a JWT whose signature already verified.
	 *
	 * @param string $jwt The token.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
	 */
	private function tokenIdOf(string $jwt): string {
		$id = ($this->claimsOf(jwt: $jwt)['jti'] ?? '');
		if (is_string($id) === false) {
			return '';
		}

		return $id;
	}//end tokenIdOf()

	/**
	 * The lower-cased `scheme://host[:port]` of a URL, '' when it has none.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
	 */
	private static function originOf(string $url): string {
		$parts = parse_url(trim($url));
		if (is_array($parts) === false || isset($parts['scheme'], $parts['host']) === false) {
			return '';
		}

		$origin = strtolower($parts['scheme'] . '://' . $parts['host']);
		if (isset($parts['port']) === true) {
			$origin .= ':' . $parts['port'];
		}

		return $origin;
	}//end originOf()

}//end class
