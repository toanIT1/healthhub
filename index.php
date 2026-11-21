<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$query = trim((string)($_GET['q'] ?? ''));
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$symptomQuery = trim((string)($_GET['symptom'] ?? ''));
$city = trim((string)($_GET['city'] ?? ''));
$region = (string)($_GET['region'] ?? 'all');
$region = in_array($region, ['all', 'vietnam', 'world'], true) ? $region : 'all';

// Nếu người dùng nhập từ khóa vào ô tìm kiếm chính (?q=), đồng thời kiểm tra xem có khớp triệu chứng nào không
if ($symptomQuery === '' && $query !== '') {
    $symptomHint = db_fetch_val(
        'SELECT TOP 1 Name FROM Symptoms WHERE Name LIKE ? OR Specialty LIKE ?',
        ['%' . $query . '%', '%' . $query . '%']
    );
    if ($symptomHint) {
        $symptomQuery = $query;
    }
}

$categories = db_fetch_all('SELECT CategoryID, Name FROM Categories WHERE IsActive = 1 ORDER BY SortOrder, Name');

$articleSql = 'SELECT a.ArticleID, a.Title, a.Slug, a.Summary, a.CoverImage, a.PublishedAt, a.IsFeatured,
                      c.Name AS CategoryName, u.DisplayName AS AuthorName
               FROM Articles a
               JOIN Categories c ON c.CategoryID = a.CategoryID
               JOIN Users u ON u.UserID = a.AuthorID
               WHERE a.Status = ? AND a.PublishedAt <= SYSUTCDATETIME()';
$articleParams = ['published'];

if ($query !== '') {
    $articleSql .= ' AND (a.Title LIKE ? OR a.Summary LIKE ? OR a.Body LIKE ?)';
    $qLike = '%' . $query . '%';
    array_push($articleParams, $qLike, $qLike, $qLike);
}
if ($categoryId > 0) {
    $articleSql .= ' AND a.CategoryID = ?';
    $articleParams[] = $categoryId;
}
$articleSql .= ' ORDER BY a.IsFeatured DESC, a.PublishedAt DESC';

$articles = db_fetch_all($articleSql, $articleParams);
$featured = $articles[0] ?? null;
$articleList = $featured ? array_slice($articles, 1) : [];

// Tra cứu triệu chứng 5 lớp
$symptoms = db_fetch_all('SELECT SymptomID, Name, Description, Specialty, WarningText FROM Symptoms ORDER BY Name');
$matchedSymptoms = [];
$relatedArticles = [];
$suggestedFacilities = [];
$didYouMean = [];
$fallbackArticles = [];

if ($symptomQuery !== '') {
    $like = '%' . $symptomQuery . '%';
    $matchedSymptoms = db_fetch_all(
        'SELECT SymptomID, Name, Description, Specialty, WarningText
         FROM Symptoms
         WHERE Name LIKE ? OR Description LIKE ? OR Specialty LIKE ?
         ORDER BY Name',
        [$like, $like, $like]
    );

    if (!$matchedSymptoms) {
        $normalized = remove_diacritics($symptomQuery);
        $strongMatches = [];
        foreach ($symptoms as $candidate) {
            $name = remove_diacritics($candidate['Name']);
            $percent = 0.0;
            similar_text($normalized, $name, $percent);
            if (mb_strlen($normalized) >= 4 && str_contains($name, $normalized)) {
                $strongMatches[] = $candidate;
            } elseif (str_contains($normalized, $name) || $percent >= 55) {
                $didYouMean[] = $candidate;
            }
        }
        if ($strongMatches) {
            $matchedSymptoms = $strongMatches;
        } else {
            $didYouMean = array_slice($didYouMean, 0, 3);
            $fallbackLike = '%' . $symptomQuery . '%';
            $fallbackArticles = db_fetch_all(
                'SELECT TOP 3 a.Title, a.Slug, a.Summary, c.Name AS CategoryName
                 FROM Articles a
                 JOIN Categories c ON c.CategoryID = a.CategoryID
                 WHERE a.Status = ? AND a.PublishedAt <= SYSUTCDATETIME()
                   AND (a.Title LIKE ? OR a.Summary LIKE ? OR a.Body LIKE ?)',
                ['published', $fallbackLike, $fallbackLike, $fallbackLike]
            );
        }
    }

    try {
        db_exec('INSERT INTO SearchLogs (Keyword, FoundCount) VALUES (?, ?)', [
            mb_substr($symptomQuery, 0, 200),
            count($matchedSymptoms),
        ]);
    } catch (RuntimeException) {
        // Bỏ qua nếu bảng SearchLogs chưa được tạo
    }

    if ($matchedSymptoms) {
        $ids = array_map(static fn(array $row): int => (int)$row['SymptomID'], $matchedSymptoms);
        $marks = implode(',', array_fill(0, count($ids), '?'));

        $relatedArticles = db_fetch_all(
            'SELECT DISTINCT a.Title, a.Slug, a.Summary, c.Name AS CategoryName
             FROM SymptomArticles sa
             JOIN Articles a ON a.ArticleID = sa.ArticleID
             JOIN Categories c ON c.CategoryID = a.CategoryID
             WHERE sa.SymptomID IN (' . $marks . ') AND a.Status = ? AND a.PublishedAt <= SYSUTCDATETIME()',
            array_merge($ids, ['published'])
        );

        $facilitySql = 'SELECT DISTINCT f.FacilityID, f.Name, f.FacilityType, f.Address, f.City, f.Phone, f.WebsiteUrl, f.Specialties, f.IsVerified
                        FROM SymptomFacilities sf
                        JOIN Facilities f ON f.FacilityID = sf.FacilityID
                        WHERE sf.SymptomID IN (' . $marks . ') AND f.IsActive = 1';
        $facilityParams = $ids;
        if ($city !== '') {
            $facilitySql .= ' AND f.City LIKE ?';
            $facilityParams[] = '%' . $city . '%';
        }
        $facilitySql .= ' ORDER BY f.IsVerified DESC, f.City, f.Name';
        $suggestedFacilities = db_fetch_all($facilitySql, $facilityParams);
    }
}

// Tin y tế mới nhất theo khu vực
$newsSql = 'SELECT TOP 5 a.ArticleID, a.Title, a.Slug, a.Summary, a.CoverImage, a.SourceName, a.PublishedAt, c.Name AS CategoryName
            FROM Articles a
            JOIN Categories c ON c.CategoryID = a.CategoryID
            WHERE a.Status = ? AND a.PublishedAt <= SYSUTCDATETIME()';
$newsParams = ['published'];
if ($region === 'vietnam') {
    $newsSql .= ' AND (a.SourceName LIKE ? OR a.SourceName LIKE ?)';
    array_push($newsParams, '%Việt Nam%', '%Bộ Y tế%');
} elseif ($region === 'world') {
    $newsSql .= ' AND (a.SourceName LIKE ? OR a.SourceName LIKE ?)';
    array_push($newsParams, '%WHO%', '%quốc tế%');
}
$newsItems = db_fetch_all($newsSql . ' ORDER BY a.PublishedAt DESC', $newsParams);
$newsFeatured = $newsItems[0] ?? null;
$newsSecondary = array_slice($newsItems, 1, 4);

$savedIds = [];
if (current_user()) {
    $savedRows = db_fetch_all('SELECT ArticleID FROM Favorites WHERE UserID = ?', [current_user()['id']]);
    $savedIds = array_map(static fn(array $r): int => (int)$r['ArticleID'], $savedRows);
}

$quickTopics = [
    ['label' => 'Đau bụng, khó tiêu', 'href' => '?symptom=' . urlencode('Đau bụng, khó tiêu') . '#symptom-results'],
    ['label' => 'Ho, đau họng', 'href' => '?symptom=' . urlencode('Ho, đau họng') . '#symptom-results'],
    ['label' => 'Nổi mẩn, ngứa da', 'href' => '?symptom=' . urlencode('Nổi mẩn, ngứa da') . '#symptom-results'],
    ['label' => 'Khám định kỳ', 'href' => '?q=' . urlencode('khám sức khỏe') . '#topics'],
    ['label' => 'Dinh dưỡng', 'href' => '?q=' . urlencode('dinh dưỡng') . '#topics'],
];

$notice = pop_flash();
$pageTitle = 'HealthHub | Hiểu đúng triệu chứng, chọn đúng nơi khám';
$activeNav = 'home';
require __DIR__ . '/partials/header.php';
?>

<main id="main">
  <?php if ($notice): ?>
    <div class="wrap"><div class="notice"><?= e($notice) ?></div></div>
  <?php endif; ?>

  <!-- HERO: Mẫu 1 Trợ lý y tế một cửa -->
  <section class="hero">
    <div class="wrap hero-grid">
      <div>
        <div class="eyebrow"><span class="eyebrow-dot"></span>CỔNG THÔNG TIN SỨC KHỎE GIA ĐÌNH</div>
        <h1>Hiểu đúng triệu chứng. <span class="hero-accent">Chọn đúng nơi khám.</span></h1>
        <p class="hero-lead">
          Tìm nhanh bài viết y tế chính thống, kiểm tra chuyên khoa gợi ý theo triệu chứng và đặt lịch tại cơ sở y tế phù hợp — rõ ràng, dễ đọc cho mọi lứa tuổi.
        </p>

        <form class="hero-search" action="index.php#topics" method="get" role="search">
          <span class="hero-search-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
            </svg>
          </span>
          <input name="q" value="<?= e($query) ?>" placeholder="Nhập triệu chứng, tên bệnh hoặc chủ đề sức khỏe..." aria-label="Tìm triệu chứng hoặc bài viết sức khỏe">
          <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </form>

        <div class="quick-chips" aria-label="Tìm kiếm gợi ý phổ biến">
          <?php foreach ($quickTopics as $chip): ?>
            <a class="chip" href="<?= e($chip['href']) ?>"><?= e($chip['label']) ?></a>
          <?php endforeach; ?>
        </div>

        <p class="hero-safety-note">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
          </svg>
          Thông tin tham khảo giáo dục sức khỏe, không thay thế chẩn đoán trực tiếp của bác sĩ.
        </p>
      </div>

      <div class="hero-media">
        <picture>
          <source media="(max-width: 639px)" srcset="assets/img/photo-f197807244-780x975.webp 780w" sizes="100vw">
          <img
            src="assets/img/photo-f197807244.webp"
            srcset="assets/img/photo-f197807244-500.webp 500w, assets/img/photo-f197807244-960.webp 960w, assets/img/photo-f197807244-1280.webp 1280w, assets/img/photo-f197807244.webp 1920w"
            sizes="(max-width: 1024px) 100vw, 45vw"
            width="1920" height="1280"
            alt="Ống nghe y tế phục vụ thăm khám và tư vấn sức khỏe ban đầu"
            fetchpriority="high" decoding="async">
        </picture>
      </div>
    </div>
  </section>

  <!-- 3 THẺ DỊCH VỤ CHÍNH -->
  <section class="service-strip" aria-label="Chức năng chính">
    <div class="wrap service-grid">
      <a class="service-card" href="#symptom-search">
        <span class="service-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M9 12h6M12 9v6"/><circle cx="12" cy="12" r="9"/>
          </svg>
        </span>
        <h2>Tra cứu triệu chứng</h2>
        <p>Nhập biểu hiện đang gặp để xem chuyên khoa phù hợp và dấu hiệu cần đi khám sớm.</p>
        <span class="service-link">Tra cứu ngay →</span>
      </a>

      <a class="service-card" href="facilities.php">
        <span class="service-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>
          </svg>
        </span>
        <h2>Cơ sở y tế &amp; Bác sĩ</h2>
        <p>Tìm bệnh viện, phòng khám theo tỉnh/thành phố, chuyên khoa và đội ngũ bác sĩ.</p>
        <span class="service-link">Xem danh bạ →</span>
      </a>

      <a class="service-card" href="appointments.php">
        <span class="service-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>
          </svg>
        </span>
        <h2>Đặt lịch khám trực tuyến</h2>
        <p>Chọn cơ sở, bác sĩ, ngày giờ phù hợp cho bản thân hoặc hồ sơ người thân trong gia đình.</p>
        <span class="service-link">Chọn giờ khám →</span>
      </a>
    </div>
  </section>

  <!-- TRA CỨU TRIỆU CHỨNG -->
  <section class="section section-surface" id="symptom-search">
    <div class="wrap">
      <div class="section-head">
        <div>
          <div class="eyebrow"><span class="eyebrow-dot"></span>TRA CỨU TRIỆU CHỨNG</div>
          <h2>Bạn hoặc người thân đang có biểu hiện gì?</h2>
          <p class="section-sub">Gõ có dấu hoặc không dấu (ví dụ: “đau bụng” hoặc “dau bung”). Hệ thống gợi ý chuyên khoa và nơi khám liên quan.</p>
        </div>
      </div>

      <div class="lookup-box">
        <form method="get" action="index.php#symptom-results">
          <div class="lookup-form-grid">
            <label class="field">
              <span>Triệu chứng hoặc chuyên khoa</span>
              <input class="input" id="symptom" name="symptom" list="symptom-options" value="<?= e($symptomQuery) ?>" placeholder="Ví dụ: đau bụng, ho, nổi mẩn..." required>
              <datalist id="symptom-options">
                <?php foreach ($symptoms as $opt): ?>
                  <option value="<?= e($opt['Name']) ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </label>
            <label class="field">
              <span>Tỉnh / thành phố (không bắt buộc)</span>
              <input class="input" name="city" value="<?= e($city) ?>" placeholder="Ví dụ: Đà Nẵng">
            </label>
            <button type="submit" class="btn btn-primary">Tra cứu ngay</button>
          </div>
        </form>

        <div class="quick-chips" style="margin-top:16px;">
          <?php foreach (array_slice($symptoms, 0, 5) as $sym): ?>
            <a class="chip <?= $symptomQuery === $sym['Name'] ? 'is-active' : '' ?>"
               href="?symptom=<?= urlencode($sym['Name']) ?>&amp;city=<?= urlencode($city) ?>#symptom-results">
              <?= e($sym['Name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($symptomQuery !== ''): ?>
        <div class="lookup-results" id="symptom-results">
          <h3 style="margin:0 0 16px; font-size:22px;">Kết quả tra cứu cho “<?= e($symptomQuery) ?>”</h3>

          <?php if ($matchedSymptoms): ?>
            <div class="cards-3">
              <?php foreach ($matchedSymptoms as $sym): ?>
                <article class="card">
                  <span class="specialty-badge">Chuyên khoa gợi ý: <?= e($sym['Specialty']) ?></span>
                  <h3><?= e($sym['Name']) ?></h3>
                  <p><?= e($sym['Description']) ?></p>
                  <?php if (!empty($sym['WarningText'])): ?>
                    <div class="warning-box">
                      <strong>Dấu hiệu cần đi khám gấp:</strong> <?= e($sym['WarningText']) ?>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>

            <?php if ($relatedArticles): ?>
              <div style="margin-top:28px;">
                <h3 style="margin:0 0 14px;">Bài viết tham khảo liên quan</h3>
                <div class="cards-2">
                  <?php foreach ($relatedArticles as $rel): ?>
                    <a class="card card-hover" href="article.php?slug=<?= urlencode($rel['Slug']) ?>">
                      <span class="eyebrow"><?= e($rel['CategoryName']) ?></span>
                      <h3><?= e($rel['Title']) ?></h3>
                      <p><?= e($rel['Summary']) ?></p>
                      <span class="service-link">Đọc bài viết →</span>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div style="margin-top:28px;">
              <div class="section-head" style="margin-bottom:14px;">
                <h3 style="margin:0;">Cơ sở y tế có chuyên khoa liên quan</h3>
                <a class="text-link" href="facilities.php">Xem toàn bộ danh bạ →</a>
              </div>

              <?php if ($suggestedFacilities): ?>
                <div class="cards-3">
                  <?php foreach ($suggestedFacilities as $fac): ?>
                    <article class="card">
                      <span class="pill <?= $fac['IsVerified'] ? 'pill-ok' : 'pill-off' ?>">
                        <?= $fac['FacilityType'] === 'hospital' ? 'Bệnh viện' : 'Phòng khám' ?>
                        · <?= $fac['IsVerified'] ? 'Đã xác minh' : 'Chưa xác minh' ?>
                      </span>
                      <h3><?= e($fac['Name']) ?></h3>
                      <p><?= e($fac['Address']) ?>, <?= e($fac['City']) ?></p>
                      <p><strong>Chuyên khoa:</strong> <?= e($fac['Specialties']) ?></p>
                      <div style="margin-top:auto; padding-top:8px; display:flex; flex-wrap:wrap; gap:8px;">
                        <a class="btn btn-primary btn-sm" href="appointments.php?facility_id=<?= (int)$fac['FacilityID'] ?>">Đặt lịch khám</a>
                        <?php if (!empty($fac['WebsiteUrl'])): ?>
                          <a class="btn btn-secondary btn-sm" href="<?= e($fac['WebsiteUrl']) ?>" target="_blank" rel="noopener">Website ↗</a>
                        <?php endif; ?>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-state">Chưa tìm thấy cơ sở khớp với khu vực này. Bạn có thể xem danh sách đầy đủ tại <a class="text-link" href="facilities.php">Danh bạ cơ sở khám</a>.</div>
              <?php endif; ?>
            </div>

          <?php else: ?>
            <div class="empty-state">
              Chưa có mục triệu chứng “<?= e($symptomQuery) ?>” trong cơ sở dữ liệu HealthHub. Hệ thống không tự ý chẩn đoán khi thiếu dữ liệu.
            </div>

            <?php if ($didYouMean): ?>
              <div class="panel" style="margin:16px 0 0;">
                <div class="eyebrow">CÓ PHẢI BẠN MUỐN TÌM</div>
                <div class="quick-chips">
                  <?php foreach ($didYouMean as $sug): ?>
                    <a class="chip" href="?symptom=<?= urlencode($sug['Name']) ?>&amp;city=<?= urlencode($city) ?>#symptom-results"><?= e($sug['Name']) ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($fallbackArticles): ?>
              <div style="margin-top:20px;">
                <h3 style="margin:0 0 12px;">Bài viết có nhắc đến từ khóa của bạn</h3>
                <div class="cards-3">
                  <?php foreach ($fallbackArticles as $fb): ?>
                    <a class="card card-hover" href="article.php?slug=<?= urlencode($fb['Slug']) ?>">
                      <span class="eyebrow"><?= e($fb['CategoryName']) ?></span>
                      <h3><?= e($fb['Title']) ?></h3>
                      <p><?= e($fb['Summary']) ?></p>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="safety-grid">
              <div class="safety-card">
                <div class="eyebrow">KHUYẾN NGHỊ AN TOÀN</div>
                <h3>Chưa rõ chuyên khoa? Hãy khám Nội tổng quát</h3>
                <p>Bác sĩ đa khoa hoặc nội tổng quát sẽ thăm khám ban đầu và hướng dẫn chuyển chuyên khoa phù hợp.</p>
                <a class="btn btn-primary btn-sm" href="facilities.php?specialty=<?= urlencode('Nội tổng quát') ?>">Tìm nơi khám tổng quát</a>
              </div>
              <div class="safety-card is-emergency">
                <div class="eyebrow" style="color:var(--warn-ink);">DẤU HIỆU CẤP CỨU</div>
                <h3>Gọi 115 hoặc đi cấp cứu ngay nếu có:</h3>
                <p>Khó thở, đau thắt ngực, lơ mơ/hôn mê, co giật, sốt cao không hạ, nôn ra máu hoặc sưng nề lan nhanh.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- TIN Y TẾ MỚI NHẤT -->
  <section class="section" id="latest">
    <div class="wrap">
      <div class="section-head">
        <div>
          <div class="eyebrow"><span class="eyebrow-dot"></span>CẬP NHẬT SỨC KHỎE</div>
          <h2>Tin y tế mới nhất</h2>
          <p class="section-sub">Phân nhóm tin nguồn trong nước (Bộ Y tế / Việt Nam) và nguồn quốc tế (WHO).</p>
        </div>
      </div>

      <div class="tabs" role="tablist" aria-label="Lọc tin theo khu vực">
        <a class="chip <?= $region === 'all' ? 'is-active' : '' ?>" href="?region=all#latest">Tất cả tin</a>
        <a class="chip <?= $region === 'vietnam' ? 'is-active' : '' ?>" href="?region=vietnam#latest">Việt Nam</a>
        <a class="chip <?= $region === 'world' ? 'is-active' : '' ?>" href="?region=world#latest">Quốc tế</a>
      </div>

      <?php if ($newsFeatured): ?>
        <div class="news-layout">
          <article class="article-card">
            <a class="article-thumb" href="article.php?slug=<?= urlencode($newsFeatured['Slug']) ?>">
              <img src="<?= e(cover_image_or_default($newsFeatured['CoverImage'] ?? '', $newsFeatured['CategoryName'])) ?>" alt="<?= e($newsFeatured['Title']) ?>" loading="lazy" decoding="async">
            </a>
            <div class="article-body">
              <div class="article-meta">
                <strong><?= e($newsFeatured['CategoryName']) ?></strong>
                <span>·</span>
                <span><?= e($newsFeatured['SourceName'] ?: 'HealthHub') ?></span>
                <span>·</span>
                <span><?= local_dt($newsFeatured['PublishedAt'], 'd/m/Y') ?></span>
              </div>
              <h3><a href="article.php?slug=<?= urlencode($newsFeatured['Slug']) ?>"><?= e($newsFeatured['Title']) ?></a></h3>
              <p><?= e($newsFeatured['Summary']) ?></p>
              <div class="article-foot">
                <a class="text-link" href="article.php?slug=<?= urlencode($newsFeatured['Slug']) ?>">Đọc chi tiết →</a>
              </div>
            </div>
          </article>

          <div class="news-list">
            <?php foreach ($newsSecondary as $news): ?>
              <a class="news-row" href="article.php?slug=<?= urlencode($news['Slug']) ?>">
                <div class="news-row-img">
                  <img src="<?= e(cover_image_or_default($news['CoverImage'] ?? '', $news['CategoryName'])) ?>" alt="<?= e($news['Title']) ?>" loading="lazy" decoding="async">
                </div>
                <div class="news-row-copy">
                  <div class="article-meta">
                    <strong><?= e($news['CategoryName']) ?></strong>
                    <span><?= local_dt($news['PublishedAt'], 'd/m/Y') ?></span>
                  </div>
                  <h3><?= e($news['Title']) ?></h3>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="empty-state">Chưa có bản tin nào trong nhóm này.</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- KIẾN THỨC THEO CHỦ ĐỀ -->
  <section class="section section-surface" id="topics">
    <div class="wrap">
      <div class="section-head">
        <div>
          <div class="eyebrow"><span class="eyebrow-dot"></span>KIẾN THỨC CHĂM SÓC SỨC KHỎE</div>
          <h2><?= $query !== '' ? 'Kết quả tìm kiếm bài viết' : 'Khám phá theo chủ đề' ?></h2>
        </div>
        <span style="color:var(--muted); font-weight:600;"><?= count($articles) ?> bài viết</span>
      </div>

      <div class="tabs">
        <a class="chip <?= !$categoryId ? 'is-active' : '' ?>" href="index.php#topics">Tất cả chủ đề</a>
        <?php foreach ($categories as $cat): ?>
          <a class="chip <?= $categoryId === (int)$cat['CategoryID'] ? 'is-active' : '' ?>"
             href="?category=<?= (int)$cat['CategoryID'] ?>#topics">
            <?= e($cat['Name']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($articles): ?>
        <div class="cards-3">
          <?php foreach ($articles as $art): ?>
            <?php $isSaved = in_array((int)$art['ArticleID'], $savedIds, true); ?>
            <article class="article-card">
              <a class="article-thumb" href="article.php?slug=<?= urlencode($art['Slug']) ?>">
                <img src="<?= e(cover_image_or_default($art['CoverImage'] ?? '', $art['CategoryName'])) ?>" alt="<?= e($art['Title']) ?>" loading="lazy" decoding="async">
              </a>
              <div class="article-body">
                <div class="article-meta">
                  <strong><?= e($art['CategoryName']) ?></strong>
                  <span>·</span>
                  <span><?= local_dt($art['PublishedAt'], 'd/m/Y') ?></span>
                </div>
                <h3><a href="article.php?slug=<?= urlencode($art['Slug']) ?>"><?= e($art['Title']) ?></a></h3>
                <p><?= e($art['Summary']) ?></p>
                <div class="article-foot">
                  <a class="text-link" href="article.php?slug=<?= urlencode($art['Slug']) ?>">Đọc bài →</a>
                  <?php if (current_user()): ?>
                    <form method="post" action="favorite.php" style="margin:0;">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="article_id" value="<?= (int)$art['ArticleID'] ?>">
                      <input type="hidden" name="redirect_to" value="index.php#topics">
                      <button type="submit" class="save-btn <?= $isSaved ? 'is-saved' : '' ?>">
                        <?= $isSaved ? '♥ Đã lưu' : '♡ Lưu' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">Chưa có bài viết phù hợp với từ khóa hoặc chuyên mục đã chọn.</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- DẢI NHẤN TRÁCH NHIỆM Y TẾ (Dark Band Rhythm) -->
  <section class="band-dark">
    <div class="wrap band-dark-inner">
      <div>
        <div class="eyebrow" style="color:#8cd3dc;">CAM KẾT THÔNG TIN MINH BẠCH</div>
        <h2 style="margin:8px 0 0; font-size:26px;">Thông tin sức khỏe có kiểm chứng</h2>
        <p>
          Mọi nội dung trên HealthHub chỉ mang tính giáo dục và tham khảo ban đầu, không thay thế thăm khám trực tiếp từ bác sĩ. Trong trường hợp khẩn cấp, vui lòng gọi 115.
        </p>
      </div>
      <a class="btn btn-primary" href="facilities.php">Tìm cơ sở y tế ngay →</a>
    </div>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
