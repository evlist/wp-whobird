const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');

module.exports = {
    // Utiliser la configuration par défaut de wp-scripts
    ...require('@wordpress/scripts/config/webpack.config'),

    optimization: {
        ...require('@wordpress/scripts/config/webpack.config').optimization,
        minimize: false, // Disable minification
    },

    plugins: [
        // Copier le contenu du répertoire assets/data dans build/assets/data
        ...require('@wordpress/scripts/config/webpack.config').plugins,
        new CopyWebpackPlugin({
            patterns: [
                {
                    from: path.resolve(__dirname, 'assets/data'),
                    to: path.resolve(__dirname, 'build/assets/data'),
                },
                {
                    from: path.resolve(__dirname, 'node_modules/@fortawesome/fontawesome-free/webfonts'),
                    to: path.resolve(__dirname, 'build/webfonts'),
                },
                {
                    from: path.resolve(__dirname, 'node_modules/@fortawesome/fontawesome-free/css/all.min.css'),
                    to: path.resolve(__dirname, 'build/css'),
                },
            ],
        }),
    ],
};
