/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Build a solid-colour PNG of any size, in memory, for specs that need an
 * image with known dimensions.
 *
 * `fixtures/tiny.png` is 1x1, which is the right size for an upload but the
 * wrong one for anything about aspect ratio, and a 1x1 source is also a poor
 * input for Nextcloud's preview generator. This writes a real, decodable PNG
 * (magic bytes, IHDR, one IDAT, IEND) with Node's own zlib, so no binary
 * fixture has to be checked in for every size a spec wants.
 */

import { deflateSync } from 'zlib'

const CRC_TABLE: number[] = (() => {
	const table: number[] = []
	for (let n = 0; n < 256; n++) {
		let c = n
		for (let k = 0; k < 8; k++) {
			c = (c & 1) !== 0 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
		}
		table.push(c >>> 0)
	}
	return table
})()

/**
 * The PNG chunk CRC (ISO 3309 CRC-32) of a buffer.
 *
 * @param buf the chunk type plus data.
 * @return the CRC, as a non-negative 32-bit number.
 */
function crc32(buf: Buffer): number {
	let c = 0xffffffff
	for (const byte of buf) {
		c = CRC_TABLE[(c ^ byte) & 0xff] ^ (c >>> 8)
	}
	return (c ^ 0xffffffff) >>> 0
}

/**
 * One length-prefixed, CRC-suffixed PNG chunk.
 *
 * @param type the four-letter chunk type.
 * @param data the chunk payload.
 * @return the encoded chunk.
 */
function chunk(type: string, data: Buffer): Buffer {
	const length = Buffer.alloc(4)
	length.writeUInt32BE(data.length, 0)
	const typed = Buffer.concat([Buffer.from(type, 'ascii'), data])
	const crc = Buffer.alloc(4)
	crc.writeUInt32BE(crc32(typed), 0)
	return Buffer.concat([length, typed, crc])
}

/**
 * A solid-colour, 8-bit RGBA PNG.
 *
 * @param width pixels across.
 * @param height pixels down.
 * @param rgba the fill colour as [r, g, b, a], each 0-255.
 * @return the PNG file bytes.
 */
export function solidPng(
	width: number,
	height: number,
	rgba: [number, number, number, number] = [200, 30, 30, 255],
): Buffer {
	const header = Buffer.alloc(13)
	header.writeUInt32BE(width, 0)
	header.writeUInt32BE(height, 4)
	header[8] = 8 // bit depth
	header[9] = 6 // colour type: RGBA
	header[10] = 0 // compression
	header[11] = 0 // filter
	header[12] = 0 // interlace

	const row = Buffer.alloc(1 + width * 4)
	row[0] = 0 // filter type "None" for this scanline
	for (let x = 0; x < width; x++) {
		row.set(rgba, 1 + x * 4)
	}
	const raw = Buffer.concat(Array.from({ length: height }, () => row))

	return Buffer.concat([
		Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
		chunk('IHDR', header),
		chunk('IDAT', deflateSync(raw)),
		chunk('IEND', Buffer.alloc(0)),
	])
}
