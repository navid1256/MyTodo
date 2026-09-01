<div class="repeatModalBackdrop" id="repeatModal" hidden>
    <section
        class="repeatModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="repeatModalTitle">
        <button class="repeatModalClose" id="closeRepeatModal" type="button" aria-label="Close repeat modal">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="repeatModalScroll">
            <h2 id="repeatModalTitle">Repeat</h2>
            <p class="repeatModalHint">Choose how often this task should repeat.</p>

            <fieldset class="repeatFrequencyOptions">
                <legend class="srOnly">Repeat frequency</legend>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="daily" checked>
                    <span>Daily</span>
                </label>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="weekly">
                    <span>Weekly</span>
                </label>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="monthly">
                    <span>Monthly</span>
                </label>
                <label>
                    <input type="radio" name="task_repeat_frequency" value="custom">
                    <span>Custom</span>
                </label>
            </fieldset>

            <section class="customRepeatSection" id="customRepeatSection" aria-labelledby="customRepeatTitle" hidden>
                <h3 id="customRepeatTitle">Repeat Every</h3>
                <div class="repeatEveryFields">
                    <label class="srOnly" for="repeatInterval">Repeat interval</label>
                    <input id="repeatInterval" type="number" min="1" max="999" step="1" inputmode="numeric" value="1">
                    <label class="srOnly" for="repeatUnit">Repeat interval unit</label>
                    <select id="repeatUnit">
                        <option value="day">Day</option>
                        <option value="week">Weeks</option>
                        <option value="month">Month</option>
                    </select>
                </div>

            </section>

            <fieldset class="repeatOnWeek" id="repeatOnWeek" hidden>
                <legend>Repeat on</legend>
                <div class="repeatWeekDays">
                    <label><input type="checkbox" value="0"><span>Sun</span></label>
                    <label><input type="checkbox" value="1"><span>Mon</span></label>
                    <label><input type="checkbox" value="2"><span>Tue</span></label>
                    <label><input type="checkbox" value="3"><span>Wed</span></label>
                    <label><input type="checkbox" value="4"><span>Thu</span></label>
                    <label><input type="checkbox" value="5"><span>Fri</span></label>
                    <label><input type="checkbox" value="6"><span>Sat</span></label>
                </div>
            </fieldset>

            <fieldset class="repeatOnMonth" id="repeatOnMonth" hidden>
                <legend>Repeat on day</legend>
                <div class="repeatMonthDays">
                    <?php for ($monthDay = 1; $monthDay <= 31; $monthDay++): ?>
                        <label>
                            <input type="radio" name="repeat_month_day" value="<?= $monthDay ?>">
                            <span><?= $monthDay ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <label class="repeatMonthLastDay">
                    <input type="radio" name="repeat_month_day" value="last">
                    <span>Last day of month</span>
                </label>
                <p class="repeatMonthHint" id="repeatMonthHint" aria-live="polite" hidden></p>
            </fieldset>

            <section class="repeatEndsSection" aria-labelledby="repeatEndsTitle">
                <h3 id="repeatEndsTitle">Repeat ends at</h3>
                <fieldset class="repeatEndOptions">
                    <legend class="srOnly">Repeat ending</legend>
                    <label>
                        <input type="radio" name="task_repeat_end" value="endlessly" checked>
                        <span>Endlessly</span>
                    </label>
                    <label>
                        <input type="radio" name="task_repeat_end" value="date">
                        <span>A date</span>
                    </label>
                    <label>
                        <input type="radio" name="task_repeat_end" value="count">
                        <span>Repeat Counts</span>
                    </label>
                </fieldset>

                <div class="repeatEndDateSection <?= $calendarCssClass ?>" id="repeatEndDateSection" hidden>
                    <div class="repeatCalendarHeader">
                        <button id="previousRepeatMonth" type="button" aria-label="Previous month">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <h4 id="repeatCalendarMonth">Month Year</h4>
                        <button id="nextRepeatMonth" type="button" aria-label="Next month">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="repeatCalendarWeekdays" aria-hidden="true">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
                        <span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>
                    <div class="repeatCalendarDays" id="repeatCalendarDays" role="grid" aria-labelledby="repeatCalendarMonth"></div>
                    <p class="repeatEndDateSummary" id="repeatEndDateSummary">Select an end date</p>
                </div>

                <label class="repeatCountField" id="repeatCountField" for="repeatCount" hidden>
                    <span>Number of repeats</span>
                    <input id="repeatCount" type="number" min="1" max="9999" step="1" inputmode="numeric" value="10">
                </label>
            </section>

            <p class="repeatModalMessage" id="repeatModalMessage" role="alert" aria-live="polite"></p>

            <div class="repeatModalActions">
                <button class="cancelRepeatButton" id="cancelRepeatButton" type="button">Cancel</button>
                <button class="applyRepeatButton" id="applyRepeatButton" type="button">Apply</button>
            </div>
        </div>
    </section>
</div>
