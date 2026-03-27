import { describe, it, expect } from 'vitest';
import type { ErrorObject } from 'ajv';
import { formatErrors } from '../src/error-formatter.js';

function makeError(overrides: Partial<ErrorObject> & { keyword: string }): ErrorObject {
	return {
		instancePath: '',
		schemaPath: '',
		params: {},
		message: '',
		...overrides,
	} as ErrorObject;
}

describe('formatErrors', () => {
	it('returns empty array for null/undefined errors', () => {
		expect(formatErrors(null)).toEqual([]);
		expect(formatErrors(undefined)).toEqual([]);
		expect(formatErrors([])).toEqual([]);
	});

	it('formats a required field error with correct field and Dutch message', () => {
		const ajvErrors: ErrorObject[] = [
			makeError({
				keyword: 'required',
				instancePath: '',
				params: { missingProperty: 'identificatie' },
			}),
		];

		const result = formatErrors(ajvErrors);
		expect(result).toHaveLength(1);
		expect(result[0]).toEqual({
			field: 'identificatie',
			rule: 'required',
			message: 'Dit veld is verplicht.',
		});
	});

	it('formats a type error with the expected type in Dutch', () => {
		const ajvErrors: ErrorObject[] = [
			makeError({
				keyword: 'type',
				instancePath: '/startdatum',
				params: { type: 'string' },
			}),
		];

		const result = formatErrors(ajvErrors);
		expect(result).toHaveLength(1);
		expect(result[0]).toEqual({
			field: 'startdatum',
			rule: 'type',
			message: 'Waarde moet van type string zijn.',
		});
	});

	it('formats a date format error in Dutch', () => {
		const ajvErrors: ErrorObject[] = [
			makeError({
				keyword: 'format',
				instancePath: '/startdatum',
				params: { format: 'date' },
			}),
		];

		const result = formatErrors(ajvErrors);
		expect(result[0]).toEqual({
			field: 'startdatum',
			rule: 'format',
			message: 'Waarde is geen geldige datum (JJJJ-MM-DD).',
		});
	});

	it('formats a URI format error in Dutch', () => {
		const result = formatErrors([
			makeError({
				keyword: 'format',
				instancePath: '/zaaktype',
				params: { format: 'uri' },
			}),
		]);

		expect(result[0]).toEqual({
			field: 'zaaktype',
			rule: 'format',
			message: 'Waarde is geen geldige URL.',
		});
	});

	it('formats an enum error listing allowed values', () => {
		const result = formatErrors([
			makeError({
				keyword: 'enum',
				instancePath: '/vertrouwelijkheidaanduiding',
				params: { allowedValues: ['openbaar', 'intern', 'geheim'] },
			}),
		]);

		expect(result[0]).toEqual({
			field: 'vertrouwelijkheidaanduiding',
			rule: 'enum',
			message: 'Ongeldige keuze. Geldige waarden zijn: openbaar, intern, geheim.',
		});
	});

	it('formats minLength error with correct count in Dutch', () => {
		const result = formatErrors([
			makeError({
				keyword: 'minLength',
				instancePath: '/omschrijving',
				params: { limit: 1 },
			}),
		]);

		expect(result[0]).toEqual({
			field: 'omschrijving',
			rule: 'minLength',
			message: 'Waarde moet minimaal 1 teken bevatten.',
		});
	});

	it('formats maxLength error with correct count in Dutch', () => {
		const result = formatErrors([
			makeError({
				keyword: 'maxLength',
				instancePath: '/omschrijving',
				params: { limit: 200 },
			}),
		]);

		expect(result[0]).toEqual({
			field: 'omschrijving',
			rule: 'maxLength',
			message: 'Waarde mag maximaal 200 tekens bevatten.',
		});
	});

	it('converts nested instancePath to dot notation', () => {
		const result = formatErrors([
			makeError({
				keyword: 'type',
				instancePath: '/verlenging/reden',
				params: { type: 'string' },
			}),
		]);

		expect(result[0].field).toBe('verlenging.reden');
	});

	it('converts array index in instancePath to dot notation', () => {
		const result = formatErrors([
			makeError({
				keyword: 'format',
				instancePath: '/eigenschappen/2/waarde',
				params: { format: 'date' },
			}),
		]);

		expect(result[0].field).toBe('eigenschappen.2.waarde');
	});

	it('formats a nested required error correctly', () => {
		const result = formatErrors([
			makeError({
				keyword: 'required',
				instancePath: '/verlenging',
				params: { missingProperty: 'reden' },
			}),
		]);

		expect(result[0].field).toBe('verlenging.reden');
	});

	it('collects multiple errors in a single pass', () => {
		const ajvErrors: ErrorObject[] = [
			makeError({
				keyword: 'required',
				instancePath: '',
				params: { missingProperty: 'identificatie' },
			}),
			makeError({
				keyword: 'format',
				instancePath: '/startdatum',
				params: { format: 'date' },
			}),
			makeError({
				keyword: 'enum',
				instancePath: '/vertrouwelijkheidaanduiding',
				params: { allowedValues: ['openbaar', 'intern'] },
			}),
		];

		const result = formatErrors(ajvErrors);
		expect(result).toHaveLength(3);
		expect(result[0].field).toBe('identificatie');
		expect(result[1].field).toBe('startdatum');
		expect(result[2].field).toBe('vertrouwelijkheidaanduiding');
	});

	it('formats pattern error with the pattern value', () => {
		const result = formatErrors([
			makeError({
				keyword: 'pattern',
				instancePath: '/bronorganisatie',
				params: { pattern: '^[0-9]{9}$' },
			}),
		]);

		expect(result[0]).toEqual({
			field: 'bronorganisatie',
			rule: 'pattern',
			message: 'Waarde voldoet niet aan het patroon: ^[0-9]{9}$.',
		});
	});

	it('formats minimum/maximum errors in Dutch', () => {
		const minResult = formatErrors([
			makeError({
				keyword: 'minimum',
				instancePath: '/volgnummer',
				params: { limit: 1 },
			}),
		]);
		expect(minResult[0].message).toBe('Waarde moet minimaal 1 zijn.');

		const maxResult = formatErrors([
			makeError({
				keyword: 'maximum',
				instancePath: '/volgnummer',
				params: { limit: 9999 },
			}),
		]);
		expect(maxResult[0].message).toBe('Waarde mag maximaal 9999 zijn.');
	});

	it('handles unknown keyword with fallback message', () => {
		const result = formatErrors([
			makeError({
				keyword: 'customKeyword',
				instancePath: '/field',
				message: 'custom error',
			}),
		]);

		expect(result[0]).toEqual({
			field: 'field',
			rule: 'customKeyword',
			message: 'custom error',
		});
	});
});
