<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$user = require_login();
$error = '';
$notice = pop_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'book');

    try {
        if ($action === 'add_profile') {
            $name = trim((string)($_POST['display_name'] ?? ''));
            $relationship = trim((string)($_POST['relationship'] ?? ''));
            $birthDate = trim((string)($_POST['birth_date'] ?? ''));
            if ($name === '' || $relationship === '') {
                throw new RuntimeException('Nhập tên và mối quan hệ của người thân.');
            }
            db_exec(
                'INSERT INTO FamilyProfiles (UserID, DisplayName, Relationship, BirthDate) VALUES (?, ?, ?, ?)',
                [$user['id'], $name, $relationship, $birthDate !== '' ? $birthDate : null]
            );
            flash('Đã thêm hồ sơ người thân.');
            redirect('appointments.php#profiles');
        }

        if ($action === 'cancel') {
            $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
            if ($appointmentId) {
                db_exec(
                    "UPDATE Appointments SET Status = 'cancelled' WHERE AppointmentID = ? AND UserID = ? AND Status IN ('pending', 'confirmed')",
                    [$appointmentId, $user['id']]
                );
                flash('Đã hủy lịch hẹn trong hệ thống HealthHub.');
            }
            redirect('appointments.php#my-appointments');
        }

        // Đặt lịch khám mới (dùng Transaction + khóa dòng SQL Server để tránh vượt chỗ và đặt trùng giờ)
        $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
        $profileId = filter_input(INPUT_POST, 'profile_id', FILTER_VALIDATE_INT) ?: null;
        $note = trim((string)($_POST['note'] ?? ''));

        if (!$slotId) {
            throw new RuntimeException('Vui lòng chọn khung giờ khám hợp lệ.');
        }

        if ($profileId) {
            $profile = db_fetch_one(
                'SELECT ProfileID, DisplayName FROM FamilyProfiles WHERE ProfileID = ? AND UserID = ?',
                [$profileId, $user['id']]
            );
            if (!$profile) {
                throw new RuntimeException('Hồ sơ người thân không hợp lệ.');
            }
            $patientName = $profile['DisplayName'];
        } else {
            $patientName = $user['name'];
        }

        db_begin();

        $slot = db_fetch_one(
            'SELECT s.SlotID, s.StartsAt, s.EndsAt, s.Capacity
             FROM AppointmentSlots s WITH (UPDLOCK, HOLDLOCK)
             WHERE s.SlotID = ? AND s.IsActive = 1 AND s.StartsAt > SYSUTCDATETIME()',
            [$slotId]
        );
        if (!$slot) {
            throw new RuntimeException('Khung giờ này không còn hiệu lực hoặc đã qua giờ.');
        }

        $booked = (int)db_fetch_val(
            "SELECT COUNT(*) FROM Appointments WHERE SlotID = ? AND Status IN ('pending', 'confirmed')",
            [$slotId]
        );
        if ($booked >= (int)$slot['Capacity']) {
            throw new RuntimeException('Khung giờ này đã đủ chỗ. Vui lòng chọn giờ khác.');
        }

        // Hạn chế đặt trùng lịch cho cùng người đi khám trong khoảng thời gian giao nhau
        if ($profileId !== null) {
            $overlap = db_fetch_val(
                "SELECT TOP 1 a.AppointmentID
                 FROM Appointments a
                 JOIN AppointmentSlots s ON s.SlotID = a.SlotID
                 WHERE a.UserID = ? AND a.ProfileID = ? AND a.Status IN ('pending', 'confirmed')
                   AND s.StartsAt < ? AND s.EndsAt > ?",
                [$user['id'], $profileId, $slot['EndsAt'], $slot['StartsAt']]
            );
        } else {
            $overlap = db_fetch_val(
                "SELECT TOP 1 a.AppointmentID
                 FROM Appointments a
                 JOIN AppointmentSlots s ON s.SlotID = a.SlotID
                 WHERE a.UserID = ? AND a.ProfileID IS NULL AND a.Status IN ('pending', 'confirmed')
                   AND s.StartsAt < ? AND s.EndsAt > ?",
                [$user['id'], $slot['EndsAt'], $slot['StartsAt']]
            );
        }
        if ($overlap) {
            throw new RuntimeException('Người khám này đã có một lịch hẹn khác trùng khung giờ.');
        }

        db_exec(
            'INSERT INTO Appointments (UserID, ProfileID, SlotID, PatientName, Note) VALUES (?, ?, ?, ?, ?)',
            [$user['id'], $profileId, $slotId, $patientName, $note !== '' ? $note : null]
        );

        db_commit();
        flash('Đã gửi yêu cầu đặt lịch mô phỏng. Vui lòng xác nhận trực tiếp với cơ sở y tế trước khi đến.');
        redirect('appointments.php#my-appointments');
    } catch (RuntimeException $ex) {
        if (db()) {
            @db_rollback();
        }
        $error = $ex->getMessage();
    }
}

$city = trim((string)($_GET['city'] ?? ''));
$specialty = trim((string)($_GET['specialty'] ?? ''));
$facilityFilter = filter_input(INPUT_GET, 'facility_id', FILTER_VALIDATE_INT) ?: 0;
$doctorFilter = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;
$dateFilter = trim((string)($_GET['date'] ?? ''));

$sql = "SELECT s.SlotID, s.StartsAt, s.EndsAt, s.Capacity,
               f.FacilityID, f.Name AS FacilityName, f.FacilityType, f.Address, f.City, f.Phone, f.WebsiteUrl,
               d.DoctorID, d.DisplayName AS DoctorName, d.Specialty AS DoctorSpecialty,
               (SELECT COUNT(*) FROM Appointments a WHERE a.SlotID = s.SlotID AND a.Status IN ('pending', 'confirmed')) AS Booked
        FROM AppointmentSlots s
        JOIN Facilities f ON f.FacilityID = s.FacilityID
        LEFT JOIN Doctors d ON d.DoctorID = s.DoctorID
        WHERE s.IsActive = 1 AND f.IsActive = 1 AND s.StartsAt > SYSUTCDATETIME()";
$params = [];

if ($city !== '') {
    $sql .= ' AND f.City LIKE ?';
    $params[] = '%' . $city . '%';
}
if ($specialty !== '') {
    $sql .= ' AND (d.Specialty LIKE ? OR f.Specialties LIKE ?)';
    $params[] = '%' . $specialty . '%';
    $params[] = '%' . $specialty . '%';
}
if ($facilityFilter > 0) {
    $sql .= ' AND f.FacilityID = ?';
    $params[] = $facilityFilter;
}
if ($doctorFilter > 0) {
    $sql .= ' AND d.DoctorID = ?';
    $params[] = $doctorFilter;
}
if ($dateFilter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    // Chuyển ngày người dùng chọn theo giờ Việt Nam sang khoảng UTC để so khớp với cột StartsAt
    $dayStart = new DateTime($dateFilter . ' 00:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $dayEnd = new DateTime($dateFilter . ' 23:59:59', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $dayStart->setTimezone(new DateTimeZone('UTC'));
    $dayEnd->setTimezone(new DateTimeZone('UTC'));
    $sql .= ' AND s.StartsAt BETWEEN ? AND ?';
    $params[] = $dayStart->format('Y-m-d H:i:s');
    $params[] = $dayEnd->format('Y-m-d H:i:s');
}
$sql .= ' ORDER BY s.StartsAt';

$allSlots = db_fetch_all($sql, $params);
$availableSlots = array_values(array_filter(
    $allSlots,
    static fn(array $slot): bool => (int)$slot['Booked'] < (int)$slot['Capacity']
));

$facilitiesList = db_fetch_all('SELECT FacilityID, Name, City FROM Facilities WHERE IsActive = 1 ORDER BY City, Name');
$doctorsList = db_fetch_all(
    'SELECT d.DoctorID, d.DisplayName, d.Specialty, f.Name AS FacilityName
     FROM Doctors d
     JOIN Facilities f ON f.FacilityID = d.FacilityID
     WHERE d.IsActive = 1
     ORDER BY f.Name, d.DisplayName'
);
$profiles = db_fetch_all(
    'SELECT ProfileID, DisplayName, Relationship FROM FamilyProfiles WHERE UserID = ? ORDER BY DisplayName',
    [$user['id']]
);
$appointments = db_fetch_all(
    'SELECT a.AppointmentID, a.PatientName, a.Note, a.Status, a.CreatedAt,
            s.StartsAt, s.EndsAt, f.Name AS FacilityName, f.Address, f.City, d.DisplayName AS DoctorName, d.Specialty AS DoctorSpecialty
     FROM Appointments a
     JOIN AppointmentSlots s ON s.SlotID = a.SlotID
     JOIN Facilities f ON f.FacilityID = s.FacilityID
     LEFT JOIN Doctors d ON d.DoctorID = s.DoctorID
     WHERE a.UserID = ?
     ORDER BY a.CreatedAt DESC',
    [$user['id']]
);

$pageTitle = 'Đặt lịch khám trực tuyến | HealthHub';
$activeNav = 'appointments';
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="page-shell">
  <div class="wrap">
    <a class="back-link" href="index.php">← Quay lại trang chủ</a>
    <div class="page-header">
      <div class="eyebrow"><span class="eyebrow-dot"></span>ĐẶT LỊCH MÔ PHỎNG</div>
      <h1>Đặt lịch khám &amp; Theo dõi lịch hẹn</h1>
      <p class="section-sub">Lọc theo cơ sở y tế, bác sĩ, chuyên khoa và ngày khám. Hệ thống kiểm tra chỗ trống và tránh đặt trùng giờ cho cùng người khám.</p>
    </div>

    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <section class="panel">
      <div class="panel-head">
        <div>
          <div class="eyebrow">01 · CHỌN LỊCH KHÁM</div>
          <h2>Khung giờ còn trống (<?= count($availableSlots) ?>)</h2>
        </div>
      </div>

      <form method="get" class="admin-form">
        <div class="filter-grid" style="grid-template-columns: repeat(5, minmax(0,1fr));">
          <label class="field">
            <span>Cơ sở y tế</span>
            <select class="select" name="facility_id">
              <option value="0">Tất cả cơ sở</option>
              <?php foreach ($facilitiesList as $fac): ?>
                <option value="<?= (int)$fac['FacilityID'] ?>" <?= $facilityFilter === (int)$fac['FacilityID'] ? 'selected' : '' ?>>
                  <?= e($fac['Name']) ?> (<?= e($fac['City']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Bác sĩ</span>
            <select class="select" name="doctor_id">
              <option value="0">Tất cả bác sĩ</option>
              <?php foreach ($doctorsList as $doc): ?>
                <option value="<?= (int)$doc['DoctorID'] ?>" <?= $doctorFilter === (int)$doc['DoctorID'] ? 'selected' : '' ?>>
                  <?= e($doc['DisplayName']) ?> · <?= e($doc['Specialty']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="field">
            <span>Chuyên khoa</span>
            <input class="input" name="specialty" value="<?= e($specialty) ?>" placeholder="Ví dụ: Tiêu hóa">
          </label>

          <label class="field">
            <span>Ngày khám</span>
            <input class="input" type="date" name="date" value="<?= e($dateFilter) ?>">
          </label>

          <label class="field">
            <span>Khu vực</span>
            <input class="input" name="city" value="<?= e($city) ?>" placeholder="Ví dụ: Đà Nẵng">
          </label>
        </div>

        <div style="margin-top:14px; display:flex; flex-wrap:wrap; gap:10px;">
          <button type="submit" class="btn btn-primary btn-sm">Lọc khung giờ</button>
          <a class="btn btn-secondary btn-sm" href="appointments.php">Xem tất cả</a>
        </div>
      </form>

      <?php if ($availableSlots): ?>
        <div class="cards-3">
          <?php foreach ($availableSlots as $slot): ?>
            <?php $remaining = (int)$slot['Capacity'] - (int)$slot['Booked']; ?>
            <article class="card">
              <span class="pill pill-done"><?= e($slot['City']) ?> · Còn <?= $remaining ?> chỗ</span>
              <h3><?= e($slot['FacilityName']) ?></h3>
              <p><?= e($slot['Address']) ?></p>
              <p>
                <strong>Thời gian:</strong> <?= local_dt($slot['StartsAt'], 'd/m/Y') ?>
                (<?= local_dt($slot['StartsAt'], 'H:i') ?> – <?= local_dt($slot['EndsAt'], 'H:i') ?>)
              </p>
              <p>
                <strong>Bác sĩ:</strong> <?= e($slot['DoctorName'] ?: 'Bác sĩ trực chuyên khoa') ?>
                <?= !empty($slot['DoctorSpecialty']) ? '· ' . e($slot['DoctorSpecialty']) : '' ?>
              </p>

              <form method="post" class="auth-form" style="margin-top:auto; padding-top:12px; border-top:1px solid var(--line); gap:10px;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="book">
                <input type="hidden" name="slot_id" value="<?= (int)$slot['SlotID'] ?>">
                <label class="field">
                  <span>Người đi khám</span>
                  <select class="select" name="profile_id">
                    <option value="">Bản thân (<?= e($user['name']) ?>)</option>
                    <?php foreach ($profiles as $profile): ?>
                      <option value="<?= (int)$profile['ProfileID'] ?>">
                        <?= e($profile['DisplayName']) ?> (<?= e($profile['Relationship']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label class="field">
                  <span>Ghi chú triệu chứng ngắn</span>
                  <input class="input" name="note" maxlength="500" placeholder="Ví dụ: Khám tổng quát buổi sáng">
                </label>
                <button type="submit" class="btn btn-primary btn-block">Đặt lịch khung giờ này</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          Chưa có khung giờ nào phù hợp điều kiện lọc. Bạn có thể bấm “Xem tất cả” hoặc chờ quản trị viên mở thêm lịch khám.
        </div>
      <?php endif; ?>
    </section>

    <section class="panel" id="profiles">
      <div class="panel-head">
        <div>
          <div class="eyebrow">02 · GIA ĐÌNH</div>
          <h2>Thêm nhanh hồ sơ người thân</h2>
        </div>
        <a class="text-link" href="profile.php#family">Quản lý chi tiết trong Hồ sơ →</a>
      </div>

      <form method="post" class="admin-form" style="margin-bottom:0;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_profile">
        <div class="form-grid-2" style="grid-template-columns: repeat(3, minmax(0,1fr));">
          <label class="field">
            <span>Tên người thân</span>
            <input class="input" name="display_name" maxlength="120" required>
          </label>
          <label class="field">
            <span>Mối quan hệ</span>
            <input class="input" name="relationship" maxlength="40" placeholder="Con, Cha/Mẹ..." required>
          </label>
          <label class="field">
            <span>Ngày sinh</span>
            <input class="input" type="date" name="birth_date">
          </label>
        </div>
        <button type="submit" class="btn btn-secondary" style="margin-top:14px;">Thêm người thân</button>
      </form>
    </section>

    <section class="panel" id="my-appointments">
      <div class="panel-head">
        <div>
          <div class="eyebrow">03 · LỊCH SỬ ĐẶT LỊCH</div>
          <h2>Lịch hẹn của tôi (<?= count($appointments) ?>)</h2>
        </div>
      </div>

      <?php if ($appointments): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Người khám</th>
                <th>Cơ sở &amp; Bác sĩ</th>
                <th>Giờ khám</th>
                <th>Ghi chú</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($appointments as $app): ?>
                <?php $st = appointment_status_meta($app['Status']); ?>
                <tr>
                  <td><strong><?= e($app['PatientName']) ?></strong></td>
                  <td>
                    <?= e($app['FacilityName']) ?> (<?= e($app['City']) ?>)<br>
                    <small style="color:var(--muted);">
                      <?= e($app['DoctorName'] ?: 'Chưa gán bác sĩ') ?>
                      <?= !empty($app['DoctorSpecialty']) ? '· ' . e($app['DoctorSpecialty']) : '' ?>
                    </small>
                  </td>
                  <td><?= local_dt($app['StartsAt'], 'd/m/Y H:i') ?> – <?= local_dt($app['EndsAt'], 'H:i') ?></td>
                  <td><?= e($app['Note'] ?: '—') ?></td>
                  <td><span class="pill <?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
                  <td>
                    <?php if (in_array($app['Status'], ['pending', 'confirmed'], true)): ?>
                      <form method="post" style="margin:0;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="appointment_id" value="<?= (int)$app['AppointmentID'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Hủy lịch</button>
                      </form>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">Bạn chưa có lịch hẹn nào trên HealthHub.</div>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
