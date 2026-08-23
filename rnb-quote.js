'use strict';

(function ($) {
  $('form.ajax_cart').on('submit', function (e) {
    var searchFormData = $(this).serializeArray(),
      dataObj = {};

    $(searchFormData).each(function (i, field) {
      dataObj[field.name] = field.value;
    });

    var data = {
      action: 'rnb_quote_booking_data',
      quote_id: dataObj.quote_id,
      product_id: dataObj.product_id,
      nonce: RFQ_QUOTE.nonce,
    };

    /**
     * Ajax calling
     *
     * @since 1.0.0
     * @return null
     */
    $.when(
      $.ajax({
        type: 'POST',
        url: RFQ_QUOTE.ajax_url,
        dataType: 'JSON',
        data: data,
        success: function (restult) {},
      })
    ).then(function (data, textStatus, jqXHR) {
      location.reload();
    });

    e.preventDefault();
  });
  $(window).on('load', function(){
    $('.rfq-to-cart').submit();
  });
  $('.rfq-to-cart').on('submit', function(e){
    e.preventDefault();
    var searchFormData = $(this).serializeArray(),
      dataObj = {};
    $(searchFormData).each(function (i, field) {
      dataObj[field.name] = field.value;
    });
    if(dataObj.quote_redirect_home){
      window.location.href = $('.rfq-home-redirect').data('redirect');
    }else{
    let redirect_url  = dataObj.checkout_url
    var data = {
      action: 'rnb_quote_booking_data',
      quote_id: dataObj.quote_id,
      product_id: dataObj.product_id,
      nonce: RFQ_QUOTE.nonce,
    };

    /**
     * Ajax calling
     *
     * @since 1.0.0
     * @return null
     */
    $.when(
      $.ajax({
        type: 'POST',
        url: RFQ_QUOTE.ajax_url,
        dataType: 'JSON',
        data: data,
        success: function (restult) {
         
        },
      })
    ).then(function (data, textStatus, jqXHR) {
      window.location.href = redirect_url;
    });
   }
  });
})(jQuery);
