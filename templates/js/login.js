/**
 * Login Module.
 * 
 * This module is responsible for two things:
 * - Show banner to accept use of cookies
 * - Handle login window & login requests
 * 
 */

$(document).ready(function() {

    // If browser does not match recommendedations, then 
    // display warning on login page.
    if(!navigator.userAgent.match(/chrome|chromium|crios/i)){
        $('#dialog-login .browser-warning').show();
    }

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

    // If the disclaimer on the use of cookies has not been shown, 
    // then display it on the screen now. 
    if (localStorage.getItem("cookieSeen") != "shown") {
        $(".cookie-banner").delay(2000).fadeIn();
    };

    // Register action to record that the user agreed to the user of cookies. 
    $(".cookie-close").click(function() {
        localStorage.setItem("cookieSeen", "shown");
        $(".cookie-banner").fadeOut();
    })

    // Create login window to login. 
    $('#dialog-login').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        height: 400,
        width: 400,
        position: { 
            my: "center center", 
            at: "center center-25%", 
            of: window 
        },
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

/**
 * Send AJAX request to login to the site. 
 */
function login() {

    var username = $('#dialog-login #uname').val();
    var password = $('#dialog-login #upass').val();

    // Exit early if not provided.
    if(username == '' || password == '') {
        return;
    }

    // Send AJAX request. 
    $.ajax({
        url:  BASE_URL + '/ajax',
        type: "POST",
        data: {
            uname:     username,
            upass:     password,
            action:    'home',
            subaction: 'login'
        },
        dataType: 'json',
        success: function(data) {
            if(data.login == true) {
                location.href = BASE_URL + '/login';
            }
            else{
                $('#dialog-login .dialog-response').text('Invalid username or password.');
                $('#dialog-login .dialog-response').show();
            }
        },
    });
}
