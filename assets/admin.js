/* global P9PS, jQuery, wp */
(function ($) {
  'use strict';
  $(function () {
    $('.p9ps-pick-logo').on('click', function () {
      const input = $(this).closest('p').find('input');
      const frame = wp.media({title: 'Offizielles Nutri-Score-Logo auswählen', multiple: false, library: {type: 'image'}});
      frame.on('select', function () { input.val(frame.state().get('selection').first().id); });
      frame.open();
    });
    const panel = $('#p9ps-food');
    if (!panel.length) return;
    let rows = JSON.parse($('#p9ps-recipe-json').val() || '[]');
    let selected = null;
    let busy = false;
    const keys = Object.keys(P9PS.labels);
    function dirty() {
      $('#p9ps-approve').prop('checked', false);
      $('.p9ps-review strong').text('Änderungen müssen vor der Shopanzeige erneut freigegeben werden.');
    }
    function sync() { $('#p9ps-recipe-json').val(JSON.stringify(rows)); }
    function message(text, error) { $('#p9ps-message').text(text).toggleClass('p9ps-error', !!error); }
    function request(task, data) {
      if (busy) return Promise.reject(new Error('Bitte laufende Abfrage abwarten.'));
      busy = true;
      panel.find('button').prop('disabled', true);
      return $.ajax({url: P9PS.url, method: 'POST', data: Object.assign({action: 'p9ps_food', task: task, nonce: P9PS.nonce, product_id: P9PS.productId}, data)})
        .then(function (r) { if (!r.success) throw new Error(r.data.message); return r.data; }, function (r) {
          throw new Error(r.responseJSON && r.responseJSON.data ? r.responseJSON.data.message : 'Abfrage fehlgeschlagen.');
        }).always(function () { busy = false; panel.find('button').prop('disabled', false); });
    }
    function fail(e) { message(e.message || String(e), true); }
    function mode() { panel.find('[data-nutrient]').prop('readonly', $('#p9ps-mode').val() === 'recipe'); }
    panel.on('input change', 'input, textarea, select', function () {
      if (this.id !== 'p9ps-approve' && !['p9ps-search', 'p9ps-results', 'p9ps-barcode', 'p9ps-density'].includes(this.id)) dirty();
    });
    $('#p9ps-mode').on('change', mode);
    function drawRows() {
      const container = $('#p9ps-recipe-rows').empty();
      rows.forEach(function (row, i) {
        const box = $('<details class="p9ps-recipe-row">');
        $('<summary>').text((i + 1) + '. ' + (row.name || 'Neue Zutat') + ' – ' + (row.grams || '?') + ' g').appendTo(box);
        function input(label, value, callback, textarea) {
          const el = textarea ? $('<textarea rows="2">') : $('<input type="text">');
          el.val(value == null ? '' : value).on('input', function () {callback($(this).val()); sync(); dirty();});
          $('<label>').text(label).append(el).appendTo(box);
        }
        input('Zutatenname', row.name, v => {row.name = v;});
        input('Einsatzmenge in Gramm', row.grams, v => {row.grams = v;});
        input('Unterzutaten / Hervorhebung (optional, HTML <strong>)', row.declaration, v => {row.declaration = v;}, true);
        const warning = $('<p class="p9ps-error" role="status">').appendTo(box);
        function consistencyWarning() {
          const conflicts = [['saturates', 'fat'], ['sugars', 'carbs']].filter(function (pair) {
            const child = row.nutrients[pair[0]], total = row.nutrients[pair[1]];
            if (child == null || total == null || child === '' || total === '') return false;
            return Number(String(child).replace(',', '.')) > Number(String(total).replace(',', '.')) + 0.000001;
          });
          warning.text(conflicts.length ? 'Widersprüchliche Zutatenwerte: ' + conflicts.map(pair => P9PS.labels[pair[0]] + ' über ' + P9PS.labels[pair[1]]).join('; ') + '. Bitte anhand belastbarer Herstellerwerte korrigieren oder eine andere Quelle wählen. Werte werden nicht automatisch gekappt.' : '').toggle(conflicts.length > 0);
          return conflicts.length > 0;
        }
        const grid = $('<div class="p9ps-nutrient-grid">').appendTo(box);
        keys.forEach(function (key) {
          const el = $('<input type="text" inputmode="decimal">').val(row.nutrients[key] == null ? '' : row.nutrients[key]);
          el.on('input', function () {row.nutrients[key] = $(this).val(); consistencyWarning(); sync(); dirty();});
          $('<label>').text(P9PS.labels[key] + ' / 100 g').append(el).appendTo(grid);
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
      const box = $('#p9ps-source-preview').empty();
      $('<h4>').text(source.name + (source.brands ? ' · ' + source.brands : '')).appendTo(box);
      $('<p>').text(source.warning).appendTo(box);
      const table = $('<table class="widefat">').appendTo(box);
      keys.forEach(function (key) {
        const tr = $('<tr>').appendTo(table);
        $('<th>').text(P9PS.labels[key] + ' / 100 ' + source.basis).appendTo(tr);
        $('<td>').text(source.nutrients[key] == null ? 'unbekannt' : String(source.nutrients[key])).appendTo(tr);
      });
      if (source.declaration) $('<p>').text('Zutaten: ' + source.declaration).appendTo(box);
      if (source.basis === 'ml') $('<label>').text('Für Verwendung als Rezeptzutat: gemessene Dichte (g/ml) ').append($('<input id="p9ps-density" inputmode="decimal" placeholder="Keine pauschale Annahme">')).appendTo(box);
      $('<button type="button" class="button">').text('Als Produkt übernehmen (ersetzt Nährwerte und Zutaten)').on('click', function () {
        $('#p9ps-mode').val('manual'); $('#p9ps-basis').val(selected.basis);
        keys.forEach(key => panel.find('[data-nutrient="' + key + '"]').val(selected.nutrients[key] == null ? '' : selected.nutrients[key]));
        $('#p9ps-ingredients').val(selected.declaration || '');
        $('#p9ps-traces').val(selected.traces || '');
        panel.find('[name="p9ps[allergens][]"]').each(function () {$(this).prop('checked', (selected.allergens || []).includes($(this).val()));});
        $('#p9ps-sources-json').val(JSON.stringify([selected.source]));
        // A source change invalidates product-specific category assumptions.
        $('#p9ps-category').val('excluded'); $('#p9ps-fruit').val(''); $('#p9ps-sweeteners').val('');
        mode(); dirty(); message('Übernommen. Zutatenliste, Allergene und Bezugsgröße prüfen, dann freigeben.');
      }).appendTo(box);
      $('<button type="button" class="button">').text('Als Rezeptzutat hinzufügen').on('click', function () {
        if (rows.length >= 100) return fail(new Error('Höchstens 100 Zutaten.'));
        let density = 1;
        if (selected.basis === 'ml') {
          const raw = String($('#p9ps-density').val()).replace(',', '.');
          if (!/^\d+(\.\d+)?$/.test(raw) || !(Number(raw) > 0) || Number(raw) > 5) return fail(new Error('Für ml-Daten ist eine positive gemessene Dichte in g/ml erforderlich.'));
          density = Number(raw);
        }
        const nutrients = {};
        keys.forEach(function (key) {nutrients[key] = selected.nutrients[key] == null ? null : selected.nutrients[key] / density;});
        rows.push({name: selected.name, declaration: selected.declaration || '', grams: '', nutrients: nutrients, source: selected.source});
        drawRows(); dirty(); message('Zutat hinzugefügt. Grammmenge und Unterzutaten ergänzen.');
      }).appendTo(box);
    }
    $('#p9ps-search-button').on('click', function () {
      message('Suche läuft …');
      request('search', {query: $('#p9ps-search').val()}).then(function (list) {
        $('#p9ps-results').empty();
        list.forEach(function (r) {$('<option>').val(r.id).text(r.id + ' · ' + r.name).appendTo('#p9ps-results');});
        message(list.length ? list.length + ' Treffer (maximal 30). Bei Bedarf Suchbegriff eingrenzen.' : 'Keine Treffer. Suchbegriff anpassen.');
      }).catch(fail);
    });
    $('#p9ps-select-bls').on('click', function () {
      request('bls', {id: $('#p9ps-results').val() || ''}).then(showSource).catch(fail);
    });
    $('#p9ps-select-off').on('click', function () {
      if (!P9PS.offEnabled) return fail(new Error('Open Food Facts zuerst unter WooCommerce → Lebensmitteldaten aktivieren.'));
      message('Open Food Facts wird abgefragt …');
      request('off', {barcode: $('#p9ps-barcode').val()}).then(showSource).catch(fail);
    });
    $('#p9ps-add-manual').on('click', function () {
      if (rows.length >= 100) return fail(new Error('Höchstens 100 Zutaten.'));
      rows.push({name: '', declaration: '', grams: '', nutrients: Object.fromEntries(keys.map(k => [k, null])), source: {}});
      drawRows(); dirty();
    });
    $('#p9ps-calculate').on('click', function () {
      request('recipe', {rows: JSON.stringify(rows), yield: $('#p9ps-yield').val()}).then(function (result) {
        $('#p9ps-mode').val('recipe'); $('#p9ps-basis').val('g');
        keys.forEach(key => panel.find('[data-nutrient="' + key + '"]').val(result.nutrients[key] == null ? '' : result.nutrients[key]));
        const box = $('#p9ps-recipe-preview').empty();
        $('<p>').text('Einsatz: ' + result.input_grams + ' g · Fertiggewicht: ' + result.yield_grams + ' g.').appendTo(box);
        $('<p>').text('Zutatenvorschlag: ' + result.ingredients).appendTo(box);
        $('<button type="button" class="button">').text('Vorschlag in Zutatenliste übernehmen und dort prüfen').on('click', function () {$('#p9ps-ingredients').val(result.ingredients); dirty();}).appendTo(box);
        mode(); dirty(); message('Berechnet. Beim Speichern wird serverseitig erneut gerechnet. Allergene und Zutatenliste selbst prüfen.');
      }).catch(fail);
    });
    drawRows(); mode();
  });
})(jQuery);
