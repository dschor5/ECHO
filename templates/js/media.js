// Global constants for video/audio recording
let mediaRecorder;
let recordedBlobs;
let mimeType;
let recDuration;
let recBtn;
let stopBtn;
let sendBtn;
let recMediaPlayer;
let playMediaPlayer;
let mediaUrl;

$(document).ready(function() {
    mimeType = getSupportedMimeTypes();
});

function getSupportedMimeTypes() {
    const options = [
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=vp8,opus',
        'video/webm;codecs=h264,opus',
    ];
    let selectedMimeType = '';
    for(let i = 0; i < options.length; i++) {
        if(MediaRecorder.isTypeSupported(options[i])) {
            selectedMimeType = options[i];
        }
    }

    if(selectedMimeType == '') {
        $('#audio-btn').prop('disabled', true);
        $('#video-btn').prop('disabled', true);
        console.error('No video/audio mime type supported by this browser.');
    }

    return selectedMimeType;
}

async function openMediaModal(mediaType) {
    //$('#modal-' + mediaType).css('display', 'block');
    $('#dialog-' + mediaType).dialog('open');
    
    recMediaPlayer = document.querySelector('#rec-' + mediaType + '-player');
    playMediaPlayer = document.querySelector('#play-' + mediaType + '-player');

    $('#' + mediaType + '-record-btn').button("enable");
    $('#' + mediaType + '-stop-btn').button("disable");
    $('#' + mediaType + '-send-btn').button("disable");
    await initMediaStream(mediaType);
}

async function initMediaStream(mediaType) {

    var constraints = {audio: false, video: false};

    if(mediaType == 'video') {
        constraints = {
            audio: { echoCancellation: {exact: true}},
            video: { width: 1280, height: 720}
        };
    }
    else if(mediaType == 'audio') {
        constraints = {
            audio: { echoCancellation: {exact: true}},
            video: false
        };
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        window.stream = stream;
        recMediaPlayer.srcObject = stream;
    }
    catch(e) {
        console.error('navigator.getUserMedia error: ', e);
    }
}

function startRecording(mediaType) {
    $('#' + mediaType + '-record-btn').button("disable");
    $('#' + mediaType + '-record-btn').css('color', '#993d3d');
    $('#' + mediaType + '-stop-btn').button("enable");
    $('#' + mediaType + '-send-btn').button("disable");

    recMediaPlayer.srcObject = stream;

    recMediaPlayer.muted = true;
    recMediaPlayer.controls = false;
    
    playMediaPlayer.pause();

    recordingMimeType = mimeType;
    if(mediaType == 'audio') {
        recordingMimeType = 'audio/webm';
    }

    recordedBlobs = [];
    try {
        mediaRecorder = new MediaRecorder(window.stream, {recordingMimeType});
    }
    catch (e) {
        try {
            mediaRecorder = new MediaRecorder(window.stream);
        }
        catch (e) {
            console.error('Exception while creating MediaRecorder: ', e);
            return;
        }
    }
    mediaRecorder.ondataavailable = handleDataAvailable;
    mediaRecorder.start();
    let dt = new Date();
    recDuration = dt.getTime();
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function stopRecording(mediaType) {
    $('#' + mediaType + '-record-btn').button("enable");
    $('#' + mediaType + '-record-btn').css('color', '#555555');
    $('#' + mediaType + '-stop-btn').button("disable");
    $('#' + mediaType + '-send-btn').button("enable");
    mediaRecorder.stop();
    let dt = new Date();

    await sleep(500);

    playMediaPlayer.muted = false;
    playMediaPlayer.controls = true;

    const blobMimeType = (recordedBlobs[0] || {}).type;
    const superBuffer = new Blob(recordedBlobs, {type: blobMimeType});
    mediaUrl = window.URL.createObjectURL(superBuffer);
    playMediaPlayer.src = mediaUrl;
    playMediaPlayer.controls = true;

    playMediaPlayer.play();
}

function handleDataAvailable(event) {
    if(event.data && event.data.size > 0) {
        recordedBlobs.push(event.data);
    }
}