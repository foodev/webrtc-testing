'use strict';

const CONFIG = require('./config/server.js');
const HTTPS = require('https');
const URL = require('url');
const FS = require('fs');

let clients = [];

let server = HTTPS.createServer({
    key: FS.readFileSync(CONFIG.SSL_KEY),
    cert: FS.readFileSync(CONFIG.SSL_CERT)
}, function(request, response) {
    response.end();
}).listen(CONFIG.PORT, function() {
    // Find out which user used sudo through the environment variable
    let uid = parseInt(process.env.SUDO_UID);

    // Set our server's uid to that user
    if (uid) {
        process.setuid(uid);
    }
});

let WebSocketServer = require('ws').Server;
let wss = new WebSocketServer({
    server: server
});

wss.on('connection', function(client) {
    let query = URL.parse(client.upgradeReq.url, true).query;

    // Forward incoming message to specified friend
    // used for WebRTC videochat
    client.on('message', function(websocketData) {
        let data = JSON.parse(websocketData);

        console.log('Trying to forward message to ' + data.friend);

        if (typeof clients[data.friend] != 'undefined') {
            clients[data.friend].send(JSON.stringify(data));
            console.log('Forwarded message to ' + data.friend);
        } else {
            console.log('FAILED to forward message to ' + data.friend);
        }
    });

    clients[query.username] = client;
});
