jQuery(document).ready(function ($) {
  // Fetch translated strings
  const translatedStrings = MODEL_DATA.translated_strings;

  const formSelector = $('form.rnb-cart');
  const bookNowButtonSelector = $('.redq_add_to_cart_button');
  const rfqButtonSelector = $('.redq_request_for_a_quote');

  bookNowButtonSelector.attr('disabled', 'disabled');
  rfqButtonSelector.attr('disabled', 'disabled');

  const productSettings = CALENDAR_DATA.calendar_props.settings;
  const validation_data = CALENDAR_DATA.calendar_props.settings.validations;

  const ajaxUrl = CALENDAR_DATA.ajax_url;
  const nonce = CALENDAR_DATA.nonce;

  let layout_two_labels = productSettings.layout_two_labels;

  if (productSettings.conditions.booking_layout === 'layout_one') {
    handleNormalLayoutTemplates(CALENDAR_DATA);
    //Check for page load
    window.onload = function () {
      $('form.cart').trigger('change');
    };
  } else {
    handleModalLayoutTemplates(CALENDAR_DATA);
  }

  localStorage.setItem('rentalAttributes', JSON.stringify({})); //Reset rental attributes

  //loaders
  const buttonLoader = $(
    "<span class='rnb-book-btn-loader'><svg version='1.1' x='0px' y='0px' viewBox='0 0 60 100' enable-background='new 0 0 0 0'><circle fill='currentColor' stroke='none' cx='6' cy='50' r='6'><animate attributeName='opacity' dur='1s' values='0;1;0' repeatCount='indefinite' begin='0.1'></animate></circle><circle fill='currentColor' stroke='none' cx='26' cy='50' r='6'><animate attributeName='opacity' dur='1s' values='0;1;0' repeatCount='indefinite' begin='0.2'></animate></circle><circle fill='currentColor' stroke='none' cx='46' cy='50' r='6'><animate attributeName='opacity' dur='1s' values='0;1;0' repeatCount='indefinite' begin='0.3'></animate></circle></svg></span>"
  );
  const inventoryLoader = $(
    "<span class='rnb-inventory-loader'><svg width='38' height='38' viewBox='0 0 38 38' stroke='#000'><g fill='none' fill-rule='evenodd'><g transform='translate(1 1)' stroke-width='2'><circle stroke-opacity='.3' cx='18' cy='18' r='18'/><path d='M36 18c0-9.94-8.06-18-18-18'><animateTransform attributeName='transform' type='rotate' from='0 18 18' to='360 18 18' dur='1s' repeatCount='indefinite'/></path></g></g></svg></span>"
  );

  function handleNormalLayoutTemplates(response) {
    RNB_TEMPLATES.pickupLocation(response.pick_up_locations);
    RNB_TEMPLATES.dropoffLocation(response.return_locations);
    RNB_TEMPLATES.resource(response.resources);
    RNB_TEMPLATES.category(response.categories);
    RNB_TEMPLATES.adults(response.adults);
    RNB_TEMPLATES.childs(response.childs);
    RNB_TEMPLATES.deposit(response.deposits);
  }

  function handleModalLayoutTemplates(response) {
    RNB_TEMPLATES.resourceModal(response.resources);
    RNB_TEMPLATES.adultModal(response.adults);
    RNB_TEMPLATES.childModal(response.childs);
    RNB_TEMPLATES.depositModal(response.deposits);
  }

  /**
   *inventorySwitching
   *
   * Fire when inventory changes
   */
  function inventorySwitching() {
    $('#booking_inventory').change(function () {
      const inventoryID = $(this).val();
      const postID = $(this).data('post-id');

      RNB_HELPER.loadingEffect('form.rnb-cart');

      $.ajax({
        type: 'post',
        dataType: 'json',
        url: ajaxUrl,
        data: {
          action: 'rnb_get_inventory_data',
          inventory_id: inventoryID,
          nonce,
          post_id: postID,
        },
        //inventory change
        beforeSend: function (xhr) {
          //add loader

          if (formSelector.find('.rnb-inventory-loader').length === 0) {
            formSelector.append(inventoryLoader);
          }
        },
        success: function (response) {
          if (response.hasOwnProperty('success') && !response.success) {
            alert(response.message);
            formSelector.find('.rnb-inventory-loader').remove();
            return;
          }

          RNB_HELPER.resetFields();
          RNB_HELPER.rnbHandleErrorAction();

          BOOKING_DATA = response.booking_data;
          CALENDAR_DATA = response.calendar_data;

          RNB_HELPER.handleProductUnitPrice(BOOKING_DATA);

          RNB_CALENDER_ACTION.init(CALENDAR_DATA);
          rnbCostHandle();

          //remove loader
          formSelector.find('.rnb-inventory-loader').remove();

          const bookingLayout =
            BOOKING_DATA.rnb_data.settings.conditions.booking_layout;

          if (bookingLayout !== 'layout_two') {
            handleNormalLayoutTemplates(response);
          }

          if (bookingLayout === 'layout_two') {
            handleModalLayoutTemplates(response);
          }

          RNB_HELPER.unLoadingEffect('form.rnb-cart');
        },
        complete: function () {
          console.log('after ajax complete');
        },
      });
    });
  }

  /**
   * ModalLayout
   *
   * @type {jQuery|undefined}
   */
  function initModal() {
    const wizard = $('#rnbSmartwizard').steps({
      stepsOrientation: 'vertical',
      headerTag: 'h3',
      bodyTag: 'section',
      transitionEffect: 'fade',
      enableFinishButton: true,
      autoFocus: true,
      onFinished: function (event, currentIndex) {
        let hasError = $('body .modal-summary').attr('data-error');
        if (hasError === 'false') {
          RNB_HELPER.rnbHandleSuccessAction();
          $('li.book-now').show();
        }
        event.preventDefault();
      },
      onStepChanging: function (event, currentIndex, newIndex) {
        const pickupLocWrapper = $('.rnb-pickup-location');
        const dropoffLocWrapper = $('.rnb-dropoff-location');
        const pickupLocInput = $('input.rnb-pickup-location');
        const dropoffLocInput = $('input.rnb-dropoff-location');

        const tabName = $('li.current')
          .children('a')
          .children('span.tab-identifier')
          .data('id');

        switch (tabName) {
          case 'location':
            if (newIndex < currentIndex) {
              return true;
            }

            const pickupLoc = pickupLocWrapper.val();
            const dropoffLoc = dropoffLocWrapper.val();
            const pickupLatLng = pickupLocWrapper.attr('data-latlng');
            const dropoffLatLng = dropoffLocWrapper.attr('data-latlng');

            if (pickupLoc === '' || pickupLatLng === 'notset') {
              pickupLocInput.css('border', '1px solid red');
              return false;
            } else {
              pickupLocInput.css('border', 'none');
            }

            if (dropoffLoc === '' || dropoffLatLng === 'notset') {
              dropoffLocInput.css('border', '1px solid red');
              return false;
            } else {
              dropoffLocInput.css('border', 'none');
            }

            pickupLocInput.css('border', '1px solid #eee');
            dropoffLocInput.css('border', '1px solid #eee');

            return true;

          case 'person':
            if (newIndex < currentIndex) {
              return true;
            }

            if (validation_data.person !== 'open') {
              return true;
            }

            const isAdultSelected = $('.rnb-adult-area').find('label.checked');
            const isAdultAvailable = $('.rnb-adult-area');
            const isChildSelected = $('.rnb-child-area').find('label.checked');
            const isChildAvailable = $('.rnb-child-area');

            // If adultBlock is available then check customer click adult or not
            if (isAdultAvailable.length && !isAdultSelected.length) {
              $('span.adultWarning').show();
              return false;
            } else {
              $('span.adultWarning').hide();
            }

            // If childBlock is available then check customer click child or not
            // if (isChildAvailable.length && !isChildSelected.length) {
            //   $('span.childWarning').show();
            //   return false;
            // } else {
            //   $('span.childWarning').hide();
            // }

            return true;
          default:
            return true;
        }
      },

      onStepChanged: function (event, currentIndex, priorIndex) {
        const wrapperH3 = $('.title-wrapper h3');
        const wrapperP = $('.title-wrapper p');
        const wrapperBookNow = $('li.book-now');
        const nextBtnClass = $('#rnbSmartwizard .actions ul li:nth-child(2)');

        const tabName = $('li.current')
          .children('a')
          .children('span.tab-identifier')
          .data('id');

        switch (tabName) {
          case 'inventory':
            wrapperH3.html(layout_two_labels.inventory.inventory_top_heading);
            wrapperP.html(layout_two_labels.inventory.inventory_top_desc);
            nextBtnClass.removeClass('disabledNextOnModal');
            wrapperBookNow.hide();
            return true;
          case 'location':
            wrapperH3.html(layout_two_labels.location.location_top_heading);
            wrapperP.html(layout_two_labels.location.location_top_desc);
            nextBtnClass.removeClass('disabledNextOnModal');
            wrapperBookNow.hide();
            return true;
          case 'duration':
            wrapperH3.html(layout_two_labels.datetime.date_top_heading);
            wrapperP.html(layout_two_labels.datetime.date_top_desc);
            nextBtnClass
              .addClass('disabled disabledNextOnModal')
              .attr('aria-disabled', 'true');
            $('.date-error-message').hide();

            wrapperBookNow.hide();
            return true;
          case 'resource':
            wrapperH3.html(layout_two_labels.resource.resource_top_heading);
            wrapperP.html(layout_two_labels.resource.resource_top_desc);
            wrapperBookNow.hide();
            return true;

          case 'person':
            wrapperH3.html(layout_two_labels.person.person_top_heading);
            wrapperP.html(layout_two_labels.person.person_top_desc);
            wrapperBookNow.hide();
            return true;
          case 'deposit':
            wrapperH3.html(layout_two_labels.deposit.deposit_top_heading);
            wrapperP.html(layout_two_labels.deposit.deposit_top_desc);
            wrapperBookNow.hide();
            return true;
          case 'epo':
            wrapperH3.html(layout_two_labels.deposit.deposit_top_heading);
            wrapperP.html(layout_two_labels.deposit.deposit_top_desc);
            wrapperBookNow.hide();
            return true;
          case 'summary':
            wrapperH3.html('Summary');
            wrapperP.html('Summary Desc');
            return true;
          default:
            return true;
        }
      },
      onInit: function (event, currentIndex) {
        RNB_CALENDER_ACTION.init(CALENDAR_DATA);

        const tabName = $('li.current')
          .children('a')
          .children('span.tab-identifier')
          .data('id');

        if (tabName === 'inventory') {
          const wrapperH3 = $('.title-wrapper h3');
          const wrapperP = $('.title-wrapper p');
          wrapperH3.html(layout_two_labels.inventory.inventory_top_heading);
          wrapperP.html(layout_two_labels.inventory.inventory_top_desc);
        }

        return false;
      },
      labels: {
        cancel: translatedStrings.cancel,
        current: translatedStrings.current,
        pagination: translatedStrings.pagination,
        finish: translatedStrings.finish,
        next: translatedStrings.next,
        previous: translatedStrings.previous,
        loading: translatedStrings.loading,
      },
    });
  }

  function rnbCostHandle() {
    $('.show_if_time').hide();
    $('.redq-quantity').hide();

    // $('body').on('click', "input[name='cat_quantity[]']", function () {
    //   const qty = $(this).val();
    //   const cat_id = $(this).data('cat_id');
    //   $(this)
    //     .parents('.categories-attr')
    //     .children('.custom-block')
    //     .children('input')
    //     .val(`${cat_id}|${qty}`);
    //   $('form.cart').trigger('change');
    // });

    $('#categoryPreview').on(
      'change',
      "input[name='cat_quantity[]']",
      function () {
        const qty = $(this).val();
        const cat_id = $(this).data('cat_id');
        $(this)
          .parents('.categories-attr')
          .children('.custom-block')
          .children('input')
          .val(`${cat_id}|${qty}`);
        $('form.cart').trigger('change');
      }
    );

    $('form.cart').on('change', function (event) {
      const self = $(this);
      const dataObj = RNB_HELPER.rnbProcessFormData(self);
      const fire_ajax = RNB_HELPER.shouldFireAjax(dataObj);

      console.log(fire_ajax, 'fire_ajax');
      let currentEvent = event.target
      if(currentEvent.hasAttribute('type')){
        if(currentEvent.getAttribute('type') == 'checkbox'){
          currentEvent.parentNode.classList.toggle("checked")
        }
        if(currentEvent.getAttribute('type') == 'radio'){
          currentEvent.parentNode.classList.toggle("checked")
          $('#'+currentEvent.getAttribute('id')).parents('label').siblings('label').removeClass('checked')
        }
      }
  
      const rentalAttributes = JSON.parse(
        localStorage.getItem('rentalAttributes')
      );

      if (_.isEqual(rentalAttributes, dataObj)) {
        return;
      }

      $('.redq_add_to_cart_button').attr('disabled', 'disabled');
      rfqButtonSelector.attr('disabled', 'disabled');

      if (fire_ajax) {
        // RNB_HELPER.loadingEffect('.rnb-loader');
        $.ajax({
          type: 'post',
          dataType: 'json',
          url: ajaxUrl,
          data: {
            action: 'rnb_calculate_inventory_data',
            nonce: nonce,
            form: dataObj,
          },
          //data change
          beforeSend: function (xhr) {
            //add loader
            bookNowButtonSelector.addClass('rnb-loading');
            if (
              bookNowButtonSelector.find('.rnb-book-btn-loader').length === 0
            ) {
              bookNowButtonSelector.append(buttonLoader);
            }
          },
          success: function (response) {
            if (response.error && response.error.length) {
              $('body .modal-summary').attr('data-error', 'true');
            } else {
              $('body .modal-summary').attr('data-error', 'false');
            }
            //remove loader
            bookNowButtonSelector.removeClass('rnb-loading');
            bookNowButtonSelector.find('.rnb-book-btn-loader').remove();

            RNB_HELPER.rnbHandleError(response);
            // RNB_HELPER.unLoadingEffect('.rnb-loader');

            event.stopPropagation();

            if (parseInt(response.available_quantity) > 0) {
              $('input.inventory-qty').attr({
                max: response.available_quantity,
                min: 1,
              });

              if ($('.redq-quantity').length > 0) {
                $('.redq-quantity').show();
              }

              const priceBreakdown = response.price_breakdown;
              let priceBreakdownMarkupContent = '<ul>';

              const quote_total =
                priceBreakdown.total && priceBreakdown.total.amount
                  ? priceBreakdown.total.amount
                  : 0;
              $('.quote_price').val(quote_total);

              for (const key in priceBreakdown) {
                if (priceBreakdown.hasOwnProperty(key)) {
                  if (key === 'quote_total') continue;
                  priceBreakdownMarkupContent += `<li class="${key}"><span class="name">${priceBreakdown[key]['text']}</span> <span class="price">${priceBreakdown[key]['cost']}</span></li>`;
                }
              }

              priceBreakdownMarkupContent += '</ul>';

              $('.booking_cost').html(priceBreakdownMarkupContent);
              $('.booking-pricing-info').fadeIn();
              //End

              $('body select.person-select').chosen('destroy');
              // $('body select.person-select').val('');

              if (response.date_multiply === 'per_hour') {
                $('body .show_if_day').children('span').hide();
                $('body .show_if_time').show();

                $('body select.person-select > option').each(function () {
                  if ($(this).data('unit') === 'per_day') {
                    $(this).hide();
                  } else {
                    $(this).show();
                  }
                });
              } else {
                $('body .show_if_time').hide();
                $('body .show_if_day').children('span').show();

                $('body select.person-select > option').each(function () {
                  if ($(this).data('unit') === 'per_hour') {
                    $(this).hide();
                  } else {
                    $(this).show();
                  }
                });
              }

              $('body select.person-select').chosen().trigger('chosen:updated');

              localStorage.setItem('rentalAttributes', JSON.stringify(dataObj));

              $(document).trigger('do_stuff_after_successful_data_fetch', [
                response,
              ]);
              //Focus on quantity field
              // $('.inventory-qty').focus();
              // $('.inventory-qty-next').focus();
            }
          },
          complete: function () {
            bookNowButtonSelector.removeClass('rnb-loading');
          },
        });
      } else {
        $('.redq-quantity').hide();
      }
    });
  }

  // RnB modal
  $('#showBooking').on('click', function () {
    $('#animatedModal').toggleClass('zoomIn');
    $('body').addClass('rnbOverflow');
    inventorySwitching();
  });

  /**
   * Initialize calendar
   *
   * @since 1.0.0
   * @version 5.9.0
   * @return null
   */
  RNB_CALENDER_ACTION.init(CALENDAR_DATA);
  inventorySwitching();
  rnbCostHandle();
  initModal();
});
