<?php
declare(strict_types=1);

// Database luôn lưu mốc thời gian UTC; giao diện hiển thị theo giờ Việt Nam.
date_default_timezone_set('Asia/Ho_Chi_Minh');

const DB_SERVER = '(local)\\SQLEXPRESS';
const DB_NAME = 'HealthHub';
const DB_USER = 'healthhub_user';
const DB_PASSWORD = '123';

/**
 * Kết nối SQL Server bằng extension sqlsrv thuần (không dùng PDO).
 * Bật ReturnDatesAsStrings để DATETIME2 trả về chuỗi giống nhau ở mọi nơi.
 */
function db()
{
    static $conn = null;
    if ($conn === null) {
        $conn = sqlsrv_connect(DB_SERVER, [
            'Database' => DB_NAME,
            'UID' => DB_USER,
            'PWD' => DB_PASSWORD,
            'CharacterSet' => 'UTF-8',
            'ReturnDatesAsStrings' => true,
            'TrustServerCertificate' => true,
        ]);
        if ($conn === false) {
            throw new RuntimeException('Không kết nối được SQL Server. Kiểm tra cấu hình trong config.php.');
        }
    }
    return $conn;
}

/** Thực thi câu SQL có tham số (?) bằng sqlsrv_query và giải phóng lỗi nếu có. */
function db_query(string $sql, array $params = [])
{
    $stmt = sqlsrv_query(db(), $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
        $message = $errors[0]['message'] ?? 'Lỗi truy vấn SQL Server.';
        throw new RuntimeException($message);
    }
    return $stmt;
}

/** Lấy toàn bộ các dòng kết quả dạng mảng kết hợp (associative array). */
function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = db_query($sql, $params);
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

/** Lấy 1 dòng đầu tiên hoặc null nếu không có. */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db_query($sql, $params);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

/** Lấy 1 giá trị ở cột đầu tiên của dòng đầu tiên (COUNT, INSERT OUTPUT...). */
function db_fetch_val(string $sql, array $params = []): mixed
{
    $stmt = db_query($sql, $params);
    $value = null;
    if (sqlsrv_fetch($stmt) === true) {
        $value = sqlsrv_get_field($stmt, 0);
    }
    sqlsrv_free_stmt($stmt);
    return $value;
}

/** Thực thi INSERT / UPDATE / DELETE và trả về số dòng bị ảnh hưởng. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db_query($sql, $params);
    $affected = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    return is_int($affected) ? $affected : 0;
}

function db_begin(): void
{
    if (!sqlsrv_begin_transaction(db())) {
        throw new RuntimeException('Không thể bắt đầu giao dịch cơ sở dữ liệu.');
    }
}

function db_commit(): void
{
    if (!sqlsrv_commit(db())) {
        throw new RuntimeException('Không thể hoàn tất giao dịch cơ sở dữ liệu.');
    }
}

function db_rollback(): void
{
    sqlsrv_rollback(db());
}

/** Chống XSS khi in dữ liệu ra HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Phiên biểu mẫu không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.');
    }
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function pop_flash(): string
{
    $message = (string)($_SESSION['flash'] ?? '');
    unset($_SESSION['flash']);
    return $message;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Chỉ cho phép chuyển hướng về đường dẫn nội bộ an toàn (tránh Open Redirect). */
function safe_back_url(string $fallback = 'index.php'): string
{
    $ref = (string)($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') {
        return $fallback;
    }
    $parsed = parse_url($ref);
    if ($parsed === false || isset($parsed['host']) && $parsed['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
        return $fallback;
    }
    $path = basename((string)($parsed['path'] ?? $fallback));
    $allowed = ['index.php', 'article.php', 'facilities.php', 'appointments.php', 'profile.php', 'admin.php'];
    if (!in_array($path, $allowed, true)) {
        return $fallback;
    }
    $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
    return $path . $query;
}

function require_login(): array
{
    if (!current_user()) {
        flash('Vui lòng đăng nhập để sử dụng chức năng này.');
        redirect('login.php');
    }
    return current_user();
}

function require_editor(): array
{
    $user = require_login();
    if (!in_array($user['role'], ['editor', 'admin'], true)) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập khu vực quản trị.');
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

/** Chuyển mốc thời gian UTC từ SQL Server sang giờ Việt Nam để hiển thị. */
function local_dt(?string $utc, string $format = 'd/m/Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $clean = preg_replace('/\.\d+$/', '', trim($utc)) ?? trim($utc);
    $ts = strtotime($clean . ' UTC');
    return $ts === false ? '' : date($format, $ts);
}

/** Tạo slug URL sạch không dấu từ tiêu đề tiếng Việt. */
function create_slug(string $value): string
{
    $value = remove_diacritics(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'healthhub-' . bin2hex(random_bytes(4));
}

/** Bỏ dấu tiếng Việt để hỗ trợ tìm kiếm gần đúng (fuzzy search) và tạo slug. */
function remove_diacritics(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $groups = [
        'a' => 'á à ả ã ạ ă ắ ằ ẳ ẵ ặ â ấ ầ ẩ ẫ ậ',
        'd' => 'đ',
        'e' => 'é è ẻ ẽ ẹ ê ế ề ể ễ ệ',
        'i' => 'í ì ỉ ĩ ị',
        'o' => 'ó ò ỏ õ ọ ô ố ồ ổ ỗ ộ ơ ớ ờ ở ỡ ợ',
        'u' => 'ú ù ủ ũ ụ ư ứ ừ ử ữ ự',
        'y' => 'ý ỳ ỷ ỹ ỵ',
    ];
    foreach ($groups as $ascii => $chars) {
        $value = strtr($value, array_fill_keys(preg_split('/\s+/u', $chars) ?: [], $ascii));
    }
    return $value;
}

/** Nhãn tiếng Việt và class màu cho trạng thái lịch hẹn / bài viết. */
function appointment_status_meta(string $status): array
{
    return match ($status) {
        'confirmed' => ['label' => 'Đã xác nhận', 'class' => 'pill-ok'],
        'completed' => ['label' => 'Đã hoàn thành', 'class' => 'pill-done'],
        'cancelled' => ['label' => 'Đã hủy', 'class' => 'pill-off'],
        default => ['label' => 'Chờ xác nhận', 'class' => 'pill-wait'],
    };
}

/** Đảm bảo đường dẫn ảnh bìa dùng ảnh thật nội bộ nếu bài viết chưa có ảnh riêng. */
function cover_image_or_default(?string $cover, string $categoryName = ''): string
{
    $cover = trim((string)$cover);
    if ($cover !== '') {
        return $cover;
    }
    $cat = mb_strtolower($categoryName, 'UTF-8');
    if (str_contains($cat, 'dinh dưỡng')) {
        return 'assets/img/photo-f51439323.webp';
    }
    if (str_contains($cat, 'tinh thần')) {
        return 'assets/img/photo-f17187090.webp';
    }
    if (str_contains($cat, 'vận động')) {
        return 'assets/img/photo-f94015098.webp';
    }
    if (str_contains($cat, 'gia đình')) {
        return 'assets/img/photo-f24034859.webp';
    }
    return 'assets/img/photo-f199976185.webp';
}
