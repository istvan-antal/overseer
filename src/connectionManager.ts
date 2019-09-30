import { Server } from 'ws';
import { diff } from 'jsondiffpatch';

const connections: Array<{
    send(message: string): void;
}> = [];

const wss = new Server({
    port: 18080,
});

let lastState: {};

const clone = (obj: {}) => JSON.parse(JSON.stringify(obj));

export const broadcastState = (state: {}) => {
    const newState = clone(state);
    if (lastState) {
        const patch = diff(lastState, newState);
        if (patch) {
            console.log('patch', JSON.stringify(patch));
            connections.forEach(ws => {
                ws.send(JSON.stringify({
                    type: 'patch',
                    data: patch,
                }));
            });
        }
    } else {
        connections.forEach(ws => {
            ws.send(JSON.stringify({
                type: 'set',
                data: newState,
            }));
        });
    }
    lastState = newState;
};

wss.on('connection', ws => {
    console.log('New connection');
    ws.send(JSON.stringify({
        type: 'set',
        data: lastState,
    }));
    connections.push(ws);

    ws.on('close', () => {
        connections.splice(connections.indexOf(ws), 1);
    });
});