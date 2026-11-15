<?php

/**
 * ResponseHelperEnvelopeGoldenTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
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

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\RespondsWithErrors;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the error envelopes of RespondsWithErrors and of
 * ResponseHelper::invalidArgument() to the responses the controllers built
 * inline before, for every message and status the controllers pass. The
 * fixture holds the inline responses, recorded with LAUNCHPAD_GOLDEN_RECORD=1.
 *
 * @covers \OCA\LaunchPad\Controller\ResponseHelper
 * @covers \OCA\LaunchPad\Controller\RespondsWithErrors
 */
class ResponseHelperEnvelopeGoldenTest extends TestCase {
	use RespondsWithErrors;

	private const FIXTURE = __DIR__ . '/fixtures/response-helper-envelopes.json';

	private const CASES = __DIR__ . '/fixtures/response-helper-envelope-cases.json';

	/**
	 * The recorded call-site cases.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach (json_decode((string)file_get_contents(self::CASES), true) as [$kind, $value, $status]) {
			$cases[$kind . ' ' . $status . ' ' . $value] = [$kind, $value, $status];
		}

		return $cases;
	}

	/**
	 * The helper answers exactly what the inline construction answered.
	 *
	 * @param string $kind   failure, errorCode, badRequest or invalidArgument.
	 * @param string $value  The message, the code, or `code|message`.
	 * @param string $status The Http status constant name.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testEnvelopeMatchesTheInlineResponse(string $kind, string $value, string $status): void {
		$key = $kind . ' ' . $status . ' ' . $value;
		$statusCode = (int)constant(Http::class . '::' . $status);
		if (getenv('LAUNCHPAD_GOLDEN_RECORD') === '1') {
			$recorded = is_file(self::FIXTURE) ? json_decode((string)file_get_contents(self::FIXTURE), true) : [];
			$recorded[$key] = $this->shape($this->inline(kind: $kind, value: $value, statusCode: $statusCode));
			ksort($recorded);
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
			$this->addToAssertionCount(1);
			return;
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true)[$key] ?? null;
		$this->assertNotNull($expected, 'no recording for ' . $key);
		$this->assertSame($expected, $this->shape($this->helper(kind: $kind, value: $value, statusCode: $statusCode)));
	}

	/**
	 * The response as the controllers built it inline.
	 *
	 * @param string $kind       The helper kind.
	 * @param string $value      The case value.
	 * @param int    $statusCode The status.
	 *
	 * @return JSONResponse The response.
	 */
	private function inline(string $kind, string $value, int $statusCode): JSONResponse {
		if ($kind === 'invalidArgument') {
			return ResponseHelper::invalidArgument(message: $value);
		}

		if ($kind === 'failure') {
			return new JSONResponse(data: ['error' => $value], statusCode: $statusCode);
		}

		if ($kind === 'errorCode') {
			return new JSONResponse(data: ['status' => 'error', 'error' => $value], statusCode: $statusCode);
		}

		[$code, $message] = explode('|', $value, 2);
		return new JSONResponse(data: ['status' => 'error', 'error' => $code, 'message' => $message], statusCode: $statusCode);
	}

	/**
	 * The response from the helper.
	 *
	 * @param string $kind       The helper kind.
	 * @param string $value      The case value.
	 * @param int    $statusCode The status.
	 *
	 * @return JSONResponse The response.
	 */
	private function helper(string $kind, string $value, int $statusCode): JSONResponse {
		if ($kind === 'invalidArgument') {
			return ResponseHelper::invalidArgument(message: $value);
		}

		if ($kind === 'failure') {
			return $this->failure(message: $value, statusCode: $statusCode);
		}

		if ($kind === 'errorCode') {
			return $this->errorCode(code: $value, statusCode: $statusCode);
		}

		[$code, $message] = explode('|', $value, 2);
		return $this->badRequestWithCode(code: $code, message: $message);
	}

	/**
	 * What a client sees of a response: its class, status and body.
	 *
	 * @param JSONResponse $response The response.
	 *
	 * @return array<string, mixed> The shape.
	 */
	private function shape(JSONResponse $response): array {
		return [
			'class' => $response::class,
			'status' => $response->getStatus(),
			'data' => $response->getData(),
			'body' => $response->render(),
		];
	}
}
