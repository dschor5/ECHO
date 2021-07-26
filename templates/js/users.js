// Get user information to edit profile.
function getUser(id) {
    $.ajax({
        url: '%http%%site_url%/users',
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
                $('#is_admin').val(data.is_admin).change();
                $('#is_crew').val(data.is_crew).change();
                $('.modal-title').text('Edit User');
            }
            else {
                $('#edit-user-id').val(0);
                $('#username').val('');
                $('#is_admin').val(0).change();
                $('#is_crew').val(1).change();
                $('.modal-title').text('Create User');
            }
            $('#edit-user').css('display', 'block');
        }
    });
}

// Process request to edit user profile. 
function editUser() {
    $.ajax({
        url: '%http%%site_url%/users',
        type: 'POST',
        data: {
            subaction: 'edituser',
            user_id:  $('#edit-user-id').val(),
            username: $('#username').val(),
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
                location.href = '%http%%site_url%/users';
            }
        }
    });
}

function confirmAction(subaction, id, username) {
    $(document).ready(function() {
        $('#confirm-subaction').val(subaction);
        $('#confirm-user-id').val(id);
        if(subaction == 'deleteuser') {
            $('.modal-title').text('Delete User');
            $('.modal-confirm-body').text("Are you sure you want to delete '".concat(username, "'?"));
        }
        else {
            $('.modal-title').text('Reset User Password');
            $('.modal-confirm-body').text("Are you sure you want to reset the password for '".concat(username, "'?"));
        }
        $('#confirm-box').css('display', 'block');
    });
}

$(document).ready(function() {
    $('#edit-user-btn').on('click', editUser);

    $('#confirm-btn').on('click', function() {
        $.ajax({
            url: '%http%%site_url%/users',
            type: 'POST',
            data: {
                subaction: $('#confirm-subaction').val(),		
                user_id: $('#confirm-user-id').val(),		
            },
            dataType: 'json',
            success: function() {
                location.href = '%http%%site_url%/users';
            }
        });
    });
});

// Actions to execute when closing modal window.
function closeModal() {
    $('#edit-user').css('display', 'none');
    $('#confirm-box').css('display', 'none');
    $('div.modal-response').hide();
}

// Event handlers for closing modal.
$(document).ready(function() {
    $('.modal').click( function(event) {
        if($(event.target).attr('class') == 'modal') {
            closeModal();
        }
    });
    $('button.modal-close').on('click', closeModal);
    $('button.cancel-btn').on('click', closeModal);
});
