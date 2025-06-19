// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

import { __ } from '@wordpress/i18n';

/**
 * Handles the admin mapping generation process for the WhoBird plugin.
 *
 * This script enables step-by-step mapping generation via Ajax when the admin clicks
 * the "generate mapping" button. Progress and results are displayed in a status list.
 */

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('whobird-generate-mapping-btn');
    const statusList = document.getElementById('whobird-mapping-steps-status');
    if (!btn || !statusList) return;

    // Start the mapping generation process when the button is clicked.
    btn.addEventListener('click', function () {
        btn.disabled = true;
        statusList.innerHTML = '';
        doStep(); // Start the first Ajax step
    });

    /**
     * Runs a single step of the mapping generation via Ajax, updating the UI with progress.
     * If another step is required, calls itself recursively with the next step.
     *
     * @param {string} [step] - Optional step name/id for multi-step processes.
     */
    function doStep(step) {
        // Display feedback for the current step.
        const li = document.createElement('li');
        li.textContent = step
            ? __('Running step: ', 'wp-whobird') + step + '...'
            : __('Starting...', 'wp-whobird');
        statusList.appendChild(li);

        // Prepare data for Ajax request.
        const data = new URLSearchParams();
        data.append('action', 'whobird_generate_mapping');
        if (step) data.append('step', step);
        data.append('_ajax_nonce', window.wpwhobirdMappingVars.nonce);

        // Send Ajax request to perform the current step.
        fetch(window.wpwhobirdMappingVars.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data.toString()
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                li.textContent = `✔️ ${resp.data.msg}`;
                // Continue with the next step if needed.
                if (resp.data.next_step) {
                    doStep(resp.data.next_step);
                } else {
                    btn.disabled = false;
                    const done = document.createElement('li');
                    done.innerHTML = '<strong>' + __('All steps completed!', 'wp-whobird') + '</strong>';
                    statusList.appendChild(done);
                }
            } else {
                li.textContent = `❌ ${resp.data && resp.data.msg ? resp.data.msg : __('Error.', 'wp-whobird')}`;
                btn.disabled = false;
            }
        })
        .catch(e => {
            li.textContent = `❌ ${__('AJAX error', 'wp-whobird')}`;
            btn.disabled = false;
        });
    }
});
