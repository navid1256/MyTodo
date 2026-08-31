<div class="accountSettingsPage">
    <header class="accountSettingsHeader dashboardSectionHeader">
        <a class="dashboardBackButton" href="/" data-dashboard-back aria-label="Back to previous page">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>
        <i class="accountSettingsIcon fa-solid fa-user-gear" aria-hidden="true"></i>
        <h1>Account Settings</h1>
    </header>

    <div class="accountSettingsFields" aria-label="Account settings preview">
        <div class="accountSettingsField">
            <label for="dateTimeSetting">Calendar System :</label>
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
