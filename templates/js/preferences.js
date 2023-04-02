/**
 * Hide dialogs and response fields.
 */
function closeModal() {
    $('#dialog-avatar').dialog('widget').hide('highlight', 0);
    $('div.dialog-response').hide();
}

/**
 * Build JQuery dialogs for editing and deleting/resetting users accounts.
 */
$(document).ready(function() {

    // Dialog to edit user accounts.
    $('#dialog-avatar').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 420,
        width: 400,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Cancel',
                click: function() { $(this).dialog('close'); }
            },
            {
                text: 'Save Avatar',
                click: editUser
            }
        ],
        modal: true,
    });

});
