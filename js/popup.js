/**
 * Included in all backend pages via backend_header event
 * to notify about reminders and messages.
 */

function checkPopup(crm_url) {
    setTimeout(sendRequest, 5000);
    setInterval(sendRequest, 60000);

    function sendRequest(){
        $.ajax({
            type: 'get',
            dateType: 'json',
            url: crm_url+'?module=popup&background_process=1',
            success: function(json) {
                if (json.data && json.data.length != 0) {
                    $("#crm-popup-area").empty();
                    $.each(json.data, function(){
                        if (itemShow(this.type,this.id) == true) {
                            initNotification(this.html);
                        }
                    });
                } else {
                    $("#crm-popup-area").fadeOut();
                }
            }
        });
    }
}

function itemShow(type,id) {
    if (!sessionStorage.getItem('reminder_closed')) {
        sessionStorage.setItem('reminder_closed', '');
    }
    if (!sessionStorage.getItem('message_closed')) {
        sessionStorage.setItem('message_closed', '');
    }

    var closed = sessionStorage.getItem(type+"_closed");
    var closed_arr = closed.split(',');
    var closed_search = closed_arr.indexOf(id);

    if (closed_search < 0) {
        return true;
    } else {
        return false;
    }
}

function initNotification (html) {
    $("#crm-popup-area").append(html);
    $("#crm-popup-area").fadeIn();
}

$(function() {
    $("#crm-popup-area").on('click','.c-notify-close, a.c-notify-link', function(){
        var item_data = $(this).parents('.crm-notification-popup').data('item').split('-');

        var old = sessionStorage.getItem(item_data['0']+'_closed');
        var ids = old + item_data['1'];
        sessionStorage.setItem(item_data['0']+'_closed', ids+',');

        $(this).parents('.crm-notification-popup').remove(); // remove item

        if ($('.crm-notification-popup').length < 1) {
            $("#crm-popup-area").fadeOut();
        }
    });
});
