import * as React from 'react';
import { render } from 'react-dom';
import App from './App';
import { Provider } from 'react-redux';
import store from './store';
import { connect } from 'react-redux';
import { State } from './store';
import { Dispatch, bindActionCreators, AnyAction } from 'redux';
import { WidgetsAction, receiveWidgetsActions } from './store/actions/widgets';

const ConnectedApp = connect(
    (state: State) => state, (dispatch: Dispatch<WidgetsAction | AnyAction>) => bindActionCreators({
}, dispatch))(App);

// tslint:disable-next-line:no-any
const update = (state: { widgets: any[] }) => {
    console.log('New State', state);
    store.dispatch(receiveWidgetsActions.receive(state.widgets));
};

const connectToWs = () => {
    const ws = new WebSocket('ws://localhost:18080/');

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