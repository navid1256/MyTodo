# طراحی مدیریت کارهای تکرارشونده

تاریخ: 2026-09-07

## هدف

سیستم Repeat موجود باید از حالت «فقط ایجاد و تولید خودکار Task» به یک سیستم قابل مدیریت تبدیل شود. کاربر باید بتواند Repeat Ruleهای خودش را در صفحه مستقل `Recurring Tasks` مشاهده کند، آن‌ها را Pause، Resume یا Cancel کند و Taskهای تکرارشونده را با دو محدوده `Only this task` و `This and future tasks` ویرایش کند.

این قابلیت نباید Taskهای گذشته یا تکمیل‌شده را تغییر دهد و هیچ عملیات یک کاربر نباید روی Rule یا Task کاربر دیگری اثر بگذارد.

## تصمیم‌های تأییدشده

### صفحه مستقل

- یک گزینه `Recurring Tasks` دقیقاً بعد از `Manage Tasks` به Navigation اضافه می‌شود.
- صفحه از طرح فهرست فشرده انتخاب‌شده استفاده می‌کند.
- هر Repeat Rule یک ردیف مستقل دارد؛ نمونه‌های تولیدشده به‌صورت ردیف‌های جدا در این صفحه نمایش داده نمی‌شوند.
- دکمه‌های مشترک `Completed` و `Add New Task` از `views/components/task-toolbar.php` حفظ می‌شوند.
- محتوای صفحه داخل `.content` رندر می‌شود.
- همان View برای English/LTR و Persian/RTL استفاده می‌شود و متن‌ها از `Translator` می‌آیند.

### Pause

- فقط Rule انتخاب‌شده از همان کاربر از `active` به `paused` می‌رود.
- `next_occurrence_at` در حالت Pause برابر `NULL` می‌شود تا Cron آن را انتخاب نکند.
- Taskهای آینده و انجام‌نشده همان Rule حذف می‌شوند.
- Reminderهای آن Taskها از طریق Foreign Key Cascade حذف می‌شوند.
- Taskهای گذشته و Taskهای تکمیل‌شده حفظ می‌شوند.
- Notificationهای قبلاً ارسال‌شده قابل بازگرداندن نیستند و در تاریخچه باقی می‌مانند.

### Resume

- فقط Rule دارای وضعیت `paused` قابل Resume است.
- برنامه تکرار از `start_at` اصلی و Rule اصلی محاسبه می‌شود؛ تاریخ Resume به Anchor جدید تبدیل نمی‌شود.
- اولین رخداد معتبر برابر اولین تاریخ منطبق با Rule در زمان Resume یا بعد از آن است.
- اگر زمان رخداد همان روز گذشته باشد، رخداد معتبر بعدی انتخاب می‌شود.
- رخدادهای ازدست‌رفته در مدت Pause ساخته نمی‌شوند.
- اگر End Date گذشته یا تعداد مجاز تکرارها قبلاً تکمیل شده باشد، Rule به `completed` می‌رود و Resume نمی‌شود.
- پس از فعال‌شدن، پنجره ۳۰روزه آینده همان لحظه تولید می‌شود.

### Cancel

- Rule از `active` یا `paused` به `cancelled` می‌رود.
- `next_occurrence_at` برابر `NULL` می‌شود.
- Taskهای آینده و انجام‌نشده و Reminderهای وابسته حذف می‌شوند.
- Taskهای گذشته و تکمیل‌شده حفظ می‌شوند.
- Cancel دائمی است و Rule لغوشده Resume نمی‌شود.

### Edit یک Task تکرارشونده

دو محدوده نمایش داده می‌شود:

1. `Only this task`
2. `This and future tasks`

گزینه `All tasks in this series` وجود ندارد، چون Taskهای گذشته و تکمیل‌شده نباید بازنویسی شوند و این گزینه با `This and future tasks` هم‌پوشانی گیج‌کننده ایجاد می‌کند.

#### Only this task

- فقط عنوان، تاریخ، ساعت و Reminderهای همان Task قابل تغییر هستند.
- تنظیم Repeat در این حالت نمایش داده نمی‌شود.
- `repeat_rule_id` و `repeat_occurrence_number` حفظ می‌شوند.
- Rule اصلی و سایر Taskها تغییر نمی‌کنند.
- این Task همچنان عضو Series است؛ بنابراین Pause یا Cancel آینده، در صورت آینده و انجام‌نشده بودن، آن را نیز متوقف می‌کند.

#### This and future tasks

- نقطه تقسیم، `repeat_occurrence_number` Task انتخاب‌شده است؛ نه تاریخ فعلی آن. این انتخاب در برابر جابه‌جایی قبلی یک رخداد مقاوم است.
- Taskهای قبل از نقطه تقسیم با Rule قبلی باقی می‌مانند.
- Rule قبلی روی `completed` قرار می‌گیرد و `next_occurrence_at` آن `NULL` می‌شود.
- Taskهای انجام‌نشده از نقطه تقسیم به بعد، به‌جز Task انتخاب‌شده، حذف می‌شوند.
- Task انتخاب‌شده با عنوان، تاریخ، ساعت و Reminderهای جدید به رخداد شماره صفر Rule جدید تبدیل می‌شود.
- Rule جدید از تاریخ و ساعت ویرایش‌شده Task انتخاب‌شده آغاز می‌شود.
- Taskها و Reminderهای پنجره ۳۰روزه آینده براساس Rule جدید تولید می‌شوند.
- Taskهای گذشته و تکمیل‌شده هیچ‌گاه حذف یا بازنویسی نمی‌شوند؛ حتی اگر شماره رخدادشان در محدوده بعدی باشد.

### Edit از صفحه Recurring Tasks

- Edit یک Rule فعال از صفحه `Recurring Tasks` روی اولین Task آینده و انجام‌نشده آن اعمال می‌شود و همان رفتار `This and future tasks` را دارد.
- اگر Rule فعال Task آینده‌ای نداشته باشد، `next_occurrence_at` به‌عنوان نقطه شروع ویرایش استفاده می‌شود.
- Rule متوقف‌شده Task آینده و `next_occurrence_at` ندارد؛ تنظیمات آن در همان Rule به‌روزرسانی می‌شود، وضعیت `paused` باقی می‌ماند و تاریخ رخداد بعدی فقط هنگام Resume از Anchor و Rule ویرایش‌شده محاسبه می‌شود.
- Ruleهای `cancelled` و `completed` قابل Edit نیستند.

## مدل وضعیت

انتقال‌های مجاز:

```text
active ──→ paused
active ──→ cancelled
active ──→ completed
paused ──→ active
paused ──→ cancelled
```

`cancelled` و `completed` وضعیت‌های نهایی هستند.

درخواست تکراری برای انتقالی که قبلاً انجام شده است پاسخ موفق Idempotent می‌گیرد، اما انتقال نامعتبر مانند Resume کردن Rule لغوشده با پاسخ `422` رد می‌شود.

## معماری Backend

### Controller

فایل جدید `App/Controllers/RepeatController.php` مسئول این عملیات است:

- نمایش صفحه Recurring Tasks
- خواندن و اعتبارسنجی شناسه‌ها، Scope و Payload
- بررسی Authentication و CSRF
- تبدیل Exceptionهای دامنه به پاسخ JSON مناسب

Controller منطق SQL یا محاسبه Schedule نخواهد داشت.

### Service

منطق چرخه عمر به `RepeatService` اضافه می‌شود:

- `getRulesForUser()`
- `pauseRule()`
- `resumeRule()`
- `cancelRule()`
- `updateSingleOccurrence()`
- `updateThisAndFuture()`

متدهای Public در بالای فایل و توابع جزئی Private در پایین باقی می‌مانند. توضیح و تست توابع از اجزای جزئی به جریان کلی انجام می‌شود.

`RepeatScheduleCalculator` فقط محاسبه تاریخ رخداد بعدی را انجام می‌دهد و مسئول تغییر وضعیت یا دیتابیس نمی‌شود. `RepeatOccurrencePlanner` فقط فهرست رخدادهای قابل تولید را می‌سازد.

### Repository

`RepeatRuleRepository` عملیات زیر را با شرط `user_id` انجام می‌دهد:

- فهرست Ruleهای یک User با Filter وضعیت
- دریافت Rule متعلق به User با `FOR UPDATE`
- Pause، Resume، Cancel و Complete کردن Rule
- به‌روزرسانی تنظیمات Rule و Reminder Templateها
- دریافت اولین Task آینده و انجام‌نشده

`TaskRepository` عملیات زیر را اضافه می‌کند:

- دریافت Task تکرارشونده متعلق به User با `FOR UPDATE`
- حذف Taskهای آینده و انجام‌نشده یک Rule
- حذف Taskهای انجام‌نشده از یک شماره رخداد به بعد
- شمارش Taskهای تکراری باقی‌مانده
- دریافت بیشترین `repeat_occurrence_number`
- انتقال Task انتخاب‌شده به Rule جدید

همه Queryهای تغییر داده باید مالکیت User را در خود SQL بررسی کنند؛ اتکا به بررسی Controller کافی نیست.

### Exceptionها

- `RepeatRuleNotFoundException` برای Rule یا Task تکرارشونده‌ای که وجود ندارد یا متعلق به User نیست.
- `RepeatRuleStateException` برای انتقال وضعیت نامعتبر.
- `RepeatValidationException` برای Payload یا Rule نامعتبر موجود حفظ می‌شود.

پیام خام PDO به Client نمایش داده نمی‌شود و Transaction در تمام خطاها Rollback می‌شود.

## شمارنده و شماره رخداد

حذف رخدادهای آینده نباید باعث برخورد Unique Index زیر شود:

```text
(repeat_rule_id, repeat_occurrence_number)
```

بنابراین دو مفهوم جدا استفاده می‌شود:

- تعداد Taskهای تکراری موجود برای رعایت `Repeat Counts`
- بیشترین `repeat_occurrence_number` برای تعیین شماره رخداد بعدی

هنگام تولید پس از Pause یا Edit، تعداد واقعی Taskهای باقی‌مانده برای محدودیت Count محاسبه می‌شود و شماره جدید از بیشترین شماره موجود به‌علاوه یک آغاز می‌شود. این کار از تولید شماره تکراری یا پایان زودهنگام Rule جلوگیری می‌کند و به Migration جدید نیاز ندارد.

## Transactionها و هم‌زمانی

Pause، Resume، Cancel و هر دو نوع Edit داخل Transaction اجرا می‌شوند:

1. Rule و Task هدف با شرط مالکیت و `FOR UPDATE` قفل می‌شوند.
2. وضعیت دوباره داخل Transaction بررسی می‌شود.
3. Taskها، Reminderها، Rule و Templateها تغییر می‌کنند.
4. در موفقیت Commit و در هر Exception، Rollback انجام می‌شود.

قفل Rule مانع اجرای هم‌زمان Cron و عملیات کاربر روی همان Series می‌شود. Unique Index موجود نیز آخرین لایه جلوگیری از رخداد تکراری است.

## API

مسیرهای جدید فقط با `POST`، Authentication و CSRF:

```text
POST /api/repeat-rules/pause
POST /api/repeat-rules/resume
POST /api/repeat-rules/cancel
POST /api/repeat-rules/update
POST /api/repeat-tasks/update
```

`/api/repeat-tasks/update` مقدار `scope` را فقط از بین `single` و `future` می‌پذیرد.

پاسخ موفق JSON شامل وضعیت جدید، Rule به‌روزشده و شناسه Taskهای حذف‌شده یا ساخته‌شده است تا UI بدون Reload به‌روزرسانی شود. پاسخ‌های اصلی:

- `200`: عملیات موفق یا Idempotent
- `401`: کاربر وارد نشده
- `403`: CSRF نامعتبر
- `404`: Rule یا Task متعلق به User پیدا نشد
- `422`: Payload یا انتقال وضعیت نامعتبر
- `500`: خطای پیش‌بینی‌نشده با پیام عمومی

## UI و Frontend

### Navigation و View

- مسیر `GET /recurring-tasks` به Dashboard اضافه می‌شود.
- کلید `recurring-tasks` به Navigation AJAX افزوده می‌شود.
- فایل جدید `views/pages/recurring-tasks.php` فهرست فشرده را رندر می‌کند.
- فایل جدید `public/assets/css/pages/recurring-tasks.css` فقط استایل مخصوص همین صفحه را دارد.
- `views/components/task-toolbar.php` بدون کپی‌کردن Markup استفاده می‌شود.

### محتوای هر Rule

- عنوان
- خلاصه قابل ترجمه Rule
- رخداد بعدی
- شرایط پایان
- تعداد Taskهای تولیدشده
- Status متنی و رنگی
- اکشن‌های Edit، Pause یا Resume و Cancel

رنگ تنها نشانه Status نیست و متن Status همیشه وجود دارد.

### Filterها

Filterهای صفحه:

```text
All | Active | Paused | Completed | Cancelled
```

Filter در Query String نگه‌داری می‌شود و با Navigation AJAX بارگذاری می‌شود. مقدار نامعتبر به `all` برمی‌گردد.

### JavaScript

- `public/assets/js/services/repeat-service.js` فقط Requestهای API را انجام می‌دهد.
- `public/assets/js/modules/recurring-tasks.js` وضعیت UI، Confirm، غیرفعال‌کردن موقت دکمه و به‌روزرسانی DOM را مدیریت می‌کند.
- هیچ عملیات موفقی باعث `window.location.reload()` نمی‌شود.
- هنگام بارگذاری Partial View، Module از مسیر مشترک `app.js` دوباره Initialize می‌شود.

### ترجمه و جهت

- تمام Labelها، Statusها، پیام‌های Confirm، Success و Error به `resources/lang/en.php` و `resources/lang/fa.php` اضافه می‌شوند.
- فقط یک View وجود دارد؛ فایل فارسی جدا ساخته نمی‌شود.
- CSS از `margin-inline-*`، `padding-inline-*`، `border-inline-*` و `inset-inline-*` استفاده می‌کند.
- English با `dir=ltr` و Sidebar چپ، Persian با `dir=rtl` و Sidebar راست کار می‌کند.
- در عرض‌های Mobile اکشن‌های ردیف داخل منوی قابل دسترس قرار می‌گیرند یا Wrap می‌شوند؛ اطلاعات اصلی پنهان نمی‌شود.
- تمام Buttonها از عنصر `<button>` استفاده می‌کنند و Label قابل فهم، Focus قابل مشاهده و وضعیت `aria-live` وجود دارد.

### Font Awesome

برای Navigation از آیکون `fa-arrows-rotate` استفاده می‌شود. قبل از استفاده، وجود Codepoint آن در Font subset فعلی بررسی می‌شود؛ در صورت نبودن، subset پروژه با همان فرایند موجود بازسازی خواهد شد.

## تست و معیار پذیرش

### Backend

- User نمی‌تواند Rule یا Task کاربر دیگر را مشاهده یا تغییر دهد.
- Pause فقط Taskهای آینده و انجام‌نشده همان Rule را حذف می‌کند.
- Resume رخدادهای دوران Pause را نمی‌سازد و Anchor اصلی را حفظ می‌کند.
- Resume یک Weekly Monday در اولین Monday معتبر شروع می‌شود.
- Resume برای Custom interval فاصله را از `start_at` اصلی حفظ می‌کند.
- Cancel نهایی است و Resume آن با `422` رد می‌شود.
- Only this task فقط همان Task و Reminderهایش را تغییر می‌دهد.
- This and future، Rule را تقسیم می‌کند و Taskهای گذشته و تکمیل‌شده را حفظ می‌کند.
- Count Rule بعد از حذف Taskهای آینده تعداد درست باقی‌مانده را تولید می‌کند.
- اجرای هم‌زمان Cron و Pause/Edit رخداد تکراری نمی‌سازد.
- تمام Transactionها در خطا Rollback می‌شوند.

### Frontend و Browser

- Navigation عادی و AJAX هر دو صفحه را باز می‌کنند.
- Toolbar شامل هر دو دکمه Completed و Add New Task است.
- Filter و اکشن‌ها بدون Reload کار می‌کنند.
- Loading، Empty، Success و Error State نمایش داده می‌شوند.
- Keyboard Navigation، Focus و Confirm Dialog بررسی می‌شوند.
- English/LTR و Persian/RTL در 320، 768، 1024 و 1440 پیکسل بررسی می‌شوند.
- Console و Network خطای پیش‌بینی‌نشده ندارند.

## خارج از محدوده این مرحله

- ارسال واقعی Web Push
- Pagination برای تعداد بسیار زیاد Ruleها
- گزارش آماری Series
- بازگرداندن Rule لغوشده
- تغییر Taskهای گذشته یا تکمیل‌شده
- گزینه `All tasks in this series`
