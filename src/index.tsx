import * as React from 'react';
import { render } from 'react-dom';
import App from './App';
import { Provider } from 'react-redux';
import store from './store';
import { connect } from 'react-redux';
import { State } from './store';
import { Dispatch, bindActionCreators, AnyAction } from 'redux';
import { WidgetsAction, receiveWidgetsActions } from './store/actions/widgets';
import gql from 'graphql-tag';
import { client } from './client';

const ConnectedApp = connect(
    (state: State) => state, (dispatch: Dispatch<WidgetsAction | AnyAction>) => bindActionCreators({
}, dispatch))(App);

// tslint:disable-next-line:no-any
let dashboard: { widgets: any[] };
// tslint:disable-next-line:no-any
/*
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
};*/

const liveQuery = client.subscribe({
    query: gql`
    {
        widgets
    }
    `
});

liveQuery.subscribe(({ data: { widgets }}) => {
    store.dispatch(receiveWidgetsActions.receive(widgets.slice()));
})

render(
    <Provider store={store}>
        <ConnectedApp />
    </Provider>,
    document.getElementById('app'),
);