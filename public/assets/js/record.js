// webkitURL is deprecated but nevertheless
URL = window.URL || window.webkitURL;

let gumStream; 						// stream from getUserMedia()
let recorder; 						// WebAudioRecorder object
let input; 							// MediaStreamAudioSourceNode  we'll be recording
let encodingType; 					// holds selected encoding for resulting audio (file)
let encodeAfterRecord = true;       // when to encode

// shim for AudioContext when it's not avb.
let AudioContext = window.AudioContext || window.webkitAudioContext;
let audioContext; // new audio context to help us record

let recordButton = document.querySelector("button.record");
let stopButton = document.querySelector("button.stop");
let timeElement = document.querySelector(".recording-wrapper .time");
let playbackElement = document.querySelector('.recording-playback');
let submitButton = document.querySelector('button[type="submit"]');

let timerInterval = null;
var currentRecordingBlob = null;

// Disable the submit button by default
submitButton.disabled = true;

// Listeners for record/pause buttons
recordButton.addEventListener("click", startRecording);
stopButton.addEventListener("click", stopRecording);

function startRecording(e) {
    e.preventDefault();

    startPreRecordingTimerOverlay(true);

    let constraints = { audio: {
                            echoCancellation: true,
                            noiseSuppression: true
                        },
                        video: false }

	navigator.mediaDevices.getUserMedia(constraints).then(function(stream) {
		audioContext = new AudioContext();
		gumStream = stream;
		input = audioContext.createMediaStreamSource(stream);

		// Uncomment to play the output through the user's speakers while recording
		// input.connect(audioContext.destination)

		encodingType = 'mp3';

		recorder = new WebAudioRecorder(input, {
            workerDir: "/assets/js/lib/WebAudioRecorder/", // must end with slash
            encoding: encodingType,
            numChannels: 2, // 2 is the default, mp3 encoding supports only 2
            onEncoderLoading: function(recorder, encoding) {
            },
            onEncoderLoaded: function(recorder, encoding) {
            }
		});

		recorder.onComplete = function(recorder, blob) {
			outputRecordingPlayback(blob, recorder.encoding);
		}

		recorder.setOptions({
            timeLimit: 1200,
            encodeAfterRecord: encodeAfterRecord,
            ogg: { quality: 0.5 },
            mp3: { bitRate: 160 }
	    });

        startPreRecordingTimerOverlay();

        setTimeout(function(){
            recorder.startRecording();
        }, 5000);

        timerInterval = setInterval(function(){
            updateTime(recorder.recordingTime());
        }, 500);

	}).catch(function(err) {
	  	// enable the record button if getUSerMedia() fails
        stopButton.classList.add('hide');
        recordButton.classList.remove('hide');
	});

	// disable the record button
    recordButton.classList.add('hide');
    stopButton.classList.remove('hide');
}

function stopRecording(e) {
    e.preventDefault();

    playbackElement.innerHTML = '<div class="msg inline">Please wait while the audio is being processed...</div>';

	// stop microphone access
	gumStream.getAudioTracks()[0].stop();

	// disable the stop button
    stopButton.classList.add('hide');
    recordButton.classList.remove('hide');

    updateTime(recorder.recordingTime());
    clearInterval(timerInterval);

	// tell the recorder to finish the recording (stop recording + encode the recorded audio)
	recorder.finishRecording();
}

function updateTime(seconds) {
    seconds = Math.floor(seconds);
    timeElement.innerHTML = Math.floor(seconds / 60) + ':' + (seconds % 60).toString().padStart(2, '0');
}

function startPreRecordingTimerOverlay(loading) {
    let overlay = document.querySelector('.timer-overlay');

    if (!overlay)  {
        overlay = document.createElement('div');
        overlay.classList.add('timer-overlay');
        document.querySelector('body').appendChild(overlay);
    }

    if (typeof loading != 'undefined' && loading) {
        overlay.innerHTML = "Please allow microphone";
    } else {
        overlay.innerHTML = "5";
        let preRecordingTimerInterval = setInterval(function(){
            let timeLeft = parseInt(overlay.innerHTML) - 1;
            overlay.innerHTML = timeLeft;

            if (timeLeft <= 0) {
                clearInterval(preRecordingTimerInterval);
                overlay.remove();
            }
        }, 1000);
    }
}

function outputRecordingPlayback(blob, encoding) {
	let url = URL.createObjectURL(blob);
    currentRecordingBlob = blob;

    playbackElement.innerHTML = '';

    let audioElement = document.createElement('audio');
	audioElement.controls = true;
	audioElement.src = url;
	playbackElement.appendChild(audioElement);

    let deleteButton = document.createElement('button');
    deleteButton.classList.add('red');
    deleteButton.innerText = "Delete this recording";
    playbackElement.appendChild(deleteButton);

    deleteButton.addEventListener('click', function(e){
        e.preventDefault();
        playbackElement.innerHTML = '';
        timeElement.innerHTML = '0:00';
        submitButton.disabled = true;
    });

    submitButton.disabled = false;
}
