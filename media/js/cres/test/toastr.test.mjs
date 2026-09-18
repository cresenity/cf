import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveToastrMethod } from '../src/util/toastr.mjs';

test('tipe valid dikembalikan apa adanya', () => {
    for (const method of ['success', 'info', 'warning', 'error']) {
        assert.equal(resolveToastrMethod(method), method);
    }
});

test('tidak peka huruf besar/kecil', () => {
    assert.equal(resolveToastrMethod('SUCCESS'), 'success');
    assert.equal(resolveToastrMethod('Error'), 'error');
    assert.equal(resolveToastrMethod('Warning'), 'warning');
});

test('alias umum dipetakan ke method setara', () => {
    assert.equal(resolveToastrMethod('danger'), 'error');
    assert.equal(resolveToastrMethod('fail'), 'error');
    assert.equal(resolveToastrMethod('warn'), 'warning');
    assert.equal(resolveToastrMethod('notice'), 'info');
    assert.equal(resolveToastrMethod('primary'), 'info');
});

test('tipe tak dikenal jatuh ke info, bukan meng-crash (TB-14233)', () => {
    assert.equal(resolveToastrMethod('bogus'), 'info');
    assert.equal(resolveToastrMethod(''), 'info');
    assert.equal(resolveToastrMethod(null), 'info');
    assert.equal(resolveToastrMethod(undefined), 'info');
    assert.equal(resolveToastrMethod(123), 'info');
});
