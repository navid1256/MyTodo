# Web Push — ثبت مرورگر و Sender مستقل

## وضعیت فعلی

- کلیدهای VAPID و ثبت/حذف Subscription پیاده‌سازی شده‌اند.
- کنترل فعال/غیرفعال‌سازی در Account Settings قرار دارد و مستقل از Save است.
- در آزمون مرورگر 2026-10-18، مجوز Allow، ثبت Subscription و حفظ Enabled پس از Reload تأیید شدند؛ یک Push مستقل نیز با پاسخ 201 به Service Worker رسید و کاربر نمایش آن را تأیید کرد. پس از اصلاح Focus، کاربر بازشدن Sent Notifications بدون `NotFoundError` را در آزمون اعلان محلی Chrome معمولی تأیید کرد. Disable، خالی‌شدن Subscription مرورگر و فعال‌سازی مجدد با حفظ وضعیت پس از Refresh نیز با گزارش کاربر تأیید شدند. عدم تأثیر روی مرورگرهای دیگر و مسیر کامل Reminder/Cron هنوز بررسی نشده‌اند.
- Service Worker فعلاً فقط برای Push است؛ Cache آفلاین و نصب PWA هنوز پیاده‌سازی نشده‌اند.
- Sender مستقل، لایه رزرو/ثبت نتیجه و Worker CLI اضافه شده‌اند؛ Worker فقط با گزینهٔ صریح `--send` ارسال واقعی می‌کند و هنوز Cron یا Route عمومی ارسال ندارد.
- سیاست Retry و پردازش چند Worker آماده و با ارسال ساختگی بررسی شده‌اند؛ ارسال Reminder واقعی از مسیر Worker، Cron، Badge خوانده‌نشده و Pagination هنوز مراحل بعدی هستند.
- `sent` در آینده به معنی پذیرش توسط سرویس Push خواهد بود، نه تضمین نمایش یا خواندن توسط کاربر.

## کلیدهای VAPID

در ریشه MyTodo، فقط یک‌بار اجرا شود:

```powershell
# برای XAMPP در ویندوز؛ مسیر را با نصب خود تطبیق بدهید.
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
php process/generate-vapid-keys.php
```

خروجی خصوصی در `storage/private/vapid.json` ذخیره می‌شود. این فایل خارج از `public/` است و در Git نادیده گرفته می‌شود. دستور فایل موجود را بازنویسی نمی‌کند. کلیدها را بدون برنامه مهاجرت Subscriptionها تغییر ندهید.

در `.env` مقدار واقعی تماس را تنظیم کنید:

```dotenv
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:your-real-email@example.com
# اختیاری: فایل کلید در مسیری خصوصی خارج از پروژه
# VAPID_KEYS_FILE=I:/private/mytodo-vapid.json
```

وقتی Public و Private هر دو خالی باشند، فایل خصوصی خوانده می‌شود. مقدارهای مستقیم Environment بر فایل کلید اولویت دارند. کلید خصوصی هرگز به مرورگر ارسال نمی‌شود. در Production فایل را با دسترسی محدود به حساب اجرای PHP نگهداری و Backup امن تهیه کنید؛ دسترسی NTFS ویندوز باید جداگانه بررسی شود.

## دیتابیس

Migration دیتابیس موجود:

`Database/migrations/20260927_create_push_subscriptions.sql`

این Migration در دیتابیس Development فعلی اعمال شده است؛ روی همان دیتابیس دوباره اجرا نشود. برای نصب تازه، Schema به‌روز `Database/mytodo.sql` همین جدول را دارد و نیازی به اجرای مجدد این Migration نیست.

Rollback اختیاری در `Database/migrations/rollbacks/20260927_create_push_subscriptions.sql` همه Subscriptionها را پاک می‌کند؛ فقط برای بازگردانی عمدی اجرا شود.

Migration مرحلهٔ رزرو: `Database/migrations/20260928_create_task_reminder_deliveries.sql` در دیتابیس Development فعلی نیز اعمال شده است؛ روی همان دیتابیس دوباره اجرا نشود. برای نصب تازه، `Database/mytodo.sql` این جدول را دارد. این Migration فقط جدول جدید می‌سازد و داده‌های موجود User، Task یا Reminder را تغییر نمی‌دهد. فایل Rollback متناظر جدول و تاریخچهٔ ارسال‌های آن را حذف می‌کند؛ فقط برای بازگردانی عمدی اجرا شود. پیش از استقرار کد جدید روی هر دیتابیس قدیمی دیگر، Migration را اعمال کنید.

## API

همه endpointها فقط `POST`، Session معتبر و `csrf_token` می‌پذیرند؛ فرمت ورودی FormData است.

| مسیر | ورودی | رفتار |
| --- | --- | --- |
| `/api/push/status` | `endpoint` اختیاری | `configured`، `publicKey` و `subscribed` برای کاربر فعلی؛ بدون کلید خصوصی |
| `/api/push/subscribe` | `subscription` به‌صورت JSON خروجی `PushSubscription.toJSON()` | ثبت یا به‌روزرسانی اشتراک متعلق به همین کاربر |
| `/api/push/unsubscribe` | `endpoint` | حذف فقط همان مرورگر و همان کاربر؛ تکرار درخواست بی‌خطر است |

خطاها مطابق API فعلی پروژه `{success:false, code, message}` هستند: `401` احراز هویت، `403` CSRF، `422` اعتبارسنجی، `409` مالکیت متعارض و `503` Web Push تنظیم‌نشده.

Endpoint با Hash یکتا ثبت می‌شود؛ User-Agent محدودشده نقش توضیح Device/Browser را دارد، نه شناسه قابل‌اعتماد دستگاه. در هر حساب حداکثر ۱۰ مرورگر ثبت می‌شود. مالکیت Subscription خودکار به حساب دیگر منتقل نمی‌شود.

برای پیشگیری از SSRF، فقط HTTPS روی پورت استاندارد و سرویس‌های شناخته‌شده Chrome، Firefox، Safari و Windows پذیرفته می‌شود. افزودن سرویس جدید نیازمند بازبینی `PushSubscriptionValidator` است.

## Sender مستقل — مرحله ۱

قرارداد در `App/Notifications/NotificationSender.php` و پیاده‌سازی در `WebPushNotificationSender.php` است. متد `send` فقط برای یک Subscription ارسال می‌کند؛ مسئول خواندن دیتابیس، مالکیت User، زمان‌بندی Cron، ثبت `sent` یا Retry نیست. Worker از Repository فقط Subscription متعلق به صاحب همان Reminder را می‌گیرد؛ هیچ Route عمومی برای ارسال مستقیم اضافه نشده است.

ورودی Subscription همان فیلدهای دیتابیس `endpoint`، `p256dh` و `auth` است. Payload فقط شامل `title`، `body`، `tag` و `url` است؛ فیلد اضافی ارسال نمی‌شود. متن UTF-8 و حجم JSON بررسی می‌شوند. لینک کلیک باید مسیر داخلی `/notifications`، `/manage-tasks` یا `/` باشد؛ Query مجاز است. این قرارداد با Service Worker فعلی یکسان است. Worker عنوان Task، Due Time در منطقه زمانی/تقویم ذخیره‌شده و Tag یکتای Reminder را می‌سازد؛ کلیک فعلاً صفحهٔ Manage Tasks را باز می‌کند، نه خود Task.

نتیجه از نوع `NotificationSendResult` است و فقط Outcome و HTTP Status را نگه می‌دارد، نه Endpoint، کلید، Payload یا خطای خام سرویس:

| Outcome | معنی |
| --- | --- |
| `accepted` | پاسخ 2xx؛ سرویس Push پیام را پذیرفته، نه لزوماً مرورگر آن را نمایش داده است |
| `expired` | پاسخ 404 یا 410؛ Subscription قابل استفاده نیست |
| `retryable` | پاسخ 408، 429 یا 5xx؛ سیاست Retry بر عهده Worker آینده است |
| `failed` | سایر پاسخ‌ها، از جمله Redirect، خطای مجوز یا Payload |
| `unknown` | پاسخ HTTP نداریم؛ پذیرش پیام معلوم نیست و Retry ممکن است تکراری شود |

پیش از ارسال، Subscription دوباره اعتبارسنجی می‌شود؛ همه IPهای DNS باید عمومی باشند و IP بررسی‌شده برای همان درخواست Pin می‌شود. `NoPrivateNetworkHttpClient` نیز IP اتصال را بررسی می‌کند. Redirect و Proxy غیرفعال‌اند؛ TLS Verification فعال است. Idle Timeout برابر ۱۰ ثانیه و حداکثر مدت درخواست HTTP برابر ۱۵ ثانیه است؛ این سقف شامل DNS اولیه سیستم نیست.

Retention فعلی سرویس Push (`TTL`) برابر ۳۶۰۰ ثانیه است تا پیام تحویل‌نشده هفته‌ها نگهداری نشود. این مقدار **فاصله اجرای Cron نیست**؛ Cron همچنان هر یک دقیقه اجرا خواهد شد. سیاست نهایی عمر Reminder در مرحله Worker قابل بازبینی است.

پکیج `nyholm/psr7` Factory استاندارد PSR-17 را فراهم می‌کند؛ Symfony HTTP Client نصب‌شده از طریق Adapter استاندارد PSR-18 استفاده می‌شود. نسخه پکیج‌های قبلی تغییر نکرده است. این انتخاب و روش Mock مطابق [راهنمای رسمی Nyholm](https://github.com/Nyholm/psr7) و [راهنمای HTTP Client در Symfony](https://symfony.com/doc/7.4/http_client.html) است.

## رزرو و ثبت نتیجه Reminder — مرحله ۲

`ReminderDispatchService` با `ReminderDeliveryRepository` کار می‌کند. هر Reminder رسیده برای هر Subscription متعلق به صاحب Task فقط یک رکورد `task_reminder_deliveries` دارد. شمارنده تلاش، زمان تلاش، Claim Token و نتیجه هر مرورگر جداست. کار تکمیل‌شده، Reminder لغوشده و Series متوقف/لغوشده رزرو نمی‌شود؛ Taskهای باقی‌مانده از Series تکمیل‌شده همچنان می‌توانند اعلان داشته باشند.

ابتدا مرورگرهای واجد شرایط پیدا می‌شوند و سپس هر رکورد به‌صورت اتمی برای Worker رزرو می‌شود. Claim Token مانع ثبت نتیجه توسط Worker قدیمی پس از پایان رزرو است. یک پذیرش از سرویس Push، وضعیت Reminder را `sent` می‌کند؛ بقیه مرورگرها فقط نتیجه خودشان را می‌گیرند و مرورگر موفق دوباره ارسال نمی‌شود. این `sent` تضمین نمایش در دستگاه نیست.

پاسخ‌های 408/429/5xx حداکثر **سه تلاش کلی** برای همان مرورگر دارند: پس از تلاش اول ۶۰ ثانیه، پس از تلاش دوم ۳۰۰ ثانیه تأخیر. 404/410 موجب حذف Subscription منقضی می‌شود، مگر کلیدهای آن در فاصله ارسال به‌روزرسانی شده باشند. اگر پاسخ HTTP وجود نداشته باشد، یا رزروی بیش از پنج دقیقه بی‌نتیجه بماند، وضعیت `unknown` است و خودکار Retry نمی‌شود؛ نتیجه نامعلوم ممکن است قبلاً به سرویس Push رسیده باشد. Reminder بدون مرورگر ثبت‌شده `failed` می‌شود. ویرایش Reminder ناموفق، سوابق ارسال زمان قبلی را پاک می‌کند؛ ویرایش در زمان ارسال فعال رد می‌شود.

Worker اکنون Sender را به این Service وصل می‌کند، اما Cron هنوز فعال نشده است. Claim و قفل دیتابیس برای جلوگیری از رزرو دوبارهٔ رکورد در حال پردازش هستند؛ آزمون چهار پردازش روی جدول‌های مشترک آزمایشی پاس شده است، اما تضمین Exactly-once در قطع ارتباط پس از پذیرش سرویس Push ممکن نیست. Worker پیش از هر ارسال، تغییر وضعیت Task/Rule/Reminder و تغییر Subscription یا تاریخ Task را دوباره کنترل می‌کند؛ Pause پس از آغاز درخواست HTTP نمی‌تواند آن درخواستِ درحال‌ارسال را پس بگیرد.

## Worker CLI — مرحله ۳

CLI یک Launcher است که به‌صورت پیش‌فرض **چهار پردازش PHP** را هم‌زمان راه می‌اندازد. هر پردازش با `ReminderWorkerRunner` یک Delivery را نزدیک به ارسال رزرو می‌کند، نتیجه را ثبت می‌کند و سراغ مورد بعدی می‌رود. سقف ثابت ۱۰ Delivery در کل اجرا حذف شده است؛ عدد ۱۰۰ در `ReminderDispatchService` فقط سقف مجاز یک دسته باقی می‌ماند و CLI دسته‌های یک‌تایی می‌گیرد.

تنظیمات در `config/reminder-worker.php` از Environment خوانده می‌شوند؛ بدون افزودن آن‌ها به `.env` نیز پیش‌فرض‌ها اعمال می‌شوند:

```dotenv
REMINDER_WORKERS=4
REMINDER_RUNTIME_SECONDS=45
```

تعداد Worker بین ۱ تا ۱۶ و مهلت رزرو جدید بین ۱ تا ۵۰ ثانیه اعتبارسنجی می‌شود. مهلت بر اساس ساعت monotonic است تا تغییر ساعت سیستم آن را مختل نکند. پایان مهلت، ارسال جاری را قطع نمی‌کند؛ فقط رزرو بعدی را متوقف می‌کند. با Timeout فعلی HTTP، یک ارسال جاری ممکن است تا حدود ۱۵ ثانیه دیگر ادامه پیدا کند؛ زمان DNS و دیتابیس جداگانه است و مهلت، سقف سختِ طول عمر پردازش نیست. سه دریافت خالی با مکث کوتاه، Worker را متوقف می‌کنند؛ این بازبینی کوتاه مانع خروج زودهنگام پس از باختن یک رقابت رزرو می‌شود.

`ReminderWorkerLockRepository` با Advisory Lock دیتابیس، اجرای هم‌پوشان Launcher را کنار می‌گذارد و برای هر Worker نیز Slot مستقل نگه می‌دارد. Slotها تا پایان ارسال جاری قفل‌اند؛ پس اگر Launcher از بین برود، اجرای بعدی نمی‌تواند Slot هنوز فعال را دوباره اشغال کند. این هماهنگی بین اتصال‌های همان دیتابیس است و به Redis یا فایل قفل محلی نیاز ندارد. پیش از تغییر تعداد Workerها یا استقرار کد، اجازه دهید پردازش‌های قبلی تمام شوند.

Payload عنوان Task و موعد آن را در Timezone و تقویم ذخیره‌شدهٔ User نشان می‌دهد. تقویم Jalali همیشه نام ماه و اعداد فارسی دارد، حتی اگر زبان رابط English باشد. در اجرای پس‌زمینه، Language=`default` به English برمی‌گردد چون زبان Browser در Worker موجود نیست. خطای ساخت Payload قبل از تماس با سرویس Push، Retry محدود می‌گیرد؛ خطای مبهم در تماس HTTP خودکار تکرار نمی‌شود.

آزمون مشترک با چهار Worker، ۱۰۰ Reminder و دو مرورگر برای هرکدام، ۲۰۰ ارسال ساختگی را با تأخیر شبیه‌سازی‌شدهٔ ۱۰۰ میلی‌ثانیه پردازش کرد؛ در اجرای اولیه حدود ۷٫۲ ثانیه طول کشید. ارسال تکراری مشاهده نشد و Launcher هم‌پوشان و Slot تکراری مسدود شدند. این نتیجه، سرعت سرویس Push واقعی یا تضمین ارسال همهٔ اعلان‌ها در همان دقیقه زیر هر باری را ثابت نمی‌کند؛ آزمون واقعی و پایش تأخیر صف هنوز لازم‌اند.

برای مشاهدهٔ راهنما بدون دست‌زدن به دیتابیس یا ارسال Push:

```powershell
php process/send-reminders.php --help
```

گزینهٔ `--send` ارسال **واقعی** را برای تمام Reminderهای رسیدهٔ واجد شرایط فعال می‌کند؛ آن را فقط پس از آماده‌کردن حساب آزمایشی و تأیید مرحلهٔ آزمون واقعی اجرا کنید. Cron/Windows Task Scheduler هنوز تنظیم نشده‌اند. خروجی Worker فقط شمارنده‌های تجمیعی است و کلیدها، Endpoint، متن Task یا خطای خام سرویس Push را نشان نمی‌دهد.

Cron هر دقیقه باید Launcher را با `--send` اجرا کند؛ `--worker=N` برای پردازش‌های فرزند است و جداگانه زمان‌بندی نمی‌شود. `proc_open` باید در PHPِ CLI فعال باشد. خطای پردازش فرزند در `failed_workers` منعکس می‌شود و Launcher با کد خروج ۱ تمام می‌شود.

تست آفلاین با کلیدهای عمومی و ثابتِ مخصوص آزمون، Encryption واقعی و HTTP/DNS شبیه‌سازی‌شده اجرا می‌شود؛ نه کلید خصوصی برنامه را می‌خواند، نه به سرویس Push متصل می‌شود، نه دیتابیس را تغییر می‌دهد:

```powershell
# Run from the MyTodo root. Restore the previous OpenSSL setting after testing.
$senderTestOpenSsl = $env:OPENSSL_CONF
try {
    $env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
    & 'C:\xampp\php\php.exe' tests/Backend/web-push-sender.test.php
    if ($LASTEXITCODE -ne 0) { throw 'Sender test failed.' }
} finally {
    $env:OPENSSL_CONF = $senderTestOpenSsl
}
```

## آزمون مرورگر

1. Apache، MySQL و Vite را روشن کنید و `https://mytodo.php/account-settings` را باز کنید.
2. هنگام ورود به صفحه هیچ Permission Prompt نباید باز شود.
3. روی Enable Notifications بزنید و مجوز را Allow کنید.
4. پس از ثبت موفق، وضعیت Enabled و دکمه Disable دیده شوند؛ Reload وضعیت را حفظ کند.
5. Disable ابتدا اشتراک سرور را حذف می‌کند، سپس Subscription مرورگر را لغو می‌کند. دستگاه‌های دیگر تغییر نمی‌کنند.
6. در حالت Denied، مجوز را از تنظیمات سایت مرورگر اصلاح و صفحه را تازه‌سازی کنید؛ کد نمی‌تواند مجوز ردشده را به‌اجبار تغییر دهد.

نتیجهٔ آزمون 2026-10-18: کاربر در تب آزمون Allow را انتخاب کرد؛ `Notification.permission` برابر `granted` شد، درخواست `POST /api/push/subscribe` پاسخ 200 داد و یک Subscription در دیتابیس ثبت شد. پس از Reload، درخواست Status پاسخ 200 داد، وضعیت Enabled باقی ماند و دکمه Disable دیده شد. این بررسی، ثبت مرورگر و پایداری وضعیت را تأیید می‌کند؛ به معنی دریافت اعلان، موفقیت Disable یا اجرای Cron نیست.

در ادامهٔ آزمون همان روز، در Chrome معمولی کاربر وضعیت Enabled را گزارش کرد، روی Disable زد و Disabled ماندن وضعیت پس از Refresh را تأیید کرد. بررسی خواندنی `getSubscription() === null` نیز `true` برگرداند؛ بنابراین در آن لحظه Subscription مرورگر وجود نداشت. باقی‌ماندن مجوز Notification روی Allow انتظار می‌رود و با لغو Subscription متفاوت است. سپس کاربر Enable را زد و بازگشت Enabled و حفظ آن بعد از Refresh را تأیید کرد. این بخش بر گزارش کاربر از رابط و خروجی Console تکیه دارد؛ تغییرات دیتابیس به‌صورت مستقل در این مرحله بررسی نشدند و بی‌تأثیربودن روی مرورگرهای دیگر هنوز تأیید نشده است. هیچ Reminder واقعی یا Cron برای این آزمون اجرا نشد.

در ادامهٔ همان آزمون و با اجازهٔ کاربر، Sender موجود فقط یک اعلان مستقل با عنوان `MyTodo - Test Notification`، متن فارسی و مقصد `/notifications` به تنها Subscription ثبت‌شده فرستاد؛ اگر مقصد یگانه تغییر می‌کرد، اسکریپت بدون ارسال متوقف می‌شد. سرویس Push پاسخ HTTP 201 (`accepted`) داد و مرورگر همان اعلان را از طریق `ServiceWorkerRegistration.getNotifications()` برگرداند. کاربر نمایش اعلان را تأیید کرد، اما پس از کلیک، تب روی Account Settings ماند و درخواست تازه‌ای به `/notifications` مشاهده نشد؛ علت این شکست هنوز قطعی نیست. این آزمون هیچ Task، Reminder یا Delivery را تغییر نداد، در آرشیو Sent Notifications دیتابیس رکوردی ایجاد نکرد و Launcher/Worker/Cron را اجرا نکرد؛ بنابراین جایگزین آزمون کامل Reminder زمان‌بندی‌شده نیست.

### ثبت موقت کلیک اعلان آزمایشی

با اجازهٔ کاربر، Service Worker فقط برای Tag مطابق `mytodo-push-test-` به‌همراه ۱۶ رقم هگز، گزارش تشخیص را در Cache `mytodo-push-click-debug-v1` ذخیره می‌کند. کلید `/__mytodo_push_click_debug_entry__` آخرین ورود کلیک یا بسته‌شدن اعلان و کلید `/__mytodo_push_click_debug__` نتیجهٔ نهایی کلیک را نگه می‌دارد. این کلیدها Route یا درخواست شبکه نیستند؛ فقط دو ورودی محدود Cache هستند و گزارش آخر جای قبلی را می‌گیرد. گزارش شامل نام رویداد، Entry Point، شناسهٔ تصادفی آزمون، مراحل انجام‌شده و نام خطا از فهرست مجاز است؛ Payload، URL، Endpoint، کلیدها، متن خطا و Stack ثبت نمی‌شوند. شناسهٔ `traceId` هر دو گزارش باید با اعلان فعلی مقایسه شود؛ گزارش نهایی قدیمی، نتیجهٔ آزمون جدید نیست.

ورود رویداد بلافاصله با پیشوند `MYTODO_PUSH_DIAGNOSTIC` در Console ثبت می‌شود و ذخیرهٔ نسخهٔ مستقل آن بدون انتظار قبل از Navigation آغاز می‌شود. ثبت نتیجهٔ نهایی پس از عملیات یا خطا انجام می‌شود؛ نبود نتیجهٔ نهایی به‌تنهایی ثابت نمی‌کند کلیک دریافت نشده است. خرابی Cache نباید نتیجهٔ Navigation را عوض کند. شنوندهٔ `notificationclose` و handler اصلی کلیک در اجرای اولیهٔ فایل ثبت می‌شوند؛ از افزودن listener در Console برای این آزمون استفاده نشود، چون Chromium هشدار ثبت دیرهنگام می‌دهد و listener موقت با شروع مجدد Worker باقی نمی‌ماند. این ابزار نه رفتار جایگزین بازکردن صفحه اضافه می‌کند و نه مشکل کلیک را رفع‌شده اعلام می‌کند.

کاربر پس از اعلان دوم `MyTodo - Click Test` نیز بازنشدن صفحهٔ مقصد را گزارش کرد. در ادامهٔ عیب‌یابی و با اجازهٔ کاربر، نسخهٔ دارای ثبت ورود/بسته‌شدن واقعاً روی `https://mytodo.php/` فعال شد و فقط یک اعلان محلی `MyTodo - Worker Event Test` با `showNotification()` نمایش داده شد. کاربر نمایش اعلان و بازنشدن مقصد را تأیید کرد؛ برای همان شناسهٔ آزمون، گزارش ورود `push_test_close` با مرحلهٔ `close-received` ذخیره شد و نتیجهٔ نهایی کلیک وجود نداشت. این شواهد دریافت close را تأیید می‌کند، نه علت قطعی شکست کلیک یا خرابی Chromium؛ کلید ورود فقط آخرین رویداد را نگه می‌دارد و تاریخچهٔ کامل نیست. این مرحله Push سروری نفرستاد، دیتابیس را تغییر نداد و Worker ارسال Reminder یا Cron را اجرا نکرد.

در آزمون مقایسه‌ای با Chrome معمولی، کاربر بازشدن Sent Notifications را تأیید کرد، اما `NotFoundError: The client was not found` نیز مشاهده شد. گزارش همان آزمون تا `navigation-complete` و سپس `focusing` پیش رفت، بدون `focus-complete`؛ در نتیجه خطای باقی‌مانده در Focus بود. کد قبلی خروجی `navigate()` را کنار می‌گذاشت و مرجع قبلی را Focus می‌کرد. اکنون فقط Client برگشتی Focus می‌شود؛ اگر مقدار برگشتی `null` باشد، مرحلهٔ `focus-skipped-no-client` ثبت می‌شود و نه مرجع قدیمی استفاده می‌شود و نه تب تکراری باز می‌شود. `NotFoundError` نیز به فهرست مجاز نام خطای تشخیصی افزوده شد، بدون ثبت متن خطا. سه تست جدید پیش از اصلاح شکست خوردند و پس از اصلاح، سیزده تست Worker و مجموعاً ۳۱ تست Frontend پاس شدند.

در تکرار آزمون محلی `MyTodo - Focus Retest` با Tag دارای شناسهٔ `2026101800000007` در 2026-10-18، کاربر بازشدن Sent Notifications و عدم تکرار `NotFoundError` را پس از اصلاح تأیید کرد. این تأیید، گزارش مستقیم کاربر از Chrome معمولی است؛ گزارش Cache جدیدی پس از اصلاح دریافت نشده است. آزمون محلی به معنی تأیید ارسال سروری به همین مرورگر یا مسیر کامل Reminder/Cron نیست. پس از آن، Disable و فعال‌سازی مجدد همین Chrome طبق گزارش بالا تأیید شدند. آزمون عدم تأثیر روی مرورگرهای دیگر و ارسال Reminder واقعیِ محدود به حساب/دادهٔ آزمایشی هنوز باقی‌اند؛ Worker عمومی یا Scheduler بدون محدودکردن دامنهٔ آزمون نباید اجرا شود. حذف ابزار موقت و فقط Cache مخصوص آن هنوز انجام نشده است؛ Cacheها یا Subscriptionهای دیگر نباید پاک شوند.

تست رسیدن واقعی اعلان باید پس از پیاده‌سازی Sender و Worker انجام شود. تست iPhone/iPad به برنامه نصب‌شده روی Home Screen نیاز دارد.

## تست‌های خودکار

```powershell
php tests/Backend/push-subscription.test.php
php tests/Backend/push-api.test.php
php tests/Backend/push-subscription.integration.php
php tests/Backend/reminder-delivery.integration.php
php tests/Backend/reminder-worker.integration.php
php tests/Backend/reminder-worker-cli.test.php
php tests/Backend/reminder-worker-runner.test.php
php tests/Backend/reminder-worker-config.test.php
php tests/Backend/reminder-worker-pool.test.php
php tests/Backend/reminder-worker-concurrency.integration.php
node --test tests/Frontend/push-notifications.test.mjs tests/Frontend/push-worker.test.mjs
```

تست Integration از MySQL و تنظیمات اتصال موجود استفاده می‌کند، اما فقط جدول‌های موقت مخصوص همان اتصال را تغییر می‌دهد؛ هیچ User، Task یا Reminder واقعی تغییر نمی‌کند.

استثنا، تست `reminder-worker-concurrency.integration.php` است: برای اشتراک داده بین پردازش‌ها، در همان دیتابیس جدول‌های خالی با پیشوند تصادفی `mytodo_worker_..._` می‌سازد، فقط همان‌ها را پر می‌کند و پس از پایان پردازش‌ها حذف می‌کند. ساختار و Indexهای جدول‌های واقعی با `CREATE TABLE ... LIKE` کپی می‌شوند؛ داده‌ها و Foreign Keyها کپی نمی‌شوند. اجرای این تست نیازمند دسترسی CREATE/DROP TABLE است و باید روی دیتابیس Development انجام شود؛ هیچ ارسال Push واقعی ندارد.
