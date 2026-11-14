<?php

/**
 * SftpUploader
 *
 * The SFTP channel (REQ-SCH-005): an upload through libcurl's SFTP support
 * with the server's host key pinned (`hostKeySha256`, required), then a
 * size check of the uploaded file, which the delivery records.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

/**
 * Uploads one artefact over SFTP.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
 */
class SftpUploader {

	/**
	 * Upload and confirm the size on the server.
	 *
	 * @param array<string, mixed> $config   `host`, `port`, `username`, `password` or `privateKey`, `remotePath`, `hostKeySha256`.
	 * @param RenderResult         $artefact The artefact.
	 *
	 * @return int The size the server reports for the uploaded file.
	 *
	 * @throws DeliveryException When the configuration is incomplete, the upload fails or the sizes differ.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function upload(array $config, RenderResult $artefact): int {
		$keyFile = null;
		if (is_string($config['privateKey'] ?? null) === true && $config['privateKey'] !== '') {
			$keyFile = tempnam(sys_get_temp_dir(), 'lpk');
			file_put_contents((string)$keyFile, $config['privateKey']);
			chmod((string)$keyFile, 0600);
		}

		try {
			$url    = $this->url(config: $config, filename: $artefact->filename);
			$stream = fopen('php://temp', 'r+');
			fwrite($stream, $artefact->content);
			rewind($stream);
			$this->transfer(options: $this->options(config: $config, keyFile: $keyFile) + [
				CURLOPT_URL => $url,
				CURLOPT_UPLOAD => true,
				CURLOPT_INFILE => $stream,
				CURLOPT_INFILESIZE => strlen($artefact->content),
			]);
			fclose($stream);

			$check = $this->options(config: $config, keyFile: $keyFile) + [CURLOPT_URL => $url, CURLOPT_NOBODY => true];
			$size  = (int)$this->transfer(options: $check, info: CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		} finally {
			if ($keyFile !== null) {
				unlink($keyFile);
			}
		}

		if ($size !== strlen($artefact->content)) {
			throw new DeliveryException('The server reports ' . $size . ' bytes for ' . $artefact->filename . ', expected ' . strlen($artefact->content));
		}

		return $size;
	}//end upload()

	/**
	 * The curl options shared by the upload and the size check.
	 *
	 * @param array<string, mixed> $config  The channel configuration.
	 * @param string|null          $keyFile The private key file, when key authentication is used.
	 *
	 * @return array<int, mixed>
	 *
	 * @throws DeliveryException When the host key or the credentials are missing.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function options(array $config, ?string $keyFile): array {
		$fingerprint = (string)($config['hostKeySha256'] ?? '');
		if ($fingerprint === '') {
			throw new DeliveryException('SFTP needs the server host key fingerprint (hostKeySha256)');
		}

		$options = [
			CURLOPT_PROTOCOLS => CURLPROTO_SFTP,
			CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256 => preg_replace('/^SHA256:/', '', $fingerprint),
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_RETURNTRANSFER => true,
		];

		$user = (string)($config['username'] ?? '');
		if ($keyFile !== null) {
			$options[CURLOPT_USERNAME]            = $user;
			$options[CURLOPT_SSH_AUTH_TYPES]      = CURLSSH_AUTH_PUBLICKEY;
			$options[CURLOPT_SSH_PRIVATE_KEYFILE] = $keyFile;
			return $options;
		}

		if (is_string($config['password'] ?? null) === false || $config['password'] === '') {
			throw new DeliveryException('SFTP needs a password or a private key');
		}

		$options[CURLOPT_USERPWD]        = $user . ':' . $config['password'];
		$options[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PASSWORD;
		return $options;
	}//end options()

	/**
	 * The sftp:// URL of the remote file.
	 *
	 * @param array<string, mixed> $config   The channel configuration.
	 * @param string               $filename The file name.
	 *
	 * @return string
	 *
	 * @throws DeliveryException When the host is missing.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function url(array $config, string $filename): string {
		$host = (string)($config['host'] ?? '');
		if ($host === '' || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
			throw new DeliveryException('SFTP needs a valid host');
		}

		$segments = array_values(array_filter(explode('/', (string)($config['remotePath'] ?? '')), static fn (string $s): bool => $s !== ''));
		if (in_array('..', $segments, true) === true) {
			throw new DeliveryException('The SFTP remote path may not contain ..');
		}

		$segments[] = $filename;
		return 'sftp://' . $host . ':' . (int)($config['port'] ?? 22) . '/' . implode('/', array_map('rawurlencode', $segments));
	}//end url()

	/**
	 * Run one curl transfer.
	 *
	 * @param array<int, mixed> $options The options.
	 * @param int|null          $info    A CURLINFO value to return instead of the body.
	 *
	 * @return mixed
	 *
	 * @throws DeliveryException When the transfer fails.
	 */
	private function transfer(array $options, ?int $info = null): mixed {
		$handle = curl_init();
		curl_setopt_array($handle, $options);
		$body  = curl_exec($handle);
		$error = curl_error($handle);
		$value = $body;
		if ($info !== null) {
			$value = curl_getinfo($handle, $info);
		}

		curl_close($handle);

		if ($body === false) {
			throw new DeliveryException('SFTP transfer failed: ' . $error);
		}

		return $value;
	}//end transfer()
}//end class
