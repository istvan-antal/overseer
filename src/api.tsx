import React from 'react';
import ReactDOM from 'react-dom';
import GraphiQL from 'graphiql';
import { Observable } from 'apollo-link';
import { parse } from 'graphql/language/parser';

import './api.scss';

const runQuery = (params: { query: string }) => new Observable(subscriber => {
    console.log(parse(params.query));
    subscriber.next({ data: {} });
});

ReactDOM.render(<GraphiQL fetcher={runQuery} />, document.getElementById('app'));