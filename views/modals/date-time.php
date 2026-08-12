<div class="dateTimeModalBackdrop" id="dateTimeModal" hidden>
    <section
        class="dateTimeModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="dateTimeModalTitle">
        <button class="dateTimeModalClose" id="closeDateTimeModal" type="button" aria-label="Close date and time modal">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="calendarHeader">
            <button class="calendarNavButton" id="previousCalendarMonth" type="button" aria-label="Previous month">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
            <h2 id="dateTimeModalTitle">July 2026</h2>
            <button class="calendarNavButton" id="nextCalendarMonth" type="button" aria-label="Next month">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="calendarWeekdays" aria-hidden="true">
            <span>Sun</span>
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
        </div>
        <div class="calendarDays" id="calendarDays" role="grid" aria-labelledby="dateTimeModalTitle"></div>

        <fieldset class="quickDateOptions">
            <legend class="srOnly">Quick date selection</legend>
            <label>
                <input type="radio" name="quick_task_date" value="today" checked>
                <span>Today</span>
            </label>
            <label>
                <input type="radio" name="quick_task_date" value="tomorrow">
                <span>Tomorrow</span>
            </label>
            <label>
                <input type="radio" name="quick_task_date" value="no-date">
                <span>No Date</span>
            </label>
        </fieldset>

        <section class="setTimeSection" id="setTimeSection" aria-labelledby="setTimeTitle">
            <div class="setTimeHeader">
                <h3 id="setTimeTitle">Set Time</h3>
                <div class="setTimeToggle" role="radiogroup" aria-label="Enable task time">
                    <label>
                        <input id="setTimeYes" type="radio" name="set_task_time" value="yes" checked>
                        <span>Yes</span>
                    </label>
                    <label>
                        <input id="setTimeNo" type="radio" name="set_task_time" value="no">
                        <span>No</span>
                    </label>
                </div>
            </div>

            <div class="timePicker" id="timePicker">
                <label>
                    <span class="timeFieldLabel">Hour</span>
                    <input id="taskTimeHour" type="number" min="1" max="12" step="1" value="12" inputmode="numeric" aria-label="Hour" required>
                </label>
                <span class="timeSeparator" aria-hidden="true">:</span>
                <label>
                    <span class="timeFieldLabel">Minute</span>
                    <input id="taskTimeMinute" type="number" min="0" max="59" step="1" value="0" inputmode="numeric" aria-label="Minute" required>
                </label>
                <label>
                    <span class="timeFieldLabel">Period</span>
                    <select id="taskTimePeriod" aria-label="AM or PM">
                        <option value="AM">AM</option>
                        <option value="PM">PM</option>
                    </select>
                </label>
            </div>
        </section>

        <p class="dateTimeModalMessage" id="dateTimeModalMessage" role="alert" aria-live="polite"></p>

        <div class="dateTimeModalActions">
            <button class="cancelDateTimeButton" id="cancelDateTimeButton" type="button">Cancel</button>
            <button class="applyDateTimeButton" id="applyDateTimeButton" type="button">Apply</button>
        </div>
    </section>
</div>