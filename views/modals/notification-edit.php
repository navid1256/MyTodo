<?php

/** @var string $csrfToken */
/** @var \App\Localization\Translator $translator */
?>
<dialog class="notificationEditBackdrop" id="notificationEditModal" aria-labelledby="notificationEditTitle">
    <section class="notificationEditModal">
        <button class="notificationEditClose" id="closeNotificationEdit" type="button" data-i18n-aria-label="notifications.editor.close" aria-label="<?= htmlspecialchars($translator->translate('notifications.editor.close'), ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <header>
            <h2 id="notificationEditTitle" data-i18n="notifications.editor.title"><?= htmlspecialchars($translator->translate('notifications.editor.title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p id="notificationEditTask"></p>
        </header>
        <form id="notificationEditForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input id="notificationEditId" name="notification_id" type="hidden">
            <div class="notificationOffsetFields">
                <label for="notificationOffsetValue">
                    <span data-i18n="notifications.editor.remind_me"><?= htmlspecialchars($translator->translate('notifications.editor.remind_me'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input id="notificationOffsetValue" name="offset_value" type="number" min="0" step="1" required inputmode="numeric">
                </label>
                <label for="notificationOffsetUnit">
                    <span data-i18n="notifications.editor.time_unit"><?= htmlspecialchars($translator->translate('notifications.editor.time_unit'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="notificationOffsetUnit" name="offset_unit">
                        <option value="minute" data-i18n="notifications.unit.minute.other"><?= htmlspecialchars($translator->translate('notifications.unit.minute.other'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="hour" data-i18n="notifications.unit.hour.other"><?= htmlspecialchars($translator->translate('notifications.unit.hour.other'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="day" data-i18n="notifications.unit.day.other"><?= htmlspecialchars($translator->translate('notifications.unit.day.other'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
            </div>
            <p class="notificationDueSummary"><span data-i18n="notifications.editor.before_due_time"><?= htmlspecialchars($translator->translate('notifications.editor.before_due_time'), ENT_QUOTES, 'UTF-8') ?></span> <strong id="notificationEditDue"></strong></p>
            <p class="notificationEditMessage" id="notificationEditMessage" role="alert" aria-live="polite"></p>
            <div class="notificationEditActions">
                <button class="notificationEditDismiss" id="dismissNotificationEdit" type="button" data-i18n="common.close"><?= htmlspecialchars($translator->translate('common.close'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="notificationEditSave" id="saveNotificationEdit" type="submit" data-i18n="notifications.editor.save_changes"><?= htmlspecialchars($translator->translate('notifications.editor.save_changes'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
    </section>
</dialog>
