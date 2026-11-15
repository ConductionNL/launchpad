<?php

/**
 * RdpFileWriter Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\RdpFileWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The lines of an RDP file and its download name.
 */
#[CoversClass(RdpFileWriter::class)]
class RdpFileWriterTest extends TestCase {

	/**
	 * A published program through a gateway writes every setting, in order.
	 *
	 * @return void
	 */
	public function testAPublishedProgramThroughAGateway(): void {
		$this->assertSame(
			"full address:s:rds01.gemeente.local\r\nserver port:i:3389\r\nremoteapplicationmode:i:1\r\n"
			. "remoteapplicationprogram:s:||Belastingen\r\ngatewayhostname:s:rdgw.gemeente.nl\r\ngatewayusagemethod:i:1\r\n"
			. "use redirection server name:i:1\r\n",
			(new RdpFileWriter())->contents(connection: ['host' => 'rds01.gemeente.local', 'port' => 3389, 'remoteApp' => '||Belastingen', 'gateway' => 'rdgw.gemeente.nl'])
		);
	}//end testAPublishedProgramThroughAGateway()

	/**
	 * A plain desktop writes no program and no gateway lines.
	 *
	 * @return void
	 */
	public function testAPlainDesktop(): void {
		$this->assertSame(
			"full address:s:pc12\r\nserver port:i:3390\r\nuse redirection server name:i:1\r\n",
			(new RdpFileWriter())->contents(connection: ['host' => 'pc12', 'port' => 3390, 'remoteApp' => '', 'gateway' => ''])
		);
	}//end testAPlainDesktop()

	/**
	 * The download name keeps the title and drops what a file name cannot hold.
	 *
	 * @return void
	 */
	public function testTheFileNameFollowsTheTitle(): void {
		$writer = new RdpFileWriter();
		$this->assertSame('Belastingen (oud).rdp', $writer->fileName(title: 'Belastingen (oud)'));
		$this->assertSame('ab.rdp', $writer->fileName(title: "a/\\:*?\"<>|\nb"));
		$this->assertSame('remote-desktop.rdp', $writer->fileName(title: '  ..  '));
		$this->assertSame('remote-desktop.rdp', $writer->fileName(title: null));
	}//end testTheFileNameFollowsTheTitle()
	/**
	 * The header carries an ASCII copy and the encoded name.
	 *
	 * @return void
	 */
	public function testTheDispositionIsAnAttachment(): void {
		$this->assertSame(
			"attachment; filename=\"Caf__ (oud).rdp\"; filename*=UTF-8''Caf%C3%A9%20%28oud%29.rdp",
			(new RdpFileWriter())->disposition(fileName: 'Café (oud).rdp')
		);
	}//end testTheDispositionIsAnAttachment()
}//end class
