import { describe, it, expect, afterEach } from 'vitest';
import {
	ZGW_COLLECTIONS,
	ZGW_COLLECTION_MAPPINGS,
	resolveSchemaPath,
	getEnvOverrideName,
} from '../src/collection-map.js';

describe('collection-map', () => {
	afterEach(() => {
		// Clean up any env vars set during tests
		delete process.env['ZRC_ZAAK_SCHEMA'];
	});

	it('contains all 22 ZGW collections', () => {
		expect(ZGW_COLLECTIONS).toHaveLength(22);
	});

	it('contains expected collection names', () => {
		const expected = [
			'zaken', 'statussen', 'resultaten', 'rollen',
			'zaakobjecten', 'zaakinformatieobjecten', 'zaakeigenschappen',
			'catalogussen', 'zaaktypen', 'statustypen', 'resultaattypen',
			'roltypen', 'eigenschappen', 'informatieobjecttypen', 'besluittypen',
			'zaaktypeinformatieobjecttypen', 'documenten', 'gebruiksrechten',
			'objectinformatieobjecten', 'besluiten', 'besluitinformatieobjecten',
			'notificaties',
		];
		for (const name of expected) {
			expect(ZGW_COLLECTIONS).toContain(name);
		}
	});

	it('resolves default schema path for zaken', () => {
		expect(resolveSchemaPath('zaken')).toBe('zgw/zrc/1.5.1/zaak.json');
	});

	it('resolves default schema path for notificaties', () => {
		expect(resolveSchemaPath('notificaties')).toBe('zgw/nrc/1.0.0/notificatie.json');
	});

	it('returns undefined for non-ZGW collection', () => {
		expect(resolveSchemaPath('directus_users')).toBeUndefined();
	});

	it('respects environment variable override', () => {
		process.env['ZRC_ZAAK_SCHEMA'] = 'custom/gemeente-x-zaak';
		expect(resolveSchemaPath('zaken')).toBe('custom/gemeente-x-zaak.json');
	});

	it('does not double-add .json extension', () => {
		process.env['ZRC_ZAAK_SCHEMA'] = 'custom/gemeente-x-zaak.json';
		expect(resolveSchemaPath('zaken')).toBe('custom/gemeente-x-zaak.json');
	});

	it('returns env override name for zaken', () => {
		expect(getEnvOverrideName('zaken')).toBe('ZRC_ZAAK_SCHEMA');
	});

	it('returns undefined env override for non-ZGW collection', () => {
		expect(getEnvOverrideName('directus_users')).toBeUndefined();
	});

	it('every mapping has a valid schemaPath ending in .json', () => {
		for (const mapping of ZGW_COLLECTION_MAPPINGS) {
			expect(mapping.schemaPath).toMatch(/\.json$/);
		}
	});
});
