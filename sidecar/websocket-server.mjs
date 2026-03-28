import crypto from 'node:crypto';
import http from 'node:http';

function createAcceptValue(secWebSocketKey) {
    return crypto
        .createHash('sha1')
        .update(`${secWebSocketKey}258EAFA5-E914-47DA-95CA-C5AB0DC85B11`)
        .digest('base64');
}

function createFrame(opcode, payloadBuffer = Buffer.alloc(0)) {
    const payloadLength = payloadBuffer.length;
    let header = null;

    if (payloadLength < 126) {
        header = Buffer.from([0x80 | opcode, payloadLength]);
    } else if (payloadLength < 65_536) {
        header = Buffer.alloc(4);
        header[0] = 0x80 | opcode;
        header[1] = 126;
        header.writeUInt16BE(payloadLength, 2);
    } else {
        header = Buffer.alloc(10);
        header[0] = 0x80 | opcode;
        header[1] = 127;
        header.writeBigUInt64BE(BigInt(payloadLength), 2);
    }

    return Buffer.concat([header, payloadBuffer]);
}

class WebSocketConnection {
    constructor(socket, { onMessage, onClose }) {
        this.socket = socket;
        this.onMessage = onMessage;
        this.onClose = onClose;
        this.buffer = Buffer.alloc(0);
        this.closed = false;

        socket.on('data', (chunk) => this.handleData(chunk));
        socket.on('close', () => this.close());
        socket.on('end', () => this.close());
        socket.on('error', () => this.close());
    }

    sendJson(payload) {
        this.sendText(JSON.stringify(payload));
    }

    sendText(text) {
        if (this.closed) {
            return;
        }

        this.socket.write(createFrame(0x1, Buffer.from(text)));
    }

    close(code = 1000, reason = '') {
        if (this.closed) {
            return;
        }

        this.closed = true;

        if (!this.socket.destroyed) {
            const payload = Buffer.alloc(2 + Buffer.byteLength(reason));
            payload.writeUInt16BE(code, 0);
            payload.write(reason, 2);
            this.socket.write(createFrame(0x8, payload));
            this.socket.end();
        }

        this.onClose?.();
    }

    handleData(chunk) {
        this.buffer = Buffer.concat([this.buffer, chunk]);

        while (this.buffer.length >= 2) {
            const firstByte = this.buffer[0];
            const secondByte = this.buffer[1];
            const opcode = firstByte & 0x0f;
            const masked = (secondByte & 0x80) === 0x80;
            let payloadLength = secondByte & 0x7f;
            let offset = 2;

            if (payloadLength === 126) {
                if (this.buffer.length < 4) {
                    return;
                }

                payloadLength = this.buffer.readUInt16BE(2);
                offset = 4;
            } else if (payloadLength === 127) {
                if (this.buffer.length < 10) {
                    return;
                }

                payloadLength = Number(this.buffer.readBigUInt64BE(2));
                offset = 10;
            }

            const maskLength = masked ? 4 : 0;
            const frameLength = offset + maskLength + payloadLength;

            if (this.buffer.length < frameLength) {
                return;
            }

            const mask = masked ? this.buffer.subarray(offset, offset + 4) : null;
            const payloadStart = offset + maskLength;
            const payload = this.buffer.subarray(payloadStart, payloadStart + payloadLength);
            this.buffer = this.buffer.subarray(frameLength);

            const decoded = Buffer.from(payload);

            if (mask) {
                for (let index = 0; index < decoded.length; index += 1) {
                    decoded[index] ^= mask[index % 4];
                }
            }

            if (opcode === 0x8) {
                this.close();
                return;
            }

            if (opcode === 0x9) {
                this.socket.write(createFrame(0xA, decoded));
                continue;
            }

            if (opcode !== 0x1) {
                continue;
            }

            try {
                this.onMessage?.(decoded.toString('utf8'));
            } catch {
                this.close(1011, 'Unhandled server error');
                return;
            }
        }
    }
}

export function createConversationRelayServer({ path = '/conversationrelay', verifyRequest, onConnection }) {
    const server = http.createServer((request, response) => {
        if (request.url === '/healthz') {
            response.writeHead(200, { 'content-type': 'application/json' });
            response.end(JSON.stringify({ ok: true }));

            return;
        }

        response.writeHead(404);
        response.end('Not found');
    });

    server.on('upgrade', async (request, socket) => {
        try {
            const pathname = new URL(request.url, 'http://localhost').pathname;

            if (pathname !== path) {
                socket.write('HTTP/1.1 404 Not Found\r\n\r\n');
                socket.destroy();
                return;
            }

            if (verifyRequest) {
                const verified = await verifyRequest(request);

                if (!verified) {
                    socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
                    socket.destroy();
                    return;
                }
            }

            const webSocketKey = request.headers['sec-websocket-key'];

            if (typeof webSocketKey !== 'string') {
                socket.write('HTTP/1.1 400 Bad Request\r\n\r\n');
                socket.destroy();
                return;
            }

            socket.write(
                [
                    'HTTP/1.1 101 Switching Protocols',
                    'Upgrade: websocket',
                    'Connection: Upgrade',
                    `Sec-WebSocket-Accept: ${createAcceptValue(webSocketKey)}`,
                    '\r\n',
                ].join('\r\n'),
            );

            const connection = new WebSocketConnection(socket, {
                onMessage: (text) => onConnection?.message(connection, text, request),
                onClose: () => onConnection?.close(connection, request),
            });

            await onConnection?.open(connection, request);
        } catch {
            socket.write('HTTP/1.1 500 Internal Server Error\r\n\r\n');
            socket.destroy();
        }
    });

    return server;
}
