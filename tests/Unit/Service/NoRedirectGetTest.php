<?php

/**
 * NoRedirectGetTest
 *
 * Unit tests for NoRedirectGet: the GET it sends never follows redirects,
 * never throws on an HTTP error status and carries the given timeouts.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\NoRedirectGet;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for NoRedirectGet.
 */
#[CoversClass(NoRedirectGet::class)]
class NoRedirectGetTest extends TestCase {
	/**
	 * The client gets the URL with the timeouts and no redirects or HTTP
	 * errors, and its response is returned as is.
	 *
	 * @return void
	 */
	public function testSendsGuardedGetAndReturnsResponse(): void {
		$response = $this->createMock(IResponse::class);
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('get')
			->with(
				'https://feed.example/rss',
				[
					'connect_timeout' => 3,
					'timeout' => 9,
					'http_errors' => false,
					'allow_redirects' => false,
				]
			)
			->willReturn($response);

		$result = (new NoRedirectGet(connectTimeout: 3, timeout: 9))->send(client: $client, url: 'https://feed.example/rss');

		$this->assertSame($response, $result);
	}//end testSendsGuardedGetAndReturnsResponse()
}//end class
