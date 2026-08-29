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
examples/                          مثال تكامل كامل وفعّال (3 خطوات)
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

## التحقق من توقيع الـ Webhook

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
