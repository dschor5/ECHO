/*
$(document).ready(setTimeout(refreshContents, 1000));

function refreshContents() {
    var response = $.ajax({
        url: '%http%%site_url%/chat',
        type: 'POST',
        data: {
            subaction: 'refresh',
        },
        dataType: 'json',
        success: function(data) {
            $('#time-mcc-value').text(data.time_mcc);
            $('#time-hab-value').text(data.time_hab);  
               
            setTimeout(refreshContents, 1000)
        }
    });
}

function receiveMessage(data) {

}
*/

var refreshAttempts = 0;

$(document).ready(function() {
    $('#send-btn').on('click', function() {
        if($('#new-msg-room').val() != '') {
            newMsgText = $('#new-msg-text').val();
            $.ajax({
                url:  '%http%%site_url%/chat',
                type: "POST",
                data: {
                    subaction: 'send',
                    conversation_id: $('#conversation_id').val(),
                    msgBody: newMsgText,
                },
                dataType: 'json',
                success: function(resp) {
                    if(resp.success) {
                        sentMsg(resp, newMsgText);
                        $('#new-msg-text').val('');
                    }
                },
                error: function(jqHR, textStatus, errorThrown) {
                    //location.href = '%http%%site_url%/chat';
                },
            });
        }
    });
});

function sentMsg(resp, msgText) {
    
}

const evtSource = new EventSource("%http%%site_url%/chat/refresh");

evtSource.onopen = function () {
    console.info("EventSource connected.");
};
  
evtSource.onerror = function (err) {
    console.error("EventSource failed:", err);
};

evtSource.addEventListener("time", function(event) {
    const data = JSON.parse(event.data);
    $('#time-mcc-value').text(data.time_mcc);
    $('#time-hab-value').text(data.time_hab);
    //$('#content').animate({scrollTop: $('#content').height()}, 1);
});

evtSource.addEventListener("logout", function(event) {
    evtSource.close();
});

evtSource.addEventListener("msg", function(event) {
    const data = JSON.parse(event.data);
    if('content' in document.createElement('template'))
    {
        var container = document.querySelector('#msg-container');
        var template = document.querySelector('#msg-sent-'.concat(data.type));
        var clone = template.content.cloneNode(true);
        clone.querySelector(".msg-from").textContent = data.author;
        clone.querySelector(".msg-content").textContent = data.message;
        var subclone = clone.querySelector(".msg-status");
        subclone.querySelector(".msg-sent-time").textContent = data.sent_time;
        subclone.querySelector(".msg-recv-time-hab").textContent = data.recv_time_hab;
        subclone.querySelector(".msg-recv-time-mcc").textContent = data.recv_time_mcc;
        subclone.querySelector(".msg-delivery-status").textContent = data.delivered_status;
        container.appendChild(clone);
    }
    else
    {
        // Browser does not support elements. 
    }
});