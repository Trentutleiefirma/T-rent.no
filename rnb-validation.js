jQuery(document).ready(($) => {
  const bookNowButtonSelector = $('.redq_add_to_cart_button');
  const $errorContainer = $('#formError');
  const inputSelectors = "input[type='text'], input[type='checkbox'], select";

  /**
   * Removes the error class when a field is filled.
   * Listens for changes or keyup events on specified input fields.
   */
  const removeErrorOnInput = () => {
    $(document).on('change keyup', inputSelectors, function () {
      if ($(this).val()) {
        $(this).removeClass('error');
        $(this).parent('.rnb-select-wrapper').find('h5').removeClass('error');
        $(this).closest('.attributes').prev('h5').removeClass('error');
      }
    });
  };

  /**
   * Handles the click event on the Book Now button.
   * Validates required fields and displays error messages if necessary.
   */
  const onBookNowBtnClick = () => {
    bookNowButtonSelector.on('click', (e) => {
      const requiredFields = CALENDAR_DATA.validate_fields;
      let errors = [];

      $('.error').removeClass('error');
      $errorContainer.empty();

      requiredFields.forEach((field) => {
        const $field = $(field.selector);

        if ($field.length === 0) return;

        const isCheckboxGroup =
          field.checkboxGroup && $field.filter(':checked').length === 0;
        const isEmpty = !$field.val();

        if (isCheckboxGroup || isEmpty) {
          $field
            .closest('.attributes, .rnb-select-wrapper')
            .find(field.titleTag)
            .addClass('error');
          errors.push(`<li>${field.message}</li>`);
        }
      });

      if (errors.length > 0) {
        e.preventDefault();
        $errorContainer
          .html(
            `<ul class="validate-notice woocommerce-error">${errors.join(
              ''
            )}</ul>`
          )
          .show();

        // Scroll to the error container
        $('html, body').animate(
          {
            scrollTop: $errorContainer.offset().top - 50,
          },
          500
        );
      } else {
        $errorContainer.hide();
      }
    });
  };

  removeErrorOnInput();
  onBookNowBtnClick();

  // Other configurations
  $('.price-showing').hide();
  $('.rnb-pricing-plan-link').click(() => $('.price-showing').slideToggle());

  $('.close-animatedModal i').on('click', () => {
    $('#animatedModal').removeClass('zoomIn');
    $('body').removeClass('rnbOverflow');
  });

  $("input[name='cat_quantity']").change(function () {
    const $self = $(this);
    const val = $self.val();
    const cat_val = $self
      .closest('.categories-attr')
      .find('.carrental_categories')
      .val()
      .split('|');
    cat_val[4] = val;
    $self
      .closest('.categories-attr')
      .find('.carrental_categories')
      .val(cat_val.join('|'));
  });

  $(
    '<li class="book-now" style="display: none;"><button type="submit" class="single_add_to_cart_button redq_add_to_cart_button btn-book-now button alt">Book Now Modal</button></li>'
  ).appendTo($('ul[aria-label="Pagination"]'));
});
