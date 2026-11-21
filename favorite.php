<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

$articleId = filter_input(INPUT_POST, 'article_id', FILTER_VALIDATE_INT);
if ($articleId) {
    $exists = db_fetch_val(
        'SELECT FavoriteID FROM Favorites WHERE UserID = ? AND ArticleID = ?',
        [$user['id'], $articleId]
    );
    if ($exists) {
        db_exec('DELETE FROM Favorites WHERE UserID = ? AND ArticleID = ?', [$user['id'], $articleId]);
        flash('Đã bỏ bài viết khỏi danh sách đã lưu.');
    } else {
        db_exec('INSERT INTO Favorites (UserID, ArticleID) VALUES (?, ?)', [$user['id'], $articleId]);
        flash('Đã lưu bài viết vào Hồ sơ cá nhân.');
    }
}

redirect(safe_back_url('index.php'));
