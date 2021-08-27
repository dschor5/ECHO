function sendTextMessage() {
    // Get text and make sure it is not empty.
    var newMsgText = ($('#new-msg-text').val()).trim();
    if(newMsgText.length == 0) {
        return;
    }
    
    // Send AJAX request to save the message. 
    $.ajax({
        url:  BASE_URL,
        type: "POST",
        data: {
            action: 'chat',
            subaction: 'send',
            conversation_id: $('#conversation_id').val(),
            msgBody: newMsgText,
        },
        dataType: 'json',
        success: handleAjaxNewMessage,
        error: handleAjaxNewMessageError,
    });
}

// Handle successful respond after sending a new messge or upload.
function handleAjaxNewMessage(resp) {
    if(resp.success) {
        $('#new-msg-text').val("");
        closeModal();
        console.log("Sent message_id=" + resp.message_id);
    }
    else {
        console.error(resp.error);
    }
}

// Handle error respond after sending a new message or upload.
function handleAjaxNewMessageError(jqHR, textStatus, errorThrown) {
    return;
}


const evtSource = new EventSource(BASE_URL + '/chat/refresh');

evtSource.onopen = function () {
    console.info("EventSource connected.");
};
  
evtSource.onerror = function (err) {
    console.error("EventSource failed:", err);
};

evtSource.addEventListener("logout", handleEventSourceLogout);
evtSource.addEventListener("msg", handleEventSourceNewMessage);
evtSource.addEventListener("notification", handleEventSourceNotification);



function handleEventSourceLogout(event) {
    evtSource.close();
    $('#send-btn').prop('disabled', true);
    $('#file-btn').prop('disabled', true);
    $('#audio-btn').prop('disabled', true);
    $('#video-btn').prop('disabled', true);
    $('#modal-logout').css('display', 'block');
    sleep(5000);
    location.href = BASE_URL;
}

function handleEventSourceNewMessage(event) {
    const data = JSON.parse(event.data);
    var scrollContainer = document.querySelector("#content");
    var autoScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight - scrollContainer.scrollTop;
    compileMsg(data, false);
    
    if(autoScroll < 200)
    {
        autoScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight;
        $('#content').animate({
            scrollTop: autoScroll.toString() + 'px'
        }, 250);
    }
}

function handleEventSourceNotification(event) {
    const data = JSON.parse(event.data);
    var container = document.querySelector('#room-new-' + data.conversation_id);
    if(container != null) {
        container.textContent = '(' + data.num_messages + ')';
    }
}

function compileMsg(data, before){
    var template = document.querySelector('#msg-sent-'.concat(data.source));
    if('content' in document.createElement('template'))
    {
        var msgClone = template.content.cloneNode(true);
        msgClone.querySelector(".msg").setAttribute('id', 'msg-id-' + data.message_id);
        msgClone.querySelector(".msg-from").textContent = data.author + "(" + data.message_id + ")";

        if(data.type === 'text') {
            msgClone.querySelector(".msg-content").innerHTML = (data.message).replace(/(?:\r\n|\r|\n)/g, '<br>');
        }
        else {
            template = document.querySelector('#msg-' + data.type);
            var contentClone = template.content.cloneNode(true);
            try {
            contentClone.querySelector(".file-location").src = BASE_URL + "/file/" + data.message_id;
            contentClone.querySelector(".file-location").type = data.mime_type;
            }
            catch(e) {}
            contentClone.querySelector("a").href = BASE_URL + "/file/" + data.message_id;
            contentClone.querySelector(".filename").textContent = data.filename;
            contentClone.querySelector(".filesize").textContent = data.filesize;
            msgClone.querySelector(".msg-content").appendChild(contentClone);
        }
        var msgStatus = msgClone.querySelector(".msg-status");
        msgStatus.querySelector(".msg-sent-time").textContent = data.sent_time;
        msgStatus.querySelector(".msg-recv-time-hab").textContent = data.recv_time_hab;
        msgStatus.querySelector(".msg-recv-time-mcc").textContent = data.recv_time_mcc;
        msgStatus.querySelector(".msg-delivery-status").textContent = data.delivered_status;

        if(before) {
            var container = document.querySelector('#msg-container');
            container.prepend(msgClone);
        }
        else {
            var container = document.querySelector('#msg-container');
            container.appendChild(msgClone);
        }
    }
    else
    {
        // Browser does not support elements. 
    }
}

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
});

function closeModal() {
    // Common
    $('div.modal-response').hide();
    try {
        stream.getTracks().forEach(function(track) {
            track.stop();
        });
    }
    catch (e) {} // Do nothing.

    // File
    $('#modal-file').css('display', 'none');
    $('#new-msg-file').val("");
    
    // Video
    $('#modal-video').css('display', 'none');

    // Audio 
    $('#modal-audio').css('display', 'none');

    // Media general
    try {
        playMediaPlayer.src = "";
        playMediaPlayer.removeAttribute('src');
        window.URL.revokeObjectURL(mediaUrl);
    }
    catch (e) {
        console.log('Cannot revoke url.');
    }

    $("#progress-wrp .progress-bar").css("width", "0%");
    $("#progress-wrp .status").text("0%");
}


function openFileModal() {
    $('#modal-file').css('display', 'block');
}

function uploadMedia(mediaType) {
    // Reset progress bar. 
    $("#progress-wrp .progress-bar").css("width", "0%");
    $("#progress-wrp .status").text("0%");

    // Create object to hold all the form data including the file 
    // or media to be uploaded with the message.
    var formData = new FormData();
    formData.append("conversation_id", $('#conversation_id').val());
    formData.append("action", "chat");
    formData.append("subaction", "upload");

    // For video messages create a new blob to transfer the data.
    if(mediaType === 'video' || mediaType === 'audio') {
        if(recordedBlobs === undefined) {
            return;
        }
        const blobMimeType = (recordedBlobs[0] || {}).type;
        const blob = new Blob(recordedBlobs, {type: blobMimeType});
        formData.append("type", mediaType);
        formData.append("data", blob, "recording");
    }
    // Files can be transferred with the nominal fields. 
    else {
        const file = document.querySelector('#new-msg-file').files[0];
        if(file === undefined) {
            return;
        }
        formData.append("type", "file");
        formData.append("fname", file.name);
        formData.append("fsize", file.size);
        formData.append("data", file, file.name);
    }

    $.ajax({
        type: "POST",
        url:  BASE_URL,
        async: true,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        timeout: 60000,
        xhr: function () {
            var myXhr = $.ajaxSettings.xhr();
            if (myXhr.upload) {
                myXhr.upload.addEventListener('progress', progressHandling, false);
            }
            return myXhr;
        },
        success: handleAjaxNewMessage,
        error: handleAjaxNewMessageError,
    });
}

function progressHandling(event) {
    var percent = 0;
    var position = event.loaded || event.position;
    var total = event.total;
    if (event.lengthComputable) {
        percent = Math.ceil(position / total * 100);
    }
    // update progressbars classes so it fits your code
    $("#progress-wrp .progress-bar").css("width", +percent + "%");
    $("#progress-wrp .status").text(percent + "%");
}

var oldMsgQueryInProgress = false;
var hasMoreMessages = true;

$(document).ready(function() {
    
    var scrollContainer = document.querySelector('#content');
    // Setup an event listener to poll for older messages.
    scrollContainer.addEventListener('scroll', function(event) {
        if(!oldMsgQueryInProgress && scrollContainer.scrollTop < 300) {
            loadPrevMsgs();
        }
    });
});

$(document).ready(loadPrevMsgs);

function loadPrevMsgs() {
    var scrollContainer = document.querySelector('#content');
    var target = document.querySelector('#msg-container');
    var child = target.querySelector('.msg');
    var msgId = (child == null) ? -1 : child.getAttribute('id').substring(7);

    if(hasMoreMessages) {
        oldMsgQueryInProgress = true;
        $.ajax({
            url:  BASE_URL,
            type: "POST",
            data: {
                action: 'chat',
                subaction: 'prevMsgs',
                conversation_id: $('#conversation_id').val(),
                message_id: msgId,
            },
            dataType: 'json',
            success: function(resp) {
                if(resp.success) {
                    var i;
                    for(i = resp.messages.length-1; i >= 0; i--) {
                        compileMsg(resp.messages[i], true);
                    }
                    scrollContainer.scrollTop = 100;
                    hasMoreMessages = (resp.messages.length > 0);
                }
                else {
                    hasMoreMessages = false;
                    console.log(resp.error);
                }
                if(child == null) {
                    // On-load scroll to the bottom to the newest messages
                    scrollContainer.scrollTop = scrollContainer.scrollHeight - scrollContainer.clientHeight;
                }
                else {
                    // Scroll to where the user was on the page.
                    scrollContainer.scrollTo(0, child.offsetTop - 80);
                }

                if(!hasMoreMessages || resp.req) {
                    scrollContainer.style.padding = "0px";
                    hasMoreMessages = false;
                    document.querySelector('#msg-container').prepend(document.querySelector('#msg-end').content.cloneNode(true));
                }

                oldMsgQueryInProgress = false;               
            },
            error: function(jqHR, textStatus, errorThrown) {
                //location.href = BASE_URL + '/chat';
            },
        });
    }
}