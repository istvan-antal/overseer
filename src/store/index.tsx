import { createStore, compose } from 'redux';
import reducers from './reducers';

export type State = ReturnType<typeof reducers>;

// tslint:disable-next-line:no-any
const composeEnhancers = (window as any).__REDUX_DEVTOOLS_EXTENSION_COMPOSE__ || compose;

const store = createStore(reducers, composeEnhancers());

export default store;