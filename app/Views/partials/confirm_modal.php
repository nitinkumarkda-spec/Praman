<?php
/**
 * Shared confirm dialog. Any form/button with data-confirm="message" opens it (see app.js).
 * Optional: data-confirm-title, data-confirm-ok, data-confirm-variant (primary|danger|success|gold).
 */
?>
<div class="modal-backdrop" id="confirm-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <div class="modal-header">
            <span class="modal-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span>
            <h2 class="modal-title" id="confirm-title">Please confirm</h2>
        </div>
        <div class="modal-body">
            <p id="confirm-message"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" data-confirm-cancel>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>No, cancel</span>
            </button>
            <button type="button" class="btn btn-primary" data-confirm-ok>
                <i class="fa-solid fa-check" aria-hidden="true"></i> <span>Yes, continue</span>
            </button>
        </div>
    </div>
</div>
