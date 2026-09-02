<?php
// public/investor_add.php — Add / Edit investor + linked user account
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/rbac.php';

requirePermission('investors.view');
$pdo = getPDO();

$editId = (int) ($_GET['edit'] ?? 0);
$investor = null;
$linkedUser = null;

$extraAttachments = [];
  if ($editId) {
    $s = $pdo->prepare("SELECT * FROM investors WHERE id=?");
    $s->execute([$editId]);
    $investor = $s->fetch();

    if ($investor) {
        $sAtt = $pdo->prepare("SELECT * FROM investor_attachments WHERE investor_id = ?");
        $sAtt->execute([$editId]);
        $extraAttachments = $sAtt->fetchAll();
    }
    if (!$investor) {
    header('Location: investors.php');
    exit;
  }

  $s2 = $pdo->prepare("SELECT * FROM users WHERE investor_id=?");
  $s2->execute([$editId]);
  $linkedUser = $s2->fetch();
}

$errors = [];
$old = $investor ?? [];
$contractCurrent = $old['contract_path'] ?? '';
$idCardCurrent = $old['id_card_path'] ?? '';

// ── Handle File Uploads ────────────────────────────────────────────────
function handleUpload($fileKey, $targetDir, $currentPath = '')
{
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE || empty($_FILES[$fileKey]['name'])) {
    return $currentPath;
  }

  $file = $_FILES[$fileKey];

  if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new Exception("حدث خطأ أثناء رفع الملف ($fileKey). كود الخطأ: " . (int)$file['error']);
  }

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'zip', 'rar'];

  if (!in_array($ext, $allowedExts, true)) {
    throw new Exception("نوع ملف غير مسموح به لـ $fileKey. المسموح: PDF, JPG, PNG, ZIP, RAR");
  }

  if ($file['size'] > 50 * 1024 * 1024) { // 50MB
    throw new Exception("حجم الملف كبير جداً. الحد الأقصى 50MB.");
  }

  // MIME Type Validation Guard
  if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/pjpeg'];
    if (!in_array($mimeType, $allowedMimes, true)) {
      throw new Exception("محتوى الملف غير صالح لـ $fileKey.");
    }
  }

  // Image Parser Integrity Validation for images
  if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
      throw new Exception("الملف المرفوع لـ $fileKey ليس صورة صالحة.");
    }
  }

  // Cryptographically random filename
  $randomName = 'inv_doc_' . bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = $targetDir . $randomName;

  $fullTargetDir = __DIR__ . '/../' . $targetDir;
  if (!is_dir($fullTargetDir)) {
    mkdir($fullTargetDir, 0755, true);
  }

  if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $dest)) {
    return $dest;
  }
  return $currentPath;
}

$uploadDir = 'uploads/investors/';

// ── POST: Save ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();

  // Investor fields — match schema: full_name, national_id, phone, city, address, notes
  $fullName = trim($_POST['full_name'] ?? '');
  $nationalId = trim($_POST['national_id'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  // User account fields
  $createUser = !empty($_POST['create_user']);
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');

  // Validate
  if (!$fullName)
    $errors[] = 'الاسم الكامل مطلوب.';
  if (!$nationalId)
    $errors[] = 'رقم الهوية مطلوب.';

  try {
    $contractPath = handleUpload('contract_file', $uploadDir, $contractCurrent);
    $idCardPath = handleUpload('id_card_file', $uploadDir, $idCardCurrent);
  } catch (Exception $e) {
    $errors[] = $e->getMessage();
  }

  if ($createUser && (!$editId || !$linkedUser)) {
    if (!$username)
      $errors[] = 'اسم المستخدم مطلوب لإنشاء الحساب.';
    if (strlen($password) < 1)
      $errors[] = 'كلمة المرور مطلوبة لإنشاء الحساب.';
    else {
      $pwCheck = validatePasswordPolicy($password);
      if (!$pwCheck['valid']) $errors[] = $pwCheck['error'];
    }
    if ($username) {
      $chk = $pdo->prepare("SELECT id FROM users WHERE username=?");
      $chk->execute([$username]);
      if ($chk->fetch())
        $errors[] = 'اسم المستخدم مستخدم بالفعل.';
    }
  }
  if (!$editId && $nationalId) {
    $chk2 = $pdo->prepare("SELECT id FROM investors WHERE national_id=?");
    $chk2->execute([$nationalId]);
    if ($chk2->fetch())
      $errors[] = 'رقم الهوية مسجّل مسبقاً.';
  }

  if (empty($errors)) {
    $pdo->beginTransaction();
    try {
      if ($editId) {
        $oldData = $investor; // needed for logger
        $pdo->prepare(
          "UPDATE investors SET full_name=?, national_id=?, phone=?, city=?, address=?, notes=?, contract_path=?, id_card_path=? WHERE id=?"
        )->execute([$fullName, $nationalId, $phone, $city, $address, $notes, $contractPath, $idCardPath, $editId]);

        // If password is provided for linked user — requires reset_password permission
        if ($linkedUser && !empty($password)) {
          if (!userCan('investor_accounts.reset_password')) {
            throw new Exception('ليس لديك صلاحية إعادة تعيين كلمة مرور المستثمر.');
          }
          $pwCheck = validatePasswordPolicy($password);
          if (!$pwCheck['valid']) {
            throw new Exception($pwCheck['error']);
          }
          $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
          $pdo->prepare("UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?")->execute([$hash, $linkedUser['id']]);
          logActivity($pdo, 'RESET_INVESTOR_PASSWORD', 'users', $linkedUser['id'], null, ['username' => $linkedUser['username']]);
        }

        // If newly creating user for existing investor
        if (!$linkedUser && $createUser && $username && $password) {
          $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
          $pdo->prepare(
            "INSERT INTO users (username, password_hash, role, investor_id, created_at)
                         VALUES (?, ?, 'investor', ?, NOW())"
          )->execute([$username, $hash, $editId]);
          logActivity(
            $pdo,
            'CREATE_USER',
            'users',
            null,
            null,
            ['username' => $username, 'role' => 'investor', 'investor_id' => $editId, 'note' => 'Account created during investor edit']
          );
        }

        logActivity(
          $pdo,
          'UPDATE_INVESTOR',
          'investors',
          $editId,
          $oldData,
          ['full_name' => $fullName, 'national_id' => $nationalId, 'phone' => $phone, 'city' => $city]
        );
        $pdo->commit();
        setFlash('success', 'تم تحديث بيانات المستثمر بنجاح.');
      } else {
        $pdo->prepare(
          "INSERT INTO investors (full_name, national_id, phone, city, address, notes, contract_path, id_card_path, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
        )->execute([$fullName, $nationalId, $phone, $city, $address, $notes, $contractPath, $idCardPath]);
        $newId = (int) $pdo->lastInsertId();

        logActivity(
          $pdo,
          'CREATE_INVESTOR',
          'investors',
          $newId,
          null,
          ['full_name' => $fullName, 'national_id' => $nationalId]
        );

        if ($createUser && $username && $password) {
          $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
          $pdo->prepare(
            "INSERT INTO users (username, password_hash, role, investor_id, created_at)
                         VALUES (?, ?, 'investor', ?, NOW())"
          )->execute([$username, $hash, $newId]);
          logActivity(
            $pdo,
            'CREATE_USER',
            'users',
            null,
            null,
            ['username' => $username, 'role' => 'investor', 'investor_id' => $newId]
          );
        }

        $pdo->commit();
        setFlash('success', 'تم إضافة المستثمر' . ($createUser ? ' وحساب الدخول' : '') . ' بنجاح.');
      }
              $investorIdForAttachments = $editId ?: ($newId ?? 0);
        if ($investorIdForAttachments) {
            if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
                foreach ($_POST['delete_attachments'] as $delId) {
                    $delId = (int)$delId;
                    $stmt = $pdo->prepare("SELECT file_path FROM investor_attachments WHERE id = ? AND investor_id = ?");
                    $stmt->execute([$delId, $investorIdForAttachments]);
                    $att = $stmt->fetch();
                    if ($att) {
                        @unlink(__DIR__ . '/../' . $att['file_path']);
                        $pdo->prepare("DELETE FROM investor_attachments WHERE id = ?")->execute([$delId]);
                    }
                }
            }
            if (!empty($_FILES['extra_attachments']['name'][0])) {
                $count = count($_FILES['extra_attachments']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['extra_attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['extra_attachments']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'zip', 'rar'])) {
                            if ($_FILES['extra_attachments']['size'][$i] <= 50 * 1024 * 1024) {
                                $originalName = basename($_FILES['extra_attachments']['name'][$i]);
                                $dest = 'uploads/investors/att_' . uniqid() . '_' . $originalName;
                                if (move_uploaded_file($_FILES['extra_attachments']['tmp_name'][$i], __DIR__ . '/../' . $dest)) {
                                    $stmt = $pdo->prepare("INSERT INTO investor_attachments (investor_id, title, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([$investorIdForAttachments, $originalName, $dest, currentUserId()]);
                                }
                            }
                        }
                    }
                }
            }
        }
        header('Location: investors.php');
      exit;
    } catch (\Exception $e) {
      $pdo->rollBack();
      $errors[] = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
    }
  }

  $old = ['full_name' => $fullName, 'national_id' => $nationalId, 'phone' => $phone, 'city' => $city, 'address' => $address, 'notes' => $notes, 'contract_path' => $contractPath, 'id_card_path' => $idCardPath];
}

$pageTitle = $editId ? 'تعديل مستثمر' : 'إضافة مستثمر';
include __DIR__ . '/../includes/header.php';
?>
<div class="layout-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-wrapper">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
      <?php include __DIR__ . '/../includes/alerts.php'; ?>

      <div class="page-header">
        <h1 class="page-title">
          <i class="bi bi-<?= $editId ? 'pencil-square' : 'person-plus' ?> me-2"></i>
          <?= $editId ? 'تعديل بيانات مستثمر' : 'إضافة مستثمر جديد' ?>
        </h1>
        <a href="investors.php" class="btn btn-outline-gold btn-sm">
          <i class="bi bi-arrow-right me-1"></i> العودة للقائمة
        </a>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <form method="post" action="" enctype="multipart/form-data">
          <?= csrfField() ?>

          <!-- Investor Info -->
          <h5 class="mb-3" style="color:var(--gold)"><i class="bi bi-person me-2"></i>البيانات الشخصية</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
              <input type="text" name="full_name" class="form-control"
                value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">رقم الهوية الوطنية <span class="text-danger">*</span></label>
              <input type="text" name="national_id" class="form-control"
                value="<?= htmlspecialchars($old['national_id'] ?? '') ?>" placeholder="1xxxxxxxxx" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">رقم الهاتف</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                placeholder="05xxxxxxxx">
            </div>
            <div class="col-md-6">
              <label class="form-label">المدينة</label>
              <select name="city" class="form-select">
                <?php
                $cities = ['أربيل', 'بغداد', 'البصرة', 'الموصل', 'السليمانية', 'كركوك', 'النجف', 'كربلاء', 'أخرى'];
                $selectedCity = $old['city'] ?? 'أربيل';
                foreach ($cities as $c):
                  ?>
                  <option value="<?= $c ?>" <?= $selectedCity === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">العنوان التفصيلي</label>
              <input type="text" name="address" class="form-control"
                value="<?= htmlspecialchars($old['address'] ?? '') ?>" placeholder="الحي، الشارع، رقم المبنى...">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2"
              placeholder="أي ملاحظات إضافية..."><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
          </div>
      </div>

      <!-- Documents -->
      <hr style="border-color:var(--border);margin:24px 0">
      <h5 class="mb-3" style="color:var(--gold)"><i class="bi bi-file-earmark-text me-2"></i>وثائق ومستمسكات</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">عقد المستثمر (PDF أو صورة)</label>
          <input type="file" name="contract_file" class="form-control" accept=".pdf,image/*">
          <?php if (!empty($old['contract_path'])): ?>
            <div class="mt-1 small">
              <a href="/<?= $old['contract_path'] ?>" target="_blank" class="text-gold">
                <i class="bi bi-file-earmark-check me-1"></i>عرض العقد الحالي
              </a>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">المستمسكات الثبوتية / الهوية (PDF أو صورة)</label>
          <input type="file" name="id_card_file" class="form-control" accept=".pdf,image/*">
          <?php if (!empty($old['id_card_path'])): ?>
            <div class="mt-1 small">
              <a href="/<?= $old['id_card_path'] ?>" target="_blank" class="text-gold">
                <i class="bi bi-file-earmark-check me-1"></i>عرض الهوية الحالية
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

              <div class="row mb-4">
          <div class="col-12">
            <h5 class="section-title border-bottom pb-2 mb-3">
              <i class="bi bi-paperclip me-2"></i>المرفقات الإضافية
            </h5>
            <label class="form-label text-warning mb-2">إضافة مرفقات جديدة (يمكنك تحديد أكثر من ملف)</label>
            <input type="file" name="extra_attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip,.rar">
            <small class="text-muted d-block mt-1">المسموح: PDF, JPG, PNG, ZIP, RAR (الحد الأقصى 50 ميجا لكل ملف)</small>
            
            <?php if ($editId && !empty($extraAttachments)): ?>
              <div class="mt-4 p-3 border border-secondary rounded">
                <strong class="d-block mb-3 text-gold">المرفقات الحالية للمستثمر:</strong>
                <ul class="list-group list-group-flush bg-transparent">
                  <?php foreach ($extraAttachments as $att): ?>
                    <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between align-items-center px-0">
                      <a href="download_file.php?investor_id=<?= $editId ?>&type=extra_attachment&attachment_id=<?= $att['id'] ?>" target="_blank" class="text-decoration-none text-white">
                        <i class="bi bi-file-earmark me-2 text-gold"></i><?= htmlspecialchars($att['title']) ?>
                      </a>
                      <label class="text-danger small ms-3" style="cursor: pointer;">
                        <input type="checkbox" name="delete_attachments[]" value="<?= $att['id'] ?>"> حذف المرفق
                      </label>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$editId): ?>
        <!-- User Account -->
        <hr style="border-color:var(--border);margin:24px 0">
        <h5 class="mb-3" style="color:var(--gold)"><i class="bi bi-key me-2"></i>حساب الدخول (اختياري)</h5>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="createUserToggle" name="create_user" value="1"
            onchange="toggleUserFields(this)">
          <label class="form-check-label me-2" for="createUserToggle">إنشاء حساب دخول للمستثمر</label>
        </div>
        <div id="userFields" style="display:none">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">اسم المستخدم</label>
              <input type="text" name="username" class="form-control" placeholder="investor_ali" autocomplete="off">
              <div class="form-text">سيستخدمه المستثمر لتسجيل الدخول</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">كلمة المرور</label>
              <div class="input-group">
                <input type="password" name="password" id="passwordField" class="form-control"
                  placeholder="6 أحرف على الأقل" autocomplete="new-password">
                <button type="button" class="btn btn-outline-gold" onclick="togglePwd()">
                  <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- Edit: show linked account -->
        <hr style="border-color:var(--border);margin:24px 0">
        <h5 class="mb-3" style="color:var(--gold)"><i class="bi bi-key me-2"></i>حساب الدخول</h5>
        <?php if ($linkedUser): ?>
          <div class="alert" style="background:var(--bg-card);border:1px solid var(--border)">
            <div class="d-flex align-items-center mb-3">
              <i class="bi bi-person-check me-2 fs-4" style="color:var(--gold)"></i>
              <div>
                <div class="fw-bold">مرتبط بحساب دخول: <span
                    class="text-gold"><?= htmlspecialchars($linkedUser['username']) ?></span></div>
                <small class="text-muted">يمكنك هنا تغيير كلمة المرور فقط.</small>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">كلمة المرور الجديدة</label>
                <div class="input-group">
                  <input type="password" name="password" id="passwordFieldEdit" class="form-control"
                    placeholder="اتركها فارغة إذا لا تريد التغيير" autocomplete="new-password">
                  <button type="button" class="btn btn-outline-gold" onclick="togglePwdEdit()">
                    <i class="bi bi-eye" id="eyeIconEdit"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="alert" style="background:var(--bg-card);border:1px solid var(--border);">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" name="create_user" id="createUserEdit"
                onchange="toggleUserFieldsEdit()">
              <label class="form-check-label fw-bold text-gold" for="createUserEdit">
                <i class="bi bi-person-plus-fill me-1"></i> إنشاء حساب دخول لهذا المستثمر الآن
              </label>
            </div>

            <div id="userFieldsEdit" style="display:none">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">اسم المستخدم</label>
                  <input type="text" name="username" class="form-control" autocomplete="off">
                </div>
                <div class="col-md-6">
                  <label class="form-label">كلمة المرور</label>
                  <div class="input-group">
                    <input type="password" name="password" id="passwordFieldNew" class="form-control"
                      placeholder="6 خانات على الأقل">
                    <button type="button" class="btn btn-outline-gold" onclick="togglePwdNew()">
                      <i class="bi bi-eye" id="eyeIconNew"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-gold px-4">
          <i class="bi bi-check-circle me-1"></i>
          <?= $editId ? 'حفظ التعديلات' : 'إضافة المستثمر' ?>
        </button>
        <a href="investors.php" class="btn btn-outline-gold">إلغاء</a>
      </div>
      </form>
    </div>

  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script>
    function toggleUserFields(cb) {
      document.getElementById('userFields').style.display = cb.checked ? 'block' : 'none';
    }
    function toggleUserFieldsEdit() {
      var cb = document.getElementById('createUserEdit');
      document.getElementById('userFieldsEdit').style.display = cb.checked ? 'block' : 'none';
    }
    function togglePwd() {
      var f = document.getElementById('passwordField');
      var i = document.getElementById('eyeIcon');
      if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
      else { f.type = 'password'; i.className = 'bi bi-eye'; }
    }
    function togglePwdEdit() {
      var f = document.getElementById('passwordFieldEdit');
      var i = document.getElementById('eyeIconEdit');
      if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
      else { f.type = 'password'; i.className = 'bi bi-eye'; }
    }
    function togglePwdNew() {
      var f = document.getElementById('passwordFieldNew');
      var i = document.getElementById('eyeIconNew');
      if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
      else { f.type = 'password'; i.className = 'bi bi-eye'; }
    }
  </script>