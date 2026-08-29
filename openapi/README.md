# مواصفة OpenAPI — Qistass Pay

مواصفة [OpenAPI 3.1](openapi.json) قياسية للـ API، قابلة للاستيراد المباشر في:

- **Postman**: File ← Import ← اختر `openapi.json` (سيولّد مجموعة طلبات تلقائيًا، بديل عن استيراد مجموعة Postman يدويًا).
- **Swagger UI / Redoc**: لعرض توثيق تفاعلي جاهز.
- أي أداة لتوليد عميل (SDK) تلقائيًا بأي لغة برمجة.

تم التحقق من صحة هذا الملف عبر [Redocly CLI](https://redocly.com/docs/cli/) (`redocly lint`) — صفر أخطاء.

**ملاحظة**: المصادقة في هذا الـ API عبر حقول (`public_key`، `secret_key`، `merchant_number`) ضمن جسم الطلب نفسه، وليست عبر ترويسات HTTP القياسية — لذلك لا تجد مخطط `securitySchemes` تقليديًا هنا؛ الحقول المطلوبة موثَّقة مباشرة ضمن كل طلب.
