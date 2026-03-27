import { describe, it, expect } from 'vitest';
import { DynamicValidator } from '../src/dynamic-validator.js';
import type { EigenschapSpecificatie } from '../src/types.js';

describe('DynamicValidator', () => {
	const validator = new DynamicValidator();

	describe('tekst format', () => {
		it('validates a text value within length constraint', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'tekst',
				lengte: '100',
			};
			const errors = validator.validateWaarde('Hello world', spec);
			expect(errors).toEqual([]);
		});

		it('returns error when text exceeds max length', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'tekst',
				lengte: '5',
			};
			const errors = validator.validateWaarde('Too long value', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('maxLength');
			expect(errors[0].field).toBe('waarde');
			expect(errors[0].message).toBe('Waarde mag maximaal 5 tekens bevatten.');
		});

		it('allows text when no length constraint is set', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'tekst',
			};
			const errors = validator.validateWaarde('Any length text here', spec);
			expect(errors).toEqual([]);
		});
	});

	describe('getal format', () => {
		it('validates a valid number', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
			};
			const errors = validator.validateWaarde('42', spec);
			expect(errors).toEqual([]);
		});

		it('validates a decimal number', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
			};
			const errors = validator.validateWaarde('3.14', spec);
			expect(errors).toEqual([]);
		});

		it('returns error for non-numeric value', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
			};
			const errors = validator.validateWaarde('not-a-number', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('type');
			expect(errors[0].message).toBe('Waarde moet een geldig getal zijn.');
		});

		it('validates minimum constraint', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
				minWaarde: '10',
			};
			const errors = validator.validateWaarde('5', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('minimum');
		});

		it('validates maximum constraint', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
				maxWaarde: '100',
			};
			const errors = validator.validateWaarde('150', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('maximum');
		});

		it('passes when within min/max range', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
				minWaarde: '0',
				maxWaarde: '100',
			};
			const errors = validator.validateWaarde('50', spec);
			expect(errors).toEqual([]);
		});
	});

	describe('datum format', () => {
		it('validates a valid date', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'datum',
			};
			const errors = validator.validateWaarde('2024-06-15', spec);
			expect(errors).toEqual([]);
		});

		it('returns error for invalid date format', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'datum',
			};
			const errors = validator.validateWaarde('15-06-2024', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('format');
			expect(errors[0].message).toBe('Waarde is geen geldige datum (JJJJ-MM-DD).');
		});

		it('returns error for non-date string', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'datum',
			};
			const errors = validator.validateWaarde('not-a-date', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('format');
		});
	});

	describe('datum_tijd format', () => {
		it('validates a valid datetime', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'datum_tijd',
			};
			const errors = validator.validateWaarde('2024-06-15T10:30:00Z', spec);
			expect(errors).toEqual([]);
		});

		it('returns error for date-only string', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'datum_tijd',
			};
			const errors = validator.validateWaarde('2024-06-15', spec);
			expect(errors).toHaveLength(1);
			expect(errors[0].rule).toBe('format');
		});
	});

	describe('null/undefined waarde', () => {
		it('returns no errors for null waarde (handled by static schema)', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'tekst',
				lengte: '10',
			};
			const errors = validator.validateWaarde(null, spec);
			expect(errors).toEqual([]);
		});

		it('returns no errors for undefined waarde', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'getal',
			};
			const errors = validator.validateWaarde(undefined, spec);
			expect(errors).toEqual([]);
		});
	});

	describe('unknown format', () => {
		it('returns no errors for unknown format type', () => {
			const spec: EigenschapSpecificatie = {
				formaat: 'onbekend',
			};
			const errors = validator.validateWaarde('anything', spec);
			expect(errors).toEqual([]);
		});
	});
});
