import React, { Component } from 'react';
import './App.css';

class App extends Component {
  constructor() {
    super();

    this.state = {
      issuesInProgress: {
        issues: []
      },
    };

    fetch('data/in-progress.json')
      .then(response => response.json())
      .then((data) => {
        this.setState({
          issuesInProgress: data,
        });
      });
  }
  render() {
    return (
      <div className="App">
        <h1>In progress</h1>
        {this.state.issuesInProgress.issues.map(issue => (
          <div key={issue.key}>
            {issue.fields.summary}
            {issue.fields.assignee && <strong>{issue.fields.assignee.displayName}</strong>}
          </div>
        ))}
      </div>
    );
  }
}

export default App;
