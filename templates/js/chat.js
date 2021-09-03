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
        success: function(resp) {
            if(resp.success) {
                $('#new-msg-text').val("");
                closeModal();
                console.info("Sent message_id=" + resp.message_id);
            }
            else {
                console.error(resp.error);
            }
        },
    });
}

// Handle successful respond after sending a new messge or upload.
function handleAjaxNewMessage(resp) {
    if(resp.success) {
        $('#new-msg-text').val("");
        closeModal();
        console.info("Sent message_id=" + resp.message_id);
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
evtSource.addEventListener("logout", handleEventSourceLogout);
evtSource.addEventListener("msg", handleEventSourceNewMessage);
evtSource.addEventListener("notification", handleEventSourceNotification);
evtSource.addEventListener("delay", handleEventSourceDelay);

function handleEventSourceLogout(event) {
    evtSource.close();
    location.href = BASE_URL;
}

function handleEventSourceDelay(event) {
    const data = JSON.parse(event.data);
    $('#owlt-value').text(data.delay);
    $('#distance-value').text(data.distance);
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
            //contentClone.querySelector(".file-location").src = BASE_URL + "/file/" + data.message_id;
            contentClone.querySelectorAll(".file-location").forEach(function(element) {
                element.src = BASE_URL + "/file/" + data.message_id;
            });
            }
            catch(e) {}
            contentClone.querySelector("a").href = BASE_URL + "/file/" + data.message_id;
            contentClone.querySelector(".filename").textContent = data.filename;
            contentClone.querySelector(".filesize").textContent = data.filesize;
            msgClone.querySelector(".msg-content").appendChild(contentClone);
        }
        var msgStatus = msgClone.querySelector(".msg-status");
        msgStatus.querySelector("time").setAttribute('status', data.delivered_status);
        msgStatus.querySelector("time").setAttribute('recv', data.recv_time);
             
        msgStatus.querySelector(".msg-sent-time").textContent = formatTime(data.sent_time);
        msgStatus.querySelector(".msg-recv-time-hab").textContent = formatTime(data.recv_time_hab);
        msgStatus.querySelector(".msg-recv-time-mcc").textContent = formatTime(data.recv_time_mcc);
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
    var currTime = new Date();
    var recvTime = null;
    matches.forEach(function(match) {
        recvTime = new Date(match.getAttribute("recv"));
        console.log(recvTime + " <= " + currTime);
        if(recvTime.getTime() <= currTime.getTime()) {
            match.removeAttribute('status');
            match.closests('.msg-delivery-status').textContent = 'Delivered!!!';
        }
    });
    
});

function closeModal() {
    // Common
    try { $('#progress-video').progressbar('widget').hide('highlight', 0); } catch(e) {}
    try { $('#progress-audio').progressbar('widget').hide('highlight', 0); } catch(e) {}
    try { $('#progress-file').progressbar('widget').hide('highlight', 0);  } catch(e) {}
    $('.dialog-response').hide('fade', 0);
    try {
        stream.getTracks().forEach(function(track) {
            track.stop();
        });
    }
    catch (e) {} // Do nothing.

    // File
    $('#new-msg-file').val("");

    // Media general
    try {
        playMediaPlayer.src = "";
        playMediaPlayer.removeAttribute('src');
        window.URL.revokeObjectURL(mediaUrl);
    }
    catch (e) {}

    $('#dialog-video').dialog('close');
    $('#dialog-audio').dialog('close');
    $('#dialog-file').dialog('close');
}


function openFileModal() {
    $('#dialog-file').dialog('open');
}


$(document).ready(function() {
    // Video
    $('#dialog-video').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 400,
        width: 600,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Start Recording',
                id: 'video-record-btn',
                click: function() { startRecording('video') },
                icon: 'ui-icon-bullet'
            },
            {
                text: 'Stop Recording',
                id: 'video-stop-btn',
                click: function() { stopRecording('video') },
                icon: 'ui-icon-stop'
            },
            {
                text: 'Send Video',
                id: 'video-send-btn',
                click: function() { uploadMedia('video') }
            }
        ],
        modal: true,
        close: closeModal
    });

    // Progress bar for uploads
    $('#progress-video').progressbar({value: false});
    $('#progress-video').progressbar('widget').hide('highlight', 0);

    // Audio
    $('#dialog-audio').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 300,
        width: 600,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Start Recording',
                id: 'audio-record-btn',
                click: function() { startRecording('audio') },
                icon: 'ui-icon-bullet'
            },
            {
                text: 'Stop Recording',
                id: 'audio-stop-btn',
                click: function() { stopRecording('audio') },
                icon: 'ui-icon-stop'
            },
            {
                text: 'Send Audio',
                id: 'audio-send-btn',
                click: function() { uploadMedia('audio') }
            }
        ],
        modal: true,
        close: closeModal
    });    

    // Progress bar for uploads
    $('#progress-audio').progressbar({value: false});
    $('#progress-audio').progressbar('widget').hide('highlight', 0);

    // Files
    $('#dialog-file').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        height: 300,
        width: 600,
        position: { my: "center center", at: "center center-25%", of: window },
        buttons: [
            {
                text: 'Send File',
                id: 'file-send-btn',
                click: function() { uploadMedia('file') }
            }
        ],
        modal: true,
        close: closeModal
    });     

    // Progress bar for uploads
    $('#progress-file').progressbar({value: false});
    $('#progress-file').progressbar('widget').hide('highlight', 0);
});

let progressBar;

function uploadMedia(mediaType) {
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
        //const blobMimeType = (recordedBlobs[0] || {}).type;
        
        const blobMimeType = (mediaType == 'video') ? 'video/webm; codecs="vp8, opus"' : 'audio/ogg; codecs=opus';
        console.log(blobMimeType);
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

    progressBar = $('#progress-' + mediaType)
    $('#progress-' + mediaType).progressbar('widget').show('highlight', 0);

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
                myXhr.upload.addEventListener('progress', progressHandling, {active: false});
            }
            return myXhr;
        },
        success: function(resp) {
            if(resp.success) {
                $('#new-msg-text').val("");
                closeModal();
                console.info("Sent message_id=" + resp.message_id);
            }
            else {
                $('.dialog-response').text(resp.error);
                $('.dialog-response').show('highlight');
                $('#progress-' + mediaType).progressbar('widget').hide('highlight', 0);
                console.error(resp.error);
            }
        },
    });
}

function progressHandling(event) {
    var percent = 0;
    var position = event.loaded || event.position;
    var total = event.total;
    if (event.lengthComputable) {
        percent = Math.ceil(position / total * 100);
    }
    progressBar.progressbar('value', percent);
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
    }, {passive: true});
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