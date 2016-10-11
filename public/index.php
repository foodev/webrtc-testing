<?php
require '../config/client.php';

$username = $_GET['username'];
$friend = $_GET['friend'];
?>
<!doctype html>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">

<style>
* {
    margin: 0;
    padding: 0;
    outline: none;
    font-size: 14px;
    font-family: Hind, sans-serif;
    -moz-user-select: none;
    -webkit-user-select: none;
    -ms-user-select: none;
    user-select: none;
    cursor: default;
}

html {
    height: 100%;
}
body {
    max-width: 980px;
    height: 100%;
    margin: 0 auto;
    color: #fff;
    line-height: 1.3em;
    background: #222;
}

.clickable, .clickable * {
    cursor: pointer;
}
.selectable {
    -moz-user-select: text;
    -webkit-user-select: text;
    -ms-user-select: text;
    user-select: text;
    cursor: text;
}

/* Call button(s) */
footer {
    position: fixed;
    width: 100%;
    max-width: 980px;
    bottom: 0;
    padding: 20px 10px;
    text-align: center;
    box-sizing: border-box;
}
footer #call,
footer #hang-up {
    border: none;
    background: transparent;
}
footer #call svg {
    fill: #0f0;
}
footer #hang-up svg {
    fill: #f00;
}

/* WebRTC videochat */
main #remoteVideo {
    width: 100%;
    height: 100%;
}
main #localVideo {
    position: absolute;
    width: 20%;
    height: 20%;
    right: 5%;
    bottom: 5%;
}
</style>

<title></title>

<body>
    <main>
        <video id="remoteVideo" autoplay></video>
        <video id="localVideo" autoplay muted></video>
    </main>

    <footer>
        <button id="call" class="clickable" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="60px" height="60px" viewBox="0 0 24 24">
                <path d="M18.48 22.926l-1.193.658c-6.979 3.621-19.082-17.494-12.279-21.484l1.145-.637 3.714 6.467-1.139.632c-2.067 1.245 2.76 9.707 4.879 8.545l1.162-.642 3.711 6.461zm-9.808-22.926l-1.68.975 3.714 6.466 1.681-.975-3.715-6.466zm8.613 14.997l-1.68.975 3.714 6.467 1.681-.975-3.715-6.467z"/>
            </svg>
        </button>
    </footer>

    <template id="pre-call">
        <button id="call" class="clickable" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="60px" height="60px" viewBox="0 0 24 24">
                <path d="M18.48 22.926l-1.193.658c-6.979 3.621-19.082-17.494-12.279-21.484l1.145-.637 3.714 6.467-1.139.632c-2.067 1.245 2.76 9.707 4.879 8.545l1.162-.642 3.711 6.461zm-9.808-22.926l-1.68.975 3.714 6.466 1.681-.975-3.715-6.466zm8.613 14.997l-1.68.975 3.714 6.467 1.681-.975-3.715-6.467z"/>
            </svg>
        </button>
    </template>

    <template id="in-call">
        <button id="hang-up" class="clickable" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="60px" height="60px" viewBox="0 0 24 24">
                <path d="M18.48 22.926l-1.193.658c-6.979 3.621-19.082-17.494-12.279-21.484l1.145-.637 3.714 6.467-1.139.632c-2.067 1.245 2.76 9.707 4.879 8.545l1.162-.642 3.711 6.461zm-9.808-22.926l-1.68.975 3.714 6.466 1.681-.975-3.715-6.466zm8.613 14.997l-1.68.975 3.714 6.467 1.681-.975-3.715-6.467z"/>
            </svg>
        </button>
    </template>

    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    <script>
    'use strict';

    // This is our main connection object
    var peerConnection = new RTCPeerConnection({
        iceServers: [{
            urls: 'stun:stun.stunprotocol.org:3478'
        }, {
            urls: [
                'turn:74.125.140.127:19305?transport=udp',
                'turn:74.125.140.127:443?transport=tcp'
            ],
            username: 'CNDY+b8FEgYsaLm0gUEYzc/s6OMT',
            credential: 'tIa/HLcngA56/b4wUytljTXtpMg='
        }]
    });

    // This is our signaling server, used to exchange the offer and answer to establish the connection
    var signalingServer = new WebSocket('<?=WEBSOCKET_SERVICE?>?username=<?=$username?>');

    // Call the friend
    var call = function() {
        requestLocalMediaStream().then(function(localMediaStream) {
            showLocalMediaStream(localMediaStream);

            // Add audio and video streams (aka "tracks") to our peer connection
            for (var track of localMediaStream.getTracks()) {
                peerConnection.addTrack(track, localMediaStream);
            }
        });
    };

    // Accept incoming call
    var acceptCall = function(data) {
        console.log('Incoming call from %s.', data.friend);

        // #4 Save the incoming offer
        peerConnection.setRemoteDescription(new RTCSessionDescription(data.sdp))
        .then(requestLocalMediaStream)
        .then(function(localMediaStream) {
            showLocalMediaStream(localMediaStream);

            // Add audio and video streams (aka "tracks") to our peer connection
            for (var track of localMediaStream.getTracks()) {
                peerConnection.addTrack(track, localMediaStream);
            }
        }).then(function() {
            console.log('Creating our answer.');

            // #5 Create an answer for the offer
            return peerConnection.createAnswer();
        }).then(function(answer) {
            console.log('Save our own answer.');

            // #6 Save our own answer
            return peerConnection.setLocalDescription(answer);
        }).then(function() {
            var callAnswer = {
                type: 'call-answer',
                friend: '<?=$friend?>',
                sdp: peerConnection.localDescription
            };

            console.log('Send the answer to the friend.');

            // #7 Send our answer to the caller via our signaling server
            signalingServer.send(JSON.stringify(callAnswer));
        }).then(function() {
            // Show "in-call" buttons
            var inCallButtons = document.querySelector('#in-call');

            document.querySelector('footer').innerHTML = '';
            document.querySelector('footer').appendChild(document.importNode(inCallButtons.content, true));

            document.querySelector('#hang-up').onclick = hangUp;
        });
    };

    // Process answer from friend
    var processAnswer = function(data) {
        console.log('The friend accepted our call.');

        // #8 Save the answer from friend
        peerConnection.setRemoteDescription(new RTCSessionDescription(data.sdp)).then(function() {
            // Show "in-call" buttons
            var inCallButtons = document.querySelector('#in-call');

            document.querySelector('footer').innerHTML = '';
            document.querySelector('footer').appendChild(document.importNode(inCallButtons.content, true));

            document.querySelector('#hang-up').onclick = hangUp;
        });
    };

    // End the current call
    var hangUp = function() {
        // Stop the audio and video streams
        for (var sender of peerConnection.getSenders()) {
            sender.track.stop();
            peerConnection.removeTrack(sender);
        }

        document.querySelector('#remoteVideo').src = '';
        document.querySelector('#localVideo').src = '';

        // Close the connection
        peerConnection.close();

        // Show "pre-call" buttons
        var preCallButtons = document.querySelector('#pre-call');

        document.querySelector('footer').innerHTML = '';
        document.querySelector('footer').appendChild(document.importNode(preCallButtons.content, true));

        document.querySelector('#call').onclick = call;
    };

    // Request to use the webcam and microphone from the client
    var requestLocalMediaStream = function() {
        return new Promise(function(resolve, reject) {
            navigator.mediaDevices.getUserMedia({
                audio: true,
                video: {
                    facingMode: 'user'
                }
            }).then(function(localMediaStream) {
                console.log('User granted access to own webcam and microphone. :)');

                resolve(localMediaStream);
            }).catch(function(mediaStreamError) {
                if (mediaStreamError.name == 'NotAllowedError') {
                    console.log('Access to own webcam and microphone DENIED by user. :(');
                }
            });
        })
    };

    // Show own video stream to the user
    var showLocalMediaStream = function(localMediaStream) {
        console.log('Show local video stream to the user.');

        document.querySelector('#localVideo').src = URL.createObjectURL(localMediaStream);
    };

    // Show audio/video stream from the friend to the user
    var showRemoteMediaStream = function(remoteMediaStream) {
        console.log('Show audio/video stream from the friend to the user.');

        document.querySelector('#remoteVideo').src = URL.createObjectURL(remoteMediaStream);
    };

    // Connection initialization starts
    peerConnection.onnegotiationneeded = function() {
        console.log('Start to establish the connection to the friend.');

        // #1 We create an offer which will be send to the friend
        peerConnection.createOffer().then(function(offer) {
            console.log('Created and save a new offer.');

            // #2 Save the own offer
            return peerConnection.setLocalDescription(offer);
        }).then(function() {
            var callOffer = {
                type: 'call-offer',
                friend: '<?=$friend?>',
                sdp: peerConnection.localDescription
            };

            console.log('Send our offer to the friend.');

            // #3 Send the offer to friend via our signaling server
            signalingServer.send(JSON.stringify(callOffer));
        });
    };

    // Show video stream of the remote webcam
    peerConnection.ontrack = function(event) {
        showRemoteMediaStream(event.streams[0]);
    };

    // Send ICE candidate to friend
    peerConnection.onicecandidate = function(event) {
        if (event.candidate) {
            console.log('Got ICE candidate:', event.candidate.candidate);

            var iceCandidate = {
                type: 'ice-candidate',
                friend: '<?=$friend?>',
                sdp: event.candidate
            };

            signalingServer.send(JSON.stringify(iceCandidate));
        }
    };

    // Something with the connection has changed
    peerConnection.oniceconnectionstatechange = function() {
        switch (peerConnection.iceConnectionState) {
            case 'failed':
            case 'disconnect':
                hangUp();
                break;
        }
    };

    // Incoming call
    signalingServer.addEventListener('message', function(event) {
        var data = JSON.parse(event.data);

        if (data.type == 'call-offer') {
            // Prompt user to accept incoming call
            if (confirm('Anruf annehmen?')) {
                acceptCall(data);
            }
        }
    }, false);

    // Answer of call request
    signalingServer.addEventListener('message', function(event) {
        var data = JSON.parse(event.data);

        if (data.type == 'call-answer') {
            processAnswer(data);
        }
    }, false);

    // Add ICE candidate
    signalingServer.addEventListener('message', function(event) {
        var data = JSON.parse(event.data);

        if (data.type == 'ice-candidate') {
            console.log('Add ICE candidate:', data.sdp.candidate);

            peerConnection.addIceCandidate(new RTCIceCandidate(data.sdp));
        }
    }, false);

    document.querySelector('#call').onclick = call;
    </script>
</body>
