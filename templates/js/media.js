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
    return selectedMimeType;
}

async function openMediaModal(mediaType) {
    $('#modal-' + mediaType).css('display', 'block');
    recBtn = document.querySelector('#' + mediaType + '-record-btn');
    stopBtn = document.querySelector('#' + mediaType + '-stop-btn');
    sendBtn = document.querySelector('#' + mediaType + '-send-btn');
    recMediaPlayer = document.querySelector('#rec-' + mediaType + '-player');
    playMediaPlayer = document.querySelector('#play-' + mediaType + '-player');

    $("#progress-wrp .progress-bar").css("width", "0%");
    $("#progress-wrp .status").text("0%");

    recBtn.disabled = false;
    stopBtn.disabled = true;
    sendBtn.disabled = true;
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
    recBtn.disabled = true;
    recBtn.style.color = '#993d3d';
    stopBtn.disabled = false;
    sendBtn.disabled = true;

    recMediaPlayer.srcObject = stream;

    recMediaPlayer.muted = true;
    recMediaPlayer.controls = false;
    
    playMediaPlayer.pause();

    recordedBlobs = [];
    try {
        mediaRecorder = new MediaRecorder(window.stream, {mimeType});
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
    recBtn.disabled = false;
    recBtn.style.color = '#000000';
    stopBtn.disabled = true;
    sendBtn.disabled = false;
    mediaRecorder.stop();
    let dt = new Date();
    console.log('Recorded: ' + (dt.getTime() - recDuration)/1000 + ' sec');

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