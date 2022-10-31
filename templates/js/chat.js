/**
 * Sends a text message. 
 */
function sendTextMessage(msgImportant) {

    // Get text and make sure it is not empty.
    var newMsgText = ($('#new-msg-text').val()).trim();
    if(newMsgText.length == 0) {
        return;
    }
    
    // Send AJAX request to save the message. 
    $.ajax({
        url:  BASE_URL + "/ajax",
        type: "POST",
        data: {
            action: 'chat',
            subaction: 'send',
            conversation_id: $('#conversation_id').val(),
            msgBody: newMsgText,
            msgType: msgImportant,
        },
        dataType: 'json',

        // On success, build the message to display on the screen.
        success: function(resp) {
            if(resp.success) {
                $('#new-msg-text').val("");
                closeModal();
                console.info("Sent message_id=" + resp.message_id);
            }
            else {
                $( "#msg-error" ).text = 'Failed to send message (1).';
                $( "#msg-error" ).show().delay(3000).fadeOut('slow', 'linear');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            $( "#msg-error" ).text = 'Failed to send message (2).';
            $( "#msg-error" ).show().delay(3000).fadeOut('slow', 'linear');
        },
    });
}

/**
 * Scrolls to the bottom of the chat window.
 */
function scrollToBottom() {
    $('#content').prop("scrollTop", $('#content').prop("scrollHeight") - $('#content').prop("clientHeight"));
}

/**
 * Detect whehter the user pressed SHIFT+ENTER and send the message.
 * @param {event} Key event.
 */
function detectShiftEnter(event) {
    if(event.keyCode == 13 && event.shiftKey) {
        event.preventDefault();
        sendTextMessage();
    }
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

const evtSource = new EventSource(BASE_URL + '/chatstream');
evtSource.addEventListener("msg", handleEventSourceNewMessage);
evtSource.addEventListener("notification", handleEventSourceNotification);
evtSource.addEventListener("delay", handleEventSourceDelay);
evtSource.addEventListener("thread", handleEventSourceThread);
evtSource.onerror = function(e) {
    $( "#msg-error" ).text = 'Lost server connection. Attempting to reconnect.';
    $( "#msg-error" ).fadeIn( "slow", "linear" );
};
evtSource.onopen = function(e) {
    $( "#msg-error" ).text = '';
    $( "#msg-error" ).fadeOut( "slow", "linear" );
}

// Wrapper so that the function can be grouped with other thread functions
// and only included if threads are enabled. 
function handleEventSourceThread(event) {
    try {
        const data = JSON.parse(event.data);
        addThreadToMenu(data.convo_id, data.thread_id, data.thread_name);
    }
    catch (e) {}
}

function handleEventSourceDelay(event) {
    const data = JSON.parse(event.data);
    $('#owlt-value').text(data.delay);
    $('#distance-value').text(data.distance);
}

function handleEventSourceNewMessage(event) {
    const data = JSON.parse(event.data);
    var scrollContainer = $("#content");
    var autoScroll = scrollContainer.prop("scrollHeight") - scrollContainer.prop("clientHeight") - scrollContainer.prop("scrollTop");
    
    compileMsg(data, false);
    
    if(autoScroll < 200)
    {
        autoScroll = scrollContainer.prop("scrollHeight") - scrollContainer.prop("clientHeight");
        $('#content').animate({
            scrollTop: autoScroll.toString() + 'px'
        }, 250);
    }

    newMessageNotification(data.author, data.type == 'important');
}

function newMessageNotification(name, important=false, thisRoom=true, ack=false) {
    if($('#feat-audio-notification-enabled').length) {
        $("#new-msg-sound")[0].pause();
        if($('#feat-important-msgs-enabled').length) {
            $('#new-important-msg-sound')[0].pause();
        }

        if(important && $('#feat-important-msgs-enabled').length) {
            $("#new-important-msg-sound")[0].play();
        }
        else{
            $("#new-msg-sound")[0].play();
        }
    }

    if($('#feat-badge-notification-enabled').length) {
        var msg = "Message in '" + name + "'.";
        var opt = {
            icon: '%http%%site_url%/%templates_dir%/media/android-chrome-192x192.png',
            requireInteraction: ack
        };
        if(thisRoom) {
            msg = "Message from " + name;
        } 

        if(!("Notification" in window)) {
            console.log("Browser does not support notifications.")
        }
        else if(Notification.persmission === "granted") {
            var notification = new Notification(msg, opt);
        }
        else if(Notification.persmission !== "denied") {
            Notification.requestPermission().then(function(permission) {
                if(permission === "granted") {
                    var notification = new Notification(msg, opt);
                }
            })
        }
    }
}

function handleEventSourceNotification(event) {
    const data = JSON.parse(event.data);
    if($('#feat-unread-msg-counts-enabled').length && $('#room-new-' + data.conversation_id).length) {
        $('#room-new-' + data.conversation_id).html( '(' + data.num_messages + 
            ((data.num_important > 0) ? '<span class="room-important">&#8252;</span>':'') + ')');
    }

    newMessageNotification($('#room-name-' + data.conversation_id).text(), data.notif_important > 0, false);
    
    if($('#feat-convo-list-order-enabled').length) {
        if($('#room-' + data.conversation_id).length) {
            $('#room-' + data.conversation_id).insertAfter( $('.room-selected').parent() );
        }
        else if($('#feat-convo-threads-enabled').length) {
            $('.room-thread').prepend($('#room-name-' + data.conversation_id).parent());
        }
    }
}



/**
 * Compile message to display on the chat window. 
 * @param {} data 
 * @param {*} before 
 */
function compileMsg(data, before){
    var template = document.querySelector('#msg-sent-template');
    if('content' in document.createElement('template'))
    {
        var msgClone = template.content.cloneNode(true);
        msgClone.querySelector(".msg").setAttribute('id', 'msg-id-' + data.message_id);
        msgClone.querySelector(".msg-from").innerHTML = data.author;
        msgClone.querySelector(".msg-id").textContent = "(" + data.message_id_alt + ")";

        // Add appropriate avatar. Only added for non-logged in user. 
        if(data.source != 'usr') {
            var imgTag = document.createElement("img");
            imgTag.setAttribute('class', 'msg-avatar');
            imgTag.setAttribute('src', '%http%%site_url%/%templates_dir%/media/'.concat(data.avatar));
            msgClone.querySelector('.msg').prepend(imgTag);
        }
        else{
            msgClone.querySelector('.msg').classList.add('response');
        }

        // Message content. Eiter text or a template for the img/audio/video/file. 
        if(data.type === 'text' || data.type === 'important') {
            msgClone.querySelector(".msg-content").innerHTML = data.message;
            if(data.type === 'important' && $('#feat-important-msgs-enabled').length) {
                msgClone.querySelector(".msg").classList.add("msg-important");
                msgClone.querySelector(".msg-content").classList.add("msg-content-important");
            }
        }
        else {
            // Copy appropriate video, audio, image, or file template. 
            template = document.querySelector('#msg-' + data.type);
            var contentClone = template.content.cloneNode(true);
            try {
                contentClone.querySelectorAll(".file-location").forEach(function(element) {
                    element.src = BASE_URL + "/file/" + data.message_id;
                });
            }
            catch(e) {
                // Do nothing.
            }
            contentClone.querySelector("a").href = BASE_URL + "/file/" + data.message_id;
            contentClone.querySelector(".filename").textContent = data.filename;
            contentClone.querySelector(".filesize").textContent = data.filesize;
            msgClone.querySelector(".msg-content").appendChild(contentClone);
        }

        msgTime = msgClone.querySelector("time");
        msgTime.setAttribute('status', data.delivered_status);
        msgTime.setAttribute('recv',   data.recv_time);
        msgTime.setAttribute('sent',   data.sent_time);
        msgTime.setAttribute('msg-id', data.message_id);
        
        msgClone.querySelector(".msg-out-seq").setAttribute('id', 'msg-out-seq-id-' + data.message_id);
        msgClone.querySelector(".msg-out-seq").setAttribute('recipient_crew', 'from' + data.is_crew);
        msgClone.querySelector(".msg-out-seq").setAttribute('sender_crew', 'msg-out-seq-id-' + data.sent_from);

        msgClone.querySelector(".msg-progress-bar").setAttribute('id', 'progress-msg-id-' + data.message_id);
        msgClone.querySelector(".msg-progress-bar-fill").setAttribute('id', 'progress-fill-msg-id-' + data.message_id);
        if($('#feat-progress-bar-enabled').length && data.delivered_status != 'Delivered') {
            msgClone.querySelector('.msg-progress-bar').style.display = "block";
        }
        
        var msgStatus = 'Sent: ' + formatTime(data.sent_time);
        if($('#feat-est-delivery-status-enabled').length) {
            msgStatus += ', Recv: ' + formatTime(data.recv_time) + ', ' + data.delivered_status;
        }
        msgClone.querySelector(".msg-delivery-status").textContent = msgStatus;
        msgClone.querySelector(".msg-delivery-status").setAttribute('id', 'status-msg-id-' + data.message_id);

        // Determine where to add the message within the DOM.
        if(before) { 
            document.querySelector('#msg-container').prepend(msgClone);
        }
        else {
            document.querySelector('#msg-container').appendChild(msgClone);
        }
        updateOutOfSeqWarning();
    }
    else
    {
        // Browser does not support elements. 
    }
}

$(document).ready(setTimeout(updateDeliveryStatus, 1000));

function updateOutOfSeqWarning() {
    var matches = document.querySelectorAll("time");
    var prevSentTime = 0;
    var currSentTime = 0;
    var prevId;
    var currId = 0;
    var index;

    for(index = 1; index < matches.length; index++) {

        currId = matches[index].getAttribute('msg-id');
        currSentTime = (new Date(matches[index].getAttribute("sent"))).getTime();
        
        prevIndex = index - 1;
        while(prevIndex >= 0) {
            prevId = matches[prevIndex].getAttribute('msg-id');
            prevSentTime = (new Date(matches[prevIndex].getAttribute("sent"))).getTime();
            prevRecvTime = (new Date(matches[prevIndex].getAttribute("recv"))).getTime();
            
            if(prevSentTime > currSentTime) {
                document.querySelector('#msg-out-seq-id-' + prevId).style.display = 'inline';
                document.querySelector('#msg-out-seq-id-' + currId).style.display = 'inline';
            }
            else if(prevRecvTime < currSentTime) {
                break;
            }

            prevIndex--;
        }
    }
}

function updateDeliveryStatus() {
    var matches = document.querySelectorAll("time[status='Transit']");
    var currTimeObj = new Date();
    var currTime = currTimeObj.getTime();
    var recvTimeObj = null;
    var recvTime = 0;
    var sentTimeObj = null;
    var sentTime = 0;
    var delay;
    var percent = 0;
    var id = 0;
    matches.forEach(function(match) {
        id = match.getAttribute('msg-id');
        
        recvTimeObj = new Date(match.getAttribute("recv"));
        recvTime = recvTimeObj.getTime();
        
        sentTimeObj = new Date(match.getAttribute("sent"));
        sentTime = sentTimeObj.getTime();
        
        delay = recvTime - sentTime;
        percent = 100.0 - (recvTime - currTime) / delay * 100.0;

        document.querySelector('#progress-fill-msg-id-' + id).style.width = percent + '%';
        if(recvTime <= currTime) {
            match.removeAttribute('status');            
            var msgStatus = 'Sent: ' + formatTime(sentTime);
            if($('#feat-est-delivery-status-enabled').length) {
                msgStatus += ', Recv: ' + formatTime(recvTime) + ', Delivered';
            }
            document.querySelector('#status-msg-id-' + id).textContent = msgStatus;
            document.querySelector('#progress-msg-id-' + id).style.display = 'none';
            document.querySelector('#progress-fill-msg-id-' + id).style.display = 'none';
        }
    });
    setTimeout(updateDeliveryStatus, 1000);
}

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

    try { $('#dialog-thread').dialog('close'); } catch(e) {}
    $('#dialog-video').dialog('close');
    $('#dialog-audio').dialog('close');
    $('#dialog-file').dialog('close');
}


function openFileModal() {
    $('#dialog-file').dialog('open');
}

$(document).ready(function() {
    $(document).tooltip();
});

$(document).ready(function() {
    if($('#feat-convo-list-order-enabled').length) {
        $('#rooms').prepend($('.room-selected').parent())
    }

    if($('#feat-important-msgs-enabled').length) {
        $('#send-btn').css('width', '73px');
        $('#send-btn').css('right', '50px');
    }
    else {
        $('#important-btn').hide();
    }

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

   // Files
   $('#dialog-image').dialog({
        autoOpen: false,
        draggable: false,
        resizable: false,
        closeOnEscape: false,
        position: { my: "center center", at: "center center", of: window },
        modal: true,
    });

});

function showImage(origImg) {
    var img = document.getElementById('large-msg-img');
    let ratio = 0.8 * Math.min(window.innerWidth / origImg.naturalWidth, 
                         window.innerHeight / origImg.naturalHeight);
    img.width = ratio*origImg.naturalWidth*0.95;
    img.height = ratio*origImg.naturalHeight*0.95;
    img.src = origImg.src;
    $('#dialog-image').dialog('option', 'width', ratio*origImg.naturalWidth);
    $('#dialog-image').dialog('option', 'height', ratio*origImg.naturalHeight + 35);
    $('#dialog-image').dialog('open');
}

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
        url:  BASE_URL + '/ajax',
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
                $( "#msg-error" ).text = 'Failed load previous messages.';
                $( "#msg-error" ).show().delay(3000).fadeOut('slow', 'linear');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            $( "#msg-error" ).text = 'Failed to upload message.';
            $( "#msg-error" ).show().delay(3000).fadeOut('slow', 'linear');
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
            url:  BASE_URL + '/ajax',
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
                }
                if(child == null) {
                    // On-load scroll to the bottom to the newest messages
                    scrollToBottom();
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
            error: function(xhr, ajaxOptions, thrownError) {
                $( "#msg-error" ).text = 'Failed load previous messages.';
                $( "#msg-error" ).show().delay(3000).fadeOut('slow', 'linear');
            },
        });
    }
}