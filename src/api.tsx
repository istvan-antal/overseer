import React from 'react';
import ReactDOM from 'react-dom';
import GraphiQL from 'graphiql';
import { parse } from 'graphql/language/parser';

import './api.scss';
import { sendQuery } from './client';

const runQuery = ({
    query,
    variables,
    operationName,
}: { query: string, variables: Record<string, any>, operationName: string }) => (
    sendQuery({
        operationName,
        variables,
        query: parse(query),
        extensions: {},
        setContext: undefined as any,
        getContext: undefined as any,
        toKey: undefined as any,
    })
);

ReactDOM.render(<GraphiQL fetcher={runQuery} />, document.getElementById('app'));