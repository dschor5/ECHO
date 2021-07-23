function editUser(id) {
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

$(document).ready(function() {
    $('#edit-user-btn').on('click', function() {
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
        })
    });
});



function deleteUser(id, username) {
    
}

function closeModal() {
    $('#edit-user').css('display', 'none');
    $('#confirm-delete').css('display', 'none');
    $('div.modal-response').hide();
}


$(document).ready(function() {
    $('.modal').click( function(event) {
        if($(event.target).attr('class') == 'modal') {
            closeModal();
        }
    });

    $('button.modal-close').on('click', closeModal);
    $('button.modal-btn-sec').on('click', closeModal);
});
