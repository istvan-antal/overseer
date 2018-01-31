import React, { Component } from 'react';
import './App.css';

const sleep = async (timeout) => new Promise(resolve => {
  setTimeout(timeout, resolve);
})

const fetchJson = async (url, retryCount = 5) => {
  try {
    return fetch(url).then(response => response.json());
  } catch (e) {
    if (!retryCount) {
      throw e;
    }
    await sleep(1000);
    return fetchJson(url, retryCount - 1);
  }
}

const IssueRow = ({ widget, issue, baseUrl }) => {
  let classes = '';

  if (widget.doneStates.includes(issue.fields.status.name)) {
    classes += 'done ';
  }

  if (widget.blockedStates.includes(issue.fields.status.name)) {
    classes += 'blocked ';
  }

  return (
    <tr className={classes}>
      <td>
        <a href={`${baseUrl}/browse/${issue.key}`} target="_blank">
          {issue.fields.summary}
        </a>
      </td>
      <td>
        {issue.fields.assignee && <strong>
          <img title={issue.fields.assignee.displayName} width="24" height="24" src={issue.fields.assignee.avatarUrls['24x24']} />
        </strong>}
      </td>
    </tr>
  );
};

class App extends Component {
  constructor() {
    super();

    this.state = {
      widgets: [],
    };

    (async () => {
      const data = await fetch('settings.json').then(response => response.json())

      const widgets = await Promise.all(data.widgets.map(async (widget, key) => ({
        ...widget,
        data: await fetchJson(`data/${key}.json`)
      })))

      this.setState({
        baseUrl: data.url,
        widgets,
      });
    })();
  }
  render() {
    return (
      <div className="App">
        {this.state.widgets.map((widget, key) => (
          <div className="widget" key={key}>
            <table>
              <thead>
                <tr>
                  <th colSpan="2">{widget.title}</th>
                </tr>
              </thead>
              <tbody>
                {widget.data.issues.sort((a, b) => widget.doneStates.includes(a.fields.status.name) ? 1 : -1).map(issue => (
                  <IssueRow widget={widget} issue={issue} baseUrl={this.state.baseUrl} key={issue.key} />
              ))}
              </tbody>
            </table>
          </div>
        ))}
      </div>
    );
  }
}

export default App;
