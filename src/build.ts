
import http from 'http';
import https from 'https';
import { setup } from './main';

module.exports.default = (config: any) => ({
    ...config,
    devServer: {
        ...config.devServer,
        after(app: http.Server | https.Server) {
            setup(app);
        },
    },
});