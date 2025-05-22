document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('whobird-generate-mapping-btn');
    const statusList = document.getElementById('whobird-mapping-steps-status');
    if (!btn || !statusList) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        statusList.innerHTML = '';
        doStep(); // Start with no step to trigger the first step
    });

    function doStep(step) {
        // Display current step (for user feedback)
        const li = document.createElement('li');
        li.textContent = step ? `Running step: ${step}...` : 'Starting...';
        statusList.appendChild(li);

        const data = new URLSearchParams();
        data.append('action', 'whobird_generate_mapping');
        if (step) data.append('step', step);
        data.append('_ajax_nonce', window.wpwhobirdMappingVars.nonce);

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
                if (resp.data.next_step) {
                    doStep(resp.data.next_step);
                } else {
                    btn.disabled = false;
                    const done = document.createElement('li');
                    done.innerHTML = '<strong>All steps completed!</strong>';
                    statusList.appendChild(done);
                }
            } else {
                li.textContent = `❌ ${resp.data && resp.data.msg ? resp.data.msg : 'Error.'}`;
                btn.disabled = false;
            }
        })
        .catch(e => {
            li.textContent = `❌ AJAX error`;
            btn.disabled = false;
        });
    }
});
