jQuery(document).ready(function ($) {
  const ajaxUrl = RFQ_DATA.ajax_url;
  const translatedStrings = RFQ_DATA.translated_strings;
  const isEnableGdpr = RFQ_DATA.enable_gdpr;
  const nonce = RFQ_DATA.nonce;
  /**
   * Configuration for RFQ
   *
   * @since 1.0.0
   * @return null
   */
  $('.quote-submit i').hide();
  $('#quote-content-confirm').magnificPopup({
    items: {
      src: '#quote-popup',
      type: 'inline',
    },
    preloader: false,
    focus: '#quote-username',

    // When elemened is focused, some mobile browsers in some cases zoom in
    // It looks not nice, so we disable it:
    callbacks: {
      beforeOpen: function () {
        if ($(window).width() < 700) {
          this.st.focus = false;
        } else {
          this.st.focus = '#quote-username';
        }
      },
      open: function () {},
    },
  });

  $('.quote-submit').on('click', function (e) {
    e.preventDefault();
    $('.quote-submit i').show();
    const cartData = $('.cart').serializeArray();
    const modalForm = {
      quote_username: $('#quote-username').val(),
      quote_password: $('#quote-password').val(),
      quote_first_name: $('#quote-first-name').val(),
      quote_last_name: $('#quote-last-name').val(),
      quote_email: $('#quote-email').val(),
      quote_phone: $('#quote-phone').val(),
      quote_message: $('#quote-message').val(),
    };

    var product_id = $('.product_id').val(),
      quote_price = $('.quote_price').val();

    let errorMsg = '';
    let proceed = true;
    $(
      '#quote-popup input[required=true], #quote-popup textarea[required=true]'
    ).each(function () {
      if (!$.trim($(this).val())) {
        //if this field is empty
        const atrName = $(this).attr('name');

        if (atrName == 'quote-first-name') {
          errorMsg += `${translatedStrings.quote_first_name}<br>`;
        } else if (atrName == 'quote-email') {
          errorMsg += `${translatedStrings.quote_email}<br>`;
        } else if (atrName == 'quote-message') {
          errorMsg += `${translatedStrings.quote_message}<br>`;
        } else if (atrName == 'quote-last-name') {
          errorMsg += `${translatedStrings.quote_last_name}<br>`;
        } else if (atrName == 'quote-phone') {
          errorMsg += `${translatedStrings.quote_phone}<br>`;
        } else if (atrName == 'quote-username') {
          errorMsg += `${translatedStrings.quote_user_name}<br>`;
        } else if (atrName == 'quote-password') {
          errorMsg += `${translatedStrings.quote_password}<br>`;
        }
        proceed = false; //set do not proceed flag
      }
      //check invalid email
      const email_reg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
      if (
        $(this).attr('type') === 'email' &&
        !email_reg.test($.trim($(this).val()))
      ) {
        $(this).parent().addClass('has-error');
        proceed = false; //set do not proceed flag
        errorMsg += `${translatedStrings.email_validation_message}<br>`;
      }

      // check invalid phone number
      const phone_reg = /[0-9\-\(\)\s]+/;
      if (
        $(this).attr('name') === 'quote-phone' &&
        !phone_reg.test($.trim($(this).val()))
      ) {
        $(this).parent().addClass('has-error');
        proceed = false; //set do not proceed flag
        errorMsg += `${translatedStrings.phone_validation_message}<br>`;
      }

      if (
        isEnableGdpr &&
        $(this).attr('name') === 'accept_gdpr' &&
        !$('#accept_gdpr').is(':checked')
      ) {
        proceed = false;
        errorMsg += `${translatedStrings.accept_gdpr_message}<br>`;
      }

      if (errorMsg !== undefined && errorMsg !== '') {
        $('.quote-modal-message').slideDown().html(errorMsg);
        $('.quote-submit i').hide();
      }
    });
    if (proceed) {
      cartData.push({ forms: modalForm });
      const quote_params = {
        action: 'redq_request_for_a_quote',
        form_data: cartData,
        user_data: modalForm,
        product_id: product_id,
        quote_price: quote_price,
        nonce: nonce,
      };

      $.ajax({
        url: ajaxUrl,
        dataType: 'json',
        type: 'POST',
        data: quote_params,
        success: function (response) {
          $('.quote-modal-message').html(response.message);
          $('.quote-submit i').hide();
          if (response.status_code === 200) {
            setTimeout(function () {
              $('#quote-content-confirm').magnificPopup('close');
            }, 2000);
          }
        },
      });
    }
  });
});
