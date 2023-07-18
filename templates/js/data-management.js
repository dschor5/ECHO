var archiveProgressTimeout = null;
var archiveInProgress = false;

function saveArchive(downloadType) {

    $('#archive-response').hide();
    $('#progress-archive').progressbar('widget').show('highlight', 0);
    
    if(archiveProgressTimeout != null) {
        clearTimeout(archiveProgressTimeout);
    }
    archiveProgressTimeout = setTimeout(checkArchiveProgress, 100);

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
            $('#archive-response').html('ERROR: Could not connect to the server.');
            $('#archive-response').show('highlight').delay(10000).fadeOut();
            clearTimeout(archiveProgressTimeout);
        },
        success: function(data) {
            if(data.success != true) {
                $('#archive-response').html(data.error);
                $('#archive-response').show('highlight').delay(10000).fadeOut();
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
        timeout: 1600,
        error: function(data) {
            $('#create-archive-btn').css('display', 'inline');
            $('#create-archive-btn').attr('disabled', false);
            $('#progress-archive-wrapper').css('display', 'none');
            $('#archive-response').html('ERROR: Failed to connect to server.');
            $('#archive-response').show('highlight').delay(10000).fadeOut();
        },
        success: function(data) {
            if(data.success == true) {
                if(data.inprogress == true) {
                    archiveInProgress = true;
                    var percent = parseFloat(data.currCount) / data.totalCount * 100;
                    $('.progress-label-file').text("Creating archive...");
                    $('#progress-archive').progressbar('value', percent);
                    $('#create-archive-btn').css('display', 'none');
                    $('#create-archive-btn').attr('disabled', true);
                    $('#progress-archive-wrapper').css('display', 'inline');
                    archiveProgressTimeout = setTimeout(checkArchiveProgress, 2000);
                }
                else {
                    if(archiveInProgress == true) {
                        $('#archive-response').html('Finished .Refresh page to update listing.');
                        $('#archive-response').show('highlight').delay(10000).fadeOut();
                    }
                    archiveInProgress = false;
                    $('#create-archive-btn').css('display', 'inline');
                    $('#create-archive-btn').attr('disabled', false);
                    $('#progress-archive-wrapper').css('display', 'none');
                    archiveProgressTimeout = setTimeout(checkArchiveProgress, 10000);
                }
            }
            else {
                $('#create-archive-btn').css('display', 'inline');
                $('#create-archive-btn').attr('disabled', false);
                $('#progress-archive-wrapper').css('display', 'none');
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

    $('#progress-archive').progressbar({value: false});
    archiveProgressTimeout = setTimeout(checkArchiveProgress, 100);
});
