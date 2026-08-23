;
jQuery(function($){
    "use strict"
    $('<div class="rnb-cancel-order-container"></div>').insertAfter(".rnb_cancel_order");
    $('.rnb_cancel_order').on('click', function(e){
        e.preventDefault();
        let orderData = $(this).attr('href');
        $(this).siblings('.rnb-cancel-order-container').toggleClass('show')
        let cancelReasonRequired = rnb_cancel_order_obj.cancel_reason_require
        let cancelBefore = rnb_cancel_order_obj.order_cancel_before;
        let cancelTime = rnb_cancel_order_obj.order_cancel_before_time
        let customerNote = rnb_cancel_order_obj.customer_note
        let popupTemplate = `
        <div class="rnb-cancel-order-popup-content">
           <button class="close-rnb-cancel-popup">X</button>
           <div class="popup-heading">
             <h3> You can cancel order before ${cancelTime} ${cancelBefore}s</h3>
             <p>${customerNote}</p>
           </div>
            <div class="order-note">
                <label for="rnb-cancel-order-customer-note">Cancel Reason</label>
                <textarea class="rnb-cancel-reason required-${cancelReasonRequired}" id="rnb-cancel-order-customer-note"></textarea>
            </div>
            <button type="button" class="rnb-cancel-now" data-url="${orderData}">Cancel Now</button>
        </div>
        `;
        $(this).siblings('.rnb-cancel-order-container').html(popupTemplate);
    });
     // call ajax 
    $(document).on('click', '.rnb-cancel-now', function( e ){
        e.preventDefault();
        let popupWrapper = $(this).parent();
        let preloader = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><radialGradient id="a8" cx=".66" fx=".66" cy=".3125" fy=".3125" gradientTransform="scale(1.5)"><stop offset="0" stop-color="#3F153D"></stop><stop offset=".3" stop-color="#3F153D" stop-opacity=".9"></stop><stop offset=".6" stop-color="#3F153D" stop-opacity=".6"></stop><stop offset=".8" stop-color="#3F153D" stop-opacity=".3"></stop><stop offset="1" stop-color="#3F153D" stop-opacity="0"></stop></radialGradient><circle transform-origin="center" fill="none" stroke="url(#a8)" stroke-width="15" stroke-linecap="round" stroke-dasharray="200 1000" stroke-dashoffset="0" cx="100" cy="100" r="70"><animateTransform type="rotate" attributeName="transform" calcMode="spline" dur="2" values="360;0" keyTimes="0;1" keySplines="0 0 1 1" repeatCount="indefinite"></animateTransform></circle><circle transform-origin="center" fill="none" opacity=".2" stroke="#3F153D" stroke-width="15" stroke-linecap="round" cx="100" cy="100" r="70"></circle></svg>`;
        let customerNote = popupWrapper.find('#rnb-cancel-order-customer-note').val();
        let button = $(this).parents('.rnb-cancel-order-container').siblings('.rnb_cancel_order');
        if(customerNote && customerNote !== ''){
             $.ajax({
                type: 'POST',
                url: rnb_cancel_order_obj.ajax_url,
                data: {
                    action: 'rnb_update_order_statue',
                    nonce: rnb_cancel_order_obj.nonce,
                    orderData: $(this).data('url'),
                    cancelReason: customerNote,
                    cancelReasonRequired: rnb_cancel_order_obj.cancel_reason_require
                },
                beforeSend: function () {
                    popupWrapper.html(preloader);
                    popupWrapper.addClass('preloader'); 
                },
                success: function(response) {
                    let template = `
                     <div class="alert-container">
                        <button class="close-rnb-cancel-popup">X</button>
                        <div class="alert-content">
                            ${response.data.html}
                        </div>
                     </div>
                    `;
                    popupWrapper.removeClass('preloader');
                    popupWrapper.addClass(response.data.class)
                    popupWrapper.addClass('ajax-response')
                    popupWrapper.html(template);
                    if(response.data.class == 'success'){
                        button.remove();
                    }
                }
        });
        }else{
            alert('Cancel Reason is Required');
            return false;
        }
    });
    $(document).on('click', '.rnb-cancel-order-container', function(e){
        if (e.target !== this) return;
        $(this).toggleClass('show');
    });
    $(document).on('click', '.close-rnb-cancel-popup', function() {
        $(this).parents('.rnb-cancel-order-container').toggleClass('show');
    });
});