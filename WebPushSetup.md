# Web Push — ثبت مرورگر و Sender مستقل

## وضعیت فعلی

- کلیدهای VAPID و ثبت/حذف Subscription پیاده‌سازی شده‌اند.
- کنترل فعال/غیرفعال‌سازی در Account Settings قرار دارد و مستقل از Save است.
- Service Worker فعلاً فقط برای Push است؛ Cache آفلاین و نصب PWA هنوز پیاده‌سازی نشده‌اند.
- Sender مستقل و تست آفلاین آن اضافه شده‌اند؛ هنوز از هیچ Route یا Worker فراخوانی نمی‌شود و هیچ Reminder واقعی ارسال نمی‌کند.
- ارسال زمان‌بندی‌شده Reminder، Retry، Cron، Badge خوانده‌نشده و Pagination هنوز مراحل بعدی هستند.
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

قرارداد در `App/Notifications/NotificationSender.php` و پیاده‌سازی در `WebPushNotificationSender.php` است. متد `send` فقط برای یک Subscription ارسال می‌کند؛ مسئول خواندن دیتابیس، مالکیت User، زمان‌بندی Cron، ثبت `sent` یا Retry نیست. Worker آینده باید Subscription متعلق به صاحب همان Reminder را از Repository بگیرد؛ هیچ Route عمومی برای ارسال مستقیم اضافه نشده است.

ورودی Subscription همان فیلدهای دیتابیس `endpoint`، `p256dh` و `auth` است. Payload فقط شامل `title`، `body`، `tag` و `url` است؛ فیلد اضافی ارسال نمی‌شود. متن UTF-8 و حجم JSON بررسی می‌شوند. لینک کلیک باید مسیر داخلی `/notifications`، `/manage-tasks` یا `/` باشد؛ Query مجاز است. این قرارداد با Service Worker فعلی یکسان است. ساخت عنوان، Due Time و Tag مخصوص هر Reminder در مرحله Worker انجام خواهد شد.

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

تست رسیدن واقعی اعلان باید پس از پیاده‌سازی Sender و Worker انجام شود. تست iPhone/iPad به برنامه نصب‌شده روی Home Screen نیاز دارد.

## تست‌های خودکار

```powershell
php tests/Backend/push-subscription.test.php
php tests/Backend/push-api.test.php
php tests/Backend/push-subscription.integration.php
node --test tests/Frontend/push-notifications.test.mjs tests/Frontend/push-worker.test.mjs
```

تست Integration از MySQL و تنظیمات اتصال موجود استفاده می‌کند، اما فقط جدول‌های موقت مخصوص همان اتصال را تغییر می‌دهد؛ هیچ User، Task یا Reminder واقعی تغییر نمی‌کند.
