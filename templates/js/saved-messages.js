function toggleSaved(message_id)
{
    // Check if the message is currently displayed on the screen. 
    if($('#msg-saved-' + message_id).length == 0) {
         return;
    }
    // Send AJAX request to save the message. 
    $.ajax({
        url:  BASE_URL + "/ajax",
        type: "POST",
        data: {
            action: 'chat',
            subaction: 'toggleSave',
            message_id: message_id,
        },
        dataType: 'json',

        // On success, build the message to display on the screen.
        success: function(resp) {
            if(resp.success) {
                if(resp.is_saved) {
                    $('#msg-saved-' + message_id).html('&nbsp;&#9733;');
                }
                else {
                    $('#msg-saved-' + message_id).html('&nbsp;&#9734;');
                }
            }
            else {
                $('.dialog-response').text(resp.error);
                $('.dialog-response').show('highlight');
            }
        },
    });
}