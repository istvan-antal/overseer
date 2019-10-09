import * as React from 'react';
import './Widget.scss';

interface Build {
    type: 'build'
    name: string;
    id: string;
    baseUrl: string;
    _links: {
        self: {
            href: string;
        };
    };
    result: string;
    nodes: Array<{
        id: string;
        type: string;
        result: string;
        displayName: string;
        actions: Array<{
            link: {
                href: string;
            };
            _class: string;
            description: string;
        }>;
    }>;
}

interface Folder {
    type: 'folder',
    name: string;
    widgets: WidgetType[];
}

interface IssueList {
    type: 'jiraIssues',
    name: string;
    issues: any[];
}


type WidgetType = Folder | Build | IssueList;

const expandStatuses = ['UNKNOWN', 'FAILURE'];

const Widget = ({ widget }: { widget: WidgetType }) => {
    if (widget.type === 'folder') {
        return (
            <div>
                <h1>{widget.name}</h1>
                <div className="Widgets">
                    {widget.widgets.map((widget, index) => <Widget widget={widget} key={index} />)}
                </div>
            </div>
        );
    }

    if (widget.type === 'jiraIssues') {
        return (
            <div className={`Widget`}>
                <span>{widget.name}</span>
                <div>
                    {widget.issues.length > 10 ?
                        `${widget.issues.length} issues`:
                        widget.issues.map(item => (
                            <div key={item.key}>
                                {item.key}
                                {' '}
                                {item.fields.summary}
                            </div>
                        ))}
                </div>
            </div>
        );
    }
    
    return (
        <div className={`Widget ${widget.result}`}>
            <a
                target="_blank"
                href={`${widget.baseUrl}${widget._links.self.href.replace(
                    // tslint:disable-next-line:max-line-length
                    /blue\/rest\/organizations\/jenkins\/pipelines\/(.+)\/pipelines\/(.+)\/branches\/(.+)\/runs\/(.+)\//gm,
                    'job/$1/job/$2/job/$3/$4/',
                )}`}
                className="WidgetTitle"
            >
                {widget.name}
            </a>
            {expandStatuses.includes(widget.result) &&
                <div className="WidgetStages">
                    {widget.nodes.filter(node => node.type === 'STAGE' || !!node.id).map(stage => (
                        <div className={`WidgetStage ${stage.result}`} key={stage.id}>
                            {stage.displayName}
                            {expandStatuses.includes(stage.result) &&
                                <div>
                                    {stage.actions.map(action => {
                                        if (action._class === 'io.jenkins.blueocean.listeners.NodeDownstreamBuildAction') {
                                            return (
                                                <a
                                                    key={action.link.href}
                                                    target="_blank"
                                                    href={`${widget.baseUrl}${action.link.href.replace(
                                                        // tslint:disable-next-line:max-line-length
                                                        /blue\/rest\/organizations\/jenkins\/pipelines\/(.+)\/pipelines\/(.+)\/branches\/(.+)\/runs\/(.+)\//gm,
                                                        'job/$1/job/$2/job/$3/$4/',
                                                    )}`}
                                                >
                                                    {action.description}
                                                </a>
                                            );
                                        }
                                        return (
                                            <div key={action.link.href}>{action.description}</div>
                                        );
                                    })}
                                </div>
                            }
                            {/*stage.details &&
                                <div>
                                    {stage.details.stageFlowNodes.map(flow => (
                                        <div key={flow.id}>
                                            {flow.name}
                                        </div>
                                    ))}
                                </div>
                            */}
                            {/*stage.error &&
                            <div>
                                {stage.error.message}
                            </div>
                            */}
                        </div>
                    ))}
                </div>
            }
        </div>
    );
};

export default Widget;