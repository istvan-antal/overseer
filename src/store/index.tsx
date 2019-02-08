import { createStore } from 'redux';
import reducers from '../reducers';

export type State = ReturnType<typeof reducers>;

const store = createStore(reducers);

export default store;