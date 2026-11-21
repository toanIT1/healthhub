<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$user = require_login();
$error = '';
$notice = pop_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'update_profile') {
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 120) {
                throw new RuntimeException('Họ tên phải từ 2 đến 120 ký tự.');
            }
            db_exec('UPDATE Users SET DisplayName = ? WHERE UserID = ?', [$displayName, $user['id']]);
            $_SESSION['user']['name'] = $displayName;
            flash('Đã cập nhật tên hiển thị.');
            redirect('profile.php');
        }

        if ($action === 'change_password') {
            $currentPw = (string)($_POST['current_password'] ?? '');
            $newPw = (string)($_POST['new_password'] ?? '');
            $confirmPw = (string)($_POST['confirm_password'] ?? '');

            $row = db_fetch_one('SELECT PasswordHash FROM Users WHERE UserID = ?', [$user['id']]);
            if (!$row || !password_verify($currentPw, $row['PasswordHash'])) {
                throw new RuntimeException('Mật khẩu hiện tại không đúng.');
            }
            if (strlen($newPw) < 8) {
                throw new RuntimeException('Mật khẩu mới cần tối thiểu 8 ký tự.');
            }
            if ($newPw !== $confirmPw) {
                throw new RuntimeException('Mật khẩu nhập lại chưa khớp.');
            }
            db_exec('UPDATE Users SET PasswordHash = ? WHERE UserID = ?', [password_hash($newPw, PASSWORD_DEFAULT), $user['id']]);
            flash('Đã đổi mật khẩu thành công.');
            redirect('profile.php');
        }

        if ($action === 'add_family') {
            $name = trim((string)($_POST['family_name'] ?? ''));
            $relationship = trim((string)($_POST['relationship'] ?? ''));
            $birthDate = trim((string)($_POST['birth_date'] ?? ''));
            if ($name === '' || $relationship === '') {
                throw new RuntimeException('Vui lòng nhập tên và mối quan hệ.');
            }
            db_exec(
                'INSERT INTO FamilyProfiles (UserID, DisplayName, Relationship, BirthDate) VALUES (?, ?, ?, ?)',
                [$user['id'], $name, $relationship, $birthDate !== '' ? $birthDate : null]
            );
            flash('Đã thêm hồ sơ người thân.');
            redirect('profile.php#family');
        }

        if ($action === 'delete_family') {
            $profileId = filter_input(INPUT_POST, 'profile_id', FILTER_VALIDATE_INT);
            if ($profileId) {
                $used = db_fetch_val(
                    'SELECT TOP 1 AppointmentID FROM Appointments WHERE ProfileID = ? AND UserID = ?',
                    [$profileId, $user['id']]
                );
                if ($used) {
                    throw new RuntimeException('Hồ sơ này đang gắn với lịch hẹn nên không thể xóa.');
                }
                db_exec('DELETE FROM FamilyProfiles WHERE ProfileID = ? AND UserID = ?', [$profileId, $user['id']]);
                flash('Đã xóa hồ sơ người thân.');
            }
            redirect('profile.php#family');
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$account = db_fetch_one('SELECT UserID, DisplayName, Email, Role, CreatedAt FROM Users WHERE UserID = ?', [$user['id']]);
$favorites = db_fetch_all(
    'SELECT a.ArticleID, a.Title, a.Slug, a.Summary, a.CoverImage, a.PublishedAt, c.Name AS CategoryName
     FROM Favorites f
     JOIN Articles a ON a.ArticleID = f.ArticleID
     JOIN Categories c ON c.CategoryID = a.CategoryID
     WHERE f.UserID = ? AND a.Status = ?
     ORDER BY f.CreatedAt DESC',
    [$user['id'], 'published']
);
$familyProfiles = db_fetch_all(
    'SELECT ProfileID, DisplayName, Relationship, BirthDate FROM FamilyProfiles WHERE UserID = ? ORDER BY DisplayName',
    [$user['id']]
);
$recentAppointments = db_fetch_all(
    'SELECT TOP 5 a.AppointmentID, a.PatientName, a.Status, s.StartsAt, f.Name AS FacilityName
     FROM Appointments a
     JOIN AppointmentSlots s ON s.SlotID = a.SlotID
     JOIN Facilities f ON f.FacilityID = s.FacilityID
     WHERE a.UserID = ?
     ORDER BY a.CreatedAt DESC',
    [$user['id']]
);

$pageTitle = 'Hồ sơ cá nhân | HealthHub';
$activeNav = 'profile';
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="page-shell">
  <div class="wrap">
    <a class="back-link" href="index.php">← Quay lại trang chủ</a>
    <div class="page-header">
      <div class="eyebrow"><span class="eyebrow-dot"></span>TÀI KHOẢN CỦA TÔI</div>
      <h1>Hồ sơ cá nhân &amp; Bài viết đã lưu</h1>
      <p class="section-sub">Quản lý thông tin đăng nhập, mật khẩu, hồ sơ người thân và xem lại các bài viết sức khỏe bạn đã lưu.</p>
    </div>

    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <div class="cards-2">
      <section class="panel" style="margin:0;">
        <div class="panel-head">
          <div>
            <div class="eyebrow">01 · THÔNG TIN TÀI KHOẢN</div>
            <h2>Cập nhật họ tên</h2>
          </div>
        </div>
        <form method="post" class="auth-form" style="margin-top:0;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_profile">
          <label class="field">
            <span>Họ và tên hiển thị</span>
            <input class="input" name="display_name" required maxlength="120" value="<?= e($account['DisplayName'] ?? '') ?>">
          </label>
          <label class="field">
            <span>Email (tài khoản)</span>
            <input class="input" value="<?= e($account['Email'] ?? '') ?>" disabled>
          </label>
          <p style="margin:0; color:var(--muted); font-size:14px;">
            Vai trò: <strong><?= e($account['Role'] ?? 'member') ?></strong> · Tham gia từ <?= local_dt($account['CreatedAt'] ?? '', 'd/m/Y') ?>
          </p>
          <button type="submit" class="btn btn-primary">Lưu thông tin</button>
        </form>
      </section>

      <section class="panel" style="margin:0;">
        <div class="panel-head">
          <div>
            <div class="eyebrow">02 · BẢO MẬT</div>
            <h2>Đổi mật khẩu</h2>
          </div>
        </div>
        <form method="post" class="auth-form" style="margin-top:0;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="change_password">
          <label class="field">
            <span>Mật khẩu hiện tại</span>
            <input class="input" type="password" name="current_password" required autocomplete="current-password">
          </label>
          <label class="field">
            <span>Mật khẩu mới (tối thiểu 8 ký tự)</span>
            <input class="input" type="password" name="new_password" minlength="8" required autocomplete="new-password">
          </label>
          <label class="field">
            <span>Nhập lại mật khẩu mới</span>
            <input class="input" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
          </label>
          <button type="submit" class="btn btn-secondary">Cập nhật mật khẩu</button>
        </form>
      </section>
    </div>

    <section class="panel" id="favorites">
      <div class="panel-head">
        <div>
          <div class="eyebrow">03 · BÀI VIẾT YÊU THÍCH</div>
          <h2>Bài viết đã lưu (<?= count($favorites) ?>)</h2>
        </div>
        <a class="text-link" href="index.php#topics">Khám phá thêm bài viết →</a>
      </div>

      <?php if ($favorites): ?>
        <div class="cards-3">
          <?php foreach ($favorites as $item): ?>
            <article class="article-card">
              <a class="article-thumb" href="article.php?slug=<?= urlencode($item['Slug']) ?>">
                <img src="<?= e(cover_image_or_default($item['CoverImage'] ?? '', $item['CategoryName'])) ?>" alt="<?= e($item['Title']) ?>" loading="lazy" decoding="async">
              </a>
              <div class="article-body">
                <div class="article-meta">
                  <strong><?= e($item['CategoryName']) ?></strong>
                  <span><?= local_dt($item['PublishedAt'], 'd/m/Y') ?></span>
                </div>
                <h3><a href="article.php?slug=<?= urlencode($item['Slug']) ?>"><?= e($item['Title']) ?></a></h3>
                <p><?= e($item['Summary']) ?></p>
                <div class="article-foot">
                  <a class="text-link" href="article.php?slug=<?= urlencode($item['Slug']) ?>">Đọc bài →</a>
                  <form method="post" action="favorite.php" style="margin:0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="article_id" value="<?= (int)$item['ArticleID'] ?>">
                    <input type="hidden" name="redirect_to" value="profile.php#favorites">
                    <button type="submit" class="save-btn is-saved">♥ Bỏ lưu</button>
                  </form>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">Bạn chưa lưu bài viết nào. Hãy bấm nút “♡ Lưu” trên các bài viết bạn quan tâm.</div>
      <?php endif; ?>
    </section>

    <section class="panel" id="family">
      <div class="panel-head">
        <div>
          <div class="eyebrow">04 · HỒ SƠ GIA ĐÌNH</div>
          <h2>Người thân dùng chung lịch khám</h2>
        </div>
      </div>

      <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_family">
        <div class="form-grid-2" style="grid-template-columns: repeat(3, minmax(0,1fr));">
          <label class="field">
            <span>Họ và tên người thân</span>
            <input class="input" name="family_name" maxlength="120" required placeholder="Ví dụ: Nguyễn Thị B">
          </label>
          <label class="field">
            <span>Mối quan hệ</span>
            <input class="input" name="relationship" maxlength="40" required placeholder="Ví dụ: Mẹ, Con, Ông/Bà">
          </label>
          <label class="field">
            <span>Ngày sinh (không bắt buộc)</span>
            <input class="input" type="date" name="birth_date">
          </label>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:14px;">Thêm hồ sơ người thân</button>
      </form>

      <?php if ($familyProfiles): ?>
        <div class="cards-3">
          <?php foreach ($familyProfiles as $fp): ?>
            <article class="card">
              <span class="pill pill-done"><?= e($fp['Relationship']) ?></span>
              <h3><?= e($fp['DisplayName']) ?></h3>
              <?php if (!empty($fp['BirthDate'])): ?>
                <p>Ngày sinh: <?= e(date('d/m/Y', strtotime((string)$fp['BirthDate']))) ?></p>
              <?php endif; ?>
              <form method="post" style="margin-top:auto;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_family">
                <input type="hidden" name="profile_id" value="<?= (int)$fp['ProfileID'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Xóa hồ sơ</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">Chưa có hồ sơ người thân nào.</div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head">
        <div>
          <div class="eyebrow">05 · LỊCH HẸN GẦN ĐÂY</div>
          <h2>Theo dõi đặt lịch</h2>
        </div>
        <a class="btn btn-primary btn-sm" href="appointments.php">Đặt lịch khám mới</a>
      </div>

      <?php if ($recentAppointments): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Người khám</th>
                <th>Cơ sở y tế</th>
                <th>Thời gian</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentAppointments as $app): ?>
                <?php $st = appointment_status_meta($app['Status']); ?>
                <tr>
                  <td><?= e($app['PatientName']) ?></td>
                  <td><?= e($app['FacilityName']) ?></td>
                  <td><?= local_dt($app['StartsAt']) ?></td>
                  <td><span class="pill <?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">Bạn chưa có lịch hẹn nào.</div>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
