import React from 'react';
import Widget from './Widget';
import { useSubscription } from '@apollo/react-hooks';
import './App.scss';
import gql from 'graphql-tag';

const App = () => {
    const { data: { widgets = [] } = {} } = useSubscription<any>(gql`
        {
            widgets
        }
    `);
    return (
        <div className="Widgets">
            {widgets.map((widget: any) => (
                <Widget key={widget._links.self.href} widget={widget} />
            ))}
        </div>
    );
};

export default App;