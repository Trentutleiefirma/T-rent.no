(function ($) {
  const ajaxUrl = rnb_order_data.ajax_url;
  $('body').on('click', '.rnb-clear-dates', function (e) {
    let order_id = $(this).data('order_id');
    let item_id = $(this).data('item_id');
    let product_id = $(this).data('product_id');

    let payloads = {
      action: 'rnb_clear_order_item_dates',
      order_id,
      item_id,
      product_id,
      dataType: 'json',
      nonce: rnb_order_data.nonce,
    };

    if (confirm('Do you want to delete dates ?')) {
      $.post(ajaxUrl, payloads, function (response) {
        alert(response.message);
      });
    }

    e.preventDefault();
  });

  $('body').on('click', '.refund-deposit', function (e) {
    let order_id = $(this).data('order_id');
    let refund_amount = window.prompt('Please Enter Deposit Amount');

    if (!refund_amount) {
      return;
    }

    let payloads = {
      action: 'rnb_refund_deposit',
      order_id,
      refund_amount,
      dataType: 'json',
      nonce: rnb_order_data.nonce,
    };

    $.post(ajaxUrl, payloads, function (response) {
      if (!response.success) {
        alert(response.data.error);
      }

      if (response.success) {
        window.location.reload();
      }
    });

    e.preventDefault();
  });
})(jQuery);
