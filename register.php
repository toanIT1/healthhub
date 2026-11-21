<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$nameInput = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nameInput = trim((string)($_POST['name'] ?? ''));
    $emailInput = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (mb_strlen($nameInput) < 2 || !filter_var($emailInput, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Vui lòng nhập họ tên hợp lệ, email đúng định dạng và mật khẩu tối thiểu 8 ký tự.';
    } else {
        $exists = db_fetch_val('SELECT UserID FROM Users WHERE Email = ?', [$emailInput]);
        if ($exists) {
            $error = 'Email này đã được đăng ký trước đó.';
        } else {
            try {
                $newId = db_fetch_val(
                    'INSERT INTO Users (DisplayName, Email, PasswordHash, Role) OUTPUT INSERTED.UserID VALUES (?, ?, ?, ?)',
                    [$nameInput, $emailInput, password_hash($password, PASSWORD_DEFAULT), 'member']
                );
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int)$newId,
                    'name' => $nameInput,
                    'role' => 'member',
                ];
                flash('Tạo tài khoản thành công. Chào mừng bạn đến với HealthHub!');
                redirect('index.php');
            } catch (RuntimeException) {
                $error = 'Chưa tạo được tài khoản. Hãy kiểm tra lại kết nối SQL Server.';
            }
        }
    }
}

$pageTitle = 'Tạo tài khoản | HealthHub';
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="auth-wrap">
  <section class="auth-card">
    <div class="eyebrow"><span class="eyebrow-dot"></span>BẮT ĐẦU SỬ DỤNG HEALTHHUB</div>
    <h1>Tạo tài khoản</h1>
    <p class="section-sub">Lưu kiến thức sức khỏe quan trọng và đặt lịch khám cho bản thân hoặc người thân.</p>

    <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label class="field">
        <span>Họ và tên</span>
        <input class="input" type="text" name="name" value="<?= e($nameInput) ?>" required maxlength="120" autocomplete="name" placeholder="Nguyễn Văn A">
      </label>
      <label class="field">
        <span>Email</span>
        <input class="input" type="email" name="email" value="<?= e($emailInput) ?>" required autocomplete="email" placeholder="ten@example.com">
      </label>
      <label class="field">
        <span>Mật khẩu (tối thiểu 8 ký tự)</span>
        <input class="input" type="password" name="password" minlength="8" required autocomplete="new-password">
      </label>
      <button type="submit" class="btn btn-primary btn-block">Tạo tài khoản</button>
    </form>

    <p style="margin-top:20px; text-align:center; color:var(--muted);">
      Đã có tài khoản? <a class="text-link" href="login.php">Đăng nhập</a>
    </p>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
