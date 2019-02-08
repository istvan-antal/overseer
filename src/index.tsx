import * as React from 'react';
import { render } from 'react-dom';
import App from './App';
import { Provider } from 'react-redux';
import store from './store';

// Replace this with actual actions union
// tslint:disable-next-line:no-any
type Actions = any;
import { connect } from 'react-redux';
import { State } from './store';
import { Dispatch, bindActionCreators } from 'redux';
const ConnectedApp = connect((state: State) => ({
}), (dispatch: Dispatch<Actions>) => bindActionCreators({
}, dispatch))(App);

render(
    <Provider store={store}>
        <ConnectedApp />
    </Provider>,
    document.getElementById('app'),
);