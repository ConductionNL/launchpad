<?php

/**
 * EmbedRequestContext
 *
 * The embed token a request was let in with (embedded-analytics task 6): set
 * by EmbedTokenMiddleware once the token is valid, read by the render route
 * and any controller downstream. Empty on every request without one.
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

/**
 * Holds the validated embed token of the current request.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
 */
class EmbedRequestContext {

	/**
	 * The token, once validated.
	 *
	 * @var EmbedToken|null
	 */
	private ?EmbedToken $token = null;

	/**
	 * How it arrived: `query` (?token=) or `bearer` (Authorization header).
	 *
	 * @var string
	 */
	private string $form = '';

	/**
	 * When the request started, in milliseconds, for the latency of its usage event.
	 *
	 * @var float
	 */
	private float $startedAt = 0.0;

	/**
	 * Record the token the request was let in with.
	 *
	 * @param EmbedToken $token     The validated token.
	 * @param string     $form      `query` or `bearer`.
	 * @param float      $startedAt Milliseconds since the epoch.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function admit(EmbedToken $token, string $form, float $startedAt): void {
		$this->token     = $token;
		$this->form      = $form;
		$this->startedAt = $startedAt;
	}//end admit()

	/**
	 * The token, or null when the request carries none.
	 *
	 * @return EmbedToken|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function token(): ?EmbedToken {
		return $this->token;
	}//end token()

	/**
	 * How the token arrived, '' without one.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function form(): string {
		return $this->form;
	}//end form()

	/**
	 * The milliseconds since the request was admitted.
	 *
	 * @param float $now Milliseconds since the epoch.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-004--read-only-enforcement-at-the-api-tier
	 */
	public function latencyMs(float $now): int {
		if ($this->startedAt <= 0.0) {
			return 0;
		}

		return max(0, (int)round($now - $this->startedAt));
	}//end latencyMs()

}//end class
