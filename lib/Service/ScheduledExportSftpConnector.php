<?php

/**
 * ScheduledExportSftpConnector
 *
 * Uploads export artefacts over SFTP (REQ-SCH-005) with phpseclib, which
 * LaunchPad does not ship: Nextcloud server bundles phpseclib for external
 * storage (`phpseclib\Net\SFTP`, version 2) and a newer install may carry
 * version 3 (`phpseclib3\Net\SFTP`). Whichever is present is used; with
 * neither, the delivery fails with a clear error instead of pretending.
 *
 * The server's host key is checked against `channelConfig.hostKeyFingerprint`
 * (SHA-256 of the raw key, hex or `SHA256:` base64) when one is configured,
 * before any credential is sent.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use RuntimeException;

/**
 * One SFTP upload per call.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
 */
class ScheduledExportSftpConnector {

	/**
	 * The phpseclib SFTP classes tried, newest first.
	 *
	 * @var list<string>
	 */
	public const CLIENT_CLASSES = ['phpseclib3\Net\SFTP', 'phpseclib\Net\SFTP'];

	/**
	 * Constructor.
	 *
	 * @param list<string> $clientClasses The SFTP client classes to try.
	 */
	public function __construct(
		private readonly array $clientClasses=self::CLIENT_CLASSES,
	) {
	}//end __construct()

	/**
	 * Upload files and answer the size the server reports for each.
	 *
	 * @param array<string, mixed>  $config The recipient's `channelConfig`: host, port, username, password or privateKey, remotePath.
	 * @param array<string, string> $files  File name => bytes.
	 *
	 * @return array<string, int> File name => size on the server after the upload.
	 *
	 * @throws RuntimeException When phpseclib is missing, the host key does not match, login fails, or an upload is short.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function upload(array $config, array $files): array {
		$host = (string)($config['host'] ?? '');
		if ($host === '') {
			throw new RuntimeException('The SFTP recipient has no host.');
		}

		$sftp = $this->connect(host: $host, port: (int)($config['port'] ?? 22));
		$this->checkHostKey(sftp: $sftp, expected: $config['hostKeyFingerprint'] ?? null);

		if ($sftp->login((string)($config['username'] ?? ''), $this->credential(config: $config)) !== true) {
			throw new RuntimeException('The SFTP server refused the login.');
		}

		$remote = rtrim((string)($config['remotePath'] ?? '.'), '/');
		$sizes  = [];
		foreach ($files as $name => $bytes) {
			$target = $remote . '/' . $name;
			if ($sftp->put($target, $bytes) !== true) {
				throw new RuntimeException('The SFTP upload of ' . $name . ' failed.');
			}

			$size = $sftp->filesize($target);
			if ($size !== strlen($bytes)) {
				throw new RuntimeException('The SFTP server reports ' . var_export($size, true) . ' bytes for ' . $name . ', expected ' . strlen($bytes) . '.');
			}

			$sizes[$name] = $size;
		}

		return $sizes;
	}//end upload()

	/**
	 * A phpseclib SFTP client, version 3 or the one Nextcloud bundles.
	 *
	 * @param string $host The host.
	 * @param int    $port The port.
	 *
	 * @return object
	 *
	 * @throws RuntimeException When neither phpseclib is installed.
	 */
	protected function connect(string $host, int $port): object {
		foreach ($this->clientClasses as $class) {
			if (class_exists($class) === true) {
				return new $class($host, $port);
			}
		}

		throw new RuntimeException('SFTP delivery needs phpseclib, which is not available on this server.');
	}//end connect()

	/**
	 * Refuse a server whose host key is not the configured one.
	 *
	 * @param object $sftp     The client.
	 * @param mixed  $expected The configured fingerprint, or nothing.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the key differs or cannot be read.
	 */
	private function checkHostKey(object $sftp, mixed $expected): void {
		if (is_string($expected) === false || $expected === '') {
			return;
		}

		$key = $sftp->getServerPublicHostKey();
		if (is_string($key) === false || $key === '') {
			throw new RuntimeException('The SFTP server did not present a host key.');
		}

		$parts = explode(' ', $key);
		$raw   = base64_decode($parts[1] ?? '', true);
		if ($raw === false) {
			throw new RuntimeException('The SFTP host key could not be read.');
		}

		$digest = hash('sha256', $raw, true);
		$hex    = bin2hex($digest);
		$b64    = 'SHA256:' . rtrim(base64_encode($digest), '=');
		$given  = str_replace(':', '', strtolower($expected));
		if (hash_equals($hex, $given) === false && hash_equals($b64, rtrim($expected, '=')) === false) {
			throw new RuntimeException('The SFTP host key does not match the configured fingerprint.');
		}
	}//end checkHostKey()

	/**
	 * The password, or the private key loaded for the installed phpseclib.
	 *
	 * @param array<string, mixed> $config The recipient's `channelConfig`.
	 *
	 * @return mixed
	 */
	private function credential(array $config): mixed {
		$key = (string)($config['privateKey'] ?? '');
		if ($key === '') {
			return (string)($config['password'] ?? '');
		}

		$passphrase = (string)($config['passphrase'] ?? '');
		if (class_exists('phpseclib3\Crypt\PublicKeyLoader') === true) {
			$password = false;
			if ($passphrase !== '') {
				$password = $passphrase;
			}

			return \phpseclib3\Crypt\PublicKeyLoader::load($key, $password);
		}

		if (class_exists('phpseclib\Crypt\RSA') === true) {
			$rsa = new \phpseclib\Crypt\RSA();
			if ($passphrase !== '') {
				$rsa->setPassword($passphrase);
			}

			$rsa->loadKey($key);
			return $rsa;
		}

		return $key;
	}//end credential()
}//end class
