/* Focused browser-side regressions for the 2026-11-03 external review. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const jquery = require('jquery');

(async function () {
  const keys = ['energy_kj', 'energy_kcal', 'fat', 'saturates', 'carbs', 'sugars', 'fibre', 'protein', 'salt'];
  const dom = new JSDOM(`
    <form>
      <div id="patsch9fs-food">
        <input id="patsch9fs-recipe-json" value="[]">
        <input id="patsch9fs-sources-json" value="[]">
        <input id="patsch9fs-approve" type="checkbox" checked>
        <input id="patsch9fs-withdrawal_ack" type="checkbox">
        <div class="patsch9fs-review"><strong>Freigegeben</strong></div>
        <select id="patsch9fs-mode"><option value="manual" selected>Manual</option><option value="recipe">Recipe</option></select>
        <select id="patsch9fs-basis"><option value="g">g</option><option value="ml">ml</option></select>
        <select id="patsch9fs-category"><option value="excluded">Excluded</option></select>
        <input id="patsch9fs-fruit"><select id="patsch9fs-sweeteners"><option value=""></option></select>
        <input id="patsch9fs-yield" value="100">
        <input id="patsch9fs-search" value="ab">
        <select id="patsch9fs-results"></select>
        <input id="patsch9fs-barcode" value="12345678">
        <input id="patsch9fs-ingredients"><input id="patsch9fs-traces">
        <div id="patsch9fs-message"></div>
        <div id="patsch9fs-source-preview"></div>
        <div id="patsch9fs-recipe-preview"></div>
        <div id="patsch9fs-recipe-rows"></div>
        <input data-nutrient="fat">
        <button id="patsch9fs-search-button" type="button">Search</button>
        <button id="patsch9fs-select-off" type="button">OFF</button>
        <button id="patsch9fs-calculate" type="button">Calculate</button>
        <button id="patsch9fs-add-manual" type="button">Add</button>
      </div>
    </form>
  `, {runScripts: 'outside-only', url: 'https://shop.example.invalid/wp-admin/post.php'});
  const {window} = dom;
  const $ = jquery(window);
  window.jQuery = $;
  window.PATSCH9FS = {
    labels: Object.fromEntries(keys.map(key => [key, key])),
    url: '/admin-ajax.php',
    nonce: 'fixture',
    productId: 12,
    offEnabled: true
  };

  $.ajax = function (options) {
    const deferred = $.Deferred();
    setTimeout(function () {
      const task = options.data.task;
      if (task === 'search') {
        deferred.resolve(-1);
      } else if (task === 'recipe') {
        deferred.resolve({
          success: true,
          data: {
            nutrients: Object.fromEntries(keys.map(key => [key, key === 'fat' ? 4.85e-7 : null])),
            input_grams: 10,
            yield_grams: 100,
            ingredients: 'Test'
          }
        });
      } else if (task === 'off') {
        deferred.resolve({
          success: true,
          data: {
            name: 'Öl', brands: '', basis: 'ml', declaration: '', traces: '', allergens: [], warning: '',
            nutrients: Object.fromEntries(keys.map(key => [key, key === 'fat' ? 92 : null])),
            source: {provider: 'off', id: '12345678'}
          }
        });
      } else {
        deferred.reject({responseJSON: null});
      }
    }, 0);
    return deferred.promise();
  };

  window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'admin.js'), 'utf8'));
  await new Promise(resolve => $(resolve));

  $('#patsch9fs-withdrawal_ack').prop('checked', true).trigger('change');
  assert.equal($('#patsch9fs-approve').prop('checked'), true, 'Legal checkout toggle does not invalidate food-data approval.');

  $('#patsch9fs-search-button').trigger('click');
  await new Promise(resolve => setTimeout(resolve, 10));
  assert.match($('#patsch9fs-message').text(), /Seite neu laden/, 'Expired/invalid AJAX responses show a stable fallback instead of throwing a TypeError.');

  $('#patsch9fs-calculate').trigger('click');
  await new Promise(resolve => setTimeout(resolve, 10));
  assert.equal($('[data-nutrient="fat"]').val(), '0.000000485', 'Small calculated values are written as plain decimals, not scientific notation.');

  $('#patsch9fs-select-off').trigger('click');
  await new Promise(resolve => setTimeout(resolve, 10));
  $('#patsch9fs-density').val('0.91');
  $('#patsch9fs-source-preview button').filter(function () { return $(this).text().includes('Rezeptzutat'); }).trigger('click');
  assert.match($('#patsch9fs-message').text(), /über 100 g/, 'Physically inconsistent density conversions are rejected before save instead of being silently clamped.');
  assert.deepEqual(JSON.parse($('#patsch9fs-recipe-json').val()), [], 'Rejected density conversions do not modify the recipe payload.');

  window.close();
  console.log('PASS: 5 Google-review admin regression assertions.');
})().catch(error => { console.error(error); process.exitCode = 1; });
