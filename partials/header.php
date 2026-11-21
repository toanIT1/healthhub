<?php
$pageTitle = $pageTitle ?? 'HealthHub | Cổng thông tin sức khỏe & Đặt lịch khám';
$pageDescription = $pageDescription ?? 'Tra cứu triệu chứng, đọc kiến thức y tế chính thống, tìm cơ sở khám và đặt lịch hẹn dễ dàng cho mọi lứa tuổi.';
$activeNav = $activeNav ?? '';
$user = current_user();
$isEditor = $user && in_array($user['role'], ['editor', 'admin'], true);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="<?= e($pageDescription) ?>">
  <meta name="theme-color" content="#f7f9fa">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap&subset=vietnamese" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '20261121' ?>">
  <script>
    if (localStorage.getItem('hh-text-size') === 'large') {
      document.documentElement.classList.add('text-large');
    }
  </script>
</head>
<body>
<a class="skip-link" href="#main">Chuyển tới nội dung chính</a>

<header class="topbar" id="top">
  <div class="wrap topbar-inner">
    <a class="brand" href="index.php" aria-label="Trang chủ HealthHub">
      <span class="brand-mark" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
          <path d="M12 5v14M5 12h14"/>
        </svg>
      </span>
      <span class="brand-text">
        <strong>health<span class="brand-accent">hub</span></strong>
        <small>HIỂU ĐÚNG · CHỌN ĐÚNG NƠI KHÁM</small>
      </span>
    </a>

    <nav class="nav-links" aria-label="Điều hướng chính">
      <a href="index.php" <?= $activeNav === 'home' ? 'aria-current="page"' : '' ?>>Trang chủ</a>
      <a href="index.php#symptom-search" <?= $activeNav === 'symptoms' ? 'aria-current="page"' : '' ?>>Tra cứu triệu chứng</a>
      <a href="facilities.php" <?= $activeNav === 'facilities' ? 'aria-current="page"' : '' ?>>Cơ sở &amp; Bác sĩ</a>
      <a href="appointments.php" <?= $activeNav === 'appointments' ? 'aria-current="page"' : '' ?>>Đặt lịch khám</a>
      <?php if ($user): ?>
        <a href="profile.php" <?= $activeNav === 'profile' ? 'aria-current="page"' : '' ?>>Hồ sơ cá nhân</a>
      <?php endif; ?>
      <div class="mobile-nav-extras">
        <button type="button" class="btn btn-secondary btn-sm text-size-sync">Aa Đổi cỡ chữ</button>
        <?php if ($user): ?>
          <?php if ($isEditor): ?>
            <a class="btn btn-secondary btn-sm" href="admin.php">Quản trị</a>
          <?php endif; ?>
          <a class="btn btn-secondary btn-sm" href="logout.php">Đăng xuất</a>
        <?php else: ?>
          <a class="btn btn-secondary btn-sm" href="login.php">Đăng nhập</a>
          <a class="btn btn-primary btn-sm" href="register.php">Tạo tài khoản</a>
        <?php endif; ?>
      </div>
    </nav>

    <div class="header-actions">
      <button type="button" class="text-size-btn" id="text-size-toggle" aria-pressed="false" title="Đổi cỡ chữ lớn hơn cho dễ đọc">
        <span aria-hidden="true">Aa</span> Cỡ chữ lớn
      </button>

      <?php if ($user): ?>
        <?php if ($isEditor): ?>
          <a class="btn btn-secondary btn-sm" href="admin.php" <?= $activeNav === 'admin' ? 'aria-current="page"' : '' ?>>Quản trị</a>
        <?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="logout.php">Đăng xuất</a>
      <?php else: ?>
        <a class="btn btn-secondary btn-sm" href="login.php">Đăng nhập</a>
        <a class="btn btn-primary btn-sm" href="register.php">Tạo tài khoản</a>
      <?php endif; ?>

      <button type="button" class="menu-toggle" aria-expanded="false" aria-label="Mở menu điều hướng">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
          <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
      </button>
    </div>
  </div>
</header>
