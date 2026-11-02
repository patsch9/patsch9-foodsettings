/* Exercise the real product-header toggle enhancement in a DOM. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const jquery = require('jquery');

(async function () {
  const dom = new JSDOM(`
    <form>
      <input id="_patsch9fs_food_enabled" type="checkbox">
      <ul><li class="patsch9fs_food_options"><a href="#patsch9fs-food">Food</a></li><li class="general_options"><a href="#general_product_data">General</a></li></ul>
      <div id="general_product_data"></div>
      <div id="patsch9fs-food">
        <div class="patsch9fs-wrap">
          <div class="patsch9fs-field"><label><input id="patsch9fs-enabled" name="patsch9fs[enabled]" type="checkbox">Enabled</label></div>
          <div class="patsch9fs-field"><label><input id="patsch9fs-same_variations" type="checkbox">Variations</label></div>
        </div>
      </div>
    </form>
  `, {runScripts: 'outside-only', url: 'https://shop.example.invalid/wp-admin/post.php'});
  const {window} = dom;
  const $ = jquery(window);
  window.jQuery = $;
  window.PATSCH9FS_PRODUCT_OPTIONS = {
    withdrawalAck: true,
    withdrawalLabel: 'Checkout-Bestätigung verlangen',
    withdrawalDescription: 'Nur bei tatsächlich individuell angefertigten Waren aktivieren.'
  };

  window.eval(fs.readFileSync(path.join(__dirname, '..', 'assets', 'product-options.js'), 'utf8'));
  await new Promise(resolve => $(resolve));

  assert.equal($('#patsch9fs-enabled').closest('.patsch9fs-field').css('display'), 'none', 'Old in-panel food toggle is hidden.');
  assert.equal($('.patsch9fs_food_options').css('display'), 'none', 'Food tab is hidden while the header option is disabled.');
  assert.equal($('#patsch9fs-withdrawal_ack').length, 1, 'Withdrawal acknowledgement setting is injected once.');
  assert.equal($('#patsch9fs-withdrawal_ack').prop('checked'), true, 'Stored withdrawal setting is restored.');
  assert.equal($('[name="patsch9fs_withdrawal_present"]').val(), '1', 'JS presence marker protects the setting during save.');
  assert.equal($('#patsch9fs-withdrawal_ack').attr('aria-describedby'), 'patsch9fs-withdrawal_ack-description', 'Legal explanation is associated accessibly.');

  $('#_patsch9fs_food_enabled').prop('checked', true).trigger('change');
  assert.notEqual($('.patsch9fs_food_options').css('display'), 'none', 'Food tab becomes visible when Lebensmittel is enabled.');
  assert.equal($('#patsch9fs-enabled').prop('checked'), true, 'Header option is synchronized into the structured payload.');

  $('#_patsch9fs_food_enabled').prop('checked', false).trigger('change');
  assert.equal($('.patsch9fs_food_options').css('display'), 'none', 'Food tab is hidden again when Lebensmittel is disabled.');
  assert.equal($('#patsch9fs-enabled').prop('checked'), false, 'Disabled header option is synchronized into the structured payload.');

  window.close();
  console.log('PASS: 10 product-option DOM regression assertions.');
})().catch(error => { console.error(error); process.exitCode = 1; });
