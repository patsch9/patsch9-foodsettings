/* global PATSCH9FS_PRODUCT_OPTIONS, jQuery */
(function ($) {
  'use strict';

  $(function () {
    const headerToggle = $('#_patsch9fs_food_enabled');
    const panel = $('#patsch9fs-food');
    const innerToggle = $('#patsch9fs-enabled');

    if (!headerToggle.length || !panel.length || !innerToggle.length) return;

    innerToggle.closest('.patsch9fs-field').hide().attr('aria-hidden', 'true');

    if (!$('#patsch9fs-withdrawal_ack').length) {
      const field = $('<div class="patsch9fs-field patsch9fs-withdrawal-field">');
      const label = $('<label for="patsch9fs-withdrawal_ack">').text(PATSCH9FS_PRODUCT_OPTIONS.withdrawalLabel);
      const checkbox = $('<input type="checkbox" id="patsch9fs-withdrawal_ack" name="patsch9fs[withdrawal_ack]" value="1">')
        .prop('checked', !!PATSCH9FS_PRODUCT_OPTIONS.withdrawalAck);
      label.append(checkbox);
      field.append(label);
      $('<p class="description">').text(PATSCH9FS_PRODUCT_OPTIONS.withdrawalDescription).appendTo(field);

      const anchor = $('#patsch9fs-same_variations').closest('.patsch9fs-field');
      if (anchor.length) field.insertAfter(anchor);
      else panel.find('.patsch9fs-wrap').prepend(field);
    }

    function syncVisibility(markDirty) {
      const enabled = headerToggle.is(':checked');
      const changed = innerToggle.is(':checked') !== enabled;
      innerToggle.prop('checked', enabled);
      $('.patsch9fs_food_options').toggle(enabled);

      if (!enabled && panel.is(':visible')) {
        const general = $('.general_options a').first();
        if (general.length) general.trigger('click');
        else panel.hide();
      }

      if (markDirty && changed) innerToggle.trigger('change');
    }

    headerToggle.on('change', function () { syncVisibility(true); });
    $(document.body).on('woocommerce-product-type-change', function () { syncVisibility(false); });
    syncVisibility(false);
  });
})(jQuery);
