<?php

/**
 * ScheduledExportSftpConnectorTest
 *
 * SFTP uploads of export artefacts (REQ-SCH-005): host-key check before the
 * login, the size the server confirms, and clear failures when phpseclib is
 * missing, the key differs, the login is refused or an upload comes up short.
 *
 * @category  Test
 * @package   Unit\Service
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

namespace Unit\Service;

use OCA\LaunchPad\Service\ScheduledExportSftpConnector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A stand-in for phpseclib's SFTP client, recording what it was asked.
 */
class FakeSftpClient {

	/** @var list<FakeSftpClient> */
	public static array $made = [];

	public static string $hostKey = 'ssh-ed25519 ZmFrZS1ob3N0LWtleS1ieXRlcyE=';

	public static bool $loginOk = true;

	public static ?int $reportedSize = null;

	/** @var array<string, string> */
	public array $files = [];

	public ?string $loggedInAs = null;

	public function __construct(public string $host, public int $port) {
		self::$made[] = $this;
	}

	public function getServerPublicHostKey(): string {
		return self::$hostKey;
	}

	public function login(string $user, mixed $credential): bool {
		$this->loggedInAs = $user . ':' . (string)$credential;
		return self::$loginOk;
	}

	public function put(string $path, string $bytes): bool {
		$this->files[$path] = $bytes;
		return true;
	}

	public function filesize(string $path): int|false {
		return self::$reportedSize ?? strlen($this->files[$path] ?? '');
	}
}

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExportSftpConnector
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
 */
class ScheduledExportSftpConnectorTest extends TestCase {

	private const CONFIG = ['host' => 'sftp.example.nl', 'port' => 2222, 'username' => 'gm0355', 'password' => 's3cret', 'remotePath' => '/dropbox/'];

	/**
	 * Reset the fake client.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		FakeSftpClient::$made         = [];
		FakeSftpClient::$loginOk      = true;
		FakeSftpClient::$reportedSize = null;
	}//end setUp()

	/**
	 * The SHA-256 fingerprint of the fake host key, as `ssh-keygen -l` prints it.
	 *
	 * @return string
	 */
	private static function fingerprint(): string {
		$raw = base64_decode(explode(' ', FakeSftpClient::$hostKey)[1], true);
		return 'SHA256:' . rtrim(base64_encode(hash('sha256', (string)$raw, true)), '=');
	}//end fingerprint()

	/**
	 * Files are uploaded under the remote path and the confirmed sizes answered.
	 *
	 * @return void
	 */
	public function testUploadsAndAnswersTheConfirmedSizes(): void {
		$connector = new ScheduledExportSftpConnector([FakeSftpClient::class]);

		$sizes = $connector->upload(config: self::CONFIG + ['hostKeyFingerprint' => self::fingerprint()], files: ['a.csv' => 'x,y', 'b.xlsx' => 'PK..']);

		$client = FakeSftpClient::$made[0];
		$this->assertSame(['sftp.example.nl', 2222, 'gm0355:s3cret'], [$client->host, $client->port, $client->loggedInAs]);
		$this->assertSame(['/dropbox/a.csv' => 'x,y', '/dropbox/b.xlsx' => 'PK..'], $client->files);
		$this->assertSame(['a.csv' => 3, 'b.xlsx' => 4], $sizes);
	}//end testUploadsAndAnswersTheConfirmedSizes()

	/**
	 * A server with another host key is refused before any credential is sent.
	 *
	 * @return void
	 */
	public function testAnotherHostKeyIsRefusedBeforeTheLogin(): void {
		$connector = new ScheduledExportSftpConnector([FakeSftpClient::class]);

		try {
			$connector->upload(config: self::CONFIG + ['hostKeyFingerprint' => 'SHA256:somethingElse'], files: ['a.csv' => 'x']);
			$this->fail('A wrong host key was accepted.');
		} catch (RuntimeException $e) {
			$this->assertSame('The SFTP host key does not match the configured fingerprint.', $e->getMessage());
		}

		$this->assertNull(FakeSftpClient::$made[0]->loggedInAs);
	}//end testAnotherHostKeyIsRefusedBeforeTheLogin()

	/**
	 * A refused login and a short upload are failures with their reason.
	 *
	 * @return void
	 */
	public function testARefusedLoginAndAShortUploadFail(): void {
		$connector = new ScheduledExportSftpConnector([FakeSftpClient::class]);

		FakeSftpClient::$loginOk = false;
		try {
			$connector->upload(config: self::CONFIG, files: ['a.csv' => 'x']);
			$this->fail('A refused login passed.');
		} catch (RuntimeException $e) {
			$this->assertSame('The SFTP server refused the login.', $e->getMessage());
		}

		FakeSftpClient::$loginOk      = true;
		FakeSftpClient::$reportedSize = 1;
		$this->expectExceptionMessage('The SFTP server reports 1 bytes for a.csv, expected 3.');
		$connector->upload(config: self::CONFIG, files: ['a.csv' => 'x,y']);
	}//end testARefusedLoginAndAShortUploadFail()

	/**
	 * Without phpseclib, the delivery fails and says why.
	 *
	 * @return void
	 */
	public function testWithoutPhpseclibItFailsClearly(): void {
		$connector = new ScheduledExportSftpConnector(['No\Such\Sftp']);

		$this->expectExceptionMessage('SFTP delivery needs phpseclib, which is not available on this server.');
		$connector->upload(config: self::CONFIG, files: ['a.csv' => 'x']);
	}//end testWithoutPhpseclibItFailsClearly()
}//end class
