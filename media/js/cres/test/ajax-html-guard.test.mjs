import test from 'node:test';
import assert from 'node:assert/strict';
import { looksLikeHtmlPage, looksLikeJsonObject, guardScriptConverter, installAjaxHtmlGuard } from '../src/util/ajax-html-guard.mjs';

test('halaman HTML dikenali, JS tidak', () => {
    assert.equal(looksLikeHtmlPage('<!DOCTYPE html><html>'), true);
    assert.equal(looksLikeHtmlPage('\n  <html lang="id">'), true);
    assert.equal(looksLikeHtmlPage('<head><title>Login</title>'), true);
    assert.equal(looksLikeHtmlPage('console.log("<html>")'), false);
    assert.equal(looksLikeHtmlPage('var a = 1 < 2;'), false);
    assert.equal(looksLikeHtmlPage('<htmlx'), false);
    assert.equal(looksLikeHtmlPage(null), false);
});

test('objek JSON dikenali, array/JS tidak', () => {
    assert.equal(looksLikeJsonObject('{"title":null,"html":"<div>"}'), true);
    assert.equal(looksLikeJsonObject('  {\n "a": 1}'), true);
    assert.equal(looksLikeJsonObject('{ a: 1 }'), false);
    assert.equal(looksLikeJsonObject('[1,2]'), false);
    assert.equal(looksLikeJsonObject('if (a) { b(); }'), false);
});

test('respons JSON tidak di-eval', () => {
    let evaluated = 0;
    const converter = guardScriptConverter(() => { evaluated++; }, () => {});
    assert.throws(() => converter('{"errCode":0}'), /received JSON/);
    assert.equal(evaluated, 0);
});

test('respons HTML tidak di-eval dan request digagalkan', () => {
    let evaluated = 0;
    let notified = 0;
    const converter = guardScriptConverter(() => { evaluated++; }, () => { notified++; });

    assert.throws(() => converter('<!doctype html><title>Login</title>'), /HTML page/);
    assert.equal(evaluated, 0);
    assert.equal(notified, 1);
});

test('script biasa tetap diteruskan ke converter asli', () => {
    const converter = guardScriptConverter((text) => 'eval:' + text, () => assert.fail('bukan HTML'));
    assert.equal(converter('alert(1)'), 'eval:alert(1)');
});

test('install sekali saja dan aman tanpa jQuery', () => {
    const original = (t) => t;
    const jq = { ajaxSettings: { converters: { 'text script': original } } };
    assert.equal(installAjaxHtmlGuard(jq, () => {}), true);
    assert.equal(installAjaxHtmlGuard(jq, () => {}), false);
    assert.notEqual(jq.ajaxSettings.converters['text script'], original);
    assert.equal(installAjaxHtmlGuard(undefined, () => {}), false);
    assert.equal(installAjaxHtmlGuard({}, () => {}), false);
});
