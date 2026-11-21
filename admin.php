<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$editor = require_editor();
$error = '';
$notice = pop_flash();

$editArticle = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId) {
    $editArticle = db_fetch_one('SELECT * FROM Articles WHERE ArticleID = ?', [$editId]);
}

$editSymptom = null;
$editSymptomId = filter_input(INPUT_GET, 'edit_symptom', FILTER_VALIDATE_INT);
if ($editSymptomId) {
    $editSymptom = db_fetch_one('SELECT * FROM Symptoms WHERE SymptomID = ?', [$editSymptomId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        // 1. BÀI VIẾT & DANH MỤC
        if ($action === 'save_article') {
            $id = filter_input(INPUT_POST, 'article_id', FILTER_VALIDATE_INT) ?: 0;
            $title = trim((string)($_POST['title'] ?? ''));
            $summary = trim((string)($_POST['summary'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
            $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'draft';
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

            if ($title === '' || $summary === '' || $body === '' || !$categoryId) {
                throw new RuntimeException('Nhập đầy đủ tiêu đề, mô tả, nội dung và danh mục.');
            }

            $coverImage = trim((string)($_POST['cover_image'] ?? '')) ?: null;
            $sourceName = trim((string)($_POST['source_name'] ?? '')) ?: null;
            $sourceUrl = trim((string)($_POST['source_url'] ?? '')) ?: null;
            $reviewedBy = trim((string)($_POST['reviewed_by'] ?? '')) ?: null;
            $publishedAt = $status === 'published' ? gmdate('Y-m-d H:i:s') : null;

            if ($id) {
                db_exec(
                    "UPDATE Articles
                     SET CategoryID = ?, Title = ?, Summary = ?, Body = ?, CoverImage = ?, SourceName = ?, SourceUrl = ?, ReviewedBy = ?,
                         Status = ?, IsFeatured = ?,
                         PublishedAt = CASE WHEN ? = 'published' THEN COALESCE(PublishedAt, ?) ELSE NULL END,
                         UpdatedAt = SYSUTCDATETIME()
                     WHERE ArticleID = ?",
                    [$categoryId, $title, $summary, $body, $coverImage, $sourceName, $sourceUrl, $reviewedBy, $status, $isFeatured, $status, $publishedAt, $id]
                );
            } else {
                $slugInput = trim((string)($_POST['slug'] ?? ''));
                $slug = create_slug($slugInput !== '' ? $slugInput : $title) . '-' . bin2hex(random_bytes(3));
                db_exec(
                    'INSERT INTO Articles (CategoryID, AuthorID, Title, Slug, Summary, Body, CoverImage, SourceName, SourceUrl, ReviewedBy, Status, IsFeatured, PublishedAt)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$categoryId, $editor['id'], $title, $slug, $summary, $body, $coverImage, $sourceName, $sourceUrl, $reviewedBy, $status, $isFeatured, $publishedAt]
                );
            }
            flash('Đã lưu bài viết.');
            redirect('admin.php#articles');
        }

        if ($action === 'delete_article') {
            $articleId = filter_input(INPUT_POST, 'article_id', FILTER_VALIDATE_INT);
            if ($articleId) {
                db_exec('DELETE FROM Articles WHERE ArticleID = ?', [$articleId]);
                flash('Đã xóa bài viết.');
            }
            redirect('admin.php#articles');
        }

        if ($action === 'save_category') {
            $catName = trim((string)($_POST['category_name'] ?? ''));
            $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT) ?: 0;
            if ($catName === '') {
                throw new RuntimeException('Tên danh mục không được để trống.');
            }
            $slug = create_slug($catName) . '-' . bin2hex(random_bytes(2));
            db_exec('INSERT INTO Categories (Name, Slug, SortOrder) VALUES (?, ?, ?)', [$catName, $slug, $sortOrder]);
            flash('Đã thêm danh mục mới.');
            redirect('admin.php#categories');
        }

        if ($action === 'toggle_category') {
            $catId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
            if ($catId) {
                db_exec('UPDATE Categories SET IsActive = CASE WHEN IsActive = 1 THEN 0 ELSE 1 END WHERE CategoryID = ?', [$catId]);
                flash('Đã cập nhật trạng thái danh mục.');
            }
            redirect('admin.php#categories');
        }

        // 2. TRIỆU CHỨNG & CẢNH BÁO SỨC KHỎE
        if ($action === 'save_symptom') {
            $symId = filter_input(INPUT_POST, 'symptom_id', FILTER_VALIDATE_INT) ?: 0;
            $name = trim((string)($_POST['symptom_name'] ?? ''));
            $description = trim((string)($_POST['symptom_description'] ?? ''));
            $specialty = trim((string)($_POST['symptom_specialty'] ?? ''));
            $warning = trim((string)($_POST['warning_text'] ?? '')) ?: null;
            if ($name === '' || $description === '' || $specialty === '') {
                throw new RuntimeException('Nhập tên triệu chứng, mô tả và chuyên khoa gợi ý.');
            }
            if ($symId) {
                db_exec(
                    'UPDATE Symptoms SET Name = ?, Description = ?, Specialty = ?, WarningText = ? WHERE SymptomID = ?',
                    [$name, $description, $specialty, $warning, $symId]
                );
                flash('Đã cập nhật thông tin triệu chứng.');
            } else {
                $slug = create_slug($name) . '-' . bin2hex(random_bytes(3));
                db_exec(
                    'INSERT INTO Symptoms (Name, Slug, Description, Specialty, WarningText) VALUES (?, ?, ?, ?, ?)',
                    [$name, $slug, $description, $specialty, $warning]
                );
                flash('Đã thêm triệu chứng mới.');
            }
            redirect('admin.php#symptoms');
        }

        if ($action === 'delete_symptom') {
            $symId = filter_input(INPUT_POST, 'symptom_id', FILTER_VALIDATE_INT);
            if ($symId) {
                db_exec('DELETE FROM Symptoms WHERE SymptomID = ?', [$symId]);
                flash('Đã xóa triệu chứng.');
            }
            redirect('admin.php#symptoms');
        }

        if ($action === 'link_symptom_article') {
            $symptomId = filter_input(INPUT_POST, 'symptom_id', FILTER_VALIDATE_INT);
            $articleId = filter_input(INPUT_POST, 'article_id', FILTER_VALIDATE_INT);
            if (!$symptomId || !$articleId) {
                throw new RuntimeException('Chọn triệu chứng và bài viết.');
            }
            db_exec(
                'IF NOT EXISTS (SELECT 1 FROM SymptomArticles WHERE SymptomID = ? AND ArticleID = ?)
                 INSERT INTO SymptomArticles (SymptomID, ArticleID) VALUES (?, ?)',
                [$symptomId, $articleId, $symptomId, $articleId]
            );
            flash('Đã gắn bài viết vào triệu chứng.');
            redirect('admin.php#symptoms');
        }

        if ($action === 'link_symptom_facility') {
            $symptomId = filter_input(INPUT_POST, 'symptom_id', FILTER_VALIDATE_INT);
            $facilityId = filter_input(INPUT_POST, 'facility_id', FILTER_VALIDATE_INT);
            if (!$symptomId || !$facilityId) {
                throw new RuntimeException('Chọn triệu chứng và cơ sở y tế.');
            }
            db_exec(
                'IF NOT EXISTS (SELECT 1 FROM SymptomFacilities WHERE SymptomID = ? AND FacilityID = ?)
                 INSERT INTO SymptomFacilities (SymptomID, FacilityID) VALUES (?, ?)',
                [$symptomId, $facilityId, $symptomId, $facilityId]
            );
            flash('Đã gắn cơ sở vào triệu chứng.');
            redirect('admin.php#symptoms');
        }

        // 3. CƠ SỞ Y TẾ & BÁC SĨ
        if ($action === 'save_facility') {
            $name = trim((string)($_POST['name'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $city = trim((string)($_POST['city'] ?? ''));
            $type = in_array($_POST['type'] ?? '', ['hospital', 'clinic'], true) ? $_POST['type'] : 'clinic';
            $website = trim((string)($_POST['website'] ?? ''));
            if ($name === '' || $address === '' || $city === '') {
                throw new RuntimeException('Tên cơ sở, địa chỉ và tỉnh/thành là bắt buộc.');
            }
            if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Đường dẫn website không hợp lệ.');
            }
            db_exec(
                'INSERT INTO Facilities (Name, FacilityType, Address, City, Phone, WebsiteUrl, Specialties, IsVerified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $type, $address, $city, trim((string)($_POST['phone'] ?? '')) ?: null, $website ?: null, trim((string)($_POST['specialties'] ?? '')) ?: null, isset($_POST['verified']) ? 1 : 0]
            );
            flash('Đã thêm cơ sở y tế.');
            redirect('admin.php#facilities');
        }

        if ($action === 'toggle_facility_verify') {
            $facId = filter_input(INPUT_POST, 'facility_id', FILTER_VALIDATE_INT);
            if ($facId) {
                db_exec('UPDATE Facilities SET IsVerified = CASE WHEN IsVerified = 1 THEN 0 ELSE 1 END WHERE FacilityID = ?', [$facId]);
                flash('Đã cập nhật trạng thái xác minh cơ sở.');
            }
            redirect('admin.php#facilities');
        }

        if ($action === 'toggle_facility_active') {
            $facId = filter_input(INPUT_POST, 'facility_id', FILTER_VALIDATE_INT);
            if ($facId) {
                db_exec('UPDATE Facilities SET IsActive = CASE WHEN IsActive = 1 THEN 0 ELSE 1 END WHERE FacilityID = ?', [$facId]);
                flash('Đã cập nhật trạng thái hiển thị cơ sở.');
            }
            redirect('admin.php#facilities');
        }

        if ($action === 'save_doctor') {
            $facilityId = filter_input(INPUT_POST, 'facility_id', FILTER_VALIDATE_INT);
            $name = trim((string)($_POST['doctor_name'] ?? ''));
            $specialty = trim((string)($_POST['doctor_specialty'] ?? ''));
            if (!$facilityId || $name === '' || $specialty === '') {
                throw new RuntimeException('Chọn cơ sở và nhập tên bác sĩ, chuyên khoa.');
            }
            db_exec('INSERT INTO Doctors (FacilityID, DisplayName, Specialty) VALUES (?, ?, ?)', [$facilityId, $name, $specialty]);
            flash('Đã thêm bác sĩ.');
            redirect('admin.php#slots');
        }

        if ($action === 'toggle_doctor') {
            $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
            if ($doctorId) {
                db_exec('UPDATE Doctors SET IsActive = CASE WHEN IsActive = 1 THEN 0 ELSE 1 END WHERE DoctorID = ?', [$doctorId]);
                flash('Đã cập nhật trạng thái bác sĩ.');
            }
            redirect('admin.php#slots');
        }

        // 4. LỊCH KHÁM & DUYỆT LỊCH HẸN
        if ($action === 'save_slot') {
            $facilityId = filter_input(INPUT_POST, 'facility_id', FILTER_VALIDATE_INT);
            $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT) ?: null;
            $startRaw = (string)($_POST['starts_at'] ?? '');
            $endRaw = (string)($_POST['ends_at'] ?? '');
            $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT) ?: 1;

            $start = DateTime::createFromFormat('Y-m-d\TH:i', $startRaw);
            $end = DateTime::createFromFormat('Y-m-d\TH:i', $endRaw);
            if (!$facilityId || !$start || !$end || $end <= $start || $capacity < 1 || $start <= new DateTime()) {
                throw new RuntimeException('Kiểm tra lại cơ sở, khung giờ tương lai và số chỗ.');
            }
            $startUtc = (clone $start)->setTimezone(new DateTimeZone('UTC'));
            $endUtc = (clone $end)->setTimezone(new DateTimeZone('UTC'));
            db_exec(
                'INSERT INTO AppointmentSlots (FacilityID, DoctorID, StartsAt, EndsAt, Capacity) VALUES (?, ?, ?, ?, ?)',
                [$facilityId, $doctorId, $startUtc->format('Y-m-d H:i:s'), $endUtc->format('Y-m-d H:i:s'), $capacity]
            );
            flash('Đã thêm khung giờ khám mô phỏng.');
            redirect('admin.php#slots');
        }

        if ($action === 'update_appointment_status') {
            $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
            $newStatus = (string)($_POST['status'] ?? '');
            if ($appointmentId && in_array($newStatus, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
                db_exec('UPDATE Appointments SET Status = ? WHERE AppointmentID = ?', [$newStatus, $appointmentId]);
                flash('Đã cập nhật trạng thái lịch hẹn.');
            }
            redirect('admin.php#appointments-manage');
        }

        // 5. QUẢN LÝ TÀI KHOẢN & PHÂN QUYỀN (Chỉ Admin)
        if ($action === 'update_user_role') {
            if (!is_admin()) {
                throw new RuntimeException('Chỉ tài khoản Admin mới có quyền phân quyền người dùng.');
            }
            $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $role = (string)($_POST['role'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (!$targetUserId || !in_array($role, ['member', 'editor', 'admin'], true)) {
                throw new RuntimeException('Dữ liệu tài khoản không hợp lệ.');
            }
            if ($targetUserId === (int)$editor['id'] && ($role !== 'admin' || $isActive === 0)) {
                throw new RuntimeException('Không thể tự hạ quyền hoặc tự khóa tài khoản đang đăng nhập.');
            }
            db_exec('UPDATE Users SET Role = ?, IsActive = ? WHERE UserID = ?', [$role, $isActive, $targetUserId]);
            flash('Đã cập nhật quyền và trạng thái tài khoản.');
            redirect('admin.php#users-manage');
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

// Thống kê tổng quan
$stats = [
    'articles' => (int)db_fetch_val("SELECT COUNT(*) FROM Articles WHERE Status = 'published'"),
    'symptoms' => (int)db_fetch_val('SELECT COUNT(*) FROM Symptoms'),
    'facilities' => (int)db_fetch_val('SELECT COUNT(*) FROM Facilities WHERE IsActive = 1'),
    'doctors' => (int)db_fetch_val('SELECT COUNT(*) FROM Doctors WHERE IsActive = 1'),
    'pending_appts' => (int)db_fetch_val("SELECT COUNT(*) FROM Appointments WHERE Status = 'pending'"),
    'users' => (int)db_fetch_val('SELECT COUNT(*) FROM Users'),
];

$categories = db_fetch_all('SELECT CategoryID, Name, SortOrder, IsActive FROM Categories ORDER BY SortOrder, Name');
$articles = db_fetch_all(
    'SELECT TOP 30 a.ArticleID, a.Title, a.Status, a.IsFeatured, a.UpdatedAt, c.Name AS CategoryName
     FROM Articles a
     JOIN Categories c ON c.CategoryID = a.CategoryID
     ORDER BY a.UpdatedAt DESC'
);
$facilities = db_fetch_all('SELECT FacilityID, Name, City, Specialties, IsVerified, IsActive FROM Facilities ORDER BY City, Name');
$doctors = db_fetch_all(
    'SELECT d.DoctorID, d.DisplayName, d.Specialty, d.IsActive, f.Name AS FacilityName
     FROM Doctors d
     JOIN Facilities f ON f.FacilityID = d.FacilityID
     ORDER BY f.Name, d.DisplayName'
);
$symptoms = db_fetch_all('SELECT SymptomID, Name, Specialty, WarningText FROM Symptoms ORDER BY Name');

$missingKeywords = [];
try {
    $missingKeywords = db_fetch_all(
        'SELECT TOP 20 Keyword, COUNT(*) AS Times, MAX(CreatedAt) AS LastSeen
         FROM SearchLogs
         WHERE FoundCount = 0
         GROUP BY Keyword
         ORDER BY Times DESC, LastSeen DESC'
    );
} catch (RuntimeException) {
    $missingKeywords = [];
}

$slots = db_fetch_all(
    "SELECT TOP 25 s.SlotID, s.StartsAt, s.EndsAt, s.Capacity, f.Name AS FacilityName, d.DisplayName AS DoctorName,
            (SELECT COUNT(*) FROM Appointments a WHERE a.SlotID = s.SlotID AND a.Status IN ('pending', 'confirmed')) AS Booked
     FROM AppointmentSlots s
     JOIN Facilities f ON f.FacilityID = s.FacilityID
     LEFT JOIN Doctors d ON d.DoctorID = s.DoctorID
     WHERE s.IsActive = 1
     ORDER BY s.StartsAt DESC"
);

$appointmentsAll = db_fetch_all(
    'SELECT TOP 30 a.AppointmentID, a.PatientName, a.Note, a.Status, a.CreatedAt,
            u.Email AS UserEmail, s.StartsAt, f.Name AS FacilityName, d.DisplayName AS DoctorName
     FROM Appointments a
     JOIN Users u ON u.UserID = a.UserID
     JOIN AppointmentSlots s ON s.SlotID = a.SlotID
     JOIN Facilities f ON f.FacilityID = s.FacilityID
     LEFT JOIN Doctors d ON d.DoctorID = s.DoctorID
     ORDER BY a.CreatedAt DESC'
);

$usersList = is_admin()
    ? db_fetch_all('SELECT UserID, DisplayName, Email, Role, IsActive, CreatedAt FROM Users ORDER BY CreatedAt DESC')
    : [];

$pageTitle = 'Quản trị HealthHub';
$activeNav = 'admin';
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="page-shell">
  <div class="wrap">
    <div class="page-header">
      <div class="eyebrow"><span class="eyebrow-dot"></span>HEALTHHUB CONTENT &amp; CLINIC STUDIO</div>
      <h1>Khu vực quản trị hệ thống</h1>
      <p class="section-sub">
        Đăng nhập với vai trò <strong><?= e($editor['role']) ?></strong>. Quản lý bài viết, danh mục, triệu chứng, cơ sở y tế, bác sĩ, duyệt lịch hẹn<?= is_admin() ? ' và phân quyền tài khoản' : '' ?>.
      </p>
    </div>

    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <!-- THỐNG KÊ TỔNG QUAN -->
    <section class="stats-grid" aria-label="Thống kê nhanh">
      <div class="stat-card"><span>Bài đã xuất bản</span><strong><?= $stats['articles'] ?></strong></div>
      <div class="stat-card"><span>Nhóm triệu chứng</span><strong><?= $stats['symptoms'] ?></strong></div>
      <div class="stat-card"><span>Cơ sở hoạt động</span><strong><?= $stats['facilities'] ?></strong></div>
      <div class="stat-card"><span>Bác sĩ</span><strong><?= $stats['doctors'] ?></strong></div>
      <div class="stat-card"><span>Lịch chờ duyệt</span><strong><?= $stats['pending_appts'] ?></strong></div>
      <div class="stat-card"><span>Tài khoản</span><strong><?= $stats['users'] ?></strong></div>
    </section>

    <div class="quick-chips" style="margin-bottom:20px;">
      <a class="chip" href="#articles">01. Bài viết</a>
      <a class="chip" href="#categories">01B. Danh mục</a>
      <a class="chip" href="#symptoms">02. Triệu chứng</a>
      <a class="chip" href="#missing-keywords">02B. Từ khóa thiếu</a>
      <a class="chip" href="#facilities">03. Cơ sở y tế</a>
      <a class="chip" href="#slots">04. Bác sĩ &amp; Lịch khám</a>
      <a class="chip" href="#appointments-manage">04B. Duyệt lịch hẹn</a>
      <?php if (is_admin()): ?><a class="chip" href="#users-manage">05. Tài khoản &amp; Phân quyền</a><?php endif; ?>
    </div>

    <!-- 01. BÀI VIẾT -->
    <section class="panel" id="articles">
      <div class="panel-head">
        <div>
          <div class="eyebrow">01 · BIÊN TẬP NỘI DUNG</div>
          <h2><?= $editArticle ? 'Chỉnh sửa bài viết' : 'Thêm bài viết sức khỏe mới' ?></h2>
        </div>
      </div>

      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_article">
        <input type="hidden" name="article_id" value="<?= (int)($editArticle['ArticleID'] ?? 0) ?>">

        <div class="form-grid-2">
          <label class="field">
            <span>Tiêu đề</span>
            <input class="input" name="title" maxlength="220" required value="<?= e($editArticle['Title'] ?? '') ?>">
          </label>
          <label class="field">
            <span>Slug URL</span>
            <input class="input" name="slug" maxlength="200" placeholder="<?= e($editArticle['Slug'] ?? 'Tự tạo từ tiêu đề') ?>" <?= $editArticle ? 'disabled' : '' ?>>
          </label>
          <label class="field wide-field">
            <span>Mô tả ngắn</span>
            <textarea class="textarea" name="summary" rows="2" maxlength="500" required><?= e($editArticle['Summary'] ?? '') ?></textarea>
          </label>
          <label class="field wide-field">
            <span>Nội dung bài viết</span>
            <textarea class="textarea" name="body" rows="6" required><?= e($editArticle['Body'] ?? '') ?></textarea>
          </label>
          <label class="field">
            <span>Danh mục</span>
            <select class="select" name="category_id" required>
              <option value="">Chọn danh mục</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['CategoryID'] ?>" <?= (int)($editArticle['CategoryID'] ?? 0) === (int)$cat['CategoryID'] ? 'selected' : '' ?>>
                  <?= e($cat['Name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">
            <span>Trạng thái</span>
            <select class="select" name="status">
              <option value="draft" <?= ($editArticle['Status'] ?? '') === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
              <option value="published" <?= ($editArticle['Status'] ?? '') === 'published' ? 'selected' : '' ?>>Xuất bản</option>
              <option value="archived" <?= ($editArticle['Status'] ?? '') === 'archived' ? 'selected' : '' ?>>Lưu trữ</option>
            </select>
          </label>
          <label class="field">
            <span>Ảnh bìa (đường dẫn hoặc URL)</span>
            <input class="input" name="cover_image" value="<?= e($editArticle['CoverImage'] ?? '') ?>" placeholder="assets/img/photo-f51439323.webp">
          </label>
          <label class="field">
            <span>Nguồn tham khảo</span>
            <input class="input" name="source_name" value="<?= e($editArticle['SourceName'] ?? '') ?>" placeholder="Ví dụ: Bộ Y tế, WHO">
          </label>
          <label class="field">
            <span>Liên kết nguồn</span>
            <input class="input" name="source_url" type="url" value="<?= e($editArticle['SourceUrl'] ?? '') ?>" placeholder="https://...">
          </label>
          <label class="field">
            <span>Người rà soát chuyên môn</span>
            <input class="input" name="reviewed_by" value="<?= e($editArticle['ReviewedBy'] ?? '') ?>">
          </label>
          <label class="check-inline wide-field">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($editArticle['IsFeatured']) ? 'checked' : '' ?>>
            Đánh dấu là bài viết nổi bật
          </label>
        </div>

        <div style="margin-top:14px; display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary">Lưu bài viết</button>
          <?php if ($editArticle): ?><a class="btn btn-secondary" href="admin.php#articles">Hủy sửa</a><?php endif; ?>
        </div>
      </form>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Tiêu đề</th><th>Danh mục</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr>
          </thead>
          <tbody>
            <?php foreach ($articles as $art): ?>
              <tr>
                <td>
                  <strong><?= e($art['Title']) ?></strong>
                  <?= $art['IsFeatured'] ? '<span class="pill pill-done" style="margin-left:6px;">Nổi bật</span>' : '' ?>
                </td>
                <td><?= e($art['CategoryName']) ?></td>
                <td><span class="pill <?= $art['Status'] === 'published' ? 'pill-ok' : 'pill-wait' ?>"><?= e($art['Status']) ?></span></td>
                <td><?= local_dt($art['UpdatedAt'], 'd/m/Y') ?></td>
                <td style="display:flex; gap:8px;">
                  <a class="btn btn-secondary btn-sm" href="admin.php?edit=<?= (int)$art['ArticleID'] ?>#articles">Sửa</a>
                  <form method="post" style="margin:0;" onsubmit="return confirm('Xóa bài viết này?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_article">
                    <input type="hidden" name="article_id" value="<?= (int)$art['ArticleID'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 01B. DANH MỤC -->
    <section class="panel" id="categories">
      <div class="panel-head">
        <div>
          <div class="eyebrow">01B · DANH MỤC BÀI VIẾT</div>
          <h2>Quản lý chuyên mục</h2>
        </div>
      </div>
      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_category">
        <div class="form-grid-2">
          <label class="field"><span>Tên danh mục</span><input class="input" name="category_name" required></label>
          <label class="field"><span>Thứ tự hiển thị</span><input class="input" type="number" name="sort_order" value="0"></label>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:14px;">Thêm danh mục</button>
      </form>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Tên danh mục</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td><?= e($cat['Name']) ?></td>
                <td><?= (int)$cat['SortOrder'] ?></td>
                <td><span class="pill <?= $cat['IsActive'] ? 'pill-ok' : 'pill-off' ?>"><?= $cat['IsActive'] ? 'Đang bật' : 'Đang ẩn' ?></span></td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_category">
                    <input type="hidden" name="category_id" value="<?= (int)$cat['CategoryID'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $cat['IsActive'] ? 'Ẩn' : 'Bật' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 02. TRIỆU CHỨNG & CẢNH BÁO -->
    <section class="panel" id="symptoms">
      <div class="panel-head">
        <div>
          <div class="eyebrow">02 · THÔNG TIN BỆNH &amp; CẢNH BÁO</div>
          <h2><?= $editSymptom ? 'Sửa triệu chứng' : 'Quản lý triệu chứng &amp; cảnh báo sức khỏe' ?></h2>
        </div>
      </div>

      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_symptom">
        <input type="hidden" name="symptom_id" value="<?= (int)($editSymptom['SymptomID'] ?? 0) ?>">
        <div class="form-grid-2">
          <label class="field"><span>Tên triệu chứng</span><input class="input" name="symptom_name" required value="<?= e($editSymptom['Name'] ?? '') ?>"></label>
          <label class="field"><span>Chuyên khoa gợi ý</span><input class="input" name="symptom_specialty" required value="<?= e($editSymptom['Specialty'] ?? '') ?>"></label>
          <label class="field wide-field"><span>Mô tả tham khảo</span><textarea class="textarea" name="symptom_description" rows="2" required><?= e($editSymptom['Description'] ?? '') ?></textarea></label>
          <label class="field wide-field"><span>Cảnh báo dấu hiệu nguy hiểm</span><textarea class="textarea" name="warning_text" rows="2"><?= e($editSymptom['WarningText'] ?? '') ?></textarea></label>
        </div>
        <div style="margin-top:14px; display:flex; gap:10px;">
          <button type="submit" class="btn btn-primary"><?= $editSymptom ? 'Cập nhật triệu chứng' : 'Thêm triệu chứng' ?></button>
          <?php if ($editSymptom): ?><a class="btn btn-secondary" href="admin.php#symptoms">Hủy sửa</a><?php endif; ?>
        </div>
      </form>

      <div class="cards-2" style="margin-bottom:20px;">
        <form method="post" class="admin-form" style="margin:0;">
          <h3 style="margin:0 0 12px;">Gắn bài viết liên quan</h3>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="link_symptom_article">
          <label class="field" style="margin-bottom:10px;"><span>Triệu chứng</span>
            <select class="select" name="symptom_id" required>
              <option value="">Chọn triệu chứng</option>
              <?php foreach ($symptoms as $sym): ?><option value="<?= (int)$sym['SymptomID'] ?>"><?= e($sym['Name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field" style="margin-bottom:12px;"><span>Bài viết</span>
            <select class="select" name="article_id" required>
              <option value="">Chọn bài viết</option>
              <?php foreach ($articles as $art): ?><option value="<?= (int)$art['ArticleID'] ?>"><?= e($art['Title']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <button type="submit" class="btn btn-secondary btn-sm">Liên kết bài viết</button>
        </form>

        <form method="post" class="admin-form" style="margin:0;">
          <h3 style="margin:0 0 12px;">Gắn cơ sở khám liên quan</h3>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="link_symptom_facility">
          <label class="field" style="margin-bottom:10px;"><span>Triệu chứng</span>
            <select class="select" name="symptom_id" required>
              <option value="">Chọn triệu chứng</option>
              <?php foreach ($symptoms as $sym): ?><option value="<?= (int)$sym['SymptomID'] ?>"><?= e($sym['Name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field" style="margin-bottom:12px;"><span>Cơ sở y tế</span>
            <select class="select" name="facility_id" required>
              <option value="">Chọn cơ sở</option>
              <?php foreach ($facilities as $fac): ?><option value="<?= (int)$fac['FacilityID'] ?>"><?= e($fac['Name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <button type="submit" class="btn btn-secondary btn-sm">Liên kết cơ sở</button>
        </form>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Triệu chứng</th><th>Chuyên khoa</th><th>Cảnh báo</th><th>Thao tác</th></tr></thead>
          <tbody>
            <?php foreach ($symptoms as $sym): ?>
              <tr>
                <td><strong><?= e($sym['Name']) ?></strong></td>
                <td><?= e($sym['Specialty']) ?></td>
                <td><?= e($sym['WarningText'] ?: '—') ?></td>
                <td style="display:flex; gap:8px;">
                  <a class="btn btn-secondary btn-sm" href="admin.php?edit_symptom=<?= (int)$sym['SymptomID'] ?>#symptoms">Sửa</a>
                  <form method="post" style="margin:0;" onsubmit="return confirm('Xóa triệu chứng này?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_symptom">
                    <input type="hidden" name="symptom_id" value="<?= (int)$sym['SymptomID'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 02B. TỪ KHÓA THIẾU -->
    <section class="panel" id="missing-keywords">
      <div class="panel-head">
        <div>
          <div class="eyebrow">02B · THỐNG KÊ TÌM KIẾM</div>
          <h2>Từ khóa tra cứu chưa có dữ liệu</h2>
        </div>
      </div>
      <?php if ($missingKeywords): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Từ khóa người dùng tìm</th><th>Số lượt tìm</th><th>Lần gần nhất</th></tr></thead>
            <tbody>
              <?php foreach ($missingKeywords as $row): ?>
                <tr>
                  <td><?= e($row['Keyword']) ?></td>
                  <td><?= (int)$row['Times'] ?></td>
                  <td><?= local_dt($row['LastSeen']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">Chưa ghi nhận từ khóa nào thiếu dữ liệu.</div>
      <?php endif; ?>
    </section>

    <!-- 03. CƠ SỞ Y TẾ -->
    <section class="panel" id="facilities">
      <div class="panel-head">
        <div>
          <div class="eyebrow">03 · DANH BẠ Y TẾ</div>
          <h2>Quản lý bệnh viện &amp; phòng khám</h2>
        </div>
      </div>
      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_facility">
        <div class="form-grid-2">
          <label class="field"><span>Tên cơ sở</span><input class="input" name="name" required></label>
          <label class="field"><span>Loại hình</span>
            <select class="select" name="type">
              <option value="hospital">Bệnh viện</option>
              <option value="clinic">Phòng khám</option>
            </select>
          </label>
          <label class="field wide-field"><span>Địa chỉ</span><input class="input" name="address" required></label>
          <label class="field"><span>Tỉnh/thành</span><input class="input" name="city" required></label>
          <label class="field"><span>Điện thoại</span><input class="input" name="phone"></label>
          <label class="field"><span>Chuyên khoa (ngăn cách ;)</span><input class="input" name="specialties" placeholder="Nội tổng quát; Tiêu hóa"></label>
          <label class="field"><span>Website chính thức</span><input class="input" name="website" type="url" placeholder="https://..."></label>
          <label class="check-inline wide-field"><input type="checkbox" name="verified" value="1"> Đã đối chiếu nguồn chính thức</label>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:14px;">Thêm cơ sở</button>
      </form>

      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Cơ sở</th><th>Khu vực</th><th>Chuyên khoa</th><th>Xác minh</th><th>Hiển thị</th></tr></thead>
          <tbody>
            <?php foreach ($facilities as $fac): ?>
              <tr>
                <td><strong><?= e($fac['Name']) ?></strong></td>
                <td><?= e($fac['City']) ?></td>
                <td><?= e($fac['Specialties']) ?></td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_facility_verify">
                    <input type="hidden" name="facility_id" value="<?= (int)$fac['FacilityID'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $fac['IsVerified'] ? 'Đã xác minh' : 'Chưa xác minh' ?></button>
                  </form>
                </td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_facility_active">
                    <input type="hidden" name="facility_id" value="<?= (int)$fac['FacilityID'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $fac['IsActive'] ? 'Đang bật' : 'Đang ẩn' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 04. BÁC SĨ & KHUNG GIỜ -->
    <section class="panel" id="slots">
      <div class="panel-head">
        <div>
          <div class="eyebrow">04 · BÁC SĨ &amp; LỊCH KHÁM MÔ PHỎNG</div>
          <h2>Quản lý bác sĩ và khung giờ khám</h2>
        </div>
      </div>

      <div class="cards-2" style="margin-bottom:20px;">
        <form method="post" class="admin-form" style="margin:0;">
          <h3 style="margin:0 0 12px;">Thêm bác sĩ</h3>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_doctor">
          <label class="field" style="margin-bottom:10px;"><span>Cơ sở y tế</span>
            <select class="select" name="facility_id" required>
              <option value="">Chọn cơ sở</option>
              <?php foreach ($facilities as $fac): ?><option value="<?= (int)$fac['FacilityID'] ?>"><?= e($fac['Name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field" style="margin-bottom:10px;"><span>Tên bác sĩ</span><input class="input" name="doctor_name" required></label>
          <label class="field" style="margin-bottom:12px;"><span>Chuyên khoa</span><input class="input" name="doctor_specialty" required></label>
          <button type="submit" class="btn btn-primary btn-sm">Thêm bác sĩ</button>
        </form>

        <form method="post" class="admin-form" style="margin:0;">
          <h3 style="margin:0 0 12px;">Thêm khung giờ khám (giờ Việt Nam)</h3>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_slot">
          <label class="field" style="margin-bottom:10px;"><span>Cơ sở y tế</span>
            <select class="select" name="facility_id" required>
              <option value="">Chọn cơ sở</option>
              <?php foreach ($facilities as $fac): ?><option value="<?= (int)$fac['FacilityID'] ?>"><?= e($fac['Name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="field" style="margin-bottom:10px;"><span>Bác sĩ (không bắt buộc)</span>
            <select class="select" name="doctor_id">
              <option value="">Chưa gán bác sĩ</option>
              <?php foreach ($doctors as $doc): ?><option value="<?= (int)$doc['DoctorID'] ?>"><?= e($doc['DisplayName'] . ' · ' . $doc['FacilityName']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <div class="form-grid-2" style="margin-bottom:10px;">
            <label class="field"><span>Bắt đầu</span><input class="input" type="datetime-local" name="starts_at" required></label>
            <label class="field"><span>Kết thúc</span><input class="input" type="datetime-local" name="ends_at" required></label>
          </div>
          <label class="field" style="margin-bottom:12px;"><span>Số chỗ tối đa</span><input class="input" type="number" name="capacity" min="1" value="3" required></label>
          <button type="submit" class="btn btn-primary btn-sm">Thêm khung giờ</button>
        </form>
      </div>

      <div class="table-wrap" style="margin-bottom:20px;">
        <table class="data-table">
          <thead><tr><th>Bác sĩ</th><th>Chuyên khoa</th><th>Cơ sở</th><th>Trạng thái</th></tr></thead>
          <tbody>
            <?php foreach ($doctors as $doc): ?>
              <tr>
                <td><strong><?= e($doc['DisplayName']) ?></strong></td>
                <td><?= e($doc['Specialty']) ?></td>
                <td><?= e($doc['FacilityName']) ?></td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_doctor">
                    <input type="hidden" name="doctor_id" value="<?= (int)$doc['DoctorID'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $doc['IsActive'] ? 'Đang hoạt động' : 'Tạm nghỉ' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Cơ sở</th><th>Bác sĩ</th><th>Khung giờ</th><th>Đã đặt</th></tr></thead>
          <tbody>
            <?php foreach ($slots as $slot): ?>
              <tr>
                <td><?= e($slot['FacilityName']) ?></td>
                <td><?= e($slot['DoctorName'] ?: 'Chưa gán') ?></td>
                <td><?= local_dt($slot['StartsAt'], 'd/m/Y H:i') ?> – <?= local_dt($slot['EndsAt'], 'H:i') ?></td>
                <td><?= (int)$slot['Booked'] ?> / <?= (int)$slot['Capacity'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 04B. DUYỆT LỊCH HẸN -->
    <section class="panel" id="appointments-manage">
      <div class="panel-head">
        <div>
          <div class="eyebrow">04B · QUẢN LÝ LỊCH HẸN BỆNH NHÂN</div>
          <h2>Duyệt &amp; cập nhật trạng thái lịch hẹn</h2>
        </div>
      </div>
      <?php if ($appointmentsAll): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Người khám</th><th>Tài khoản</th><th>Cơ sở / Bác sĩ</th><th>Giờ khám</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead>
            <tbody>
              <?php foreach ($appointmentsAll as $ap): ?>
                <?php $st = appointment_status_meta($ap['Status']); ?>
                <tr>
                  <td><strong><?= e($ap['PatientName']) ?></strong><br><small style="color:var(--muted);"><?= e($ap['Note'] ?: '') ?></small></td>
                  <td><?= e($ap['UserEmail']) ?></td>
                  <td><?= e($ap['FacilityName']) ?><br><small style="color:var(--muted);"><?= e($ap['DoctorName'] ?: 'Chưa gán') ?></small></td>
                  <td><?= local_dt($ap['StartsAt']) ?></td>
                  <td><span class="pill <?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
                  <td>
                    <form method="post" style="display:flex; gap:6px; align-items:center; margin:0;">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="update_appointment_status">
                      <input type="hidden" name="appointment_id" value="<?= (int)$ap['AppointmentID'] ?>">
                      <select class="select" name="status" style="min-height:38px; padding:4px 8px; width:auto;">
                        <option value="pending" <?= $ap['Status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                        <option value="confirmed" <?= $ap['Status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                        <option value="completed" <?= $ap['Status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                        <option value="cancelled" <?= $ap['Status'] === 'cancelled' ? 'selected' : '' ?>>Hủy lịch</option>
                      </select>
                      <button type="submit" class="btn btn-primary btn-sm">Lưu</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">Chưa có lịch hẹn nào từ người dùng.</div>
      <?php endif; ?>
    </section>

    <!-- 05. QUẢN LÝ TÀI KHOẢN & PHÂN QUYỀN (Chỉ Admin) -->
    <?php if (is_admin()): ?>
      <section class="panel" id="users-manage">
        <div class="panel-head">
          <div>
            <div class="eyebrow">05 · PHÂN QUYỀN HỆ THỐNG</div>
            <h2>Quản lý tài khoản người dùng</h2>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Họ tên</th><th>Email</th><th>Ngày tạo</th><th>Vai trò &amp; Trạng thái</th></tr></thead>
            <tbody>
              <?php foreach ($usersList as $u): ?>
                <tr>
                  <td><strong><?= e($u['DisplayName']) ?></strong></td>
                  <td><?= e($u['Email']) ?></td>
                  <td><?= local_dt($u['CreatedAt'], 'd/m/Y') ?></td>
                  <td>
                    <form method="post" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:0;">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="update_user_role">
                      <input type="hidden" name="user_id" value="<?= (int)$u['UserID'] ?>">
                      <select class="select" name="role" style="min-height:38px; padding:4px 8px; width:auto;">
                        <option value="member" <?= $u['Role'] === 'member' ? 'selected' : '' ?>>member</option>
                        <option value="editor" <?= $u['Role'] === 'editor' ? 'selected' : '' ?>>editor</option>
                        <option value="admin" <?= $u['Role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                      </select>
                      <label class="check-inline">
                        <input type="checkbox" name="is_active" value="1" <?= $u['IsActive'] ? 'checked' : '' ?>> Hoạt động
                      </label>
                      <button type="submit" class="btn btn-secondary btn-sm">Cập nhật</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
