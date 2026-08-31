# Qistass Pay — SDK

مكتبة لدمج بوابة الدفع الخاصة بـ [قسطاس باي](https://pay.qistass.com) في أي موقع (ووردبريس، سلة، Zid، أو متجر مستقل مبني بأي تقنية) دون الحاجة إلى قراءة توثيق الـ API كاملًا — مكتبة PHP للخادم، إضافةً إلى زر جافاسكريبت جاهز للواجهة الأمامية.

هذه المكتبة غلاف حول نفس الـ API الحقيقي والمُختبَر أصلًا في إضافة WooCommerce الرسمية — لا تضيف أي منطق جديد على الخادم، بل تجعل استخدام الـ API الموجود أسهل وأسرع فحسب.

## جرِّب الـ API مباشرة (بلا كود)

مجموعة [Postman](postman/) جاهزة — استوردها وعبِّئ مفاتيحك، وجرِّب إنشاء طلب دفع حقيقي والتحقق منه خلال دقائق، قبل كتابة أي سطر كود.

كما تتوفر [مواصفة OpenAPI 3.1](openapi/) قياسية وكاملة، قابلة للاستيراد بأي أداة (Postman، Swagger UI، مولّدات SDK تلقائية).

## المكونات

```
src/QistassPay.php                 المكتبة الأساسية (PHP، دون أي اعتماديات خارج curl/json)
src/QistassPayException.php        استثناء للأخطاء المنطقية (merchant_not_found، توقيع غير صحيح...)
src/QistassPayNetworkException.php استثناء لأخطاء الشبكة والاتصال
assets/qistass-button.js           زر دفع جاهز (Vanilla JS، دون اعتماديات)
assets/qistass-button.css          تنسيق الزر بهوية قسطاس (أخضر/ذهبي)
examples/                          أمثلة تكامل كاملة وفعّالة — دفعة واحدة واشتراكات
```

## التثبيت

**عبر Composer** (بعد نشر الحزمة على Packagist أو مستودع خاص):
```bash
composer require qistass/pay-sdk
```

**أو مباشرة دون Composer** — انسخ مجلد `src/` إلى مشروعك، ثم:
```php
require 'src/QistassPay.php';
require 'src/QistassPayException.php';
require 'src/QistassPayNetworkException.php';
use QistassPay\QistassPay;
```

## البداية السريعة (3 خطوات، مطابقة تمامًا لتوثيق الـ API الرسمي)

### 1. إنشاء الكائن بالمفاتيح الخاصة بك

تُطلَب المفاتيح من `pay.qistass.com/merchant` بعد التسجيل والتحقق. **لا تضع `secret_key` أبدًا في كود الواجهة الأمامية** — يجب أن يبقى على الخادم فقط (كمتغيرات بيئة).

```php
use QistassPay\QistassPay;

$qistass = new QistassPay(
    $_ENV['QISTASS_PUBLIC_KEY'],
    $_ENV['QISTASS_SECRET_KEY'],
    $_ENV['QISTASS_MERCHANT_NUMBER'],
    $_ENV['QISTASS_WEBHOOK_SECRET'] // اختياري، مطلوب فقط للتحقق من الـ webhook
);
```

### 2. إنشاء طلب دفع وتوجيه الزبون

```php
$order = $qistass->createPaymentOrder(
    45000,                                       // المبلغ، **بعملة التاجر نفسه** (المحددة في لوحة تحكمه)، وليس دائمًا بالليرة السورية
    'ORD-2026-0142',                              // معرّف الطلب في نظامك
    'https://yoursite.com/qistass/webhook.php',   // اختياري
    'https://yoursite.com/qistass/return.php'     // اختياري
);

header('Location: ' . $order['redirect_url']);
```

### 3. التحقق من الدفع (بعد العودة، أو من الـ webhook — دائمًا، ودون الاعتماد على أي منهما وحده)

```php
if ($qistass->isPaid($transactionId, expectedAmount: 45000)) {
    // فعِّل الطلب
}
```

## الزر الجاهز (الواجهة الأمامية)

**مهم**: لا يتصل الزر بـ API قسطاس مباشرة أبدًا (لأن `secret_key` ممنوع في المتصفح) — بل يتصل بنقطة نهاية على *خادمك أنت*، وهي التي تستدعي `createPaymentOrder()` من المكتبة أعلاه وتُعيد `{ redirect_url }`.

**يفتح الزر افتراضيًا نافذة منبثقة (Popup)**، لا صفحة كاملة — تجربة مماثلة تمامًا لتجربة PayPal: لا يغادر الزبون موقعك إطلاقًا. هذه **نافذة حقيقية**، وليست `<iframe>` — إذ ترسل صفحة الدفع ترويسة `X-Frame-Options: SAMEORIGIN` عمدًا (حماية من هجمات clickjacking)، ولذلك لا يمكن وضعها داخل `iframe` من موقعك، والنافذة المنبثقة هي الخيار الصحيح الوحيد لهذا النوع من التجربة.

```html
<div id="qistass-checkout"></div>
<link rel="stylesheet" href="assets/qistass-button.css">
<script src="assets/qistass-button.js"></script>
<script>
  QistassPay.renderButton('#qistass-checkout', {
    endpoint: '/your-backend-route',   // يستدعي createPaymentOrder() على خادمك
    label: 'ادفع عبر قسطاس باي',
    onReturn: function (returnedUrl) {
      // أُغلقت النافذة (أو عادت إلى موقعك). لا تعتمد عليها وحدها —
      // استدعِ isPaid() من خادمك دائمًا للتحقق الحقيقي.
    }
  });
</script>
```

للعودة إلى تجربة الصفحة الكاملة (تحويل تقليدي بدل نافذة منبثقة)، أضف `mode: 'redirect'` إلى الإعدادات نفسها — وتعود المكتبة إلى هذا الوضع تلقائيًا أيضًا إذا حجب المتصفح النافذة المنبثقة (كما يحدث في بعض المتصفحات المدمجة داخل التطبيقات على الهاتف).

راجع مجلد `examples/` للاطلاع على مثال كامل وفعّال: `index.html` (الزر) + `create-checkout.php` + `webhook.php` + `return.php`.

## الاشتراكات (فوترة متكررة — مناسبة لمشاريع SaaS)

بدل دفعة واحدة، أنشئ تفويضًا يُشحن تلقائيًا كل دورة (أسبوعية، شهرية، أو سنوية) من غير أي تدخل منك بعد التفويض الأول:

```php
$result = $qistass->createSubscription(
    9990,              // المبلغ لكل دورة، بعملة استقبالك
    'user_42',         // معرّفك الخاص — استخدم شيء يربطك بحساب الزبون عندك (id المستخدم مثلًا)
    'https://yoursite.com/qistass/subscription-webhook.php',
    'https://yoursite.com/account?subscribed=1',
    'monthly',         // 'weekly' أو 'monthly' أو 'yearly'
    0                  // عدد أيام الفترة التجريبية المجانية (0 إلى 60) — 0 يعني بلا فترة تجريبية
);

header('Location: ' . $result['redirect_url']); // نفس تدفق PIN المعروف — بلا واجهة جديدة من طرفك
```

**فترة تجريبية مجانية**: مرّر `trialDays` بين 1 و60. الزبون يؤكّد اشتراكه بنفس رابط التفويض المعروف — بلا أي خصم — وأول خصم فعلي يصير تلقائيًا بعد انتهاء المدة، ويصلك عبر نفس حدث `subscription.charged` الذي يصل عند أي تجديد عادي (لا يوجد حدث منفصل لـ"انتهت الفترة التجريبية").

**كيف تنفتح الميزات المدفوعة فعليًا عند الزبون؟** عبر الـ webhook فقط، وليس رابط العودة:

| الحدث | يعني |
|---|---|
| `subscription.charged` | تفويض ناجح أو تجديد شهري ناجح — فعّل/جدّد الميزة |
| `subscription.payment_failed` | فشل تجديد (رصيد غير كافٍ) — الاشتراك ما زال حيًا، إعادة محاولة تلقائية بعد يومين |
| `subscription.canceled` | لا شحن بعد الآن (سواء ألغاه التاجر، أو الزبون بنفسه، أو فشلتين متتاليتين) — عطّل الميزة |

```php
$qistass->subscriptionStatus($subscriptionId);  // تحقق من الحالة الحالية يدويًا في أي وقت
$qistass->cancelSubscription($subscriptionId);  // إلغاء فوري، بلا شحن لاحق
```

⚠️ **رابط العودة (`callback_url`) لتجربة المستخدم فقط** — لا يجب أبدًا أن يكون هو المصدر يلي يفعّل الميزة، لأنو ممكن الزبون يسكّر المتصفح قبل ما يوصل، أو يُزوَّر من طرف العميل. الـ webhook (موقَّع ومُتحقَّق منه من طرف السيرفر) هو المصدر الوحيد الموثوق.

مثال كامل شغّال: `examples/subscribe.php` + `examples/subscription-webhook.php`.

## التحقق من توقيع الـ Webhook

نفس آلية التحقق تنطبق على إشعارات الدفعة الواحدة وإشعارات الاشتراكات معًا:

```php
$payload = $qistass->handleIncomingWebhook(); // يتحقق من X-Qistass-Signature تلقائيًا
// يطرح QistassPayException إذا كان التوقيع غير صحيح
```

## معالجة الأخطاء

```php
use QistassPay\QistassPayException;      // خطأ منطقي (merchant_not_found، توقيع غير صحيح...)
use QistassPay\QistassPayNetworkException; // خطأ شبكة أو اتصال

try {
    $order = $qistass->createPaymentOrder(45000, 'ORD-1');
} catch (QistassPayException $e) {
    // يحتوي $e->status على القيمة الحرفية الواردة من قسطاس (مثل "merchant_not_found")
} catch (QistassPayNetworkException $e) {
    // مشكلة في الاتصال بالخادم
}
```

## ملاحظات أمنية (وفق القواعد الموثَّقة رسميًا)

- يبقى `secret_key` على الخادم فقط، ولا يظهر أبدًا في كود المتصفح.
- يجب التحقق دائمًا من `payment-verification` (عبر `isPaid()`) قبل تفعيل أي طلب — دون الاعتماد على الـ webhook أو رابط العودة وحدهما.
- الحد الأقصى لطلبات الـ API: 300 طلب في الدقيقة لكل تاجر (موثَّق في صفحة `pages/api-terms`).

## التوافق

أي منصة قادرة على تشغيل PHP على الخادم — ووردبريس/ووكومرس، سلة، Zid، Laravel أو Symfony مخصص، أو حتى ملفات PHP عادية دون أي إطار عمل، كما هو موضح في مثال `examples/`.
