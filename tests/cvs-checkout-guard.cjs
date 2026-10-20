'use strict';

/**
 * Integration-style browser regression matrix; no orders or network requests.
 * Supply jsdom and real local vendor sources through YS_CVS_JSDOM_MODULE,
 * YS_CVS_JQUERY_JS, YS_CVS_BLOCKUI_JS and YS_CVS_CHECKOUT_JS. The jsdom variable
 * is optional when `require('jsdom')` is already available.
 * Run: node tests/cvs-checkout-guard.cjs [--without-guard] [--output=report.json]
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { JSDOM, VirtualConsole, ResourceLoader } = require(process.env.YS_CVS_JSDOM_MODULE || 'jsdom');
const sourcePaths = Object.fromEntries(['JQUERY', 'BLOCKUI', 'CHECKOUT'].map(name => {
    const file = process.env['YS_CVS_' + name + '_JS'];
    if (!file) throw new Error('Missing local fixture source: YS_CVS_' + name + '_JS');
    return [name.toLowerCase(), file];
}));
const sources = Object.fromEntries(Object.entries(sourcePaths).map(([key, file]) => [key, fs.readFileSync(file, 'utf8')]));
const withoutGuard = process.argv.includes('--without-guard');
const outputArgument = process.argv.find(value => value.startsWith('--output='));
const guardPath = path.resolve(__dirname, '../assets/js/cvs-checkout-guard.js');
const guard = withoutGuard ? '' : fs.readFileSync(guardPath, 'utf8');
const fields = {
    modern: { id: 'RY_CVSStoreID', name: 'RY_CVSStoreName', address: 'RY_CVSAddress', service: 'RY_LogisticsSubType' },
    legacy: { id: 'CVSStoreID', name: 'CVSStoreName', address: 'CVSAddress', service: 'LogisticsSubType' },
    paynow: { id: 'paynow_storeid', name: 'paynow_storename', address: 'paynow_storeaddress', service: 'paynow_service' },
};
const contracts = [
    { rate: 'ry_ecpay_shipping_cvs_family:6', provider: 'ry_ecpay', fields: fields.modern, expected: 'FAMIC2C', extra_fields: [] },
    { rate: 'ry_ecpay_shipping_cvs_711:4', provider: 'ry_ecpay', fields: fields.modern, expected: 'UNIMARTC2C', extra_fields: [] },
    { rate: 'ry_ecpay_shipping_cvs_family:16', provider: 'woomp_ecpay', fields: fields.legacy, expected: 'FAMIC2C', extra_fields: [] },
    { rate: 'ry_ecpay_shipping_cvs_711:14', provider: 'woomp_ecpay', fields: fields.legacy, expected: 'UNIMARTC2C', extra_fields: [] },
    { rate: 'paynow_shipping_c2c_family:8', provider: 'paynow', fields: fields.paynow, expected: '03', extra_fields: [] },
    { rate: 'paynow_shipping_c2c_711:7', provider: 'paynow', fields: fields.paynow, expected: '01', extra_fields: [] },
    { rate: 'paynow_shipping_c2c_hilife:9', provider: 'paynow', fields: fields.paynow, expected: '05', extra_fields: [] },
    { rate: 'paynow_shipping_c2c_family_frozen:18', provider: 'paynow', fields: fields.paynow, expected: '23', extra_fields: ['paynow_reservedno', 'paynow_shipdate'] },
];
const message = '尚未選擇超商取貨門市，請先選擇超商。';
const allFields = [...new Set(contracts.flatMap(contract => Object.values(contract.fields).concat(contract.extra_fields)))];
const fixture = '<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"></head><body>' +
    '<form name="checkout" class="checkout woocommerce-checkout" method="post" action="/native-submit-forbidden">' +
    '<input class="input-text" name="billing_first_name" value="測試">' +
    '<fieldset id="shipping_methods">' + contracts.map((contract, index) => '<label><input type="radio" name="shipping_method[0]" value="' + contract.rate + '"' + (index === 0 ? ' checked' : '') + '>CVS</label>').join('') +
    '<label><input type="radio" name="shipping_method[0]" value="flat_rate:3">Postal</label></fieldset>' +
    '<div id="store_fields">' + allFields.map(name => '<input type="hidden" name="' + name + '" value="">').join('') + '</div>' +
    '<button type="button" class="ry-choose-cvs" id="choose-cvs-btn">選擇超商門市</button>' +
    '<div id="order_review"><div id="payment"><input type="radio" name="payment_method" value="bacs" id="payment_method_bacs" checked>' +
    '<div class="payment_box payment_method_bacs"></div><button type="submit" id="place_order"><span>下單</span></button></div></div></form></body></html>';
const cases = [];

class NoNetwork extends ResourceLoader {
    fetch(url) { throw new Error('Unexpected network access: ' + url); }
}

async function environment() {
    const errors = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM(fixture, { url: 'https://checkout-fixture.invalid/checkout/', runScripts: 'outside-only', resources: new NoNetwork(), pretendToBeVisual: true, virtualConsole });
    const w = dom.window;
    const alerts = [];
    const attempts = [];
    w.alert = value => alerts.push(value);
    w.eval(sources.jquery);
    const $ = w.jQuery;
    w.eval(sources.blockui);
    w.ysCvsCheckoutGuard = { contracts: JSON.parse(JSON.stringify(contracts)), message };
    w.wc = { customPlaceOrderButton: { __getForm: () => $('form.checkout'), __cleanup() {}, __maybeShow() {}, __maybeHideDefaultButtonOnInit() {} } };
    w.wc_checkout_params = { is_checkout: '0', option_guest_checkout: 'yes', checkout_url: '/?wc-ajax=checkout', wc_ajax_url: '/?wc-ajax=%%endpoint%%', i18n_checkout_error: 'Fixture response only' };
    $.ajax = options => {
        attempts.push({ url: options.url, type: options.type || 'GET', data: options.data });
        return $.Deferred().promise({ abort() {} });
    };
    w.eval(sources.checkout);
    if (!withoutGuard) w.eval(guard);
    await new Promise(resolve => $(resolve));
    await new Promise(resolve => w.setTimeout(resolve, 0));
    return { dom, w, $, form: w.document.querySelector('form.checkout'), alerts, attempts, errors };
}

function select(e, rate) {
    for (const input of e.form.querySelectorAll('[name="shipping_method[0]"]')) input.checked = input.value === rate;
}
function field(e, name) { return [...e.form.elements].find(input => input.name === name); }
function complete(e, contract = contracts[0]) {
    const values = { id: 'TEST001', name: '本地模擬門市', address: '本地模擬地址', service: contract.expected };
    for (const [key, name] of Object.entries(contract.fields)) field(e, name).value = values[key];
    for (const name of contract.extra_fields) field(e, name).value = '本地測試值';
}
function shipping(e, rates) {
    e.form.querySelector('#shipping_methods').innerHTML = rates.map((rate, index) => '<input type="hidden" name="shipping_method[' + index + ']" value="' + rate + '">').join('');
}
function domContracts(e, value, raw = false) {
    let element = e.form.querySelector('#ys-cvs-checkout-contracts');
    if (!element) {
        element = e.w.document.createElement('div');
        element.id = 'ys-cvs-checkout-contracts';
        element.hidden = true;
        e.form.append(element);
    }
    element.dataset.contracts = raw ? value : JSON.stringify(value);
    return element;
}
function submit(e, kind) {
    if (kind === 'click') e.form.querySelector('#place_order span').click();
    else if (kind === 'native') e.form.requestSubmit();
    else if (kind === 'jquery') e.$(e.form).trigger('submit');
    else if (kind === 'handler') e.$(e.form).triggerHandler('submit');
    else throw new Error('Unknown attempt kind: ' + kind);
}
function checkoutRequests(e) { return e.attempts.filter(attempt => attempt.url === e.w.wc_checkout_params.checkout_url).length; }

async function run(name, setup, kind, blocked) {
    const e = await environment();
    let failure = '';
    try {
        if (setup) await setup(e);
        submit(e, kind);
    } catch (error) { failure = error.message; }
    const alerts = e.alerts.length;
    const requests = checkoutRequests(e);
    const pass = !failure && !e.errors.length && alerts === (blocked ? 1 : 0) && requests === (blocked ? 0 : 1) && e.alerts.every(value => value === message);
    cases.push({ name, alerts, requests, pass, ...(failure ? { failure } : {}), ...(e.errors.length ? { errors: e.errors } : {}) });
    e.dom.window.close();
}

(async () => {
    for (const contract of contracts) {
        for (const kind of ['click', 'native', 'jquery', 'handler']) {
            await run(contract.rate + ' missing store / ' + kind, e => select(e, contract.rate), kind, true);
            await run(contract.rate + ' complete store / ' + kind, e => { select(e, contract.rate); complete(e, contract); }, kind, false);
        }
    }
    for (const contract of [contracts[0], contracts[2], contracts[4]]) {
        for (const name of Object.values(contract.fields)) {
            for (const invalid of ['missing', 'empty', 'spaces', 'disabled', 'duplicate']) {
                await run(contract.provider + ' ' + name + ' / ' + invalid, e => {
                    select(e, contract.rate);
                    complete(e, contract);
                    const input = field(e, name);
                    if (invalid === 'missing') input.remove();
                    if (invalid === 'empty') input.value = '';
                    if (invalid === 'spaces') input.value = '  \t\n  ';
                    if (invalid === 'disabled') input.disabled = true;
                    if (invalid === 'duplicate') input.after(input.cloneNode(true));
                }, 'jquery', true);
            }
        }
    }
    for (const contract of [contracts[0], contracts[2], contracts[4]]) {
        for (const name of [contract.fields.id, contract.fields.name, contract.fields.address]) {
            await run(contract.provider + ' invisible Unicode-only ' + name, e => {
                select(e, contract.rate); complete(e, contract);
                field(e, name).value = '\u200B\uFEFF\u3000';
            }, 'jquery', true);
        }
    }
    for (const name of contracts[7].extra_fields) {
        for (const invalid of ['missing', 'empty', 'disabled', 'duplicate']) {
            await run('Frozen extra ' + name + ' / ' + invalid, e => {
                select(e, contracts[7].rate); complete(e, contracts[7]);
                const input = field(e, name);
                if (invalid === 'missing') input.remove();
                if (invalid === 'empty') input.value = '';
                if (invalid === 'disabled') input.disabled = true;
                if (invalid === 'duplicate') input.after(input.cloneNode(true));
            }, 'click', true);
        }
    }
    for (const [family, seven] of [[contracts[0], contracts[1]], [contracts[2], contracts[3]], [contracts[4], contracts[5]]]) {
        await run(family.provider + ' stale Family store cannot satisfy 711', e => { select(e, seven.rate); complete(e, family); }, 'click', true);
        await run(family.provider + ' stale 711 store cannot satisfy Family', e => { select(e, family.rate); complete(e, seven); }, 'native', true);
    }
    for (const [contract, expected] of [[contracts[0], 'FAMI'], [contracts[1], 'UNIMART']]) {
        await run('Current B2C configuration accepted for ' + contract.rate, e => {
            select(e, contract.rate);
            e.w.ysCvsCheckoutGuard.contracts.find(item => item.rate === contract.rate).expected = expected;
            complete(e, { ...contract, expected });
        }, 'click', false);
    }
    await run('Disabled fieldset is not successful controls', e => {
        complete(e);
        const fieldsElement = e.form.querySelector('#store_fields');
        const disabled = e.w.document.createElement('fieldset'); disabled.disabled = true;
        fieldsElement.replaceWith(disabled); disabled.append(fieldsElement);
    }, 'click', true);
    await run('Bracketed array store value is rejected', e => { complete(e); field(e, contracts[0].fields.id).name += '[]'; }, 'jquery', true);
    await run('Disabled duplicate is not a successful control', e => {
        complete(e); const input = field(e, contracts[0].fields.id); const duplicate = input.cloneNode(true); duplicate.disabled = true; input.after(duplicate);
    }, 'click', false);
    await run('Unused provider store fields do not affect current selection', e => {
        complete(e); field(e, contracts[4].fields.service).value = 'wrong unrelated brand';
    }, 'jquery', false);
    await run('Postal checkout without a store passes', e => select(e, 'flat_rate:3'), 'click', false);
    await run('Other provider checkout is untouched', e => shipping(e, ['custom_shipping_cvs:3']), 'jquery', false);
    await run('Unknown rate instance is not a prefix match', e => shipping(e, ['ry_ecpay_shipping_cvs_family:60']), 'click', false);
    await run('No shipping controls passes', e => e.form.querySelector('#shipping_methods').replaceChildren(), 'jquery', false);
    await run('Hidden shipping control is protected', e => shipping(e, [contracts[0].rate]), 'jquery', true);
    await run('Select shipping control is protected', e => {
        e.form.querySelector('#shipping_methods').innerHTML = '<select name="shipping_method[0]"><option value="' + contracts[0].rate + '">CVS</option></select>';
    }, 'native', true);
    await run('Unindexed known shipping control is protected', e => {
        shipping(e, [contracts[0].rate]); field(e, 'shipping_method[0]').name = 'shipping_method';
    }, 'jquery', true);
    await run('Duplicate selected CVS shipping control is rejected', e => {
        complete(e); const input = field(e, 'shipping_method[0]'); const duplicate = input.cloneNode(true); duplicate.type = 'hidden'; input.after(duplicate);
    }, 'click', true);
    await run('Mixed CVS and postal still requires a store', e => shipping(e, [contracts[0].rate, 'flat_rate:3']), 'jquery', true);
    await run('Mixed CVS and postal with a complete store passes', e => { shipping(e, [contracts[0].rate, 'flat_rate:3']); complete(e); }, 'click', false);
    await run('Two CVS packages each need their own provider fields', e => { shipping(e, [contracts[0].rate, contracts[4].rate]); complete(e); }, 'native', true);
    await run('Two complete CVS provider selections pass', e => { shipping(e, [contracts[0].rate, contracts[4].rate]); complete(e); complete(e, contracts[4]); }, 'jquery', false);
    await run('Conflicting CVS brands with one shared store are rejected', e => { shipping(e, [contracts[0].rate, contracts[1].rate]); complete(e); }, 'jquery', true);
    await run('Unrelated duplicate postal controls do not alter CVS guard scope', e => {
        shipping(e, [contracts[0].rate, 'flat_rate:3']); complete(e);
        const postal = field(e, 'shipping_method[1]'); postal.after(postal.cloneNode(true));
    }, 'jquery', false);
    await run('Payment/store fragments replaced are still checked', e => {
        for (const id of ['payment', 'store_fields']) { const element = e.form.querySelector('#' + id); element.outerHTML = element.outerHTML; }
        e.$(e.w.document.body).trigger('updated_checkout', [{ fragments: {} }]);
    }, 'click', true);
    await run('Repeated checkout initialization alerts once', e => {
        for (let i = 0; i < 3; i++) e.$(e.w.document.body).trigger('updated_checkout', [{ fragments: {} }]);
    }, 'jquery', true);
    await run('Later event handler cannot undo rejection', e => e.$(e.form).on('checkout_place_order.lateTrue', () => true), 'jquery', true);
    await run('DOM contracts override old window contracts for a new rate', e => {
        const next = { ...contracts[0], rate: 'ry_ecpay_shipping_cvs_family:26' };
        shipping(e, [next.rate]); domContracts(e, [next]);
        e.$(e.w.document.body).trigger('updated_checkout', [{ fragments: {} }]);
    }, 'click', true);
    await run('DOM contract fragment replacement reads new complete rate', e => {
        const old = domContracts(e, contracts);
        const next = { ...contracts[4], rate: 'paynow_shipping_c2c_family:28' };
        const replacement = old.cloneNode(); replacement.dataset.contracts = JSON.stringify([next]); old.replaceWith(replacement);
        shipping(e, [next.rate]); complete(e, next);
        e.$(e.w.document.body).trigger('updated_checkout', [{ fragments: {} }]);
    }, 'jquery', false);
    await run('DOM current subtype overrides stale window subtype', e => {
        const next = { ...contracts[0], expected: 'FAMI' };
        domContracts(e, [next]); complete(e);
    }, 'click', true);
    await run('Empty DOM contracts override stale window protection', e => domContracts(e, []), 'jquery', false);
    for (const value of ['not-json', 'null', '{}']) {
        await run('Malformed DOM metadata delegates to server: ' + value, e => domContracts(e, value, true), 'click', false);
    }
    for (const value of [undefined, null, {}, { contracts: null }, { contracts: {} }]) {
        await run('Missing config delegates to server: ' + JSON.stringify(value), e => { e.w.ysCvsCheckoutGuard = value; }, 'jquery', false);
    }
    for (const patch of [{ fields: null }, { expected: '' }, { fields: { id: 'RY_CVSStoreID' } }, { extra_fields: 'bad' }]) {
        await run('Malformed selected contract is rejected: ' + JSON.stringify(patch), e => { complete(e); Object.assign(e.w.ysCvsCheckoutGuard.contracts[0], patch); }, 'click', true);
    }
    await run('Duplicate selected contracts are rejected', e => { complete(e); e.w.ysCvsCheckoutGuard.contracts.push(e.w.ysCvsCheckoutGuard.contracts[0]); }, 'jquery', true);
    await run('Unknown provider contract remains untouched', e => {
        e.w.ysCvsCheckoutGuard.contracts = [{ ...contracts[0], provider: 'custom_provider' }];
    }, 'click', false);
    await run('A different form cannot supply missing store data', e => {
        const other = e.w.document.createElement('form'); other.innerHTML = e.form.querySelector('#store_fields').innerHTML;
        e.w.document.body.append(other);
        for (const input of other.elements) input.value = 'unrelated form value';
    }, 'jquery', true);
    const recovery = await environment();
    submit(recovery, 'click'); complete(recovery); submit(recovery, 'click');
    cases.push({ name: 'Missing then completed store can retry', alerts: recovery.alerts.length, requests: checkoutRequests(recovery), pass: recovery.alerts.length === 1 && checkoutRequests(recovery) === 1 && !recovery.errors.length });
    recovery.dom.window.close();
    const result = {
        fixture: 'Actual WooCommerce checkout.js, jQuery and blockUI in jsdom; $.ajax recorder; no network/orders.',
        guardLoaded: !withoutGuard,
        sources: Object.fromEntries(Object.entries(sourcePaths).map(([key, file]) => [key, { path: file, sha256: crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex') }])),
        total: cases.length, passed: cases.filter(test => test.pass).length, failed: cases.filter(test => !test.pass).length, cases,
    };
    if (outputArgument) fs.writeFileSync(outputArgument.slice('--output='.length), JSON.stringify(result, null, 2) + '\n');
    console.log(JSON.stringify({ total: result.total, passed: result.passed, failed: result.failed, ...(outputArgument ? { output: outputArgument.slice('--output='.length) } : {}) }, null, 2));
    if (!outputArgument && result.failed) console.log(JSON.stringify(cases.filter(test => !test.pass), null, 2));
    process.exitCode = result.failed ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
