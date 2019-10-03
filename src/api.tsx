import React from 'react';
import ReactDOM from 'react-dom';
import GraphiQL from 'graphiql';
import { Observable } from 'apollo-link';

import './api.scss';

const runQuery = (params: {}) => new Observable(subscriber => {
    console.log(params);
    subscriber.next({ data: {} });
});

ReactDOM.render(<GraphiQL fetcher={runQuery} />, document.getElementById('app'));