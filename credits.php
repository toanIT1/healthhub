<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$creditsPath = __DIR__ . '/assets/img/credits.json';
$credits = [];
if (is_file($creditsPath)) {
    $raw = json_decode((string)file_get_contents($creditsPath), true);
    if (is_array($raw)) {
        $credits = $raw['images'] ?? $raw;
    }
}

$pageTitle = 'Nguồn ảnh minh họa | HealthHub';
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="page-shell">
  <div class="wrap article-detail">
    <a class="back-link" href="index.php">← Quay lại trang chủ</a>
    <div class="page-header">
      <div class="eyebrow"><span class="eyebrow-dot"></span>BẢN QUYỀN &amp; GHI NGUỒN</div>
      <h1>Nguồn ảnh minh họa</h1>
      <p class="section-sub">Các hình ảnh y tế và dinh dưỡng trên HealthHub được sử dụng từ kho ảnh mở Wikimedia Commons theo giấy phép CC0, CC BY và Public Domain.</p>
    </div>

    <?php if ($credits): ?>
      <div class="panel">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Tệp ảnh</th>
                <th>Tác giả</th>
                <th>Giấy phép</th>
                <th>Nguồn gốc</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($credits as $item): ?>
                <?php if (!is_array($item)) continue; ?>
                <tr>
                  <td><?= e($item['title'] ?? $item['file'] ?? 'Ảnh minh họa') ?></td>
                  <td><?= e($item['by'] ?? 'Wikimedia Commons') ?></td>
                  <td><?= e($item['licence'] ?? 'CC / Public Domain') ?></td>
                  <td>
                    <?php if (!empty($item['source'])): ?>
                      <a class="text-link" href="<?= e($item['source']) ?>" target="_blank" rel="noopener">Xem nguồn ↗</a>
                    <?php else: ?>
                      Wikimedia Commons
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state">Danh sách ghi nguồn ảnh đang được cập nhật tại thư mục <code>assets/img/credits.json</code>.</div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
