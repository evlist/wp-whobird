// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Block editor code for the WhoBird block editor interface.
 * 
 * Provides the UI controls for selecting the observation period and unit
 * when editing the block in the WordPress editor.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { TextControl, SelectControl } from '@wordpress/components';
import './editor.scss';

/**
 * Edit component for the WhoBird block.
 * 
 * Renders the block's controls and preview in the WordPress block editor.
 * 
 * @param {Object} props - Block props from the editor.
 * @return {Element} Element to render in the editor.
 */
export default function Edit(props) {
    const { attributes, setAttributes } = props;

    return (
        <div {...useBlockProps()}>
            <p>
                <i className="fas fa-crow"></i>
                { __('WhoBIRD observations', 'wp-whobird') }
            </p>
            <TextControl
                label={__('Observation Period', 'wp-whobird')}
                value={attributes.periodNumber || 1}
                onChange={(value) => setAttributes({ periodNumber: value })}
                type="number"
            />
            <SelectControl
                label={__('Period Unit', 'wp-whobird')}
                value={attributes.periodUnit || 'day'}
                options={[
                    { label: __('Day(s)', 'wp-whobird'), value: 'day' },
                    { label: __('Week(s)', 'wp-whobird'), value: 'week' },
                    { label: __('Month(s)', 'wp-whobird'), value: 'month' },
                ]}
                onChange={(value) => setAttributes({ periodUnit: value })}
            />
        </div>
    );
}
