function saveArchive(downloadType) {
    $.ajax({
        url: BASE_URL,
        type: 'POST',
        data: {
            action: 'admin',
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

function clearData() {
    $.ajax({
        url: BASE_URL + '/ajax',
        type: 'POST',
        data: {
            action: 'admin',
            subaction: $('#confirm-subaction').val(),        
            archive_id: $('#confirm-archive-id').val(),        
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


function confirmAction(subaction, id, str) {
    $(document).ready(function() {
        $('#confirm-subaction').val(subaction);
        $('#confirm-archive-id').val(id);

        if(subaction == 'deletearchive') {
            $('#dialog-confirm').dialog({title: 'Delete Archive'});
            $('.modal-confirm-body').text("Are you sure you want to delete the " + str + "?");
            $('#confirm-btn').text('Delete Archive');
        }
        else {
            $('#dialog-confirm').dialog({title: 'Delete All Data'});
            $('.modal-confirm-body').text("Are you sure you want to delete all messages and user accounts (except admin accounts)?");
            $('#confirm-btn').text('Delete All Data');
        }
        
        $('#dialog-confirm').dialog('open');
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

