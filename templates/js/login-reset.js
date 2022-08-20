$(document).ready(function() {
    $('#dialog-login').dialog({
        autoOpen: true,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 280,
        width: 400,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Reset Password',
                click: resetPassword
            },
            {
                text: 'Cancel',
                click: function() {
                    location.href = BASE_URL + '/logout';
                }
            }
        ],
        modal: true,
        beforeClose: function(event, ui) {return false;},
    });
});

function resetPassword() {
    var upass1 = $('#dialog-login #upass1').val();
    var upass2 = $('#dialog-login #upass2').val();
    if(upass1 != '' && upass2 != '') {
        $.ajax({
            url:  BASE_URL + '/ajax',
            type: "POST",
            data: {
                password1: upass1,
                password2: upass2,
                action: 'home',
                subaction: 'reset',
            },
            dataType: 'json',
            success: function(data) {
                if(data.success == true) {
                    location.href = BASE_URL;
                }
                else{
                    $('#dialog-login .dialog-response').text(data.message);
                    $('#dialog-login .dialog-response').show();
                }
            },
        });
    }
    else{
        $('#dialog-login .dialog-response').text('Password cannot be empty.');
        $('#dialog-login .dialog-response').show();
    }
}
