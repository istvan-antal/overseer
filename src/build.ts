import { run } from './main';

module.exports.default = (config: any) => ({
    ...config,
    devServer: {
        ...config.devServer,
        after(app: any) {
            run();
        },
    },
});