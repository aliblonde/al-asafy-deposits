<?php
// public/privacy_policy.php — Official Mobile App Privacy Policy & Data Safety
$pageTitle = 'سياسة الخصوصية وحماية البيانات — مجموعة العسافي للاستثمارات';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0f1115;
            --bg-card: #181b21;
            --gold: #d4af37;
            --gold-light: #f3cf65;
            --text-primary: #f5f6f8;
            --text-muted: #9aa0a6;
            --border: rgba(212, 175, 55, 0.2);
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.8;
            padding: 40px 15px;
        }
        .container {
            max-width: 860px;
        }
        .policy-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        h1, h2, h3, h4 {
            color: var(--gold);
            font-weight: 700;
        }
        h1 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 15px;
        }
        h2 {
            font-size: 1.3rem;
            margin-top: 25px;
            margin-bottom: 12px;
        }
        p, li {
            color: #d1d5db;
            font-size: 0.95rem;
        }
        .badge-secure {
            background: rgba(39, 174, 96, 0.15);
            color: #2ecc71;
            border: 1px solid rgba(39, 174, 96, 0.3);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin-bottom: 15px;
        }
        .footer-note {
            text-align: center;
            margin-top: 30px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="policy-card">
        <div class="badge-secure">وثيقة معتمدة ومطابقة لسياسات Google Play & Apple App Store</div>
        <h1>سياسة الخصوصية وحماية البيانات</h1>
        <p class="text-muted">آخر تحديث: 21 نوفمبر 2026</p>

        <p>
            تلتزم <strong>مجموعة العسافي للاستثمارات</strong> بحماية خصوصية وأمان بيانات مستخدمي وعملاء تطبيق الهاتف المحمول المخصص لبوابة المستثمر. توضح هذه الوثيقة ماهية البيانات التي يتم جمعها، وكيفية استخدامها وحمايتها، وحقوق المستخدمين الكاملة بشأنها.
        </p>

        <h2>1. المعلومات التي نقوم بجمعها</h2>
        <p>يقوم التطبيق بمعالجة البيانات التالية حصراً لخدمة العملاء المسجلين لدى المجموعة:</p>
        <ul>
            <li><strong>بيانات الحساب الشخصية:</strong> الاسم الكامل، رقم الهاتف، والمدينة المسجلة في عقد الإيداع.</li>
            <li><strong>البيانات المالية:</strong> تفاصيل الودائع الاستثمارية، مبالغ الأرباح المتراكمة، سجل طلبات صرف الأرباح، وإيصالات الحركات المالية.</li>
            <li><strong>بيانات الأمان وسجلات الوصول:</strong> عنوان IP، وقت تسجيل الدخول، لتأمين الحساب من أي وصول غير مصرح به.</li>
        </ul>

        <h2>2. كيف نستخدم معلوماتك؟</h2>
        <ul>
            <li>تمكين المستثمر من متابعة وتدقيق حساباته الاستثمارية وأرباح ودائعه لحظياً.</li>
            <li>معالجة طلبات سحب الأرباح المعتمدة وتوثيق إيصالات الصرف الرسمية.</li>
            <li>حماية الحسابات المصرفية وتطبيق معايير مكافحة الاحتيال السيبراني.</li>
            <li><strong>لا نقوم إطلاقاً</strong> ببيع أو تأجير أو مشاركة أي بيانات شخصية أو مالية مع أي طرف ثالث لأغراض تسويقية أو دعائية.</li>
        </ul>

        <h2>3. أمان وتشفير البيانات (Data Security)</h2>
        <p>
            تخضع كافة الاتصالات بين تطبيق الهاتف المحمول وخوادم النظام لأعلى معايير التشفير العالمي عبر بروتوكول <strong>HTTPS / SSL (TLS 1.3)</strong>، مع تشفير كلمات المرور باستخدام خوارزميات التجزئة الصخريّة <strong>(BCRYPT)</strong> واستخدام رموز التوثيق الآمنة <strong>(JWT Tokens)</strong> لمنع أي تسريب أو اعتراض للبيانات.
        </p>

        <h2>4. حق المستخدم في حذف الحساب (Account Deletion)</h2>
        <p>
            امتثالاً لسياسات متجري Google Play و Apple App Store:
            يحق لأي مستخدم في أي وقت طلب حذف حسابه وبيانات دخوله بشكل كامل مباشرة من داخل تطبيق الهاتف المحمول عبر الانتقال إلى <strong>(الملف الشخصي ⬅️ الأمان ⬅️ طلب حذف الحساب)</strong>، أو من خلال التواصل مع خدمة العملاء.
        </p>

        <h2>5. التواصل والدعم الفني</h2>
        <p>
            لأي استفسارات أو ملاحظات تتعلق بسياسة الخصوصية وحماية بياناتك الاستثمارية، يمكنك التواصل مباشرة مع إدارة المجموعة:
        </p>
        <ul>
            <li>الموقع الرسمي: <a href="https://alasafygroup.xyz" class="text-warning text-decoration-none">www.alasafygroup.xyz</a></li>
            <li>البريد الإلكتروني للدعم: info@alasafygroup.com</li>
            <li>الإدارة المركزية: مجموعة العسافي للاستثمارات</li>
        </ul>
    </div>

    <div class="footer-note">
        جميع الحقوق محفوظة © <?= date('Y') ?> مجموعة العسافي للاستثمارات — نظام إدارة الودائع
    </div>
</div>

</body>
</html>
