<div class="accountSettingsPage">
    <header class="accountSettingsHeader">
        <i class="accountSettingsIcon fa-solid fa-user-gear" aria-hidden="true"></i>
        <h1>Account Settings</h1>
    </header>

    <div class="accountSettingsFields" aria-label="Account settings preview">
        <div class="accountSettingsField">
            <label for="dateTimeSetting">Date &amp; Time :</label>
            <select id="dateTimeSetting" data-account-setting="date-system">
                <option value="gregorian" selected>Gregorian</option>
                <option value="jalali">Jalali</option>
            </select>
        </div>

        <div class="accountSettingsField">
            <label for="languageSetting">Language :</label>
            <select id="languageSetting" data-account-setting="language">
                <option value="default" selected>Default</option>
                <option value="english">English</option>
                <option value="persian">Persian</option>
            </select>
        </div>
    </div>
</div>
