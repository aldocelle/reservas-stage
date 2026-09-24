import test from 'node:test';
import assert from 'node:assert/strict';
import { formatRut, isValidRut, normalizeRut, validateRut } from '../src/rut.js';

const expected = '123456785';
for (const value of ['123456785', '12.345.678-5', '12345678-5', ' 12 345 678 5 ']) {
  test(`normaliza ${value}`, () => {
    assert.equal(normalizeRut(value), expected);
    assert.equal(validateRut(value), true);
    assert.equal(isValidRut(value), true);
  });
}

test('normaliza K y k', () => {
  assert.equal(normalizeRut('6-K'), '6K');
  assert.equal(normalizeRut('6-k'), '6K');
  assert.equal(isValidRut('6-K'), true);
  assert.equal(isValidRut('12.345.678-K'), false);
});

test('rechaza RUT inválidos', () => {
  for (const value of ['12345678-9', '12345678', '00000000-0', '', ' ', '12345678X5', '12345678--5', '1.234.56-5', '12345678901234567890']) {
    assert.equal(isValidRut(value), false, value);
  }
});

test('permite escribir progresivamente', () => {
  assert.equal(formatRut('1'), '1');
  assert.equal(formatRut('1234'), '1.234');
  assert.equal(formatRut('123456785'), '12.345.678-5');
  assert.equal(formatRut('6K'), '6-K');
});

test('formatea de forma visual', () => assert.equal(formatRut(expected), '12.345.678-5'));
