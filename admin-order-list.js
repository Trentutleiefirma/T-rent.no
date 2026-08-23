;
jQuery(function($){
   $('input[name="_wp_http_referer"]').remove();
   $(document).on('submit', '#wc-orders-filter-rnb', function(e){
    e.preventDefault();
    let currentBtnText =  $(this).find('#doaction').val();
    console.log(currentBtnText);
    $(this).find('#doaction').val(currentBtnText+ ' ...');
      var data = {
        'action': 'rnb_order_table',
        'data'  :  $(this).serialize(),
        '_nonce': rnb_order_list._nonce
	 };
    // console.log(rnb_order_list);
	jQuery.post(rnb_order_list.ajax_url, data, function( res ) {
        location.reload();
        $(this).find('#doaction').text(currentBtnText);
	});
   });
    // update column display list 
    $(document).on('change', '.rnb-advance-settings input[type="checkbox"]', function(e){
        e.preventDefault();
        let allSelectedValue = [];
        $(this).parents('.rnb-advance-settings').find('input[type="checkbox"]:checked').each(function(){
            allSelectedValue.push($(this).val());
        });
        var data = {
            'action': 'update_screen_options_columns',
            'data'  :  allSelectedValue,
            '_nonce': rnb_order_list._nonce
         };
        // console.log(rnb_order_list);
        jQuery.post(rnb_order_list.ajax_url, data, function( res ) {
            console.log(res);
        });
    });
   // search submit 
   $(document).on('click', '.rnb_page_rnb-order #search-submit', function(e){
        e.preventDefault();
        var currentURL = window.location.href.split('?')[0];

        updateURLParameter(currentURL, 's', $('#orders-search-input-search-input').val());
        console.log($('#orders-search-input-search-input').val())
   });
});
function updateURLParameter(url, param, value) {
    var urlParams = new URLSearchParams(window.location.search);

    // Set or update the parameter
    urlParams.set(param, value);

    // Build the new URL
    var newURL = url + '?' + urlParams.toString();

    // Update the browser URL without reloading the page
    window.history.pushState({ path: newURL }, '', newURL);
    location.reload();
  }

