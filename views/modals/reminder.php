          <?php /** @var \App\Localization\Translator $translator */ ?>
          <div class="reminderModalBackdrop" id="reminderModal" hidden>
              <section
                  class="reminderModal"
                  role="dialog"
                  aria-modal="true"
                  aria-labelledby="reminderModalTitle">
                  <button class="reminderModalClose" id="closeReminderModal" type="button" data-i18n-aria-label="reminder.modal.close" aria-label="<?= htmlspecialchars($translator->translate('reminder.modal.close'), ENT_QUOTES, 'UTF-8') ?>">
                      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                  </button>

                  <div class="reminderModalHeader">
                      <h2 id="reminderModalTitle" data-i18n="reminder.modal.title"><?= htmlspecialchars($translator->translate('reminder.modal.title'), ENT_QUOTES, 'UTF-8') ?></h2>
                      <label class="reminderCountField" for="reminderCount">
                          <span class="srOnly" data-i18n="reminder.modal.count"><?= htmlspecialchars($translator->translate('reminder.modal.count'), ENT_QUOTES, 'UTF-8') ?></span>
                          <select id="reminderCount">
                              <?php for ($reminderCount = 1; $reminderCount <= 5; $reminderCount++): ?>
                                  <option value="<?= $reminderCount ?>"><?= $reminderCount ?></option>
                              <?php endfor; ?>
                          </select>
                      </label>
                      <span id="reminderCountLabel" data-i18n="reminder.modal.label"><?= htmlspecialchars($translator->translate('reminder.modal.label'), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>

                  <p class="reminderModalHint" data-i18n="reminder.modal.hint"><?= htmlspecialchars($translator->translate('reminder.modal.hint'), ENT_QUOTES, 'UTF-8') ?></p>

                  <div class="reminderList" id="reminderList"></div>

                  <p class="reminderModalMessage" id="reminderModalMessage" role="alert" aria-live="polite"></p>

                  <div class="reminderModalActions">
                      <button class="cancelReminderButton" id="cancelReminderButton" type="button" data-i18n="common.cancel"><?= htmlspecialchars($translator->translate('common.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                      <button class="applyReminderButton" id="applyReminderButton" type="button" data-i18n="common.apply"><?= htmlspecialchars($translator->translate('common.apply'), ENT_QUOTES, 'UTF-8') ?></button>
                  </div>
              </section>
          </div>
