// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

import { TextControl, SelectControl } from '@wordpress/components';
/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */

export default function Edit(props) {
    const { attributes, setAttributes } = props;

    return (
        <div {...useBlockProps()}>
            <p>
            <i className="fas fa-crow"></i> {/* Example FontAwesome icon */}
            { __(
                    'WhoBIRD observations',
                    'wp-whobird'
                ) }
            </p>
            <TextControl
                label={__('Observation Period', 'wp-whobird')} // Wrap label text with __()
                value={attributes.periodNumber || 1}
                onChange={(value) => setAttributes({ periodNumber: value })}
                type="number"
            />
            <SelectControl
                label={__('Period Unit', 'wp-whobird')} // Wrap label text with __()
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
