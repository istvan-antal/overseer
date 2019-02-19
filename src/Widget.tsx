import * as React from 'react';
import { Widget } from './store/actions/widgets';
import './Widget.scss';

/*
// tslint:disable-next-line:no-any
const recursiveFind = (id: string, nodes: any[]) => {
    const hit = nodes.find(action => action.id === id);
    if (hit) {
        return hit;
    }
    for (const node of nodes) {
        if (!node.nodes || !node.nodes.length) {
            continue;
        }
        // tslint:disable-next-line:no-any
        const result: any | undefined = recursiveFind(id, node.nodes);
        if (result) {
            return result;
        }
    }
    return undefined;
};

// tslint:disable-next-line:no-any
const flatten = (nodes: any[]): any[] => (
    [...nodes, ...nodes.map(node => node.nodes ? flatten(node.nodes) : []).reduce((a, b) => [...a, ...b], [])]
);

// tslint:disable-next-line:no-any
const populateChildren = (node: any, nodes: any[]) => {
    node.children = nodes.filter(item => !!item.parents && item.parents.includes(node.id));
    // tslint:disable-next-line:no-any
    node.children.forEach((child: any) => {
        populateChildren(child, nodes);
    });
    return node;
};*/

const expandStatuses = ['UNKNOWN', 'FAILURE'];

export default ({ widget }: { widget: Widget }) => (
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
            {widget.nickname}
        </a>
        {expandStatuses.includes(widget.result)  &&
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