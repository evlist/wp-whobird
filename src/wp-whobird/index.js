// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Entry point for the WhoBird block registration.
 * 
 * This file registers the block with WordPress and connects the editor component and styles.
 */

import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import Edit from './edit';
import metadata from './block.json';

// Register the WhoBird block using block metadata and edit component.
registerBlockType(metadata.name, {
    edit: Edit,
});
