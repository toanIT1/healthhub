<footer class="site-footer" id="about">
  <div class="wrap footer-inner">
    <div class="footer-brand-col">
      <a class="brand footer-brand" href="index.php">
        <span class="brand-mark" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round">
            <path d="M12 5v14M5 12h14"/>
          </svg>
        </span>
        <span class="brand-text"><strong>health<span class="brand-accent">hub</span></strong></span>
      </a>
      <p class="footer-note">Cổng thông tin giáo dục sức khỏe, tra cứu cơ sở y tế và đặt lịch khám mô phỏng phục vụ học tập.</p>
    </div>

    <div class="footer-links">
      <div>
        <h3>Dịch vụ</h3>
        <a href="index.php#symptom-search">Tra cứu triệu chứng</a>
        <a href="facilities.php">Danh bạ cơ sở &amp; bác sĩ</a>
        <a href="appointments.php">Đặt lịch khám trực tuyến</a>
      </div>
      <div>
        <h3>Tài khoản</h3>
        <?php if (current_user()): ?>
          <a href="profile.php">Hồ sơ &amp; bài viết đã lưu</a>
          <a href="appointments.php#my-appointments">Lịch hẹn của tôi</a>
          <a href="logout.php">Đăng xuất</a>
        <?php else: ?>
          <a href="login.php">Đăng nhập</a>
          <a href="register.php">Đăng ký thành viên</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="wrap footer-legal">
    <span>© 2026 HealthHub · Đồ án Cơ sở 2 (VKU)</span>
    <span>Thông tin chỉ mang tính tham khảo, không thay thế chẩn đoán y khoa.</span>
    <a href="credits.php">Nguồn ảnh minh họa</a>
  </div>
</footer>

<nav class="mobile-actionbar" aria-label="Thao tác nhanh trên điện thoại">
  <a class="btn btn-secondary" href="index.php#symptom-search">Tra triệu chứng</a>
  <a class="btn btn-primary" href="appointments.php">Đặt lịch khám</a>
</nav>

<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/../assets/app.js') ?: '20261121' ?>"></script>
</body>
</html>
