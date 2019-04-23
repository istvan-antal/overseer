import { run } from 'charge-sdk';
import { broadcastState } from './connectionManager';
import fetch from 'node-fetch';
const config = require('../config.json');

const REFRESH_INTERVAL = 5000;
const state: {
    // tslint:disable-next-line:no-any
    widgets: any[];
} = {
    widgets: [],
};

const BASE_URL = config.baseUrl;
const API_TOKEN = config.apiToken;
const username = config.username;

const builds = config.builds;

interface Build {
    path: string;
    nickname: string;
    type?: 'multibranch';
}

const fetchData = async (path: string) => (await fetch(
    `${BASE_URL}${path}`,
    {
        headers: {
            Authorization: 'Basic ' + Buffer.from(`${username}:${API_TOKEN}`).toString('base64'),
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
        `blue/rest/organizations/jenkins/pipelines/${build.path}/latestRun`);
    const nodes = await fetchData(
        `${data._links.self.href}/nodes/`);
    state.widgets.push({
        ...data,
        baseUrl: BASE_URL,
        nickname: build.nickname,
        nodes,
        // workflow,
    });
};

const update = () => {
    (async () => {
        state.widgets = [];
        for (const build of builds) {
            if (build.type === 'multibranch') {
                const data = await fetchData(
                    `blue/rest/organizations/jenkins/pipelines/${build.path}`);
                for (const branch of data.branchNames) {
                    await addBuildBranch({
                        path: `${build.path}/branches/${branch.replace(/%2F/g, '%252F')}`,
                        nickname: `${build.nickname} - ${branch.replace(/%2F/g, '/')}`,
                    });
                }
                continue;
            }
            await addBuildBranch(build);
        }
        broadcastState(state);
        setTimeout(update, REFRESH_INTERVAL);
    })().catch(error => {
        console.error(error);
        // tslint:disable-next-line:no-magic-numbers
        setTimeout(update, REFRESH_INTERVAL * 5);
    });
};

update();
run();