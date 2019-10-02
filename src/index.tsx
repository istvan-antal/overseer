import * as React from 'react';
import { render } from 'react-dom';
import App from './App';
import { Provider } from 'react-redux';
import store from './store';
import { connect } from 'react-redux';
import { State } from './store';
import { Dispatch, bindActionCreators, AnyAction } from 'redux';
import { WidgetsAction, receiveWidgetsActions } from './store/actions/widgets';
import { patch } from 'jsondiffpatch';

const ConnectedApp = connect(
    (state: State) => state, (dispatch: Dispatch<WidgetsAction | AnyAction>) => bindActionCreators({
}, dispatch))(App);

// tslint:disable-next-line:no-any
let dashboard: { widgets: any[] };
// tslint:disable-next-line:no-any
const update = (state: { type: 'set' | 'patch'; data: any }) => {
    switch (state.type) {
    case 'set':
        dashboard = state.data;
        break;
    case 'patch':
        patch(dashboard, state.data);
        break;
    default:
        throw new Error(`Unhandled type ${state.type}`);
    }
    store.dispatch(receiveWidgetsActions.receive(dashboard.widgets.slice()));
};

const connectToWs = () => {
    const ws = new WebSocket('ws://localhost:6001/api/ws');

    ws.onopen = e => {
        console.log('Connected');
    };

    ws.onclose = e => {
        console.log('Disconnected');
        // tslint:disable-next-line:no-magic-numbers
        setTimeout(connectToWs, 30000);
    };

    ws.onmessage = e => {
        update(JSON.parse(e.data));
    };
};

connectToWs();

render(
    <Provider store={store}>
        <ConnectedApp />
    </Provider>,
    document.getElementById('app'),
);