// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');
const wpConfig = require('@wordpress/scripts/config/webpack.config');

const originalEntry =
    typeof wpConfig.entry === 'function'
        ? wpConfig.entry()
        : wpConfig.entry;

module.exports = {
    ...wpConfig,
    entry: {
        ...originalEntry,
        'wp-whobird/admin-mapping': './src/wp-whobird/admin-mapping.js',
    },
    optimization: {
        ...wpConfig.optimization,
        minimize: false,
    },
    plugins: [
        ...(wpConfig.plugins || []),
        new CopyWebpackPlugin({
            patterns: [
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
