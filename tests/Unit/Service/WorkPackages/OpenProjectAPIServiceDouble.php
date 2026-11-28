<?php

/**
 * A stand-in for the official OpenProject integration's API service, with the
 * two methods launchpad calls, so the work packages reader can be tested
 * without the app.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\WorkPackages
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenProject\Service;

/**
 * Answers with what a test gives it and records the request.
 */
class OpenProjectAPIService {
	/**
	 * The token getAccessToken() answers.
	 *
	 * @var string
	 */
	public string $token = 'token';

	/**
	 * What request() answers.
	 *
	 * @var array<mixed>
	 */
	public array $response = [];

	/**
	 * The last request: user, endpoint and params.
	 *
	 * @var array<int, mixed>
	 */
	public array $lastRequest = [];

	/**
	 * The person's token.
	 *
	 * @param string|null $userId The person.
	 *
	 * @return string
	 */
	public function getAccessToken(?string $userId): string {
		return $this->token;
	}//end getAccessToken()

	/**
	 * Record the request and answer.
	 *
	 * @param string       $userId   The person.
	 * @param string       $endPoint The API path.
	 * @param array<mixed> $params   The query.
	 *
	 * @return array<mixed>
	 */
	public function request(string $userId, string $endPoint, array $params=[]): array {
		$this->lastRequest = [$userId, $endPoint, $params];
		return $this->response;
	}//end request()
}//end class
