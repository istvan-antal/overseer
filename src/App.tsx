import React from 'react';
import Widget from './Widget';
import { useSubscription } from '@apollo/react-hooks';
import './App.scss';
import gql from 'graphql-tag';

const App = () => {
    const { data: { connection: { connected } = { connected: false, } } = {
    } } = useSubscription<any>(gql`
        {
            connection {
                connected
            }
        }
    `);
    const { data: { widgets = [] } = {} } = useSubscription<any>(gql`
        {
            widgets
        }
    `);

    if (!connected) {
        return <div>Offline</div>;
    }

    return (
        <div className="Widgets">
            {widgets.map((widget: any, index: number) => (
                <Widget key={index} widget={widget} />
            ))}
        </div>
    );
};

export default App;