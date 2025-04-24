const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');

module.exports = {
    // Utiliser la configuration par défaut de wp-scripts
    ...require('@wordpress/scripts/config/webpack.config'),

    plugins: [
        // Copier le contenu du répertoire assets/data dans build/assets/data
        ...require('@wordpress/scripts/config/webpack.config').plugins,
        new CopyWebpackPlugin({
            patterns: [
                {
                    from: path.resolve(__dirname, 'assets/data'),
                    to: path.resolve(__dirname, 'build/assets/data'),
                },
            ],
        }),
    ],
};
