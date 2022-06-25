const HEARTBEAT_FREQ_MSEC = 30 * 1000; 
const SHOW_TIMEOUT_SEC    = 120;  

var timeoutInterval;
var countdownInterval;
var nextHeartbeat = Date.now() + HEARTBEAT_FREQ_MSEC;
var hasTimeout = false;

$(document).ready(function() {
    // Dialog to confirm deletion/reseting of a user account
    $('#timeout-dialog').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 200,
        width: 400,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Stay logged in',
                click: initTimeout,
            },
        ],
        modal: true,
    });  
    initTimeout();  
});

/**
 * Initialize user login timout. 
 * - Set internal variables in local storage
 */
function initTimeout() {
    updateExpiredTime();
    window.addEventListener("mousemove", updateExpiredTime);
    window.addEventListener("scroll", updateExpiredTime);
    window.addEventListener("keydown", updateExpiredTime);
    countdownInterval = setInterval(updateCountdown, 1000);
    $('#timeout-dialog').dialog('close');
}

/**
 * Event handler for any user interaction with the site.
 */
function updateExpiredTime() {

    // Do not update if the app has already timeout
    if(hasTimeout) {
        return;
    }
    
    // Clear any existing timeout intervals.
    if(timeoutInterval) {
        clearTimeout(timeoutInterval);
    }
    
    // Since the user is still active, set a new expiration timer. 
    localStorage.setItem("_expiredTime", Date.now() + TIMEOUT_MSEC);
    timeoutInterval = setInterval(checkTimeout, TIMEOUT_MSEC);

    // Send heartbeat on regular intervals to update the server side
    // settings and reset the cookie expiration time. 
    if(nextHeartbeat < Date.now()) {
        $.ajax({
            url: BASE_URL + '/ajax',
            type: 'POST',
            data: {
                action:   'home',
                subaction:'heartbeat',
            },
            dataType: 'json',
            success: function(data) {
                if(data.success != true) {
                    alert("heartbeat success");
                }
                else {
                    alert("heartbeat failed");
                    //location.href = BASE_URL + '/admin/users';
                }
            }
        });
        nextHeartbeat = Date.now() + HEARTBEAT_FREQ_MSEC;
    }

}

function updateCountdown() {
    timeLeftSec = (localStorage.getItem("_expiredTime") - Date.now()) / 1000;
    $('#timeout-counter').text = timeLeftSec;

    if(timeLeftSec < SHOW_TIMEOUT_SEC) {
        // Remove event listeners to force user to click button to continue. 
        window.removeEventListener("mousemove", updateExpiredTime);
        window.removeEventListener("scroll", updateExpiredTime);
        window.removeEventListener("keydown", updateExpiredTime);
        $('#timeout-dialog').dialog('open');
    }
}

function checkTimeout() {
    expiredTime = localStorage.getItem("_expiredTime");
   
    if(expiredTime < Date.now()) {
        hasTimeout = true;
        clearTimeout(timeoutInterval);
        clearTimeout(countdownInterval);
        //location.href = BASE_URL + '/logout';
        alert("logout");
    }   
}

