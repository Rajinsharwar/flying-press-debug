<?php
/**
 * Template for the Admin page.
 *
 * @package flp-debug
 */

?>

<div class="wrap">
    <h1>FLP Debug Tools</h1>

    <form id="flp-debug-form">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="flp_debug_action">Select an Action</label></th>
                <td>
                    <select name="flp_debug_action" id="flp_debug_action">
                        <option value="single">Test a single URL preload</option>
                        <!-- <option value="multiple">Test multiple URL preload</option> -->
                    </select>
                </td>
            </tr>
            <tr id="flp-debug-url-row">
                <th scope="row"><label for="flp_debug_url">URL to Test</label></th>
                <td>
                    <input type="text" name="flp_debug_url" id="flp_debug_url" placeholder="https://example.com/" style="width: 350px;">
                </td>
            </tr>
        </table>

        <button type="submit" class="button button-primary">Run Debug</button>
    </form>

    <div id="flp-debug-status" style="margin-top: 20px;"></div>
    <!-- Log Modal -->
    <div id="flp-log-modal" style="display:none;">
        <div class="flp-log-backdrop"></div>
        <div class="flp-log-wrapper">
            <div class="flp-log-header">
                <h2>Debug Log</h2>
                <button class="button" onclick="closeModal()">Close</button>
            </div>
            <div class="flp-log-content">
                <pre><code id="flp-log-modal-content" class="language-php"></code></pre>
            </div>
        </div>
    </div>
</div>
<style>
#flp-log-modal {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    z-index: 9999;
}

.flp-log-backdrop {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.7);
}

.flp-log-wrapper {
    position: absolute;
    top: 10%;
    left: 50%;
    transform: translateX(-50%);
    width: 600px;
    max-height: 80%;
    background: #fff;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
    border-radius: 4px;
    display: flex;
    flex-direction: column;
}

.flp-log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid #ccc;
    background: #f8f9fa;
}

.flp-log-content {
    padding: 16px;
    overflow-y: auto;
    max-height: 400px;
}

.flp-log-content pre {
    background: #f4f4f4;
    padding: 12px;
    border-radius: 4px;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
    color: #333;
    white-space: pre-wrap;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('flp-debug-form');
    const statusBox = document.getElementById('flp-debug-status');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const action = document.getElementById('flp_debug_action').value;
        const url = document.getElementById('flp_debug_url').value;
        statusBox.innerHTML = '';
        closeModal();

        let steps = [];

        if (action === 'single') {
            steps = [ 'clear_home_url', 'queue_home_url', 'start_queue_home_url', 'process_home_url', 'verify_cache_home_url' ];
        } else if (action === 'multiple') {
            // not implemented.
            steps = [ 'clear_home_and_post_url', 'queue_home_url_and_post_url', 'start_queue_home_url_and_post_url', 'process_home_url_and_post_url', 'verify_cache_home_url_and_post_url' ];
        }

        runStepsSequentially(steps, 0);
    });

    function runStepsSequentially(steps, index) {
        if (index >= steps.length) return;

        const step = steps[index];
        const stepId = `step-${index}`;
        const stepDiv = document.createElement('div');
        stepDiv.id = stepId;
        stepDiv.innerHTML = `<strong>${formatStepLabel(step)}</strong>: <span>⏳ Running...</span>`;
        statusBox.appendChild(stepDiv);

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'flp_debug_run_step',
                step: step,
                url: document.getElementById('flp_debug_url').value,
                _ajax_nonce: '<?php echo wp_create_nonce('flp_debug_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            const span = stepDiv.querySelector('span');
            if (data.success) {
                span.innerHTML = `✅ ${data.data.message} <a href="#" data-log="${encodeURIComponent(data.data.log || 'No logs provided')}" onclick="openLogModal(this)">Log</a>`;
            } else {
                const logId = `log-${stepId}`;
                span.innerHTML = `❌ ${data.data.message} <a href="#" data-log="${encodeURIComponent(data.data.log || 'No logs provided')}" onclick="openLogModal(this)">Log</a>`;
            }

            runStepsSequentially(steps, index + 1);
        })
        .catch(err => {
            const span = stepDiv.querySelector('span');
            span.innerHTML = `❌ Request error: ${err.message}`;
            runStepsSequentially(steps, index + 1);
        });
    }

    function formatStepLabel(step) {
        switch (step) {
            case 'clear_home_url': return 'Clear Home URL';
            case 'queue_home_url': return 'Queue Home URL';
            case 'start_queue_home_url': return 'Start Queue Home URL';
            case 'process_home_url': return 'Process Home URL';
            case 'verify_cache_home_url': return 'Verify Cache of Home URL';
            default: return step;
        }
    }

    // Modal Handling
    window.openLogModal = function (el) {
        const logContent = decodeURIComponent(el.getAttribute('data-log'));
        const codeElem = document.getElementById('flp-log-modal-content');
        codeElem.textContent = logContent;
        document.getElementById('flp-log-modal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    };

    window.closeModal = function () {
        document.getElementById('flp-log-modal').style.display = 'none';
        document.body.style.overflow = '';
    };

    // Show/hide URL field based on action
    const actionSelect = document.getElementById('flp_debug_action');
    const urlRow = document.getElementById('flp-debug-url-row');
    function toggleUrlRow() {
        if (actionSelect.value === 'single') {
            urlRow.style.display = '';
        } else {
            urlRow.style.display = 'none';
        }
    }
    actionSelect.addEventListener('change', toggleUrlRow);
    toggleUrlRow(); // Initialize on page load
});
</script>

