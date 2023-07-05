var archiveProgressTimeout = null;

function saveArchive(downloadType) {

    
    $('#progress-archive').progressbar('widget').show('highlight', 0);
    $('#dialog-progress').dialog('open');
    if(archiveProgressTimeout != null) {
        clearTimeout(archiveProgressTimeout);
    }
    archiveProgressTimeout = setTimeout(checkArchiveProgress, 1000);

    $.ajax({
        url: BASE_URL + "/ajax",
        type: 'POST',
        data: {
            action: 'admin',
            subaction: downloadType,
            timezone: $('#archive-tz option:selected').val(),
            perspective: $('#archive-perspective option:selected').val(),
            scope: $('#archive-scope option:selected').val(),
            notes: $('#archive-notes').val(),
        },
        dataType: 'json',
        timeout: 1000 * 60 * 20,
        error: function(data) {
            $('#progress-archive').progressbar('widget').show('highlight', 0);
            $('#dialog-progress').dialog('close');
            $('div.dialog-response').text('Error creating archive. See log for details.');
            $('div.dialog-response').show();
            clearTimeout(archiveProgressTimeout);
        },
        success: function(data) {
            if(data.success != true) {
                $('div.dialog-response').text(data.error);
                $('div.dialog-response').show();
                $('#progress-archive').progressbar('widget').show('highlight', 0);
                $('#dialog-progress').dialog('close');
                clearTimeout(archiveProgressTimeout);
            }
            else {
                location.href = BASE_URL + '/admin/data';
            }
        }
    });    
}

function checkArchiveProgress() {
    $.ajax({
        url: BASE_URL + "/ajax",
        type: 'POST',
        data: {
            action: 'admin',
            subaction: 'backupstatus',
        },
        dataType: 'json',
        timeout: 800,
        error: function(data) {
            // Do nothing
        },
        success: function(data) {
            if(data.success == true) {
                var percent = parseFloat(data.currCount) / data.totalCount * 100;
                $('#progress-archive').progressbar('value', percent);
            }
        }
    });

    archiveProgressTimeout = setTimeout(checkArchiveProgress, 1000);
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
        else if(subaction == 'resetlog') {
            $('#dialog-confirm').dialog({title: 'Reset System Log'});
            $('.modal-confirm-body').text("Are you sure you want to reset the System Log?");
            $('#confirm-btn').text('Reset System Log');
        }
        else {
            $('#dialog-confirm').dialog({title: 'Delete All Data'});
            $('.modal-confirm-body').text("Are you sure you want to delete all messages and threads?");
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
                click: clearData,
            }
        ],
        modal: true,
    });

    $('#dialog-progress').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 300,
        width: 400,
        position: { my: "center center", at: "center center-25%", of: window },
        modal: true,
        open: function(event, ui) {
            $(".ui-dialog-titlebar-close").hide();
        }
    });
    $('#progress-archive').progressbar({value: false});

});
