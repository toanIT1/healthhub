<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $emailInput = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = db_fetch_one(
        'SELECT UserID, DisplayName, PasswordHash, Role FROM Users WHERE Email = ? AND IsActive = 1',
        [$emailInput]
    );

    if ($user && password_verify($password, $user['PasswordHash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['UserID'],
            'name' => $user['DisplayName'],
            'role' => $user['Role'],
        ];
        flash('Đăng nhập thành công. Chào mừng bạn quay lại HealthHub!');
        redirect(in_array($user['Role'], ['editor', 'admin'], true) ? 'admin.php' : 'index.php');
    }

    $error = 'Email hoặc mật khẩu chưa chính xác, hoặc tài khoản đã bị khóa.';
}

$pageTitle = 'Đăng nhập | HealthHub';
$notice = pop_flash();
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="auth-wrap">
  <section class="auth-card">
    <div class="eyebrow"><span class="eyebrow-dot"></span>TÀI KHOẢN HEALTHHUB</div>
    <h1>Đăng nhập</h1>
    <p class="section-sub">Lưu bài viết hữu ích, quản lý hồ sơ người thân và theo dõi lịch khám của bạn.</p>

    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label class="field">
        <span>Email</span>
        <input class="input" type="email" name="email" value="<?= e($emailInput) ?>" required autocomplete="email" placeholder="ten@example.com">
      </label>
      <label class="field">
        <span>Mật khẩu</span>
        <input class="input" type="password" name="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
    </form>

    <p style="margin-top:20px; text-align:center; color:var(--muted);">
      Chưa có tài khoản? <a class="text-link" href="register.php">Tạo tài khoản mới</a>
    </p>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
