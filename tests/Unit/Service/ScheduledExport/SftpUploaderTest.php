<?php

/**
 * SftpUploaderTest
 *
 * The SFTP channel's configuration (REQ-SCH-005): the host key is pinned and
 * required, password or key authentication, and the remote URL is built
 * from encoded segments with no traversal.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
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

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\DeliveryException;
use OCA\LaunchPad\Service\ScheduledExport\RenderResult;
use OCA\LaunchPad\Service\ScheduledExport\SftpUploader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\SftpUploader
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\RenderResult
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-23
 */
class SftpUploaderTest extends TestCase {

	/**
	 * Password auth pins the host key and sends the credentials.
	 *
	 * @return void
	 */
	public function testPasswordOptionsPinTheHostKey(): void {
		$options = (new SftpUploader())->options(config: ['hostKeySha256' => 'SHA256:abc=', 'username' => 'u', 'password' => 'p'], keyFile: null);

		$this->assertSame('abc=', $options[CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256]);
		$this->assertSame(CURLPROTO_SFTP, $options[CURLOPT_PROTOCOLS]);
		$this->assertSame('u:p', $options[CURLOPT_USERPWD]);
		$this->assertSame(CURLSSH_AUTH_PASSWORD, $options[CURLOPT_SSH_AUTH_TYPES]);
	}//end testPasswordOptionsPinTheHostKey()

	/**
	 * Key auth uses the key file and no password.
	 *
	 * @return void
	 */
	public function testKeyOptions(): void {
		$options = (new SftpUploader())->options(config: ['hostKeySha256' => 'abc', 'username' => 'u'], keyFile: '/tmp/k');

		$this->assertSame('/tmp/k', $options[CURLOPT_SSH_PRIVATE_KEYFILE]);
		$this->assertSame(CURLSSH_AUTH_PUBLICKEY, $options[CURLOPT_SSH_AUTH_TYPES]);
		$this->assertArrayNotHasKey(CURLOPT_USERPWD, $options);
	}//end testKeyOptions()

	/**
	 * Without a host key, or without credentials, nothing is sent.
	 *
	 * @return void
	 */
	public function testMissingHostKeyOrCredentialsIsRefused(): void {
		$uploader = new SftpUploader();
		try {
			$uploader->options(config: ['password' => 'p'], keyFile: null);
			$this->fail('expected a refusal');
		} catch (DeliveryException $e) {
			$this->assertSame('SFTP needs the server host key fingerprint (hostKeySha256)', $e->getMessage());
		}

		$this->expectExceptionMessage('SFTP needs a password or a private key');
		$uploader->upload(config: ['host' => 'h', 'hostKeySha256' => 'abc'], artefact: new RenderResult(format: 'csv', filename: 'a.csv', content: 'x'));
	}//end testMissingHostKeyOrCredentialsIsRefused()

	/**
	 * The URL encodes each segment; a bad host or .. is refused.
	 *
	 * @return void
	 */
	public function testUrl(): void {
		$uploader = new SftpUploader();
		$this->assertSame('sftp://sftp.cbs.nl:2222/dropbox/gemeente%200355/r%C3%A9sum%C3%A9.xlsx', $uploader->url(config: ['host' => 'sftp.cbs.nl', 'port' => 2222, 'remotePath' => '/dropbox/gemeente 0355/'], filename: 'résumé.xlsx'));
		$this->assertSame('sftp://h:22/a.csv', $uploader->url(config: ['host' => 'h'], filename: 'a.csv'));

		foreach ([['host' => 'h;rm'], ['host' => 'h', 'remotePath' => '/a/../etc']] as $config) {
			try {
				$uploader->url(config: $config, filename: 'a');
				$this->fail('expected a refusal');
			} catch (DeliveryException $e) {
				$this->assertNotSame('', $e->getMessage());
			}
		}
	}//end testUrl()
}//end class
