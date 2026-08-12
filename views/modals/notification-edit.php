<?php

/** @var string $csrfToken */
?>
<div class="notificationEditBackdrop" id="notificationEditModal" hidden>
    <section class="notificationEditModal" role="dialog" aria-modal="true" aria-labelledby="notificationEditTitle">
        <button class="notificationEditClose" id="closeNotificationEdit" type="button" aria-label="Close notification editor">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <header>
            <h2 id="notificationEditTitle">Edit Notification</h2>
            <p id="notificationEditTask"></p>
        </header>
        <form id="notificationEditForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input id="notificationEditId" name="notification_id" type="hidden">
            <div class="notificationOffsetFields">
                <label for="notificationOffsetValue">
                    <span>Remind me</span>
                    <input id="notificationOffsetValue" name="offset_value" type="number" min="0" step="1" required inputmode="numeric">
                </label>
                <label for="notificationOffsetUnit">
                    <span>Time unit</span>
                    <select id="notificationOffsetUnit" name="offset_unit">
                        <option value="minute">Minutes</option>
                        <option value="hour">Hours</option>
                        <option value="day">Days</option>
                    </select>
                </label>
            </div>
            <p class="notificationDueSummary">Before due time: <strong id="notificationEditDue"></strong></p>
            <p class="notificationEditMessage" id="notificationEditMessage" role="alert" aria-live="polite"></p>
            <div class="notificationEditActions">
                <button class="notificationEditDismiss" id="dismissNotificationEdit" type="button">Close</button>
                <button class="notificationEditSave" id="saveNotificationEdit" type="submit">Save Changes</button>
            </div>
        </form>
    </section>
</div>