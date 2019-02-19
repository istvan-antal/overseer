import { Server } from 'ws';

const connections: Array<{
    send(message: string): void;
}> = [];

const wss = new Server({
    port: 18080,
});

let lastState: string;

export const broadcastState = (state: {}) => {
    const data = JSON.stringify(state);
    if (data === lastState) {
        return;
    }
    lastState = data;
    connections.forEach(ws => {
        ws.send(data);
    });
};

wss.on('connection', ws => {
    console.log('New connection');
    ws.send(lastState);
    connections.push(ws);

    ws.on('close', () => {
        connections.splice(connections.indexOf(ws), 1);
    });
});