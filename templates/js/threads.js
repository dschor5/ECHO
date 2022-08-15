$(document).ready(function() {
    // Video
    $('#dialog-thread').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 400,
        width: 600,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Create Thread',
                id: 'create-thread',
                click: function() { createThread() }
            }
        ],
        modal: true,
        close: closeModal
    });
});

function openThreadModal()
{
    $('#dialog-thread').dialog('open');
}

function createThread()
{
    // Get text and make sure it is not empty.
    var threadName = ($('#thread_name').val()).trim();
    if(threadName.length == 0) {
        return;
    }

    // Send AJAX request to save the message. 
    $.ajax({
        url:  BASE_URL + "/ajax",
        type: "POST",
        data: {
            action: 'chat',
            subaction: 'newThread',
            conversation_id: $('#conversation_id').val(),
            thread_name: threadName,
        },
        dataType: 'json',

        // On success, build the message to display on the screen.
        success: function(resp) {
            if(resp.success) {
                location.href = BASE_URL + '/chat/' + resp.thread_id;
            }
            else {
                // TODO - Add error on screen
                console.error(resp.error);
            }
        },
    });
}
