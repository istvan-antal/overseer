import { spawn } from 'child_process';

const settings: {
    auth: string;
    url: string;
    widgets: Array<{
        jql: string;
    }>
} = require('./public/settings.json');

const update = () => {
    settings.widgets.forEach((widget, key) => {
        spawn('curl', [
            '-u',
            settings.auth,
            '-o',
            `public/data/${key}.json`,
            `${settings.url}/rest/api/2/search/?maxResults=1000&jql=${encodeURIComponent(widget.jql)}`
        ],
        {
            stdio: 'inherit',
        });
    });
    
};

setInterval(update, 60000);
update();