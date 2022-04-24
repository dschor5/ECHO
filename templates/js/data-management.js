function downloadData(downloadType) {
    $.ajax({
        url: BASE_URL + '/admin',
        type: 'POST',
        data: {
            subaction: downloadType,
        },
        dataType: 'json',
        success: function(data) {
            if(data.success != true) {
                $('div.dialog-response').text(data.error);
                $('div.dialog-response').show();
            }
            else {
                location.href = BASE_URL + '/admin/data';
            }
        }
    });    
}

function confirmDelete() {
    $('#dialog-confirm').dialog('open');
}

function clearData() {
    $.ajax({
        url: BASE_URL + '/admin',
        type: 'POST',
        data: {
            subaction: 'clear',
        },
        dataType: 'json',
        success: function(data) {
            if(data.success != true) {
                $('div.dialog-response').text(data.error);
                $('div.dialog-response').show();
            }
            else {
                location.href = BASE_URL + '/admin/data';
            }
        }
    });
}

// Event handlers for closing modal.
$(document).ready(function() {
    $('#dialog-confirm').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 200,
        width: 400,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Cancel',
                click: function() { $(this).dialog('close'); }
            },
            {
                text: 'OK',
                id: 'confirm-btn',
                click: clearData
            }
        ],
        modal: true,
    });

});
