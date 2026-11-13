<?php

/**
 * Unit tests for UrlSafetyValidator.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\UrlSafetyValidator;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Which outbound URLs the widgets may fetch, and the admin host allow-list.
 *
 * Hosts are IP literals, `localhost` and a reserved `.invalid` name, so no
 * test depends on outside DNS.
 *
 * @covers \OCA\LaunchPad\Service\UrlSafetyValidator
 */
class UrlSafetyValidatorTest extends TestCase {

	/**
	 * A validator whose allow-list setting answers the given value.
	 *
	 * @param string $allowList The stored allow-list.
	 *
	 * @return UrlSafetyValidator
	 */
	private function validator(string $allowList = ''): UrlSafetyValidator {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($app === 'launchpad' && $key === 'allowed_hosts') ? $allowList : $default
		);

		return new UrlSafetyValidator($config);
	}//end validator()

	/**
	 * Only HTTPS to a resolvable public address is safe.
	 *
	 * @return void
	 */
	public function testOnlyHttpsToAPublicAddressIsSafe(): void {
		$validator = $this->validator();

		self::assertTrue($validator->isSafe('https://8.8.8.8/feed.ics'));
		self::assertFalse($validator->isSafe('http://8.8.8.8/feed.ics'), 'plain http');
		self::assertFalse($validator->isSafe('ftp://8.8.8.8/feed.ics'), 'another scheme');
		self::assertFalse($validator->isSafe('https:///feed.ics'), 'no host');
		self::assertFalse($validator->isSafe('//8.8.8.8'), 'no scheme');
		self::assertFalse($validator->isSafe('https://x.invalid/feed.ics'), 'does not resolve');
	}//end testOnlyHttpsToAPublicAddressIsSafe()

	/**
	 * Private, loopback and reserved addresses are refused.
	 *
	 * @return void
	 */
	public function testPrivateAndReservedAddressesAreRefused(): void {
		$validator = $this->validator();

		foreach (['https://10.0.0.1/', 'https://192.168.1.10/', 'https://172.16.0.5/', 'https://localhost/', 'https://127.0.0.1/', 'https://169.254.169.254/'] as $url) {
			self::assertFalse($validator->isSafe($url), $url);
		}
	}//end testPrivateAndReservedAddressesAreRefused()

	/**
	 * An empty, blank, unreadable or empty-list setting allows every host.
	 *
	 * @return void
	 */
	public function testAnUnsetAllowListAllowsEveryHost(): void {
		foreach (['', '   ', 'not json', '[]', '"calendar.example"'] as $stored) {
			self::assertTrue($this->validator($stored)->checkAllowList('https://anything.example/x', 'launchpad', 'allowed_hosts'), $stored);
		}
	}//end testAnUnsetAllowListAllowsEveryHost()

	/**
	 * A configured list allows exactly its hosts, case-insensitively, and nothing without a host.
	 *
	 * @return void
	 */
	public function testAConfiguredListAllowsExactlyItsHosts(): void {
		$validator = $this->validator('["Calendar.Example", 42, "news.example"]');

		self::assertTrue($validator->checkAllowList('https://calendar.example/a.ics', 'launchpad', 'allowed_hosts'));
		self::assertTrue($validator->checkAllowList('https://NEWS.example/rss', 'launchpad', 'allowed_hosts'));
		self::assertFalse($validator->checkAllowList('https://sub.calendar.example/a.ics', 'launchpad', 'allowed_hosts'));
		self::assertFalse($validator->checkAllowList('https://42/', 'launchpad', 'allowed_hosts'));
		self::assertFalse($validator->checkAllowList('not a url', 'launchpad', 'allowed_hosts'));
	}//end testAConfiguredListAllowsExactlyItsHosts()

	/**
	 * The list is read from the app and key the caller names.
	 *
	 * @return void
	 */
	public function testTheListIsReadFromTheNamedSetting(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->expects(self::once())->method('getValueString')
			->with('launchpad', 'news_widget_allowed_hosts', '')->willReturn('["news.example"]');

		self::assertFalse((new UrlSafetyValidator($config))->checkAllowList('https://other.example/', 'launchpad', 'news_widget_allowed_hosts'));
	}//end testTheListIsReadFromTheNamedSetting()
}//end class
