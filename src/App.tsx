import * as React from 'react';
import { State } from './store';
import Widget from './Widget';
import './App.scss';

export default class App extends React.Component<State> {
    render() {
        return (
            <div className="Widgets">
                {this.props.widgets.map(widget => (
                    <Widget key={widget._links.self.href} widget={widget} />
                ))}
            </div>
        );
    }
}