<?php
require '../config/client.php';

$username = $_GET['username'];
$callee = $_GET['callee'];
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
    bottom: 0;
    padding: 20px 10px;
    text-align: center;
    box-sizing: border-box;
}
footer #call {
    border: none;
    background: transparent;
}
footer #call svg {
    fill: #fff;
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

    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    <script>
    'use strict';

    // This is our main connection object
    var peerConnection = new RTCPeerConnection({
        iceServers: [
            {
                urls: 'stun:stun.l.google.com:19302'
            }
        ]
    });

    // This is our signaling server, used to exchange the offer and answer to establish the connection
    var ourchatService = new WebSocket('<?=WEBSOCKET_SERVICE?>?username=<?=$username?>');

    // Call the friend
    var call = function() {
        // Request to use the webcam and microphone from the client
        navigator.mediaDevices.getUserMedia({
            audio: true,
            video: {
                facingMode: 'user'
            }
        }).then(function(localMediaStream) {
            showLocalMediaStream(localMediaStream);

            // Add audio and video streams (aka "tracks") to our peer connection
            for (var track of localMediaStream.getTracks()) {
                peerConnection.addTrack(track, localMediaStream);
            }
        });
    };

    // Accept incoming call
    var acceptCall = function(data) {
        console.log('#4 Save the incoming offer:', data.sdp);

        // #4 Save the incoming offer
        peerConnection.setRemoteDescription(new RTCSessionDescription(data.sdp)).then(function() {
            // Request to use the webcam and microphone from the client
            return navigator.mediaDevices.getUserMedia({
                audio: true,
                video: true/*{
                    facingMode: 'user'
                }*/
            });
        }).then(function(localMediaStream) {
            showLocalMediaStream(localMediaStream);

            // Add audio and video streams (aka "tracks") to our peer connection
            for (var track of localMediaStream.getTracks()) {
                peerConnection.addTrack(track, localMediaStream);
            }
        }).then(function() {
            console.log('#5 Creating our answer.');

            // #5 Create an answer for the offer
            return peerConnection.createAnswer();
        }).then(function(answer) {
            console.log('#6 Save our own answer:', answer);

            // #6 Save our own answer
            return peerConnection.setLocalDescription(answer);
        }).then(function() {
            var callAnswer = {
                type: 'call-answer',
                callee: '<?=$callee?>',
                sdp: peerConnection.localDescription
            };

            console.log('#7 Send the answer to the caller:', callAnswer);

            // #7 Send our answer to the caller via our signaling server
            ourchatService.send(JSON.stringify(callAnswer));
        });
    };

    // Process answer from callee
    var processAnswer = function(data) {
        console.log('#8 Save the answer from callee:', data.sdp);

        // #8 Save the answer from callee
        peerConnection.setRemoteDescription(new RTCSessionDescription(data.sdp));
    };

    var showLocalMediaStream = function(localMediaStream) {
        document.querySelector('#localVideo').src = URL.createObjectURL(localMediaStream);
    };

    // Connection initialization starts
    peerConnection.onnegotiationneeded = function() {
        console.log('#1 Creating a new offer.');

        // #1 We create an offer which will be send to the callee
        peerConnection.createOffer().then(function(offer) {
            console.log('#2 Save our offer:', offer);

            // #2 Save the own offer
            return peerConnection.setLocalDescription(offer);
        }).then(function() {
            var callOffer = {
                type: 'call-offer',
                callee: '<?=$callee?>',
                sdp: peerConnection.localDescription
            };

            console.log('#3 Send the offer to callee:', callOffer);

            // #3 Send the offer to callee via our signaling server
            ourchatService.send(JSON.stringify(callOffer));
        });
    };

    // Show video stream of the remote webcam
    peerConnection.ontrack = function(event) {
        document.querySelector('#remoteVideo').src = URL.createObjectURL(event.streams[0]);
    };

    // Send ICE candidate to callee
    peerConnection.onicecandidate = function(event) {
        if (event.candidate) {
            console.log('Got ICE candidate:', event.candidate.candidate);

            var iceCandidate = {
                type: 'ice-candidate',
                callee: '<?=$callee?>',
                sdp: event.candidate
            };

            console.log('Send ICE candidate to callee:', iceCandidate);

            ourchatService.send(JSON.stringify(iceCandidate));
        }
    }

    // Incoming call
    ourchatService.addEventListener('message', function(event) {
        var data = JSON.parse(event.data);

        if (data.type == 'call-offer') {
            // Prompt user to accept incoming call
            if (confirm('Anruf annehmen?')) {
                acceptCall(data);
            }
        }
    }, false);

    // Answer of call request
    ourchatService.addEventListener('message', function(event) {
        var data = JSON.parse(event.data);

        if (data.type == 'call-answer') {
            processAnswer(data);
        }
    }, false);

    // Add ICE candidate
    ourchatService.addEventListener('message', function(event) {
        var data = JSON.parse(event.data);

        if (data.type == 'ice-candidate') {
            peerConnection.addIceCandidate(new RTCIceCandidate(data.sdp));
        }
    }, false);

    document.querySelector('#call').onclick = call;
    </script>
</body>
