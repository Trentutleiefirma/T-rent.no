jQuery(function ($) {
  'use strict';
  let getHeading = $('body').find('.wp-heading-inline');
  const docs = rnb_docs.docs;
  const btnLink = rnb_docs.button_name;
  docs.forEach((item) => {
    if (getHeading.text().trim() === item.name) {
      getHeading.after(
        `<a href="${item.link}" class="additional-link">${btnLink}</a>`
      );
    }
  });
});
