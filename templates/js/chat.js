/**
 * Initialize markdown editor.
 */
var simplemde = null;
var simplemdeOps = { 
    autoDownloadFontAwesome: false,
    element: $("#new-msg-text")[0], 
    promptURLs: false,
    forceSync: true, 
    toolbar: false, 
    status: false,
    spellChecker: false,
    shortcuts: {
        "toggleCodeBlock": null, // unbind Ctrl-Alt-C
        "toggleBold": null, // disabled per request
        "cleanBlock": null,
        "toggleHeadingSmaller": null, // disabled per request
        "toggleLink": null, // disabled per request
        "toggleItalic": null, // disabled per request
        "toggleUnorderedList": null, // disabled per request
        "togglePreview": null, // disabled per request
        "toggleCodeBlock": null, // disabled per request
        "drawImage": null,
        "toggleOrderedList": null, // disabled per request
        "toggleHeadingBigger": null, // disabled per request
        "toggleSideBySide": null,
        "toggleFullScreen": null
        }
    };
$(document).ready(function(){
    if($('#feat-markdown-support-enabled').length)
    {
        simplemde = new SimpleMDE(simplemdeOps);
        //$('.CodeMirror').keyup(detectShiftEnter); // disabled per request
    }
});

function ajaxRequest(options) {
    var token = $('meta[name="csrf-token"]').attr('content');
    var defaults = {
        dataType: 'json',
        timeout: 20000,
        error: function(jqXHR, textStatus, errorThrown) {
            var detail = textStatus ? (' (' + textStatus + ')') : '';
            showError('Request failed' + detail + '.');
        }
    };
    if(token) {
        defaults.headers = {'X-CSRF-Token': token};
    }
    return $.ajax($.extend(true, {}, defaults, options));
}

/**
 * Send AJAX text/important message to the server. 
 * 
 * @param {int} msgImportant  // 0=normal, 1=important
 */
function sendTextMessage(msgImportant) {
    // Exit out early if there is no connection to the server.
    if(serverConnection.active == false) {
        showError('Failed to send message (3).');
        return;
    }

    // Get message text and exit early if empty. 
    var newMsgText = ($('#new-msg-text').val()).trim();
    if(newMsgText.length == 0) {
        return;
    }

    // Disable text field while processing AJAX command.
    $('#new-msg-text').attr('disabled', true);

    // Send AJAX request to save the message. 
    ajaxRequest({
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
        success: function(resp) {
            if(resp.success) {
                $('#new-msg-text').val("");
                if($('#feat-markdown-support-enabled').length)
                {
                    simplemde.value("");
                }
                if(!messageSearch.active) {
                    compileMsg(resp, false);
                    scrollToBottom();
                }
            }
            else {
                showError('Failed to send message (1).');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            showError('Failed to send message (2).');
        },
        complete: function() {
            $('#new-msg-text').attr('disabled', false);
        }
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
 * 
 * @param {event} Key event.
 */
function detectShiftEnter(event) {
    if(event.keyCode == 13 && event.shiftKey && !event.repeat) {
        //event.preventDefault(); // disabled per request
        //sendTextMessage(); // disabled per request
    }
}

// Handle successful respond after sending a new messge or upload.
function handleAjaxNewMessage(resp) {
    if(resp.success) {
        $('#new-msg-text').val("");
        closeModal();
    }
    else {
        console.error(resp.error);
    }
}

var evtSourceTimeout = setTimeout(handleEventSourceError, 15000);
let serverConnection = {active: true, errorId: undefined};
$(document).ready(function(){
    // Load previous messages first
    loadPrevMsgs();

    // Then start listening for events with new messages. 
    // This prevents cases where we are receiving/parsing messages from AJAX and EventSource at the same time.
    const evtSource = new EventSource(BASE_URL + '/chatstream', { withCredentials: true });
    evtSource.addEventListener("msg", handleEventSourceNewMessage);
    evtSource.addEventListener("notification", handleEventSourceNotification);
    evtSource.addEventListener("delay", handleEventSourceDelay);
    evtSource.addEventListener("thread", handleEventSourceNewThread);
    evtSource.addEventListener("room", handleEventSourceNewRoom);
    evtSource.addEventListener('error', handleEventSourceError);
});


/**
 * Display error if the EventSource connection is lost.
 * 
 * @param {Event} event 
 */
function handleEventSourceError(event) {
    if(serverConnection.active) {
        serverConnection.active = false;
        serverConnection.errorId = showError('Lost server connection. Attempting to reconnect every 10 sec.', true);
    }
}

function handleEventSourceAnnouncement(event) {
    const data = JSON.parse(event.data);
    
}

function handleEventSourceNewRoom(event) {
    const data = JSON.parse(event.data);
    if($('#room-' + data.convo_id).length == 0) {
        var divRoom = document.createElement('div');
        divRoom.setAttribute('id', 'room-' + data.convo_id);
        
        var divRoomName = document.createElement('div');
        divRoomName.classList.add('room');

        var threadsDiv = null;

        if(data.convo_current) {
            divRoomName.classList.add('room-selected');

            if($('#feat-convo-threads-enabled').length) {
                threadsDiv = document.createElement('div');
                threadsDiv.setAttribute('id', 'room-thread-' + data.convo_id);
                threadsDiv.setAttribute('class', 'room-thread');

                if($('#feat-convo-threads-all-enabled').length) {
                    var newThread = document.createElement('a');
                    newThread.setAttribute('id', 'new-thread');
                    newThread.setAttribute('href', '#');
                    newThread.setAttribute('onclick', 'openThreadModal()');
                    newThread.innerText = '+ New Thread';
                    threadsDiv.appendChild(newThread);
                }
            }
            
            if(threadsDiv.hasChildNodes()) {
                threadsDiv.setAttribute('style', 'display:block;');
            }
        }
        
        var divRoomLink = document.createElement('a');
        divRoomLink.setAttribute('href', '%http%%site_url%/chat/' + data.convo_id);
        
        var span = document.createElement('span');
        span.setAttribute('id', 'room-name-' + data.convo_id);
        span.innerHTML = data.convo_name + '&nbsp;';
        divRoomLink.appendChild(span);

        span = document.createElement('span');
        span.setAttribute('id', 'room-new-' + data.convo_id);
        divRoomLink.appendChild(span);

        divRoomName.appendChild(divRoomLink);
        divRoom.appendChild(divRoomName);

        if(threadsDiv != null) {
            divRoom.appendChild(threadsDiv);
        }

        document.getElementById('rooms').appendChild(divRoom);
        if(data.last_msg_time) {
            $(divRoom).data('last-msg-time', data.last_msg_time);
        }
        sortRooms();
    }
    else {
        // Update existing room with new last message time
        if($('#feat-convo-list-order-enabled').length && data.last_msg_time) {
            $('#room-' + data.convo_id).data('last-msg-time', data.last_msg_time);
            sortRooms();
        }
    }
}

// Wrapper so that the function can be grouped with other thread functions
// and only included if threads are enabled. 
function handleEventSourceNewThread(event) {
    try {
        const data = JSON.parse(event.data);
        addThreadToMenu(data.convo_id, data.thread_id, data.thread_name, data.thread_selected);
    }
    catch (e) {}
}

function handleEventSourceDelay(event) {
    const data = JSON.parse(event.data);
    $('#owlt-value').text(data.delay);
    $('#distance-value').text(data.distance);

    // Expect message every 15 sec. If not received, 
    // assume there is a connection error.
    clearTimeout(evtSourceTimeout);
    evtSourceTimeout = setTimeout(handleEventSourceError, 15000);

    if(serverConnection.errorId  !== undefined) {
        closeError(serverConnection.errorId);
    }
    serverConnection.active = true;
}

function handleEventSourceNewMessage(event) {
    const data = JSON.parse(event.data);

    if(messageSearch.active) {
        if(data.send_notification == true)
        {
            newMessageNotification(data.author, data.message_type == 'important');
        }
        return;
    }

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

    if(data.send_notification == true)
    {
        newMessageNotification(data.author, data.message_type == 'important');
    }

    if($('#feat-convo-list-order-enabled').length) {
        $('.room-selected').parent().data('last-msg-time', Date.now());
        sortRooms();
    }
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

/**
 * Sort conversations in #rooms by last message time (most recent first),
 * keeping the mission chat (conversation_id=1) pinned at the top.
 * Thread order within each conversation is preserved since threads are
 * children of their parent div#room-{id}.
 */
function sortRooms() {
    if(!$('#feat-convo-list-order-enabled').length) return;
    var rooms = $('#rooms > div[id^="room-"]').get();
    rooms.sort(function(a, b) {
        if(a.id === 'room-1' && b.id !== 'room-1') return -1;
        if(b.id === 'room-1' && a.id !== 'room-1') return 1;
        return (parseFloat($(b).data('last-msg-time')) || 0) - (parseFloat($(a).data('last-msg-time')) || 0);
    });
    $.each(rooms, function(i, el) {
        $('#rooms').append(el);
    });
}

function handleEventSourceNotification(event) {
    const data = JSON.parse(event.data);
    if($('#feat-unread-msg-counts-enabled').length && $('#room-new-' + data.conversation_id).length) {
        $('#room-new-' + data.conversation_id).html( '(' + data.num_messages + 
            ((data.num_important > 0) ? '<span class="room-important">&#8252;</span>':'') + ')');
    }

    newMessageNotification($('#room-name-' + data.conversation_id).text(), data.notif_important > 0, false);
    
    if($('#feat-convo-list-order-enabled').length) {
        var msgTime = data.last_msg_time || 0;
        if($('#room-' + data.conversation_id).length) {
            if(msgTime > 0) {
                $('#room-' + data.conversation_id).data('last-msg-time', msgTime);
            }
        } else if($('#feat-convo-threads-enabled').length) {
            if(msgTime > 0) {
                $('#room-name-' + data.conversation_id).closest('#rooms > div').data('last-msg-time', msgTime);
            }
        }
        sortRooms();
    }
}



/**
 * Compile message to display on the chat window. 
 * @param {} data 
 * @param {*} before 
 */
function compileMsg(data, before){
    var template = document.querySelector('#msg-sent-template');

    // Avoid duplicating messages if sent twiece by the server.
    if($('#msg-id-' + data.message_id).length > 0) {
        return;
    }

    // Track oldest message received (not by date, but using ids)
    if(oldestMsgRecv == null) {
        oldestMsgRecv = data.message_id;
    }
    else {
        oldestMsgRecv = Math.min(oldestMsgRecv, data.message_id);
    }

    if('content' in document.createElement('template'))
    {
        var msgClone = template.content.cloneNode(true);
        msgClone.querySelector(".msg").setAttribute('id', 'msg-id-' + data.message_id);
        msgClone.querySelector(".msg-from").innerHTML = data.author;
        msgClone.querySelector(".msg-id").textContent = "(" + data.message_id_alt + ")";

        if ($('#feat-saved-messages-enabled').length) {
            var msgSaved = msgClone.querySelector(".msg-saved");
            
            if (msgSaved) {
                msgSaved.setAttribute('id', 'msg-saved-' + data.message_id);
                
                // Set the star icon based on the boolean from our SQL query
                // 1 (true) = Filled Star, 0 (false) = Empty Star
                console.log("Message ID " + data.message_id + " is_saved: " + data.is_saved);
                msgSaved.innerHTML = data.is_saved == 0 ? '&#9734;' : '&#9733;';
                
                // Ensure it's visible (in case CSS hides it by default)
                msgSaved.style.display = 'inline-block'; 
                
                msgSaved.setAttribute('onclick', 'toggleSaved(' + data.message_id + ')');
            }
        }

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

        // Message content. Either text or a template for the img/audio/video/file. 
        if(data.message_type === 'text' || data.message_type === 'important') {
            msgClone.querySelector(".msg-content").innerHTML = data.message;
            if(data.type === 'important' && $('#feat-important-msgs-enabled').length) {
                msgClone.querySelector(".msg").classList.add("msg-important");
                msgClone.querySelector(".msg-content").classList.add("msg-content-important");
            }
        }
        else {
            // Copy appropriate video, audio, image, or file template. 
            template = document.querySelector('#msg-' + data.message_type);
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
            msgClone.querySelector(".msg-content").innerHTML = data.message;
            msgClone.querySelector(".msg-content").appendChild(contentClone);
        }

        if(data.search_match) {
            var goToBtn = document.createElement('button');
            goToBtn.setAttribute('type', 'button');
            goToBtn.setAttribute('class', 'msg-view-context-btn');
            goToBtn.setAttribute('onclick', 'goToMessage(' + data.message_id + ')');
            goToBtn.textContent = 'Go to message';
            msgClone.querySelector('.msg-content').appendChild(goToBtn);
        }

        if(messageSearch.active && messageSearch.query.length > 0) {
            applySearchHighlight(msgClone.querySelector('.msg-content'), messageSearch.query);
        }

        if(data.search_anchor) {
            msgClone.querySelector('.msg').classList.add('msg-search-anchor');
        }

        msgTime = msgClone.querySelector("time");
        msgTime.setAttribute('status', data.delivered_status);
        msgTime.setAttribute('recv-local',   data.recv_time_local);
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
        checkAndInsertDaySeparator();
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

    if($('#feat-out-of-seq-enabled').length) {
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
    $('#video-caption').val("");
    $('#audio-caption').val("");
    $('#file-caption').val("");
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
    // Show error if there is no active connection
    if(serverConnection.active == false) {
        $('.dialog-response').text('Lost server connection. Cannot send files, audio, or video.');
        $('.dialog-response').show('highlight');
    }    
    $('#dialog-file').dialog('open');
}

$(document).ready(function() {
    $(document).tooltip();
});

$(document).ready(function() {
    sortRooms();

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
        height: 500,
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
        height: 400,
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
        height: 400,
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

/**
 * Show modal with larger version of the image. 
 * 
 * @param {img} origImg 
 */
function showImage(origImg) {

    // Get larger image placeholder
    var img = document.getElementById('large-msg-img');

    // Calculate ratio to upscale/downscale the image
    let ratio = 0.8 * Math.min(
        window.innerWidth / origImg.naturalWidth, 
        window.innerHeight / origImg.naturalHeight
        );
    
    // Update the image width, height, and set the source. 
    img.width  = ratio * origImg.naturalWidth  * 0.95;
    img.height = ratio * origImg.naturalHeight * 0.95;
    img.src    = origImg.src;

    // Set dimensions for image dialog modal
    $('#dialog-image').dialog('option', 'width',  ratio * origImg.naturalWidth);
    $('#dialog-image').dialog('option', 'height', ratio * origImg.naturalHeight + 35);
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

    var captionBox = '#' + mediaType + '-caption';
    formData.append("caption", $(captionBox).val().trim());

    var fileSize = 0;

    // For video messages create a new blob to transfer the data.
    if(mediaType === 'video' || mediaType === 'audio') {
        if(recordedBlobs === undefined) {
            return;
        }
        
        const blobMimeType = (mediaType == 'video') ? 
            'video/webm; codecs="vp8, opus"' : 'audio/ogg; codecs=opus';

        const file = new Blob(recordedBlobs, {type: blobMimeType});
        formData.append("type", mediaType);
        formData.append("data", file, "recording");
        fileSize = file.size;
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
        fileSize = file.size;
    }

    // Catch invalid file sizes before failing the upload.
    if(fileSize > $('.MAX_FILE_SIZE').val())
    {
        $('.dialog-response').text('Invalid file size (0 < size < ' + $('.MAX_FILE_SIZE_HUMAN').val() + ')');
        $('.dialog-response').show('highlight');
        return;
    }

    progressBar = $('#progress-' + mediaType)
    $('#progress-' + mediaType).progressbar('widget').show('highlight', 0);

    ajaxRequest({
        type: "POST",
        url:  BASE_URL + '/ajax',
        async: true,
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        timeout: 1800000, // 30min
        xhr: function () {
            var myXhr = $.ajaxSettings.xhr();
            $(captionBox).attr('disabled', true);
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
                $(captionBox).val("");
                compileMsg(resp, false);
            }
            else {
                $('.dialog-response').text(resp.error);
                $('.dialog-response').show('highlight');
                $('#progress-' + mediaType).progressbar('widget').hide('highlight', 0);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            var errorMsg = 'status=' + ((textStatus == null) ? 'null' : textStatus) + ', ' + 
                           'exception=' + ((errorThrown == null) ? 'null' : errorThrown);
            $('.dialog-response').text('Error uploading file (' + errorMsg + ')');
            $('.dialog-response').show('highlight');
            $('#progress-' + mediaType).progressbar('widget').hide('highlight', 0);
        },
        complete: function() {
            $(captionBox).attr('disabled', false);
        }
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
var oldestMsgRecv = null;
var messageSearch = {
    active: false,
    query: '',
    nextCursorId: Number.MAX_SAFE_INTEGER,
    hasMore: false,
    inProgress: false,
};

// State for "Go to message" anchor navigation.
var anchorNavigation = {
    active: false,
    targetMessageId: null,
    attempts: 0,
    maxAttempts: 50,
};

/**
 * Get a Date object representing the same moment but adjusted for the user's timezone.
 * This allows us to get the local date/time components.
 * 
 * @param {string|Date} timestamp ISO format timestamp string or Date object
 * @returns {Date} A Date object adjusted by the timezone offset
 */
function getLocalDate(timestamp) {
    var date = new Date(timestamp);
    var offset = (USER_IN_MCC) ? TZ_MCC_OFFSET : TZ_HAB_OFFSET;
    var ts = date.getTime() + offset * 1000; // offset is in seconds, convert to milliseconds
    
    var localDate = new Date();
    localDate.setTime(ts);
    return localDate;
}

/**
 * Get the day label for a given timestamp.
 * Returns "Mission Day #" if within mission, otherwise returns the date.
 * Uses the user's local timezone for day calculations.
 * 
 * @param {string|Date} timestamp ISO format timestamp string or Date object
 * @returns {Object} Object with properties: {dayIdentifier, dayLabel}
 */
function getDayLabel(timestamp) {
    var localDate = getLocalDate(timestamp);
    var missionStart = getLocalDate($('#mission_start').val());
    var missionEnd = getLocalDate($('#mission_end').val());
    var habDayName = $('#hab_day_name').val() || 'Mission Day';
    
    // Create day identifier (YYYY-MM-DD in local timezone)
    var dayIdentifier = localDate.getUTCFullYear() + '-' + 
                       String(localDate.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                       String(localDate.getUTCDate()).padStart(2, '0');
    
    var missionStartDayIdentifier = missionStart.getUTCFullYear() + '-' + 
                                    String(missionStart.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                                    String(missionStart.getUTCDate()).padStart(2, '0');
    
    var missionEndDayIdentifier = missionEnd.getUTCFullYear() + '-' + 
                                  String(missionEnd.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                                  String(missionEnd.getUTCDate()).padStart(2, '0');
    
    // Compare as date strings (YYYY-MM-DD format sorts lexicographically)
    if(dayIdentifier >= missionStartDayIdentifier && dayIdentifier <= missionEndDayIdentifier) {
        // Create UTC dates from identifiers for day calculation
        var localDay = new Date(dayIdentifier + 'T00:00:00Z');
        var missionStartDay = new Date(missionStartDayIdentifier + 'T00:00:00Z');
        var daysDiff = Math.floor((localDay - missionStartDay) / (1000 * 60 * 60 * 24));
        
        return {
            dayIdentifier: dayIdentifier,
            dayLabel: habDayName + ' ' + (daysDiff + 1)
        };
    }
    else {
        // Format as readable date using UTC so the adjusted dayIdentifier/dayLabel stay in sync.
        var options = { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' };
        var formattedDate = localDate.toLocaleDateString('en-US', options);
        return {
            dayIdentifier: dayIdentifier,
            dayLabel: formattedDate
        };
    }
}

/**
 * Insert a day separator before the given message element.
 * 
 * @param {HTMLElement} msgElement The message element
 * @param {string} dayLabel The label for the day
 */
function insertDaySeparator(msgElement, dayLabel) {
    var separator = document.createElement('div');
    separator.className = 'msg-day-separator';
    
    var separatorText = document.createElement('div');
    separatorText.className = 'msg-day-separator-text';
    separatorText.textContent = dayLabel;
    
    separator.appendChild(separatorText);
    msgElement.parentNode.insertBefore(separator, msgElement);
}

/**
 * Rebuild all day separators based on current message order in the DOM.
 */
function refreshDaySeparators() {
    var container = document.querySelector('#msg-container');
    if(container == null) {
        return;
    }

    container.querySelectorAll('.msg-day-separator').forEach(function(separator) {
        separator.remove();
    });

    var prevDayIdentifier = null;
    container.querySelectorAll('.msg').forEach(function(msgElement) {
        var timeElement = msgElement.querySelector('time');
        if(timeElement == null) {
            return;
        }

        var dayInfo = getDayLabel(timeElement.getAttribute('recv-local'));
        if(prevDayIdentifier == null || prevDayIdentifier !== dayInfo.dayIdentifier) {
            insertDaySeparator(msgElement, dayInfo.dayLabel);
        }
        prevDayIdentifier = dayInfo.dayIdentifier;
    });
}

/**
 * Refresh day separators after message list changes.
 */
function checkAndInsertDaySeparator() {
    refreshDaySeparators();
}

function mountSearchInHeader() {
    var search = document.getElementById('msg-search');
    var header = document.getElementById('header');
    if(search == null || header == null) {
        return;
    }

    var navBox = header.querySelector('div[style*="float: right"]');
    if(navBox == null || navBox.parentNode == null) {
        return;
    }

    search.classList.add('msg-search-header');
    navBox.parentNode.insertBefore(search, navBox);
}

$(document).ready(function() {

    mountSearchInHeader();

    $('#msg-search-btn').on('click', function() {
        startMessageSearch();
    });

    $('#msg-search-clear').on('click', function() {
        clearMessageSearch();
    });

    $('#msg-search-more').on('click', function() {
        loadSearchMessages(false);
    });

    $('#msg-search-input').on('keyup', function(event) {
        if(event.keyCode === 13) {
            startMessageSearch();
        }
    });

    updateSearchToolbar();
    
    var scrollContainer = document.querySelector('#content');
    // Setup an event listener to poll for older messages.
    scrollContainer.addEventListener('scroll', function(event) {
        if(messageSearch.active) {
            // Load more search results when scrolling up
            if(!messageSearch.inProgress && scrollContainer.scrollTop < 300) {
                loadSearchMessages(false);
            }
            return;
        }
        if(!oldMsgQueryInProgress && scrollContainer.scrollTop < 300) {
            loadPrevMsgs();
        }
    }, {passive: true});
});



/**
 * Load previous messages in this conversation. 
 * Used for two cases:
 *  1) Loading messages when the page loads. 
 *  2) Loading older messages when the user scrolls up in the conversation. 
 */
function loadPrevMsgs() {

    if(messageSearch.active) {
        return;
    }

    // The 
    if(serverConnection.active == false) {
        showError('Could not load older messages.')
        return;
    }

    var scrollContainer = document.querySelector('#content');
    var target = document.querySelector('#msg-container');
    var child = target.querySelector('.msg');

    var recvTime = (new Date()).toISOString();
    if(child != null) {
        recvTime = child.querySelector('time').getAttribute('recv-local');
    }

    if(hasMoreMessages) {
        oldMsgQueryInProgress = true;
        ajaxRequest({
            url:  BASE_URL + '/ajax',
            type: "POST",
            data: {
                action: 'chat',
                subaction: 'prevMsgs',
                conversation_id: $('#conversation_id').val(),
                message_id: (oldestMsgRecv == null) ? -1 : oldestMsgRecv,
                last_recv_time: recvTime,
            },
            dataType: 'json',
            success: function(resp) {
                if(resp.success) {
                    var i;
                    for(i = resp.messages.length-1; i >= 0; i--) {
                        compileMsg(resp.messages[i], true);
                    }
                    if(!anchorNavigation.active) {
                        scrollContainer.scrollTop = 100;
                    }
                    hasMoreMessages = (resp.messages.length > 0);
                }
                else {
                    hasMoreMessages = false;
                }

                // Anchor navigation: cascade-load older pages until the target
                // message appears in the DOM, then scroll to it.
                if(anchorNavigation.active) {
                    anchorNavigation.attempts++;
                    var targetEl = document.querySelector('#msg-id-' + anchorNavigation.targetMessageId);
                    if(targetEl != null) {
                        // Found — center on it and briefly highlight.
                        anchorNavigation.active = false;
                        anchorNavigation.targetMessageId = null;
                        anchorNavigation.attempts = 0;
                        targetEl.scrollIntoView({block: 'center'});
                        targetEl.classList.add('msg-search-anchor');
                        setTimeout(function() { targetEl.classList.remove('msg-search-anchor'); }, 3000);
                    } else if(hasMoreMessages && anchorNavigation.attempts < anchorNavigation.maxAttempts) {
                        // Not found yet — load the next older batch.
                        setTimeout(function() { loadPrevMsgs(); }, 50);
                    } else {
                        // History exhausted without finding the message.
                        anchorNavigation.active = false;
                        anchorNavigation.targetMessageId = null;
                        anchorNavigation.attempts = 0;
                        scrollToBottom();
                    }
                    if(!hasMoreMessages) {
                        scrollContainer.style.padding = "0px";
                        document.querySelector('#msg-container').prepend(document.querySelector('#msg-end').content.cloneNode(true));
                    }
                    return;
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

            },
            error: function(xhr, ajaxOptions, thrownError) {
                showError('Failed loading previous messages.');
            },
            complete: function() {
                oldMsgQueryInProgress = false;
            }
        });
    }
}

function clearMessageContainer() {
    $('#msg-container').empty();
    oldestMsgRecv = null;
    hasMoreMessages = true;
}

function parseSearchTermsClient(query) {
    var terms = [];
    var buffer = '';
    var inQuote = false;
    var i;

    for(i = 0; i < query.length; i++) {
        var ch = query.charAt(i);

        if(ch === '"') {
            inQuote = !inQuote;
            continue;
        }

        if(!inQuote && ch === '+') {
            var term = buffer.trim();
            if(term.length > 0) {
                terms.push(term);
            }
            buffer = '';
            continue;
        }

        buffer += ch;
    }

    var finalTerm = buffer.trim();
    if(finalTerm.length > 0) {
        terms.push(finalTerm);
    }

    var dedup = [];
    terms.forEach(function(term) {
        if(dedup.indexOf(term) < 0) {
            dedup.push(term);
        }
    });

    dedup.sort(function(a, b) {
        return b.length - a.length;
    });

    return dedup;
}

function escapeRegexLiteral(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function buildHighlightRegex(query) {
    var terms = parseSearchTermsClient(query);
    var patternParts = [];

    terms.forEach(function(term) {
        var clean = term.trim();
        if(clean.length > 0) {
            patternParts.push(escapeRegexLiteral(clean));
        }
    });

    if(patternParts.length === 0) {
        return null;
    }

    return new RegExp('(' + patternParts.join('|') + ')', 'ig');
}

function applySearchHighlight(container, query) {
    if(!container || !query || query.length === 0) {
        return;
    }

    var regex = buildHighlightRegex(query);
    if(regex == null) {
        return;
    }

    var walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
    var textNodes = [];
    var node;

    while((node = walker.nextNode())) {
        if(!node.nodeValue || node.nodeValue.trim().length === 0) {
            continue;
        }

        if(node.parentElement && (
            node.parentElement.classList.contains('msg-search-hit') ||
            node.parentElement.tagName === 'BUTTON' ||
            node.parentElement.tagName === 'SCRIPT' ||
            node.parentElement.tagName === 'STYLE'
        )) {
            continue;
        }

        textNodes.push(node);
    }

    textNodes.forEach(function(textNode) {
        var text = textNode.nodeValue;
        if(!regex.test(text)) {
            regex.lastIndex = 0;
            return;
        }
        regex.lastIndex = 0;

        var frag = document.createDocumentFragment();
        var lastIndex = 0;
        var match;

        while((match = regex.exec(text)) !== null) {
            if(match.index > lastIndex) {
                frag.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
            }

            var mark = document.createElement('mark');
            mark.setAttribute('class', 'msg-search-hit');
            mark.textContent = match[0];
            frag.appendChild(mark);

            lastIndex = match.index + match[0].length;
        }

        if(lastIndex < text.length) {
            frag.appendChild(document.createTextNode(text.slice(lastIndex)));
        }

        textNode.parentNode.replaceChild(frag, textNode);
        regex.lastIndex = 0;
    });
}

function updateSearchToolbar() {
    if(!messageSearch.active) {
        $('#msg-search-status').text('');
        $('#msg-search-more').hide();
        return;
    }

    $('#msg-search-more').toggle(messageSearch.hasMore);
    if(messageSearch.query.length > 0) {
        $('#msg-search-status').text('Search active: ' + messageSearch.query);
    }
}

function startMessageSearch() {
    var query = ($('#msg-search-input').val() || '').trim();
    if(query.length === 0) {
        showError('Please enter a search keyword.');
        return;
    }

    messageSearch.active = true;
    messageSearch.query = query;
    messageSearch.nextCursorId = Number.MAX_SAFE_INTEGER;
    messageSearch.hasMore = false;

    clearMessageContainer();
    loadSearchMessages(true);
}

function reloadSearchResults() {
    if(messageSearch.query.length === 0) {
        clearMessageSearch();
        return;
    }

    messageSearch.active = true;
    messageSearch.nextCursorId = Number.MAX_SAFE_INTEGER;
    messageSearch.hasMore = false;

    clearMessageContainer();
    loadSearchMessages(true);
}

function clearMessageSearch() {
    messageSearch.active = false;
    messageSearch.query = '';
    messageSearch.nextCursorId = Number.MAX_SAFE_INTEGER;
    messageSearch.hasMore = false;
    messageSearch.inProgress = false;

    $('#msg-search-input').val('');
    updateSearchToolbar();

    clearMessageContainer();
    loadPrevMsgs();
}

function loadSearchMessages(reset) {
    if(!messageSearch.active || messageSearch.inProgress) {
        return;
    }

    if(!reset && !messageSearch.hasMore) {
        return;
    }

    if(serverConnection.active == false) {
        showError('Could not search older messages.');
        return;
    }

    messageSearch.inProgress = true;
    $('#msg-search-status').text('Searching...');

    var scrollContainer = document.querySelector('#content');
    var prevScrollHeight = scrollContainer ? scrollContainer.scrollHeight : 0;

    ajaxRequest({
        url: BASE_URL + '/ajax',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'chat',
            subaction: 'searchMsgs',
            conversation_id: $('#conversation_id').val(),
            query: messageSearch.query,
            cursor_message_id: reset ? -1 : messageSearch.nextCursorId,
            num_msgs: 25,
        },
        success: function(resp) {
            if(!resp.success) {
                showError(resp.error || 'Search failed.');
                return;
            }

            if(reset) {
                clearMessageContainer();
            }

            var i;
            if(reset) {
                // Chronological order: oldest at top, newest at bottom
                for(i = resp.messages.length - 1; i >= 0; i--) {
                    compileMsg(resp.messages[i], false);
                }
                scrollToBottom();
            } else {
                // Prepend older results above existing ones
                for(i = 0; i < resp.messages.length; i++) {
                    compileMsg(resp.messages[i], true);
                }

                // Preserve viewport position while older results are inserted above.
                if(scrollContainer != null) {
                    var newScrollHeight = scrollContainer.scrollHeight;
                    scrollContainer.scrollTop += (newScrollHeight - prevScrollHeight);
                }
            }

            messageSearch.hasMore = !!resp.has_more;
            var nextCursor = parseInt(resp.next_cursor_message_id, 10);
            if(!isNaN(nextCursor) && nextCursor > 0) {
                messageSearch.nextCursorId = nextCursor;
            }

            if(resp.messages.length === 0 && reset) {
                $('#msg-search-status').text('No matching messages found.');
            }
            else {
                $('#msg-search-status').text('Showing matching messages only.');
            }

            updateSearchToolbar();
        },
        error: function() {
            showError('Failed to run message search.');
        },
        complete: function() {
            messageSearch.inProgress = false;
        }
    });
}

function goToMessage(messageId) {
    if(messageSearch.inProgress || oldMsgQueryInProgress) {
        return;
    }

    // Exit search mode.
    messageSearch.active = false;
    messageSearch.query = '';
    messageSearch.nextCursorId = Number.MAX_SAFE_INTEGER;
    messageSearch.hasMore = false;
    messageSearch.inProgress = false;
    $('#msg-search-input').val('');
    updateSearchToolbar();

    // Arm anchor navigation so loadPrevMsgs will cascade until it finds this message.
    anchorNavigation.active = true;
    anchorNavigation.targetMessageId = messageId;
    anchorNavigation.attempts = 0;

    clearMessageContainer();
    loadPrevMsgs();
}



/**
 * Show an error message. If not persistent, the error message
 * will automatically disappear after 5sec.
 * 
 * @param {string} msg 
 * @param {boolean} persistent 
 */
function showError(msg, persistent=false) {
    // Avoid duplicating the same error message on screen.
    var existingErrors = document.querySelectorAll('#msg-error .msg-error-text');
    for(var i = 0; i < existingErrors.length; i++) {
        var existing = existingErrors[i];
        if(existing.dataset.msg === msg && existing.dataset.persistent === (persistent ? '1' : '0')) {
            var count = parseInt(existing.dataset.count || '1', 10) + 1;
            existing.dataset.count = count;
            existing.innerHTML = '<strong>ERROR:</strong> ' + msg + ' (x' + count + ')';

            if(persistent) {
                $(existing).stop(true, true).show();
            }
            else {
                $(existing).stop(true, true).fadeIn('slow', function() {
                    $(this).delay(5000).fadeOut('slow', function() {
                        // Reset count after the message fades out.
                        existing.dataset.count = '1';
                        existing.innerHTML = '<strong>ERROR:</strong> ' + msg;
                    });
                });
            }
            return existing.id;
        }
    }

    var newErrorId = 'msg-error-' + $('.msg-error-text').length + 1;
    var newError = document.createElement('div');
    newError.setAttribute('class', 'msg-error-text');
    newError.setAttribute('id', newErrorId);
    newError.dataset.msg = msg;
    newError.dataset.persistent = persistent ? '1' : '0';
    newError.dataset.count = '1';
    newError.innerHTML = '<strong>ERROR:</strong> ' + msg;
    document.getElementById('msg-error').appendChild(newError);

    console.log('Error "' + msg + '", persistent="' + persistent + '"');


    if(persistent) {
        $('#' + newErrorId).fadeIn('slow');
    }
    else {
        $('#' + newErrorId).fadeIn('slow', function() {
            $(this).delay(5000).fadeOut('slow', function() {
                // Reset count after the message fades out.
                newError.dataset.count = '1';
                newError.innerHTML = '<strong>ERROR:</strong> ' + msg;
            });
        });
    }

    return newErrorId;
}

/**
 * Remove an error message by the div id.
 * 
 * @param {string} errorId 
 */
function closeError(errorId) {
    var div = document.getElementById(errorId);
    if(div) {
        div.remove();
    }
}
