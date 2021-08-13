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
                        $('#new-msg-text').val("");
                        console.log(resp.message_id);
                    }
                    else {
                        console.log(resp.error);
                    }
                },
                error: function(jqHR, textStatus, errorThrown) {
                    //location.href = '%http%%site_url%/chat';
                },
            });
        }
    });
});


const evtSource = new EventSource("%http%%site_url%/chat/refresh");

evtSource.onopen = function () {
    console.info("EventSource connected.");
};
  
evtSource.onerror = function (err) {
    console.error("EventSource failed:", err);
};

evtSource.addEventListener("time", function(event) {
    const data = JSON.parse(event.data);
    //$('#time-mcc-value').text(data.time_mcc);
    //$('#time-hab-value').text(data.time_hab);
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
        clone.querySelector(".msg-content").innerHTML = (data.message).replace(/(?:\r\n|\r|\n)/g, '<br>');
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

$(document).ready(function() {
    var matches = document.querySelectorAll("time[status='Transit']");
    var sentTime = null;
    var recvTime = null;
    matches.forEach(function(match) {
        sentTime = new Date(match.getAttribute("sent"));
        recvTime = new Date(match.getAttribute("recv"));
    });
    
});

$(document).ready(function() {
    $('.modal').click( function(event) {
        if($(event.target).attr('class') == 'modal') {
            closeModal();
        }
    });

    $('button.modal-close').on('click', closeModal);
    $('button.modal-btn-sec').on('click', closeModal);

    $('#video-btn').prop('disabled', false);
    $('#video-btn').on('click', function() {
        $('#modal_video').css('display', 'block');
    });

    $('#audio-btn').prop('disabled', false);
    $('#audio-btn').on('click', function() {
        $('#modal_audio').css('display', 'block');
    });
    $('#file-btn').prop('disabled', false);
    $('#file-btn').on('click', function() {
        $('#modal_file').css('display', 'block');
    });
});

function closeModal() {
    $('#modal_file').css('display', 'none');
    $('#modal_video').css('display', 'none');
    $('#modal_audio').css('display', 'none');
    // Clear other variables 
    $('div.modal-response').hide();
}

class File {
    constructor(file) {
        this.file = file;
    }

    upload() {
        var fromData = new FormData();
        FormData.append("file", this.file, this.file.name);
        FormData.append("upload_file", true);

        $.ajax({
            type: "POST",
            url: '%http%%site_url%/chat',
            xhr: function() {
                var myXhr = #.ajaxSettings.xhr();
                if(myXhr.upload) {
                    myXhr.upload.addEventListener('progress', this.progressHandling, false);
                }
                return myXhr;
            },
            success: function(data) {
                // What to do on success
            },
            error: function(error) {
                // What to do on errors
            },
            async: true,
            data: FormData,
            cache: false,
            contentType: false, 
            processData: false,
            timeout: 60000
        });
    }

    progressHandling(event) {
        var percent = 0;
        var position = event.loaded || event.position;
        var total = event.total;
        var progress_bar_id
    }
}