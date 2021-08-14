$(document).ready(function() {
    // Register key-press event 
    $('#uname, #upass').keypress( function(event) {
        if(event.which == 13) {
            login();
        }
    });

    // Register on-click to close modal when the user clicks outside the window.
    $('.modal').click( function(event) {
        if($(event.target).attr('class') == 'modal') {
            closeModal();
        }
    });
});

function login() {
    var username = $('#uname').val();
    var password = $('#upass').val();
    if(username != '' && password != '') {
        $.ajax({
            url:  BASE_URL,
            type: "POST",
            data: {
                uname: username,
                upass: password,
                subaction: 'login'
            },
            dataType: 'json',
            success: function(data) {
                if(data.login == true) {
                    location.href = BASE_URL + '/chat';
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