function deleteOrResetUser() {
    $.ajax({
        url: BASE_URL + '/users',
        type: 'POST',
        data: {
            subaction: $('#confirm-subaction').val(),        
            user_id: $('#confirm-user-id').val(),        
        },
        dataType: 'json',
        success: function() {
            location.href = BASE_URL + '/users';
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
