// Get user information to edit profile.
function getUser(id) {
    $.ajax({
        url: BASE_URL + '/users',
        type: 'POST',
        data: {
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
            $('#dialog-edit-user').dialog('open');
        }
    });
}

// Process request to edit user profile. 
function editUser() {
    $.ajax({
        url: BASE_URL + '/users',
        type: 'POST',
        data: {
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
                $('div.modal-response').text(data.error);
                $('div.modal-response').show();
            }
            else {
                location.href = BASE_URL + '/users';
            }
        }
    });
}

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

// Actions to execute when closing modal window.
function closeModal() {
    $('#dialog-edit-user').dialog('widget').hide('highlight', 0);
    $('#dialog-confirm').dialog('widget').hide('highlight', 0);
    $('div.modal-response').hide();
}

// Event handlers for closing modal.
$(document).ready(function() {
    $('#dialog-edit-user').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 400,
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
