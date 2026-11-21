# HealthHub — Cổng Thông Tin Y Tế & Đặt Lịch Khám Bệnh

Đồ án Cơ sở 2 · Trường Đại học Công nghệ Thông tin và Truyền thông Việt - Hàn (VKU)

## 1. Công nghệ sử dụng
- **Backend:** PHP 8.2+ thuần (sử dụng extension `sqlsrv` kết nối trực tiếp SQL Server, **không dùng PDO**).
- **Cơ sở dữ liệu:** Microsoft SQL Server Express (`HealthHub` — 13 bảng).
- **Frontend:** HTML5, CSS3 thuần, JavaScript tối giản (giao diện *Frost & Slate* dễ đọc cho cả người lớn tuổi và người trẻ, hỗ trợ nút tăng cỡ chữ).

## 2. Yêu cầu môi trường & Cài đặt
1. Cài **XAMPP** (PHP 8.2+) và **SQL Server Express** (ví dụ `(local)\SQLEXPRESS`).
2. Bật extension **`php_sqlsrv`** trong `php.ini` (XAMPP PHP 8.2 x64).
3. Tạo SQL Login `healthhub_user` (mật khẩu mặc định trong `config.php` là `123`, bạn nên đổi lại khi triển khai thực tế) có quyền đọc/ghi/tạo bảng trên database `HealthHub`.
4. Mở [schema.sql](schema.sql) trong SSMS và nhấn **Execute**. Script tự động tạo database `HealthHub`, 13 bảng và nạp dữ liệu mẫu an toàn (tài khoản mẫu, danh mục, bài viết, triệu chứng, cơ sở y tế, bác sĩ, khung giờ khám).
5. Kiểm tra cấu hình kết nối trong [config.php](config.php) (`DB_SERVER`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`).
6. Mở trình duyệt truy cập theo cổng Apache của XAMPP (mặc định cổng 8080 trên máy hiện tại):
   - `http://localhost:8080/healthhub/` (hoặc `http://localhost/healthhub/` nếu Apache chạy cổng 80).

### Tài khoản mẫu (chỉ tạo khi bảng `Users` chưa có dữ liệu)
- **Admin mẫu:** `admin@healthhub.local` / Mật khẩu: `HealthHub@2026`
- **Member mẫu:** `member@healthhub.local` / Mật khẩu: `HealthHub@2026`

## 3. Cấu trúc thư mục
- `config.php`: Kết nối `sqlsrv`, các hàm truy vấn (`db_fetch_all`, `db_fetch_one`, `db_fetch_val`, `db_exec`), transaction, bảo mật XSS (`e()`), CSRF (`csrf_token()`, `verify_csrf()`), phân quyền, chuyển múi giờ UTC -> Việt Nam (`local_dt()`), bỏ dấu tiếng Việt (`remove_diacritics()`).
- `partials/header.php`, `partials/footer.php`: Giao diện đầu/cuối trang dùng chung, thanh điều hướng, nút đổi cỡ chữ lớn, thanh thao tác nhanh trên điện thoại.
- `index.php`: Trang chủ (Hero tìm kiếm thống nhất, 3 thẻ dịch vụ chính, tra cứu triệu chứng 5 lớp + ghi `SearchLogs`, tin y tế theo khu vực, lọc bài viết theo chuyên mục).
- `article.php`: Chi tiết bài viết, người rà soát, nguồn tham khảo, nút lưu bài viết và bài liên quan.
- `facilities.php`: Danh bạ cơ sở y tế và danh sách bác sĩ theo khu vực, chuyên khoa, loại hình.
- `appointments.php`: Đặt lịch khám mô phỏng (lọc cơ sở, bác sĩ, chuyên khoa, ngày, khu vực; transaction `UPDLOCK, HOLDLOCK`; chặn đặt trùng lịch; theo dõi & hủy lịch).
- `profile.php`: Hồ sơ cá nhân, đổi mật khẩu, quản lý bài viết đã lưu (`Favorites`), quản lý hồ sơ người thân (`FamilyProfiles`).
- `admin.php`: Trang quản trị đầy đủ (thống kê nhanh, CRUD bài viết/danh mục/triệu chứng/cơ sở/bác sĩ, duyệt trạng thái lịch hẹn, xem từ khóa thiếu `SearchLogs`, phân quyền tài khoản cho `admin`).
- `login.php`, `register.php`, `logout.php`, `favorite.php`, `credits.php`: Xác thực tài khoản, toggle yêu thích, ghi nguồn ảnh minh họa.

## 4. Quy ước kỹ thuật quan trọng
- **Múi giờ:** Database luôn lưu thời gian theo **UTC** (`SYSUTCDATETIME()`, `gmdate()`). Khi hiển thị ra giao diện dùng `local_dt()` để đổi về giờ Việt Nam (`Asia/Ho_Chi_Minh`). Khi admin tạo khung giờ khám (`datetime-local`), hệ thống tự quy đổi từ giờ Việt Nam sang UTC trước khi lưu.
- **Bảo mật:** Mọi truy vấn SQL đều dùng tham số `?` trong `sqlsrv_query`; mọi form `POST` đều kiểm tra CSRF token; mật khẩu dùng `password_hash` / `password_verify`.
