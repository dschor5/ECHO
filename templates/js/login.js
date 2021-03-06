$(document).ready(function() {
    // Register action to open modal
    $('#current-delay-login').click(function() {
        $('#dialog-login').dialog('open');
    });

    // Register keypress event to submit login
    $('#dialog-login #uname, #dialog-login #upass').keypress( function(event) {
        if(event.which == 13) {
            login();
        }
    });

    // Modal options
    $('#dialog-login').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        height: 280,
        width: 400,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Login',
                click: login
            },
            {
                text: 'Cancel',
                click: function() {
                    $(this).dialog('close');
                }
            }
        ],
        modal: true,
        beforeClose: function(event, ui) {
            $('#dialog-login .dialog-response').hide();
            $('#dialog-login #uname').val('');
            $('#dialog-login #upass').val('');
        }
    });
});

function login() {
    var username = $('#dialog-login #uname').val();
    var password = $('#dialog-login #upass').val();
    if(username != '' && password != '') {
        $.ajax({
            url:  BASE_URL + '/ajax',
            type: "POST",
            data: {
                uname: username,
                upass: password,
                action: 'home',
                subaction: 'login'
            },
            dataType: 'json',
            success: function(data) {
                if(data.login == true) {
                    location.href = BASE_URL + '/chat';
                }
                else{
                    $('#dialog-login .dialog-response').text('Invalid username or password.');
                    $('#dialog-login .dialog-response').show();
                }
            },
        });
    }
    else{
        $('#dialog-login .dialog-response').text('Invalid username or password.');
        $('#dialog-login .dialog-response').show();
    }
}
