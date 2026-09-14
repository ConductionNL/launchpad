/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Build and read ZIP archives inside an e2e test.
 *
 * The export-import specs need two things no other spec needed: archives
 * crafted to be WRONG in one specific way (no manifest, a manifest that is not
 * JSON, a schema version this LaunchPad cannot read, one dashboard file out of
 * ten corrupt), and a way to look inside the archive the admin page hands back
 * from a download.
 *
 * WHY THERE IS NO LIBRARY HERE. `fflate` exists in `node_modules`, but only as
 * something another package depends on. Reaching into a transitive dependency
 * means this suite breaks the next time that package's tree changes, for a
 * reason that has nothing to do with LaunchPad, so this uses Node's own
 * `zlib` instead: STORE (no compression) on the way out, STORE and DEFLATE on
 * the way in. That is the whole of what these tests need, and it is the same
 * format `ZipArchive` reads on the server.
 */

import { inflateRawSync } from 'zlib'

/** One file in an archive. */
export interface ZipEntry {
	name: string
	content: string | Buffer
}

/** CRC-32, the checksum every ZIP entry carries. */
function crc32(buf: Buffer): number {
	let crc = -1
	for (let i = 0; i < buf.length; i++) {
		crc ^= buf[i]
		for (let bit = 0; bit < 8; bit++) {
			// 0xEDB88320 is the reversed polynomial ZIP uses.
			crc = crc & 1 ? (crc >>> 1) ^ 0xedb88320 : crc >>> 1
		}
	}
	return (crc ^ -1) >>> 0
}

/**
 * Build a ZIP archive from a list of entries, stored uncompressed.
 *
 * @param entries the files to put in the archive.
 * @return the archive bytes, ready to hand to a file input.
 */
export function buildZip(entries: ZipEntry[]): Buffer {
	const locals: Buffer[] = []
	const centrals: Buffer[] = []
	let offset = 0

	for (const entry of entries) {
		const name = Buffer.from(entry.name, 'utf8')
		const data = Buffer.isBuffer(entry.content)
			? entry.content
			: Buffer.from(entry.content, 'utf8')
		const sum = crc32(data)

		const local = Buffer.alloc(30 + name.length)
		local.writeUInt32LE(0x04034b50, 0) // local file header
		local.writeUInt16LE(20, 4) // version needed
		local.writeUInt16LE(0, 6) // flags
		local.writeUInt16LE(0, 8) // method: stored
		local.writeUInt32LE(0, 10) // time + date
		local.writeUInt32LE(sum, 14)
		local.writeUInt32LE(data.length, 18)
		local.writeUInt32LE(data.length, 22)
		local.writeUInt16LE(name.length, 26)
		local.writeUInt16LE(0, 28) // extra length
		name.copy(local, 30)
		locals.push(local, data)

		const central = Buffer.alloc(46 + name.length)
		central.writeUInt32LE(0x02014b50, 0) // central directory header
		central.writeUInt16LE(20, 4) // version made by
		central.writeUInt16LE(20, 6) // version needed
		central.writeUInt16LE(0, 8)
		central.writeUInt16LE(0, 10)
		central.writeUInt32LE(0, 12)
		central.writeUInt32LE(sum, 16)
		central.writeUInt32LE(data.length, 20)
		central.writeUInt32LE(data.length, 24)
		central.writeUInt16LE(name.length, 28)
		central.writeUInt32LE(0, 30) // extra + comment lengths
		central.writeUInt16LE(0, 36) // disk number
		central.writeUInt32LE(0, 38) // attributes
		central.writeUInt32LE(offset, 42)
		name.copy(central, 46)
		centrals.push(central)

		offset += local.length + data.length
	}

	const centralBytes = Buffer.concat(centrals)
	const end = Buffer.alloc(22)
	end.writeUInt32LE(0x06054b50, 0) // end of central directory
	end.writeUInt16LE(entries.length, 8)
	end.writeUInt16LE(entries.length, 10)
	end.writeUInt32LE(centralBytes.length, 12)
	end.writeUInt32LE(offset, 16)

	return Buffer.concat([...locals, centralBytes, end])
}

/**
 * Read an archive into a name-to-bytes map.
 *
 * Reads the central directory rather than scanning for local headers, so an
 * entry the archive lists but never wrote shows up as missing rather than
 * being silently skipped. Handles stored and deflated entries, which is
 * everything `ZipArchive` writes.
 *
 * @param buffer the archive bytes.
 * @return every entry, keyed by name. Directory entries are included as empty.
 */
export function readZip(buffer: Buffer): Record<string, Buffer> {
	const endIndex = buffer.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]))
	if (endIndex < 0) {
		throw new Error('not a ZIP archive: no end-of-central-directory record')
	}

	const count = buffer.readUInt16LE(endIndex + 10)
	let pointer = buffer.readUInt32LE(endIndex + 16)
	const out: Record<string, Buffer> = {}

	for (let i = 0; i < count; i++) {
		if (buffer.readUInt32LE(pointer) !== 0x02014b50) {
			throw new Error(`corrupt central directory at entry ${i}`)
		}
		const method = buffer.readUInt16LE(pointer + 10)
		const compressedSize = buffer.readUInt32LE(pointer + 20)
		const nameLength = buffer.readUInt16LE(pointer + 28)
		const extraLength = buffer.readUInt16LE(pointer + 30)
		const commentLength = buffer.readUInt16LE(pointer + 32)
		const localOffset = buffer.readUInt32LE(pointer + 42)
		const name = buffer
			.subarray(pointer + 46, pointer + 46 + nameLength)
			.toString('utf8')

		const localNameLength = buffer.readUInt16LE(localOffset + 26)
		const localExtraLength = buffer.readUInt16LE(localOffset + 28)
		const dataStart = localOffset + 30 + localNameLength + localExtraLength
		const raw = buffer.subarray(dataStart, dataStart + compressedSize)
		out[name] = method === 0 ? raw : inflateRawSync(raw)

		pointer += 46 + nameLength + extraLength + commentLength
	}

	return out
}

/**
 * Read one entry as JSON.
 *
 * @param archive the map `readZip` returned.
 * @param name the entry name.
 * @return the parsed value.
 */
export function readJsonEntry(archive: Record<string, Buffer>, name: string): any {
	const entry = archive[name]
	if (entry === undefined) {
		throw new Error(
			`archive has no ${name}; it holds: ${Object.keys(archive).join(', ')}`,
		)
	}
	return JSON.parse(entry.toString('utf8'))
}

/** The names of the dashboard files in an archive. */
export function dashboardEntries(archive: Record<string, Buffer>): string[] {
	return Object.keys(archive).filter(
		(name) => name.startsWith('dashboards/') && name.endsWith('.json'),
	)
}

/** A minimal valid dashboard payload for a crafted archive. */
export function dashboardPayload(
	uuid: string,
	name: string,
	extra: Record<string, unknown> = {},
): string {
	return JSON.stringify({ uuid, name, widgets: [], ...extra })
}

/** A manifest for a crafted archive. */
export function manifest(overrides: Record<string, unknown> = {}): string {
	return JSON.stringify({
		schemaVersion: 1,
		exportedAt: '2026-01-01T00:00:00Z',
		exportedBy: 'admin',
		launchpadVersion: 'launchpad/v1',
		scope: 'site',
		dashboardCount: 1,
		includedAssets: [],
		...overrides,
	})
}
