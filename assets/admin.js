/* global PATSCH9FS, jQuery, wp */
(function ($) {
  'use strict';
  $(function () {
    $('.patsch9fs-pick-logo').on('click', function () {
      const input = $(this).closest('p').find('input');
      const frame = wp.media({title: 'Offizielles Nutri-Score-Logo auswählen', multiple: false, library: {type: 'image'}});
      frame.on('select', function () { input.val(frame.state().get('selection').first().id); });
      frame.open();
    });
    const panel = $('#patsch9fs-food');
    if (!panel.length) return;
    let rows = JSON.parse($('#patsch9fs-recipe-json').val() || '[]');
    let selected = null;
    let busy = false;
    const keys = Object.keys(PATSCH9FS.labels);
    function dirty() {
      $('#patsch9fs-approve').prop('checked', false);
      $('.patsch9fs-review strong').text('Änderungen müssen vor der Shopanzeige erneut freigegeben werden.');
    }
    function sync() { $('#patsch9fs-recipe-json').val(JSON.stringify(rows)); }
    function message(text, error) { $('#patsch9fs-message').text(text).toggleClass('patsch9fs-error', !!error); }
    function responseMessage(response, fallback) {
      return response && response.data && typeof response.data.message === 'string' && response.data.message ? response.data.message : fallback;
    }
    function request(task, data) {
      if (busy) return Promise.reject(new Error('Bitte laufende Abfrage abwarten.'));
      busy = true;
      panel.find('button').prop('disabled', true);
      return $.ajax({url: PATSCH9FS.url, method: 'POST', data: Object.assign({action: 'patsch9fs_food', task: task, nonce: PATSCH9FS.nonce, product_id: PATSCH9FS.productId}, data)})
        .then(function (r) {
          if (!r || r.success !== true) throw new Error(responseMessage(r, 'Abfrage fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.'));
          return r.data;
        }, function (r) {
          throw new Error(responseMessage(r && r.responseJSON, 'Abfrage fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.'));
        }).always(function () { busy = false; panel.find('button').prop('disabled', false); });
    }
    function fail(e) { message(e.message || String(e), true); }
    function mode() { panel.find('[data-nutrient]').prop('readonly', $('#patsch9fs-mode').val() === 'recipe'); }
    function plainDecimal(value) {
      if (value == null || value === '') return '';
      const text = String(value);
      const match = /^(-?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/.exec(text);
      if (!match) return text;
      const sign = match[1];
      const integer = match[2];
      const fraction = match[3] || '';
      const exponent = Number(match[4]);
      const digits = integer + fraction;
      const decimalIndex = integer.length + exponent;
      if (decimalIndex <= 0) return sign + '0.' + '0'.repeat(-decimalIndex) + digits;
      if (decimalIndex >= digits.length) return sign + digits + '0'.repeat(decimalIndex - digits.length);
      return sign + digits.slice(0, decimalIndex) + '.' + digits.slice(decimalIndex);
    }
    panel.on('input change', 'input, textarea, select', function () {
      if (this.id !== 'patsch9fs-approve' && !['patsch9fs-search', 'patsch9fs-results', 'patsch9fs-barcode', 'patsch9fs-density', 'patsch9fs-withdrawal_ack'].includes(this.id)) dirty();
    });
    $('#patsch9fs-mode').on('change', mode);
    function drawRows() {
      const container = $('#patsch9fs-recipe-rows').empty();
      rows.forEach(function (row, i) {
        const box = $('<details class="patsch9fs-recipe-row">');
        $('<summary>').text((i + 1) + '. ' + (row.name || 'Neue Zutat') + ' – ' + (row.grams || '?') + ' g').appendTo(box);
        function input(label, value, callback, textarea) {
          const el = textarea ? $('<textarea rows="2">') : $('<input type="text">');
          el.val(value == null ? '' : value).on('input', function () {callback($(this).val()); sync(); dirty();});
          $('<label>').text(label).append(el).appendTo(box);
        }
        input('Zutatenname', row.name, v => {row.name = v;});
        input('Einsatzmenge in Gramm', row.grams, v => {row.grams = v;});
        input('Unterzutaten / Hervorhebung (optional, HTML <strong>)', row.declaration, v => {row.declaration = v;}, true);
        const warning = $('<p class="patsch9fs-error" role="status">').appendTo(box);
        function consistencyWarning() {
          const conflicts = [['saturates', 'fat'], ['sugars', 'carbs']].filter(function (pair) {
            const child = row.nutrients[pair[0]], total = row.nutrients[pair[1]];
            if (child == null || total == null || child === '' || total === '') return false;
            return Number(String(child).replace(',', '.')) > Number(String(total).replace(',', '.')) + 0.000001;
          });
          warning.text(conflicts.length ? 'Widersprüchliche Zutatenwerte: ' + conflicts.map(pair => PATSCH9FS.labels[pair[0]] + ' über ' + PATSCH9FS.labels[pair[1]]).join('; ') + '. Bitte anhand belastbarer Herstellerwerte korrigieren oder eine andere Quelle wählen. Werte werden nicht automatisch gekappt.' : '').toggle(conflicts.length > 0);
          return conflicts.length > 0;
        }
        const grid = $('<div class="patsch9fs-nutrient-grid">').appendTo(box);
        keys.forEach(function (key) {
          const el = $('<input type="text" inputmode="decimal">').val(row.nutrients[key] == null ? '' : plainDecimal(row.nutrients[key]));
          el.on('input', function () {row.nutrients[key] = $(this).val(); consistencyWarning(); sync(); dirty();});
          $('<label>').text(PATSCH9FS.labels[key] + ' / 100 g').append(el).appendTo(grid);
        });
        if (row.source && row.source.provider) $('<p>').text('Quellsnapshot: ' + row.source.provider.toUpperCase() + ' ' + row.source.id).appendTo(box);
        $('<button type="button" class="button">').text('Zutat entfernen').on('click', function () {rows.splice(i, 1); sync(); drawRows(); dirty();}).appendTo(box);
        container.append(box);
        if (consistencyWarning()) box.prop('open', true);
      });
      sync();
    }
    function showSource(source) {
      selected = source;
      const box = $('#patsch9fs-source-preview').empty();
      $('<h4>').text(source.name + (source.brands ? ' · ' + source.brands : '')).appendTo(box);
      $('<p>').text(source.warning).appendTo(box);
      const table = $('<table class="widefat">').appendTo(box);
      keys.forEach(function (key) {
        const tr = $('<tr>').appendTo(table);
        $('<th>').text(PATSCH9FS.labels[key] + ' / 100 ' + source.basis).appendTo(tr);
        $('<td>').text(source.nutrients[key] == null ? 'unbekannt' : String(source.nutrients[key])).appendTo(tr);
      });
      if (source.declaration) $('<p>').text('Zutaten: ' + source.declaration).appendTo(box);
      if (source.basis === 'ml') $('<label>').text('Für Verwendung als Rezeptzutat: gemessene Dichte (g/ml) ').append($('<input id="patsch9fs-density" inputmode="decimal" placeholder="Keine pauschale Annahme">')).appendTo(box);
      $('<button type="button" class="button">').text('Als Produkt übernehmen (ersetzt Nährwerte und Zutaten)').on('click', function () {
        $('#patsch9fs-mode').val('manual'); $('#patsch9fs-basis').val(selected.basis);
        keys.forEach(key => panel.find('[data-nutrient="' + key + '"]').val(selected.nutrients[key] == null ? '' : plainDecimal(selected.nutrients[key])));
        $('#patsch9fs-ingredients').val(selected.declaration || '');
        $('#patsch9fs-traces').val(selected.traces || '');
        panel.find('[name="patsch9fs[allergens][]"]').each(function () {$(this).prop('checked', (selected.allergens || []).includes($(this).val()));});
        $('#patsch9fs-sources-json').val(JSON.stringify([selected.source]));
        // A source change invalidates product-specific category assumptions.
        $('#patsch9fs-category').val('excluded'); $('#patsch9fs-fruit').val(''); $('#patsch9fs-sweeteners').val('');
        mode(); dirty(); message('Übernommen. Zutatenliste, Allergene und Bezugsgröße prüfen, dann freigeben.');
      }).appendTo(box);
      $('<button type="button" class="button">').text('Als Rezeptzutat hinzufügen').on('click', function () {
        if (rows.length >= 100) return fail(new Error('Höchstens 100 Zutaten.'));
        let density = 1;
        if (selected.basis === 'ml') {
          const raw = String($('#patsch9fs-density').val()).replace(',', '.');
          if (!/^\d+(\.\d+)?$/.test(raw) || !(Number(raw) > 0) || Number(raw) > 5) return fail(new Error('Für ml-Daten ist eine positive gemessene Dichte in g/ml erforderlich.'));
          density = Number(raw);
        }
        const nutrients = {};
        const impossible = [];
        keys.forEach(function (key) {
          if (selected.nutrients[key] == null) {
            nutrients[key] = null;
            return;
          }
          const converted = selected.nutrients[key] / density;
          if (!key.startsWith('energy_') && converted > 100.000001) impossible.push(PATSCH9FS.labels[key]);
          nutrients[key] = plainDecimal(converted);
        });
        if (impossible.length) return fail(new Error('Dichte und Quellwerte sind widersprüchlich: ' + impossible.join(', ') + ' würde je 100 g über 100 g liegen. Bitte Dichte/Packungsangaben prüfen oder belastbare Werte je 100 g manuell verwenden.'));
        rows.push({name: selected.name, declaration: selected.declaration || '', grams: '', nutrients: nutrients, source: selected.source});
        drawRows(); dirty(); message('Zutat hinzugefügt. Grammmenge und Unterzutaten ergänzen.');
      }).appendTo(box);
    }
    $('#patsch9fs-search-button').on('click', function () {
      message('Suche läuft …');
      request('search', {query: $('#patsch9fs-search').val()}).then(function (list) {
        $('#patsch9fs-results').empty();
        list.forEach(function (r) {$('<option>').val(r.id).text(r.id + ' · ' + r.name).appendTo('#patsch9fs-results');});
        message(list.length ? list.length + ' Treffer (maximal 30). Bei Bedarf Suchbegriff eingrenzen.' : 'Keine Treffer. Suchbegriff anpassen.');
      }).catch(fail);
    });
    $('#patsch9fs-select-bls').on('click', function () {
      request('bls', {id: $('#patsch9fs-results').val() || ''}).then(showSource).catch(fail);
    });
    $('#patsch9fs-select-off').on('click', function () {
      if (!PATSCH9FS.offEnabled) return fail(new Error('Open Food Facts zuerst unter WooCommerce → Lebensmitteldaten aktivieren.'));
      message('Open Food Facts wird abgefragt …');
      request('off', {barcode: $('#patsch9fs-barcode').val()}).then(showSource).catch(fail);
    });
    $('#patsch9fs-add-manual').on('click', function () {
      if (rows.length >= 100) return fail(new Error('Höchstens 100 Zutaten.'));
      rows.push({name: '', declaration: '', grams: '', nutrients: Object.fromEntries(keys.map(k => [k, null])), source: {}});
      drawRows(); dirty();
    });
    $('#patsch9fs-calculate').on('click', function () {
      request('recipe', {rows: JSON.stringify(rows), yield: $('#patsch9fs-yield').val()}).then(function (result) {
        $('#patsch9fs-mode').val('recipe'); $('#patsch9fs-basis').val('g');
        keys.forEach(key => panel.find('[data-nutrient="' + key + '"]').val(result.nutrients[key] == null ? '' : plainDecimal(result.nutrients[key])));
        const box = $('#patsch9fs-recipe-preview').empty();
        $('<p>').text('Einsatz: ' + result.input_grams + ' g · Fertiggewicht: ' + result.yield_grams + ' g.').appendTo(box);
        $('<p>').text('Zutatenvorschlag: ' + result.ingredients).appendTo(box);
        $('<button type="button" class="button">').text('Vorschlag in Zutatenliste übernehmen und dort prüfen').on('click', function () {$('#patsch9fs-ingredients').val(result.ingredients); dirty();}).appendTo(box);
        mode(); dirty(); message('Berechnet. Beim Speichern wird serverseitig erneut gerechnet. Allergene und Zutatenliste selbst prüfen.');
      }).catch(fail);
    });
    drawRows(); mode();
  });
})(jQuery);
