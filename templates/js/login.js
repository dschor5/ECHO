$(document).ready(function() {
    $('#uname, #upass').keypress( function(event) {
        if(event.which == 13) {
            login();
        }
    });

    $('#login-btn').on('click', login);

    $('.modal').click( function(event) {
        if($(event.target).attr('class') == 'modal') {
            closeModal();
        }
    });

    $('button.modal-close').on('click', closeModal);
    $('button.modal-btn-sec').on('click', closeModal);
});

function login() {
    var username = $('#uname').val();
    var password = $('#upass').val();
    if(username != '' && password != '') {
        $.ajax({
            url:  '%http%%site_url%/',
            type: "POST",
            data: {
                uname: username,
                upass: password,
                subaction: 'login'
            },
            dataType: 'json',
            success: function(data) {
                if(data.login == true) {
                    location.href = '%http%%site_url%/chat';
                }
                else{
                    $('div.modal-response').text('Invalid username or password.');
                    $('div.modal-response').show();
                }
            },
        });
    }
    else{
        $('div.modal-response').text('Invalid username or password.');
        $('div.modal-response').show();
    }
}

function closeModal() {
    $('#loginform').css('display', 'none');
    $('#uname').val('');
    $('#upass').val('');
    $('div.modal-response').hide();
}