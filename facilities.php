<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$city = trim((string)($_GET['city'] ?? ''));
$specialty = trim((string)($_GET['specialty'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$type = in_array($type, ['hospital', 'clinic'], true) ? $type : '';

$sql = 'SELECT FacilityID, Name, FacilityType, Address, City, Phone, WebsiteUrl, Specialties, IsVerified
        FROM Facilities
        WHERE IsActive = 1';
$params = [];

if ($city !== '') {
    $sql .= ' AND City LIKE ?';
    $params[] = '%' . $city . '%';
}
if ($specialty !== '') {
    $sql .= ' AND Specialties LIKE ?';
    $params[] = '%' . $specialty . '%';
}
if ($type !== '') {
    $sql .= ' AND FacilityType = ?';
    $params[] = $type;
}
$sql .= ' ORDER BY IsVerified DESC, City, Name';

$facilities = db_fetch_all($sql, $params);
$doctorsByFacility = [];
if ($facilities) {
    $allDoctors = db_fetch_all(
        'SELECT DoctorID, FacilityID, DisplayName, Specialty FROM Doctors WHERE IsActive = 1 ORDER BY DisplayName'
    );
    foreach ($allDoctors as $doc) {
        $doctorsByFacility[(int)$doc['FacilityID']][] = $doc;
    }
}

$pageTitle = 'Danh bạ cơ sở y tế & Bác sĩ | HealthHub';
$activeNav = 'facilities';
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="page-shell">
  <div class="wrap">
    <a class="back-link" href="index.php">← Quay lại trang chủ</a>
    <div class="page-header">
      <div class="eyebrow"><span class="eyebrow-dot"></span>DANH BẠ Y TẾ</div>
      <h1>Tìm cơ sở khám &amp; bác sĩ phù hợp</h1>
      <p class="section-sub">Lọc theo tỉnh/thành phố, chuyên khoa hoặc loại hình cơ sở. Nhấn “Xem lịch khám” để chọn khung giờ phù hợp.</p>
    </div>

    <form class="filter-panel" method="get">
      <div class="filter-grid">
        <label class="field">
          <span>Khu vực</span>
          <input class="input" name="city" value="<?= e($city) ?>" placeholder="Ví dụ: Đà Nẵng">
        </label>
        <label class="field">
          <span>Chuyên khoa</span>
          <input class="input" name="specialty" value="<?= e($specialty) ?>" placeholder="Ví dụ: Tiêu hóa, Da liễu">
        </label>
        <label class="field">
          <span>Loại cơ sở</span>
          <select class="select" name="type">
            <option value="">Tất cả</option>
            <option value="hospital" <?= $type === 'hospital' ? 'selected' : '' ?>>Bệnh viện</option>
            <option value="clinic" <?= $type === 'clinic' ? 'selected' : '' ?>>Phòng khám</option>
          </select>
        </label>
        <div class="field">
          <span>&nbsp;</span>
          <button type="submit" class="btn btn-primary">Tìm cơ sở</button>
        </div>
        <?php if ($city !== '' || $specialty !== '' || $type !== ''): ?>
          <div class="field">
            <span>&nbsp;</span>
            <a class="btn btn-secondary" href="facilities.php">Xóa lọc</a>
          </div>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($facilities): ?>
      <div class="cards-3">
        <?php foreach ($facilities as $facility): ?>
          <?php $docs = $doctorsByFacility[(int)$facility['FacilityID']] ?? []; ?>
          <article class="card card-hover">
            <div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; align-items:center;">
              <span class="pill <?= $facility['IsVerified'] ? 'pill-ok' : 'pill-off' ?>">
                <?= $facility['FacilityType'] === 'hospital' ? 'Bệnh viện' : 'Phòng khám' ?>
                · <?= $facility['IsVerified'] ? 'Đã xác minh' : 'Chưa xác minh' ?>
              </span>
            </div>

            <h3><?= e($facility['Name']) ?></h3>
            <p><?= e($facility['Address']) ?>, <?= e($facility['City']) ?></p>
            <?php if (!empty($facility['Specialties'])): ?>
              <p><strong>Chuyên khoa:</strong> <?= e($facility['Specialties']) ?></p>
            <?php endif; ?>
            <?php if (!empty($facility['Phone'])): ?>
              <p><strong>Liên hệ:</strong> <a class="text-link" href="tel:<?= e($facility['Phone']) ?>"><?= e($facility['Phone']) ?></a></p>
            <?php endif; ?>

            <?php if ($docs): ?>
              <div class="doctor-mini-list">
                <strong>Bác sĩ tại cơ sở:</strong>
                <?php foreach ($docs as $doc): ?>
                  <span>• <?= e($doc['DisplayName']) ?> (<?= e($doc['Specialty']) ?>)</span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div style="margin-top:auto; padding-top:10px; display:flex; flex-wrap:wrap; gap:10px;">
              <a class="btn btn-primary btn-sm" href="appointments.php?facility_id=<?= (int)$facility['FacilityID'] ?>">Xem lịch khám</a>
              <?php if (!empty($facility['WebsiteUrl'])): ?>
                <a class="btn btn-secondary btn-sm" href="<?= e($facility['WebsiteUrl']) ?>" target="_blank" rel="noopener">Website ↗</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        Không tìm thấy cơ sở y tế phù hợp với bộ lọc hiện tại. Hãy thử bỏ bớt điều kiện tìm kiếm hoặc liên hệ quản trị viên bổ sung dữ liệu.
      </div>
    <?php endif; ?>

    <aside class="medical-note">
      <strong>Thông tin minh bạch</strong>
      <p style="margin:6px 0 0; color:var(--muted);">
        HealthHub tổng hợp danh bạ phục vụ tra cứu và mô phỏng đặt lịch đồ án. Vui lòng xác nhận lại giờ làm việc và chi phí trực tiếp với cơ sở y tế trước khi đến khám.
      </p>
    </aside>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
