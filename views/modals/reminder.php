          <div class="reminderModalBackdrop" id="reminderModal" hidden>
              <section
                  class="reminderModal"
                  role="dialog"
                  aria-modal="true"
                  aria-labelledby="reminderModalTitle">
                  <button class="reminderModalClose" id="closeReminderModal" type="button" aria-label="Close reminder modal">
                      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                  </button>

                  <div class="reminderModalHeader">
                      <h2 id="reminderModalTitle">Set</h2>
                      <label class="reminderCountField" for="reminderCount">
                          <span class="srOnly">Number of reminders</span>
                          <select id="reminderCount">
                              <?php for ($reminderCount = 1; $reminderCount <= 5; $reminderCount++): ?>
                                  <option value="<?= $reminderCount ?>"><?= $reminderCount ?></option>
                              <?php endfor; ?>
                          </select>
                      </label>
                      <span id="reminderCountLabel">Reminder</span>
                  </div>

                  <p class="reminderModalHint">Choose when you want to be notified before the task due time.</p>

                  <div class="reminderList" id="reminderList"></div>

                  <p class="reminderModalMessage" id="reminderModalMessage" role="alert" aria-live="polite"></p>

                  <div class="reminderModalActions">
                      <button class="cancelReminderButton" id="cancelReminderButton" type="button">Cancel</button>
                      <button class="applyReminderButton" id="applyReminderButton" type="button">Apply</button>
                  </div>
              </section>
          </div>