import webpack from 'webpack';
import { run } from './main';

module.exports.default = (config: any) => ({
    ...config,
    plugins: [
        ...config.plugins,
        new webpack.ContextReplacementPlugin(/graphql-language-service-interface[\\/]dist$/, new RegExp(`^\\./.*\\.js$`)),
        new webpack.ContextReplacementPlugin(/graphql-language-service-utils[\\/]dist$/, new RegExp(`^\\./.*\\.js$`)),
        new webpack.ContextReplacementPlugin(/graphql-language-service-parser[\\/]dist$/, new RegExp(`^\\./.*\\.js$`)),
    ],
    devServer: {
        ...config.devServer,
        after(app: any) {
            run();
        },
    },
});