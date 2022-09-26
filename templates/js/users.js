/**
 * Ajax request to get user information and populate fields. 
 * @param {number} id 
 */
function getUser(id) {
    $.ajax({
        url: BASE_URL + '/ajax',
        type: 'POST',
        data: {
            action: 'admin',
            subaction: 'getuser',        
            user_id: id,        
        },
        dataType: 'json',
        success: function(data) {
            if(data.success == true) {
                $('#edit-user-id').val(data.user_id);
                $('#username').val(data.username);
                $('#alias').val(data.alias);
                $('#is_admin').val(data.is_admin).change();
                $('#is_crew').val(data.is_crew).change();
                $('#dialog-edit-user').dialog({title: 'Edit User'});
            }
            else {
                $('#edit-user-id').val(0);
                $('#username').val('');
                $('#alias').val('');
                $('#is_admin').val(0).change();
                $('#is_crew').val(1).change();
                $('#dialog-edit-user').dialog({title: 'Create User'});
            }
            $('div.dialog-response').hide();
            $('#dialog-edit-user').dialog('open');
        }
    });
}

/**
 * Ajax request to edit/update user profile. 
 */
function editUser() {
    $.ajax({
        url: BASE_URL + '/ajax',
        type: 'POST',
        data: {
            action:   'admin',
            subaction: 'edituser',
            user_id:  $('#edit-user-id').val(),
            username: $('#username').val(),
            alias:    $('#alias').val(),
            is_crew:  $('#is_crew').val(),
            is_admin: $('#is_admin').val(),
        },
        dataType: 'json',
        success: function(data) {
            if(data.success != true) {
                $('div.dialog-response').text(data.error);
                $('div.dialog-response').show();
            }
            else {
                location.href = BASE_URL + '/admin/users';
            }
        }
    });
}

/**
 * Populate and show dialog to reset/delete a user account.
 * @param {string} subaction 
 * @param {number} id 
 * @param {string} username 
 */
function confirmAction(subaction, id, username) {
    $(document).ready(function() {
        $('#confirm-subaction').val(subaction);
        $('#confirm-user-id').val(id);

        if(subaction == 'deleteuser') {
            $('#dialog-confirm').dialog({title: 'Delete User'});
            $('.modal-confirm-body').text("Are you sure you want to delete '" + username + "'?");
            $('#confirm-btn').text('Delete User');
        }
        else {
            $('#dialog-confirm').dialog({title: 'Reset User Password'});
            $('.modal-confirm-body').text("Are you sure you want to reset the password for '" + username + "'?");
            $('#confirm-btn').text('Reset Password');
        }
        
        $('#dialog-confirm').dialog('open');
    });
}

/**
 * Ajax request to delete or reset a user account.
 * On success, reload page. 
 */
function deleteOrResetUser() {
    $.ajax({
        url: BASE_URL + '/ajax',
        type: 'POST',
        data: {
            action: 'admin',
            subaction: $('#confirm-subaction').val(),        
            user_id: $('#confirm-user-id').val(),        
        },
        dataType: 'json',
        success: function() {
            location.href = BASE_URL + '/admin/users';
        }
    });
}

/**
 * Hide dialogs and response fields.
 */
function closeModal() {
    $('#dialog-edit-user').dialog('widget').hide('highlight', 0);
    $('#dialog-confirm').dialog('widget').hide('highlight', 0);
    $('div.dialog-response').hide();
}

/**
 * Build JQuery dialogs for editing and deleting/resetting users accounts.
 */
$(document).ready(function() {

    // Dialog to edit user accounts.
    $('#dialog-edit-user').dialog({
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
                text: 'Save User',
                click: editUser
            }
        ],
        modal: true,
    });

    // Dialog to confirm deletion/reseting of a user account
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
                click: deleteOrResetUser
            }
        ],
        modal: true,
    });

});
