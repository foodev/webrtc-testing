var WebSocketServer = require('websocket').server;

var http = require('http').createServer();
http.listen(8080);

websocket = new WebSocketServer({
    httpServer: http,
    autoAcceptConnections: false
});

var clients = {};

websocket.on('request', function(request) {
    // @todo Make sure we only accept requests from an allowed origin (https://github.com/Worlize/WebSocket-Node)
    var client = request.accept(request.requestedProtocols[0], request.origin);

    if (typeof clients[client.protocol] == 'undefined') {
        clients[client.protocol] = [];
    }

    clients[client.protocol].push(client);
    console.log('added new client @' + client.protocol + ': ', client.remoteAddress);

    client.on('message', function(message) {
        console.log('message from client @' + client.protocol + ': ', message);

        for (var i = 0, l = clients[client.protocol].length; i < l; i++) {
            if (clients[client.protocol][i] && clients[client.protocol][i].send) {
                clients[client.protocol][i].send(message.utf8Data);
            }
        }
    });
});
