<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$article = db_fetch_one(
    'SELECT a.*, c.Name AS CategoryName, u.DisplayName AS AuthorName
     FROM Articles a
     JOIN Categories c ON c.CategoryID = a.CategoryID
     JOIN Users u ON u.UserID = a.AuthorID
     WHERE a.Slug = ? AND a.Status = ? AND a.PublishedAt <= SYSUTCDATETIME()',
    [$slug, 'published']
);

if (!$article) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy bài viết | HealthHub';
    require __DIR__ . '/partials/header.php';
    echo '<main id="main" class="page-shell"><div class="wrap"><div class="empty-state"><h1>Không tìm thấy bài viết</h1><p>Bài viết không tồn tại hoặc chưa được xuất bản.</p><a class="btn btn-primary" href="index.php">Quay lại trang chủ</a></div></div></main>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$isSaved = false;
if (current_user()) {
    $isSaved = (bool)db_fetch_val(
        'SELECT FavoriteID FROM Favorites WHERE UserID = ? AND ArticleID = ?',
        [current_user()['id'], (int)$article['ArticleID']]
    );
}

$related = db_fetch_all(
    'SELECT TOP 3 a.ArticleID, a.Title, a.Slug, a.Summary, a.CoverImage, a.PublishedAt, c.Name AS CategoryName
     FROM Articles a
     JOIN Categories c ON c.CategoryID = a.CategoryID
     WHERE a.CategoryID = ? AND a.ArticleID <> ? AND a.Status = ? AND a.PublishedAt <= SYSUTCDATETIME()
     ORDER BY a.PublishedAt DESC',
    [(int)$article['CategoryID'], (int)$article['ArticleID'], 'published']
);

$cover = cover_image_or_default($article['CoverImage'] ?? '', $article['CategoryName']);
$pageTitle = $article['Title'] . ' | HealthHub';
$pageDescription = $article['Summary'];
$notice = pop_flash();
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="page-shell">
  <div class="wrap article-detail">
    <a class="back-link" href="index.php#topics">← Quay lại danh sách bài viết</a>
    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>

    <div class="article-meta">
      <strong><?= e($article['CategoryName']) ?></strong>
      <span>·</span>
      <span><?= local_dt($article['PublishedAt'], 'd/m/Y') ?></span>
      <span>·</span>
      <span>Biên tập: <?= e($article['AuthorName']) ?></span>
      <?php if (!empty($article['ReviewedBy'])): ?>
        <span>·</span>
        <span>Rà soát: <?= e($article['ReviewedBy']) ?></span>
      <?php endif; ?>
    </div>

    <div class="page-header" style="margin-bottom: 16px;">
      <h1><?= e($article['Title']) ?></h1>
      <p class="hero-lead"><?= e($article['Summary']) ?></p>
    </div>

    <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:20px;">
      <?php if (current_user()): ?>
        <form method="post" action="favorite.php" style="margin:0;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="article_id" value="<?= (int)$article['ArticleID'] ?>">
          <input type="hidden" name="redirect_to" value="article.php?slug=<?= e(urlencode($article['Slug'])) ?>">
          <button type="submit" class="save-btn <?= $isSaved ? 'is-saved' : '' ?>">
            <?= $isSaved ? '♥ Đã lưu bài viết' : '♡ Lưu bài viết này' ?>
          </button>
        </form>
      <?php else: ?>
        <a class="save-btn" href="login.php">♡ Đăng nhập để lưu bài</a>
      <?php endif; ?>
      <a class="btn btn-secondary btn-sm" href="index.php#symptom-search">Tra cứu triệu chứng</a>
    </div>

    <div class="article-cover-lg">
      <img src="<?= e($cover) ?>" alt="<?= e($article['Title']) ?>" loading="eager" decoding="async">
    </div>

    <article class="prose">
      <?php
      $paragraphs = preg_split('/\R{2,}/', str_replace('\n', "\n", (string)$article['Body'])) ?: [];
      foreach ($paragraphs as $para):
          $para = trim($para);
          if ($para === '') continue;
      ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
    </article>

    <?php if (!empty($article['SourceName']) || !empty($article['SourceUrl'])): ?>
      <div class="medical-note" style="background: var(--surface-card);">
        <strong>Nguồn tham khảo:</strong>
        <span><?= e($article['SourceName'] ?: 'Tài liệu y tế') ?></span>
        <?php if (!empty($article['SourceUrl'])): ?>
          — <a class="text-link" href="<?= e($article['SourceUrl']) ?>" target="_blank" rel="noopener">Mở liên kết nguồn ↗</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <aside class="medical-note">
      <strong>Lưu ý an toàn y tế</strong>
      <p style="margin:6px 0 0; color:var(--muted);">
        Bài viết cung cấp kiến thức tham khảo, không thay thế chẩn đoán hoặc chỉ định điều trị từ bác sĩ. Nếu có triệu chứng nặng hoặc kéo dài, hãy đến cơ sở y tế gần nhất.
      </p>
    </aside>

    <?php if ($related): ?>
      <section style="margin-top:42px;">
        <div class="section-head">
          <div>
            <div class="eyebrow"><span class="eyebrow-dot"></span>ĐỌC THÊM</div>
            <h2>Bài viết cùng chuyên mục</h2>
          </div>
        </div>
        <div class="cards-3">
          <?php foreach ($related as $item): ?>
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
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
