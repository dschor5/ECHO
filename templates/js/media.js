/**
 * Media Recording Module for sending audio/video messages. 
 * 
 * This module is largely based on the WebRTC samples available at:
 * https://webrtc.github.io/samples/src/content/getusermedia/record/
 * 
 * The support for audio/video codecs varies for different browsers
 * and may change over time depending on licensing. For the purpose of
 * development/testing, the codecs were selected to be compatible with
 * Google Chrome version 110. 
 * 
 * More information on codecs:
 * [1] https://www.webrtc-experiment.com/RecordRTC/simple-demos/isTypeSupported.html
 * [2] https://developer.mozilla.org/en-US/docs/Web/Media/Formats/WebRTC_codecs
 * [3] https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Video_codecs
 */

/**
 * Global constants for video/audio recording
 */

// Instance of MediaRecorder object.
// https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder
let mediaRecorder;

// Blobs of memory containing the audio/video capture. 
let recordedBlobs;

// Mime type for audio/video recording
let mimeType;

// Record button
let recBtn;

// Stop button
let stopBtn;

// Send button
let sendBtn;

// Recording media player
let recMediaPlayer;

// Replay media player
let playMediaPlayer;

// URL created for the audio/video recording
let mediaUrl;


/**
 * onload - Get the supported mime types. 
 * This is the limiting factor for staying with one browser. 
 */
$(document).ready(function() {
    mimeType = getSupportedMimeTypes();
});

/**
 * Get list of supported mime types for video recording.
 * 
 * @returns (string) Codec selected.
 */
function getSupportedMimeTypes() {

    // List of acceptable codecs. 
    const options = [
        'video/webm;codecs=vp8,opus',
        'video/webm;codecs=vp9,opus',
        'video/webm;codecs=h264,opus',
    ];

    // Check that at least one of acceptable codecs 
    // is supported by the current user browser.
    let selectedMimeType = '';
    for(let i = 0; i < options.length; i++) {
        if(MediaRecorder.isTypeSupported(options[i])) {
            selectedMimeType = options[i];
            break; // Only need one!
        }
    }

    // If audio/video is not supported by this particular browser, 
    // then the corresponding buttons in the interface will be disabled. 
    if(selectedMimeType == '') {
        $('#audio-btn').prop('disabled', true);
        $('#video-btn').prop('disabled', true);
    }

    return selectedMimeType;
}

/**
 * Open the window to record, preview, and send audio/video messages.
 * 
 * @param {string} mediaType // Either "audio" or "video"
 */
async function openMediaModal(mediaType) {

    // Do not open if there is no EventStream connection to the server.
    // TODO: Show message
    if(serverConnection.active == false) {
        return;
    }

    $('#dialog-' + mediaType).dialog('open');
    
    // Populate module variables tracking the rec and play buttons.
    recMediaPlayer = document.querySelector('#rec-' + mediaType + '-player');
    playMediaPlayer = document.querySelector('#play-' + mediaType + '-player');

    // Setup initial state for buttons.
    $('#' + mediaType + '-record-btn').button("enable");
    $('#' + mediaType + '-stop-btn').button("disable");
    $('#' + mediaType + '-send-btn').button("disable");

    // Initialize media stream. 
    await initMediaStream(mediaType);
}


/**
 * Initialize the MediaRecorder object.
 *  
 * @param {string} mediaType // Either "audio" or "video"
 */
async function initMediaStream(mediaType) {
    // Default used if the 'mediaType' param is invalid. 
    var constraints = {audio: false, video: false};

    // Define the settings for video/audio. 
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

    // Create the MediaRecorder object with those parameters
    // and link the objects to the media stream. 
    try {
        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        window.stream = stream;
        recMediaPlayer.srcObject = stream;
    }
    catch(e) {
        console.error('navigator.getUserMedia error: ', e);
    }
}


/**
 * Call when the user starts recording. 
 * 
 * @param {string} mediaType // Either "audio" or "video"
 */
function startRecording(mediaType) {
    // Setup buttons at start of recording. 
    $('#' + mediaType + '-record-btn').button("disable");
    $('#' + mediaType + '-record-btn').css('color', '#993d3d');
    $('#' + mediaType + '-stop-btn').button("enable");
    $('#' + mediaType + '-send-btn').button("disable");

    // Set recording source and configuration. 
    recMediaPlayer.srcObject = stream;
    recMediaPlayer.muted = true;
    recMediaPlayer.controls = false;
    
    // Pause preview player while recording.
    playMediaPlayer.pause();

    // Set mimetype for recordings. 
    // NOTE: These are hardcoded for Google Chorme compatible codecs.
    recordingMimeType = 'video/webm';
    if(mediaType == 'audio') {
        recordingMimeType = 'audio/webm';
    }

    // Clear array containing recording. 
    recordedBlobs = [];
    try {
        // Start recording stream. 
        let options = {mimeType: recordingMimeType};
        mediaRecorder = new MediaRecorder(window.stream, options);
    }
    catch (e) {
        // While not ideal, try recording without defining the mime type.
        console.log("Created MediaRecorder without mimeType specified.");
        try {
            mediaRecorder = new MediaRecorder(window.stream);
        }
        catch (e) {
            console.error('Exception while creating MediaRecorder: ', e);
            return;
        }
    }

    // Save blobs into an array. 
    mediaRecorder.ondataavailable = handleDataAvailable;

    // Start recording. 
    mediaRecorder.start();
}


/**
 * Sleep for desired milliseconds.
 * 
 * @param {int} ms // Milliseconds 
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}


/**
 * Call when the user stops recording. 
 * 
 * @param {string} mediaType // Either "audio" or "video"
 */
async function stopRecording(mediaType) {
    // Setup buttons at end of recording. 
    $('#' + mediaType + '-record-btn').button("enable");
    $('#' + mediaType + '-record-btn').css('color', '#555555');
    $('#' + mediaType + '-stop-btn').button("disable");
    $('#' + mediaType + '-send-btn').button("enable");

    // Stop recording
    mediaRecorder.stop();

    // Wait half a second to let the player stop and save the stream.
    await sleep(500);

    // Configure preview player.
    playMediaPlayer.muted = false;
    playMediaPlayer.controls = true;
    const blobMimeType = (recordedBlobs[0] || {}).type;
    const superBuffer = new Blob(recordedBlobs, {type: blobMimeType});
    mediaUrl = window.URL.createObjectURL(superBuffer);
    playMediaPlayer.src = mediaUrl;
    playMediaPlayer.controls = true;

    // Start preview. 
    playMediaPlayer.play();
}


/**
 * Handler to receive a full blob of data and push it into an array. 
 * 
 * @param {Event} event 
 */
function handleDataAvailable(event) {
    if(event.data && event.data.size > 0) {
        recordedBlobs.push(event.data);
    }
}
