-- HealthHub SQL Server Schema & Idempotent Seed Data
-- Chạy trong SQL Server Management Studio (SSMS) hoặc sqlcmd.
-- Lưu ý: Toàn bộ thời gian trong database lưu theo chuẩn UTC (SYSUTCDATETIME()).

IF DB_ID('HealthHub') IS NULL
    CREATE DATABASE HealthHub;
GO

USE HealthHub;
GO

IF OBJECT_ID('Users', 'U') IS NULL
CREATE TABLE Users (
    UserID INT IDENTITY(1,1) PRIMARY KEY,
    DisplayName NVARCHAR(120) NOT NULL,
    Email NVARCHAR(255) NOT NULL UNIQUE,
    PasswordHash NVARCHAR(255) NOT NULL,
    Role NVARCHAR(20) NOT NULL DEFAULT 'member' CHECK (Role IN ('member','editor','admin')),
    IsActive BIT NOT NULL DEFAULT 1,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('Categories', 'U') IS NULL
CREATE TABLE Categories (
    CategoryID INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(100) NOT NULL,
    Slug NVARCHAR(120) NOT NULL UNIQUE,
    SortOrder INT NOT NULL DEFAULT 0,
    IsActive BIT NOT NULL DEFAULT 1
);

IF OBJECT_ID('Articles', 'U') IS NULL
CREATE TABLE Articles (
    ArticleID INT IDENTITY(1,1) PRIMARY KEY,
    CategoryID INT NOT NULL REFERENCES Categories(CategoryID),
    AuthorID INT NOT NULL REFERENCES Users(UserID),
    Title NVARCHAR(220) NOT NULL,
    Slug NVARCHAR(240) NOT NULL UNIQUE,
    Summary NVARCHAR(500) NOT NULL,
    Body NVARCHAR(MAX) NOT NULL,
    CoverImage NVARCHAR(500) NULL,
    SourceName NVARCHAR(200) NULL,
    SourceUrl NVARCHAR(1000) NULL,
    ReviewedBy NVARCHAR(150) NULL,
    Status NVARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (Status IN ('draft','published','archived')),
    IsFeatured BIT NOT NULL DEFAULT 0,
    PublishedAt DATETIME2 NULL,
    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('Favorites', 'U') IS NULL
CREATE TABLE Favorites (
    FavoriteID INT IDENTITY(1,1) PRIMARY KEY,
    UserID INT NOT NULL REFERENCES Users(UserID) ON DELETE CASCADE,
    ArticleID INT NOT NULL REFERENCES Articles(ArticleID) ON DELETE CASCADE,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_Favorites_User_Article UNIQUE(UserID, ArticleID)
);

IF OBJECT_ID('Facilities', 'U') IS NULL
CREATE TABLE Facilities (
    FacilityID INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(180) NOT NULL,
    FacilityType NVARCHAR(30) NOT NULL CHECK (FacilityType IN ('hospital','clinic')),
    Address NVARCHAR(300) NOT NULL,
    City NVARCHAR(120) NOT NULL,
    Phone NVARCHAR(40) NULL,
    WebsiteUrl NVARCHAR(1000) NULL,
    Specialties NVARCHAR(500) NULL,
    IsVerified BIT NOT NULL DEFAULT 0,
    IsActive BIT NOT NULL DEFAULT 1
);

IF OBJECT_ID('Symptoms', 'U') IS NULL
CREATE TABLE Symptoms (
    SymptomID INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(180) NOT NULL,
    Slug NVARCHAR(200) NOT NULL UNIQUE,
    Description NVARCHAR(600) NOT NULL,
    Specialty NVARCHAR(120) NOT NULL,
    WarningText NVARCHAR(500) NULL
);

IF OBJECT_ID('SymptomArticles', 'U') IS NULL
CREATE TABLE SymptomArticles (
    SymptomID INT NOT NULL REFERENCES Symptoms(SymptomID) ON DELETE CASCADE,
    ArticleID INT NOT NULL REFERENCES Articles(ArticleID) ON DELETE CASCADE,
    CONSTRAINT PK_SymptomArticles PRIMARY KEY (SymptomID, ArticleID)
);

IF OBJECT_ID('SymptomFacilities', 'U') IS NULL
CREATE TABLE SymptomFacilities (
    SymptomID INT NOT NULL REFERENCES Symptoms(SymptomID) ON DELETE CASCADE,
    FacilityID INT NOT NULL REFERENCES Facilities(FacilityID) ON DELETE CASCADE,
    CONSTRAINT PK_SymptomFacilities PRIMARY KEY (SymptomID, FacilityID)
);

IF OBJECT_ID('FamilyProfiles', 'U') IS NULL
CREATE TABLE FamilyProfiles (
    ProfileID INT IDENTITY(1,1) PRIMARY KEY,
    UserID INT NOT NULL REFERENCES Users(UserID) ON DELETE CASCADE,
    DisplayName NVARCHAR(120) NOT NULL,
    Relationship NVARCHAR(40) NOT NULL,
    BirthDate DATE NULL,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('Doctors', 'U') IS NULL
CREATE TABLE Doctors (
    DoctorID INT IDENTITY(1,1) PRIMARY KEY,
    FacilityID INT NOT NULL REFERENCES Facilities(FacilityID),
    DisplayName NVARCHAR(150) NOT NULL,
    Specialty NVARCHAR(120) NOT NULL,
    IsActive BIT NOT NULL DEFAULT 1
);

IF OBJECT_ID('AppointmentSlots', 'U') IS NULL
CREATE TABLE AppointmentSlots (
    SlotID INT IDENTITY(1,1) PRIMARY KEY,
    FacilityID INT NOT NULL REFERENCES Facilities(FacilityID),
    DoctorID INT NULL REFERENCES Doctors(DoctorID),
    StartsAt DATETIME2 NOT NULL,
    EndsAt DATETIME2 NOT NULL,
    Capacity INT NOT NULL DEFAULT 1 CHECK (Capacity > 0),
    IsActive BIT NOT NULL DEFAULT 1,
    CONSTRAINT CK_AppointmentSlots_Time CHECK (EndsAt > StartsAt)
);

IF OBJECT_ID('Appointments', 'U') IS NULL
CREATE TABLE Appointments (
    AppointmentID INT IDENTITY(1,1) PRIMARY KEY,
    UserID INT NOT NULL REFERENCES Users(UserID),
    ProfileID INT NULL REFERENCES FamilyProfiles(ProfileID),
    SlotID INT NOT NULL REFERENCES AppointmentSlots(SlotID),
    PatientName NVARCHAR(120) NOT NULL,
    Note NVARCHAR(500) NULL,
    Status NVARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (Status IN ('pending','confirmed','cancelled','completed')),
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_Appointments_User_Slot UNIQUE(UserID, SlotID)
);

IF OBJECT_ID('SearchLogs', 'U') IS NULL
CREATE TABLE SearchLogs (
    LogID INT IDENTITY(1,1) PRIMARY KEY,
    Keyword NVARCHAR(200) NOT NULL,
    FoundCount INT NOT NULL DEFAULT 0,
    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

-- 1. Tài khoản mẫu mặc định cho bản cài mới (mật khẩu: HealthHub@2026)
IF NOT EXISTS (SELECT 1 FROM Users)
INSERT INTO Users (DisplayName, Email, PasswordHash, Role) VALUES
(N'Quản trị viên HealthHub', N'admin@healthhub.local', N'$2y$10$e7dnJnP3T5Nq6uu7NzD1neI3J4QGEzpx5Bt82g.8o7evNf895n1VC', N'admin'),
(N'Thành viên mẫu', N'member@healthhub.local', N'$2y$10$e7dnJnP3T5Nq6uu7NzD1neI3J4QGEzpx5Bt82g.8o7evNf895n1VC', N'member');
GO

-- 2. Danh mục bài viết
IF NOT EXISTS (SELECT 1 FROM Categories)
INSERT INTO Categories (Name, Slug, SortOrder) VALUES
(N'Dinh dưỡng', N'dinh-duong', 1),
(N'Sức khỏe tinh thần', N'suc-khoe-tinh-than', 2),
(N'Vận động', N'van-dong', 3),
(N'Phòng bệnh', N'phong-benh', 4),
(N'Sức khỏe gia đình', N'suc-khoe-gia-dinh', 5);
GO

-- 3. Bài viết mẫu (lấy AuthorID đầu tiên hiện có để tránh lỗi khóa ngoại)
DECLARE @DefaultAuthorID INT = (SELECT TOP 1 UserID FROM Users ORDER BY UserID ASC);

IF @DefaultAuthorID IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM Articles WHERE Slug = N'an-uong-can-bang-thay-doi-nho')
    INSERT INTO Articles (CategoryID, AuthorID, Title, Slug, Summary, Body, CoverImage, SourceName, SourceUrl, ReviewedBy, Status, IsFeatured, PublishedAt)
    VALUES (
        (SELECT TOP 1 CategoryID FROM Categories WHERE Slug = N'dinh-duong'),
        @DefaultAuthorID,
        N'Ăn uống cân bằng: những thay đổi nhỏ tạo nên khác biệt lớn',
        N'an-uong-can-bang-thay-doi-nho',
        N'Một chế độ ăn đa dạng, vừa đủ và phù hợp với thể trạng là nền tảng thiết thực để chăm sóc sức khỏe mỗi ngày.',
        N'Ăn uống cân bằng không có nghĩa là phải theo đuổi một thực đơn khắt khe. Hãy bắt đầu từ những lựa chọn dễ duy trì: bổ sung rau quả trong bữa ăn, uống đủ nước theo nhu cầu cơ thể và ưu tiên thực phẩm đa dạng.\n\nNhu cầu dinh dưỡng thay đổi theo tuổi, tình trạng sức khỏe và mức độ vận động. Nếu đang điều trị bệnh hoặc cần chế độ ăn riêng, hãy trao đổi với bác sĩ hoặc chuyên gia dinh dưỡng.\n\nThông tin trong bài mang tính giáo dục sức khỏe, không thay thế tư vấn cá nhân.',
        N'assets/img/photo-f51439323.webp',
        N'WHO',
        N'https://www.who.int/',
        N'Ban biên tập y khoa HealthHub',
        N'published', 1, SYSUTCDATETIME()
    );

    IF NOT EXISTS (SELECT 1 FROM Articles WHERE Slug = N'lang-nghe-suc-khoe-tinh-than')
    INSERT INTO Articles (CategoryID, AuthorID, Title, Slug, Summary, Body, CoverImage, SourceName, SourceUrl, ReviewedBy, Status, IsFeatured, PublishedAt)
    VALUES (
        (SELECT TOP 1 CategoryID FROM Categories WHERE Slug = N'suc-khoe-tinh-than'),
        @DefaultAuthorID,
        N'Chăm sóc sức khỏe tinh thần bắt đầu từ việc lắng nghe bản thân',
        N'lang-nghe-suc-khoe-tinh-than',
        N'Nghỉ ngơi, kết nối với người tin cậy và tìm hỗ trợ chuyên môn khi cần là những bước quan trọng để chăm sóc sức khỏe tinh thần.',
        N'Sức khỏe tinh thần là một phần quan trọng của sức khỏe tổng thể. Cảm xúc căng thẳng có thể xuất hiện trong những giai đoạn khác nhau của cuộc sống. Việc nhận diện cảm xúc và duy trì các thói quen sinh hoạt lành mạnh có thể hỗ trợ bạn ứng phó tốt hơn.\n\nHãy cân nhắc trò chuyện với người thân đáng tin cậy hoặc chuyên gia sức khỏe tâm thần nếu khó khăn kéo dài và ảnh hưởng đến cuộc sống hằng ngày.',
        N'assets/img/photo-f17187090.webp',
        N'WHO',
        N'https://www.who.int/',
        N'Ban biên tập y khoa HealthHub',
        N'published', 0, DATEADD(day, -2, SYSUTCDATETIME())
    );

    IF NOT EXISTS (SELECT 1 FROM Articles WHERE Slug = N'kham-suc-khoe-dinh-ky')
    INSERT INTO Articles (CategoryID, AuthorID, Title, Slug, Summary, Body, CoverImage, SourceName, SourceUrl, ReviewedBy, Status, IsFeatured, PublishedAt)
    VALUES (
        (SELECT TOP 1 CategoryID FROM Categories WHERE Slug = N'phong-benh'),
        @DefaultAuthorID,
        N'Khám sức khỏe định kỳ: hiểu đúng để chủ động hơn',
        N'kham-suc-khoe-dinh-ky',
        N'Việc khám và tầm soát nên dựa trên độ tuổi, tiền sử và tư vấn của nhân viên y tế thay vì áp dụng một lịch giống nhau cho tất cả.',
        N'Khám sức khỏe định kỳ giúp bạn trao đổi với nhân viên y tế về tình trạng hiện tại, tiền sử gia đình và các yếu tố nguy cơ. Danh mục kiểm tra phù hợp phụ thuộc vào từng người, không phải ai cũng cần cùng một xét nghiệm.\n\nHãy chuẩn bị danh sách thuốc đang sử dụng, triệu chứng cần hỏi và thông tin sức khỏe liên quan trước buổi khám.',
        N'assets/img/photo-f199976185.webp',
        N'Bộ Y tế',
        N'https://moh.gov.vn/',
        N'Ban biên tập y khoa HealthHub',
        N'published', 0, DATEADD(day, -4, SYSUTCDATETIME())
    );

    IF NOT EXISTS (SELECT 1 FROM Articles WHERE Slug = N'phong-benh-chu-dong')
    INSERT INTO Articles (CategoryID, AuthorID, Title, Slug, Summary, Body, CoverImage, SourceName, SourceUrl, ReviewedBy, Status, IsFeatured, PublishedAt)
    VALUES (
        (SELECT TOP 1 CategoryID FROM Categories WHERE Slug = N'phong-benh'),
        @DefaultAuthorID,
        N'Phòng bệnh chủ động qua thói quen sinh hoạt hằng ngày',
        N'phong-benh-chu-dong',
        N'Giữ vệ sinh tay, tiêm chủng đầy đủ theo khuyến cáo và duy trì vận động vừa sức giúp giảm nguy cơ mắc nhiều bệnh phổ biến.',
        N'Phòng bệnh chủ động là cách bảo vệ sức khỏe hiệu quả và ít tốn kém nhất cho cả trẻ nhỏ và người lớn tuổi.\n\nHãy duy trì thói quen rửa tay bằng xà phòng, ngủ đủ giấc, vận động tối thiểu 30 phút mỗi ngày phù hợp với thể trạng và theo dõi lịch tiêm chủng định kỳ.',
        N'assets/img/photo-f94015098.webp',
        N'WHO',
        N'https://www.who.int/',
        N'Ban biên tập y khoa HealthHub',
        N'published', 0, DATEADD(day, -1, SYSUTCDATETIME())
    );

    IF NOT EXISTS (SELECT 1 FROM Articles WHERE Slug = N'cham-soc-suc-khoe-gia-dinh-theo-mua')
    INSERT INTO Articles (CategoryID, AuthorID, Title, Slug, Summary, Body, CoverImage, SourceName, SourceUrl, ReviewedBy, Status, IsFeatured, PublishedAt)
    VALUES (
        (SELECT TOP 1 CategoryID FROM Categories WHERE Slug = N'suc-khoe-gia-dinh'),
        @DefaultAuthorID,
        N'Theo dõi sức khỏe người cao tuổi và trẻ nhỏ khi giao mùa',
        N'cham-soc-suc-khoe-gia-dinh-theo-mua',
        N'Thời tiết thay đổi dễ làm tăng các vấn đề hô hấp và tiêu hóa ở nhóm tuổi nhạy cảm; nhận biết dấu hiệu sớm giúp xử trí an toàn.',
        N'Khi thời tiết chuyển mùa, trẻ em và người cao tuổi là hai nhóm dễ bị ảnh hưởng nhất. Gia đình nên giữ ấm cổ họng vào sáng sớm, đảm bảo ăn chín uống sôi và theo dõi thân nhiệt khi có biểu hiện ho hoặc mệt mỏi.\n\nNếu xuất hiện sốt cao liên tục hoặc khó thở, hãy đưa người thân tới cơ sở y tế để được thăm khám kịp thời.',
        N'assets/img/photo-f24034859.webp',
        N'Bộ Y tế Việt Nam',
        N'https://moh.gov.vn/',
        N'Ban biên tập y khoa HealthHub',
        N'published', 0, DATEADD(day, -3, SYSUTCDATETIME())
    );
END
GO

-- 4. Triệu chứng & cảnh báo
IF NOT EXISTS (SELECT 1 FROM Symptoms WHERE Slug = N'dau-bung-kho-tieu')
INSERT INTO Symptoms (Name, Slug, Description, Specialty, WarningText) VALUES
(N'Đau bụng, khó tiêu', N'dau-bung-kho-tieu', N'Thông tin tham khảo về một số nguyên nhân thường gặp của đau bụng hoặc khó tiêu và cách chuẩn bị khi đi khám.', N'Tiêu hóa', N'Đau dữ dội, kéo dài, nôn ra máu hoặc đi ngoài phân đen cần được đánh giá y tế khẩn cấp.');

IF NOT EXISTS (SELECT 1 FROM Symptoms WHERE Slug = N'man-ngua-da')
INSERT INTO Symptoms (Name, Slug, Description, Specialty, WarningText) VALUES
(N'Nổi mẩn, ngứa da', N'man-ngua-da', N'Tìm hiểu những yếu tố có thể liên quan đến kích ứng hoặc dị ứng da và khi nào nên hỏi bác sĩ da liễu.', N'Da liễu', N'Khó thở, sưng môi/lưỡi hoặc choáng sau khi nổi mẩn là dấu hiệu cấp cứu.');

IF NOT EXISTS (SELECT 1 FROM Symptoms WHERE Slug = N'ho-dau-hong')
INSERT INTO Symptoms (Name, Slug, Description, Specialty, WarningText) VALUES
(N'Ho, đau họng', N'ho-dau-hong', N'Thông tin giáo dục sức khỏe về ho và đau họng, cách theo dõi triệu chứng và lựa chọn chuyên khoa phù hợp.', N'Tai mũi họng', N'Khó thở, tím tái hoặc lơ mơ cần gọi cấp cứu 115 ngay.');

IF NOT EXISTS (SELECT 1 FROM Symptoms WHERE Slug = N'dau-dau-chong-mat')
INSERT INTO Symptoms (Name, Slug, Description, Specialty, WarningText) VALUES
(N'Đau đầu, chóng mặt', N'dau-dau-chong-mat', N'Theo dõi tần suất đau đầu, nghỉ ngơi hợp lý và khám chuyên khoa khi triệu chứng kéo dài hoặc kèm mất ngủ.', N'Nội tổng quát', N'Đau đầu dữ dội đột ngột, yếu liệt tay chân hoặc nói khó cần cấp cứu ngay.');
GO

-- 5. Cơ sở y tế mẫu (dữ liệu minh họa đồ án)
IF NOT EXISTS (SELECT 1 FROM Facilities)
INSERT INTO Facilities (Name, FacilityType, Address, City, Phone, WebsiteUrl, Specialties, IsVerified) VALUES
(N'Bệnh viện Đa khoa Khu vực Ngũ Hành Sơn (Mẫu)', N'hospital', N'Đường Lê Văn Hiến, Quận Ngũ Hành Sơn', N'Đà Nẵng', N'0236 000 0101', N'https://example.com', N'Nội tổng quát; Tiêu hóa; Tai mũi họng', 1),
(N'Phòng khám Đa khoa Gia đình Sông Hàn (Mẫu)', N'clinic', N'Đường Nguyễn Văn Linh, Quận Hải Châu', N'Đà Nẵng', N'0236 000 0202', N'https://example.com', N'Nội tổng quát; Da liễu; Nhi khoa', 1),
(N'Phòng khám Chuyên khoa Tiêu hóa & Gan mật (Mẫu)', N'clinic', N'Đường Điện Biên Phủ, Quận Thanh Khê', N'Đà Nẵng', N'0236 000 0303', N'https://example.com', N'Tiêu hóa; Nội tổng quát', 1);
GO

-- 6. Bác sĩ mẫu
IF NOT EXISTS (SELECT 1 FROM Doctors)
INSERT INTO Doctors (FacilityID, DisplayName, Specialty)
SELECT f.FacilityID, d.DocName, d.Spec
FROM (VALUES
    (N'Bệnh viện Đa khoa Khu vực Ngũ Hành Sơn (Mẫu)', N'BS.CKI Nguyễn Minh Tuấn', N'Nội tổng quát'),
    (N'Bệnh viện Đa khoa Khu vực Ngũ Hành Sơn (Mẫu)', N'ThS.BS Lê Thị Thu Hà', N'Tai mũi họng'),
    (N'Phòng khám Đa khoa Gia đình Sông Hàn (Mẫu)', N'BS.CKI Trần Quốc Bảo', N'Da liễu'),
    (N'Phòng khám Đa khoa Gia đình Sông Hàn (Mẫu)', N'BS. Phạm Ngọc Lan', N'Nội tổng quát'),
    (N'Phòng khám Chuyên khoa Tiêu hóa & Gan mật (Mẫu)', N'BS.CKII Võ Văn Hùng', N'Tiêu hóa')
) AS d(FacName, DocName, Spec)
JOIN Facilities f ON f.Name = d.FacName;
GO

-- 7. Khung giờ khám mẫu trong 3 ngày tới (lưu theo UTC)
IF NOT EXISTS (SELECT 1 FROM AppointmentSlots)
INSERT INTO AppointmentSlots (FacilityID, DoctorID, StartsAt, EndsAt, Capacity)
SELECT d.FacilityID, d.DoctorID,
       DATEADD(hour, 2, DATEADD(day, v.DayOffset, CAST(CAST(SYSUTCDATETIME() AS DATE) AS DATETIME2))),
       DATEADD(hour, 3, DATEADD(day, v.DayOffset, CAST(CAST(SYSUTCDATETIME() AS DATE) AS DATETIME2))),
       3
FROM Doctors d
CROSS JOIN (VALUES (1), (2), (3)) AS v(DayOffset);
GO

-- 8. Liên kết Triệu chứng - Bài viết & Triệu chứng - Cơ sở
INSERT INTO SymptomArticles (SymptomID, ArticleID)
SELECT s.SymptomID, a.ArticleID
FROM Symptoms s CROSS JOIN Articles a
WHERE ((s.Slug = N'dau-bung-kho-tieu' AND a.Slug = N'an-uong-can-bang-thay-doi-nho')
   OR (s.Slug = N'man-ngua-da' AND a.Slug = N'phong-benh-chu-dong')
   OR (s.Slug = N'ho-dau-hong' AND a.Slug = N'cham-soc-suc-khoe-gia-dinh-theo-mua')
   OR (s.Slug = N'dau-dau-chong-mat' AND a.Slug = N'kham-suc-khoe-dinh-ky'))
  AND NOT EXISTS (SELECT 1 FROM SymptomArticles sa WHERE sa.SymptomID = s.SymptomID AND sa.ArticleID = a.ArticleID);
GO

INSERT INTO SymptomFacilities (SymptomID, FacilityID)
SELECT s.SymptomID, f.FacilityID
FROM Symptoms s
JOIN Facilities f ON f.Specialties LIKE N'%' + s.Specialty + N'%'
WHERE NOT EXISTS (SELECT 1 FROM SymptomFacilities sf WHERE sf.SymptomID = s.SymptomID AND sf.FacilityID = f.FacilityID);
GO
