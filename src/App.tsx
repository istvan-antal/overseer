import * as React from 'react';
import './App.css';

import { fetchJson, sleep } from './io';

const IssueRow = ({ widget, issue, baseUrl }: any) => {
  let classes = '';

  if (widget.doneStates.includes(issue.fields.status.name)) {
    classes += 'done ';
  }

  if (widget.inProgressStates.includes(issue.fields.status.name)) {
    classes += 'progress ';
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


interface Props {
}

interface State {
  baseUrl?: string;
  widgets: any[];
}

class App extends React.Component<Props, State> {
  private update: () => Promise<void>;
  constructor() {
    super({});

    this.state = {
      widgets: [],
    };

    this.update = async () => {
      try {
        const data = await fetchJson('settings.json');

        const widgets = await Promise.all(data.widgets.map(async (widget: any, key: number) => ({
          ...widget,
          data: await fetchJson(`data/${key}.json`)
        })))

        this.setState({
          baseUrl: data.url,
          widgets,
        });
      } finally {
        await sleep(60000);
        this.update();
      }
    };

    this.update();
  }
  render() {
    return (
      <div className="App">
        {this.state.widgets.map((widget, key) => (
          <div className="widget" key={key}>
            <table>
              <thead>
                <tr>
                  <th colSpan={2}>{widget.title}</th>
                </tr>
              </thead>
              <tbody>
                {(widget.data.issues || []).sort((a: any, b: any) => widget.doneStates.includes(a.fields.status.name) ? 1 : -1).map((issue: any) => (
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
