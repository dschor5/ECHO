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

function addThreadToMenu(conversation_id, thread_id, thread_name) {
    var linkTag = document.createElement('a');
    linkTag.setAttribute('href', BASE_URL + '/chat/' + thread_id);
    var span1 = document.createElement('span');
    span1.setAttribute('id', 'room-name-' + thread_id);
    span1.innerHTML = thread_name;
    var span2 = document.createElement('span');
    span2.setAttribute('id', 'room-new-' + thread_id);
    linkTag.appendChild(span1);
    linkTag.appendChild(span2);
    document.querySelector('#new-thread').prepend(linkTag);
}