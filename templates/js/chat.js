function sendTextMessage() {
    var newMsgText = ($('#new-msg-text').val()).trim();

    if(newMsgText.length == 0) {
        return;
    }
    
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
                console.log("Sent message_id=" + resp.message_id);
            }
            else {
                console.log(resp.error);
            }
        },
        error: function(jqHR, textStatus, errorThrown) {
            //location.href = BASE_URL + '/chat';
        },
    });
}

function sendUpload(uploadType) {
    $.ajax({
        url:  BASE_URL + '/chat',
        type: "POST",
        data: {
            subaction: 'upload',
            type: uploadType,
            conversation_id: $('#conversation_id').val(),
            msgBody: newMsgText,
        },
        dataType: 'json',
        success: function(resp) {
            if(resp.success) {
                closeModal();
                console.log("Sent message_id=" + resp.message_id);
            }
            else {
                console.log(resp.error);
            }
        },
        error: function(jqHR, textStatus, errorThrown) {
            //location.href = BASE_URL + '/chat';
        },
    });
}


const evtSource = new EventSource(BASE_URL + '/chat/refresh');

evtSource.onopen = function () {
    console.info("EventSource connected.");
};
  
evtSource.onerror = function (err) {
    console.error("EventSource failed:", err);
};

evtSource.addEventListener("logout", function(event) {
    evtSource.close();
    $('#send-btn').prop('disabled', true);
    $('#file-btn').prop('disabled', true);
    $('#audio-btn').prop('disabled', true);
    $('#video-btn').prop('disabled', true);
    $('#modal-logout').css('display', 'block');
    sleep(5000);
    location.href = BASE_URL;
});

evtSource.addEventListener("msg", function(event) {
    const data = JSON.parse(event.data);
    var scrollContainer = document.querySelector("#content");
    var autoScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight - scrollContainer.scrollTop;
    compileMsg(data, false)
    
    if(autoScroll < 200)
    {
        autoScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight;
        $('#content').animate({
            scrollTop: autoScroll.toString() + 'px'
        }, 250);
    }
});

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

    $("#progress-wrp .progress-bar").css("width", "0%");
    $("#progress-wrp .status").text("0%");

    var formData = new FormData();
    formData.append("conversation_id", $('#conversation_id').val());
    formData.append("subaction", "upload");

    if(mediaType === 'video' || mediaType === 'audio') {
        if(recordedBlobs === undefined) {
            return;
        }
        const blobMimeType = (recordedBlobs[0] || {}).type;
        const blob = new Blob(recordedBlobs, {type: blobMimeType});
        formData.append("type", mediaType);
        formData.append("data", blob, "recording");
    }
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
        url:  BASE_URL + '/chat',
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
        success: function (data) {
            closeModal();
        },
        error: function (error) {
            // handle error
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
    // update progressbars classes so it fits your code
    $("#progress-wrp .progress-bar").css("width", +percent + "%");
    $("#progress-wrp .status").text(percent + "%");
}

var oldMsgQueryInProgress = false;
var hasMoreMessages = true;

$(document).ready(function() {
    // On-load scroll to the bottom to the newest messages
    var scrollContainer = document.querySelector("#content");
    scrollContainer.scrollTop = scrollContainer.scrollHeight - scrollContainer.clientHeight;

    // Setup an event listener to poll for older messages.
    scrollContainer.addEventListener('scroll', function(event) {
        if(!oldMsgQueryInProgress && scrollContainer.scrollTop < 300) {
            loadPrevMsgs();
        }
    });
});

function loadPrevMsgs() {
    var scrollContainer = document.querySelector('#content');
    var target = document.querySelector('#msg-container');
    var child = target.querySelector('.msg');

    if(child != null && hasMoreMessages) {
        var msgId = child.getAttribute('id').substring(7);
        oldMsgQueryInProgress = true;
        $.ajax({
            url:  BASE_URL,
            type: "POST",
            data: {
                action: 'chat',
                subaction: 'prevMsgs',
                conversation_id: $('#conversation_id').val(),
                message_id: msgId
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
                oldMsgQueryInProgress = false;
                scrollContainer.scrollTo(0, child.offsetTop - 80);
                if(!hasMoreMessages) {
                    scrollContainer.style.padding = "0px";
                }
            },
            error: function(jqHR, textStatus, errorThrown) {
                //location.href = BASE_URL + '/chat';
            },
        });
    }

    
}