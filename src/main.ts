import { Server } from 'ws';
import { diff } from 'jsondiffpatch';
import http from 'http';
import https from 'https';
import fetch from 'node-fetch';
const config = require('../config.json');

const connections: Array<{
    send(message: string): void;
}> = [];

let lastState: {};

const clone = (obj: {}) => JSON.parse(JSON.stringify(obj));

const REFRESH_INTERVAL = 5000;
const currentState: {
    // tslint:disable-next-line:no-any
    widgets: any[];
} = {
    widgets: [],
};

const widgets = config.widgets;

interface Build {
    path: string;
    name: string;
    type: 'multibranch' | 'multibranchBuildFolder' | 'build';
    baseUrl: string;
    username: string;
    apiToken: string;
}

interface JiraIssues {
    name: string;
    type: 'jiraIssues';
    baseUrl: string;
    username: string;
    apiToken: string;
    jql: string;
}

interface FolderWidget {
    name: string;
    type: 'folder';
    widgets: Build[];
}

type Widget = FolderWidget | Build | JiraIssues;

const fetchData = async (
    path: string,
    server: { 
        baseUrl: string;
        username: string;
        apiToken: string;
     }) => (await fetch(
         `${server.baseUrl}${path}`,
    {
        headers: {
            Authorization: 'Basic ' + Buffer.from(`${server.username}:${server.apiToken}`).toString('base64'),
        },
    })).json();

const addBuildBranch = async (build: Build) => {
    // tslint:disable-next-line:max-line-length
    /* const data = await fetchData(`job/${build.path.split('/').join('/job/')}/lastBuild/api/json?tree=id,url,displayName,status,actions[*[*[*[*[*]]]]]`);
    const workflow = await fetchData(`job/${build.path.split('/').join('/job/')}/lastBuild/wfapi`);
    for (const stage of workflow.stages) {
        // tslint:disable-next-line:prefer-switch
        if (stage.status === 'IN_PROGRESS' || stage.status === 'FAILED') {
            stage.details = await fetchData(stage._links.self.href);
            for (const flow of stage.details.stageFlowNodes) {
                flow.details = await fetchData(flow._links.log.href);
            }
        }
    }*/
    const data = await fetchData(
        `blue/rest/organizations/jenkins/pipelines/${build.path}/latestRun`,
        {
            baseUrl: build.baseUrl,
            username: build.username,
            apiToken: build.apiToken,
        });

    const nodes = await fetchData(
        `${data._links.self.href}/nodes/`,
        {
            baseUrl: build.baseUrl,
            username: build.username,
            apiToken: build.apiToken,
        }
    );
    return ({
        type: 'build',
        ...data,
        baseUrl: build.baseUrl,
        name: build.name,
        nodes,
        // workflow,
    });
};

export const run = () => {
    const wss = new Server({
        port: 6002,
    });

    wss.on('error', error => {
        console.error(error);
    })

    wss.on('connection', ws => {
        console.log('new connection');
        ws.on('message', message => {
            console.log('message', message);
        });
        console.log('New connection');
        ws.send(JSON.stringify({
            id: 1,
            type: 'set',
            data: lastState,
        }));
        connections.push(ws);

        ws.on('close', () => {
            connections.splice(connections.indexOf(ws), 1);
        });
    });

    const broadcastState = (state: {}) => {
        const newState = clone(state);
        if (lastState) {
            const patch = diff(lastState, newState);
            if (patch) {
                console.log('patch', JSON.stringify(patch));
                connections.forEach(ws => {
                    ws.send(JSON.stringify({
                        id: 1,
                        type: 'patch',
                        data: patch,
                    }));
                });
            }
        } else {
            connections.forEach(ws => {
                ws.send(JSON.stringify({
                    id: 1,
                    type: 'set',
                    data: newState,
                }));
            });
        }
        lastState = newState;
    };

    const processWidgets = async (widgets: Widget[]) => {
        const results: Array<{}> = [];
        for (const widget of widgets) {
            switch (widget.type) {
                case 'jiraIssues':
                    results.push({
                        type: 'jiraIssues',
                        name: widget.name,
                        issues: (await fetchData(`rest/api/2/search?jql=${encodeURIComponent(widget.jql)}`, {
                            baseUrl: widget.baseUrl,
                            username: widget.username,
                            apiToken: widget.apiToken,
                        })).issues,
                    });
                    break;
                case 'folder':
                    results.push({
                        type: 'folder',
                        name: widget.name,
                        widgets: await processWidgets(widget.widgets),
                    });
                    break;
                case 'multibranchBuildFolder': {
                    const folderData = await fetchData(`blue/rest/organizations/jenkins/pipelines/${widget.path}`, {
                        baseUrl: widget.baseUrl,
                        username: widget.username,
                        apiToken: widget.apiToken,
                    });
                    for (const reposiory of folderData.pipelineFolderNames) {
                        const data = await fetchData(`blue/rest/organizations/jenkins/pipelines/${widget.path}/${reposiory}`, {
                            baseUrl: widget.baseUrl,
                            username: widget.username,
                            apiToken: widget.apiToken,
                        });
                        for (const branch of data.branchNames) {
                            results.push(await addBuildBranch({
                                type: 'build',
                                path: `${widget.path}/${reposiory}/branches/${branch.replace(/%2F/g, '%252F')}`,
                                name: `${widget.name} : ${reposiory} - ${branch.replace(/%2F/g, '/')}`,
                                baseUrl: widget.baseUrl,
                                username: widget.username,
                                apiToken: widget.apiToken,
                            }));
                        }
                    }
                }
                    break;
                case 'multibranch': {
                    const data = await fetchData(`blue/rest/organizations/jenkins/pipelines/${widget.path}`, {
                        baseUrl: widget.baseUrl,
                        username: widget.username,
                        apiToken: widget.apiToken,
                    });
                    for (const branch of data.branchNames) {
                        results.push(await addBuildBranch({
                            type: 'build',
                            path: `${widget.path}/branches/${branch.replace(/%2F/g, '%252F')}`,
                            name: `${widget.name} - ${branch.replace(/%2F/g, '/')}`,
                            baseUrl: widget.baseUrl,
                            username: widget.username,
                            apiToken: widget.apiToken,
                        }));
                    }
                }
                    break;
                case 'build':
                    results.push(await addBuildBranch(widget));
                    break;
                default:
                    throw new Error('Invalid type');
            }
        }
        return results;
    };

    const update = () => {
        (async () => {
            currentState.widgets = await processWidgets(widgets);
            broadcastState(currentState);
            setTimeout(update, REFRESH_INTERVAL);
        })().catch(error => {
            console.error(error);
            // tslint:disable-next-line:no-magic-numbers
            setTimeout(update, REFRESH_INTERVAL * 5);
        });
    };

    update();
};