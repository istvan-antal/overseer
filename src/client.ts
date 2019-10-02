import { ApolloClient } from 'apollo-client';
import { InMemoryCache } from 'apollo-cache-inmemory';
import { ApolloLink, Observable, Operation } from 'apollo-link';
import { patch } from 'jsondiffpatch';
import { Observer } from 'apollo-client/util/Observable';

let ws: WebSocket;
let connected = false;

const connectToWs = () => {
    ws = new WebSocket('ws://localhost:6002');

    ws.onopen = e => {
        console.log('Connected');
        for (const [id, operation] of Object.entries(subscriptionOperations)) {
            ws.send(JSON.stringify({
                id: +id,
                operation,
            }));
        }
        connected = true;
    };

    ws.onclose = e => {
        connected = false;
        console.log('Disconnected');
        // tslint:disable-next-line:no-magic-numbers
        setTimeout(connectToWs, 30000);
    };

    ws.onmessage = e => {
        const { id, type, data }: { id: number; type: 'set' | 'patch', data: {} } = JSON.parse(e.data);
        const observer = subscriptionObservers[id];
        if (!observer) {
            throw new Error(`Observer doesn't exist ${id}`);
        }

        switch (type) {
        case 'set':
            subscriptionDataCache[id] = data;
            break;
        case 'patch':
            patch(subscriptionDataCache[id], data);
            break;
        default:
            throw new Error(`Invalid type ${type}`);
        }

        observer.next!({ data: subscriptionDataCache[id] });
    };
};

connectToWs();

let subscriptionId = 0;
const subscriptionObservers: { [key: number]: Observer<any> } = {};
const subscriptionOperations: { [key: number]: Operation } = {};
const subscriptionDataCache: { [key: number]: {} } = {};

const sendQuery = (operation: Operation) => new Observable<any>(subcriber => {
    const currentSubscriptionId = ++subscriptionId;
    subscriptionObservers[currentSubscriptionId] = subcriber;
    subscriptionOperations[currentSubscriptionId] = operation;
    if (connected) {
        ws.send(JSON.stringify({
            id: currentSubscriptionId,
            operation,
        }));
    }
});

const wsLink = new ApolloLink((operation, forward) => new Observable(subscriber => {
    const subscription = sendQuery(operation).subscribe(data => {
        subscriber.next(data);
    }, error => {
        subscriber.error(error);
    }, () => {
        subscriber.complete();
    })
    return () => {
        subscription.unsubscribe();
    };
}));

export const client = new ApolloClient({
    link: wsLink,
    cache: new InMemoryCache(),
    defaultOptions: {
        query: {
            fetchPolicy: 'no-cache',
        },
    },
});