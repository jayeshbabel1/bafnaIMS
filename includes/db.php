<?php
require_once __DIR__ . '/../config/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        // Attempt to create the database if it doesn't exist
        $dsnNoDB = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $tmp = new PDO($dsnNoDB, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tmp->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        unset($tmp);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    }

   // setupSchema($pdo);
    return $pdo;
}

/*function setupSchema(PDO $db): void {
    // Users
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(200) NOT NULL,
        email         VARCHAR(200) NOT NULL UNIQUE,
        password      VARCHAR(255) NOT NULL,
        phone         VARCHAR(20),
        firm          VARCHAR(200),
        city          VARCHAR(100),
        role          VARCHAR(50),
        experience    VARCHAR(50),
        verified      TINYINT(1) NOT NULL DEFAULT 1,
        reset_token   VARCHAR(100),
        reset_expires INT UNSIGNED,
        created_at    INT UNSIGNED NOT NULL DEFAULT (UNIX_TIMESTAMP())
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Products
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name               VARCHAR(255) NOT NULL,
        category           VARCHAR(100),
        subcategory        VARCHAR(100),
        color_subcategory  VARCHAR(100),
        quarry_number      VARCHAR(100) UNIQUE,
        total_quantity     DOUBLE NOT NULL DEFAULT 0,
        quantity_available DOUBLE NOT NULL DEFAULT 0,
        quantity_on_hold   DOUBLE NOT NULL DEFAULT 0,
        pieces             INT NOT NULL DEFAULT 0,
        thickness          VARCHAR(50),
        sizes              VARCHAR(255),
        cutter_size        VARCHAR(100),
        origin             VARCHAR(200),
        finish             VARCHAR(200),
        description        TEXT,
        in_stock           TINYINT(1) NOT NULL DEFAULT 1,
        featured           TINYINT(1) NOT NULL DEFAULT 0,
        palette            VARCHAR(100) DEFAULT '[\"F2F0EC\",\"D8CFC4\",\"BFB0A0\"]',
        measurement_sheet  VARCHAR(255),
        dna_report         VARCHAR(255),
        sort_order         INT NOT NULL DEFAULT 0,
        created_at         INT UNSIGNED NOT NULL DEFAULT (UNIX_TIMESTAMP())
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Product photos
    $db->exec("CREATE TABLE IF NOT EXISTS product_photos (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id  INT UNSIGNED NOT NULL,
        filename    VARCHAR(255) NOT NULL,
        sort_order  INT NOT NULL DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Shortlist
    $db->exec("CREATE TABLE IF NOT EXISTS shortlist (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        product_id  INT UNSIGNED NOT NULL,
        created_at  INT UNSIGNED NOT NULL DEFAULT (UNIX_TIMESTAMP()),
        UNIQUE KEY uq_shortlist (user_id, product_id),
        FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Inquiries
    $db->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      INT UNSIGNED NOT NULL,
        product_id   INT UNSIGNED NOT NULL,
        message      TEXT,
        qty_required VARCHAR(50),
        status       VARCHAR(20) NOT NULL DEFAULT 'pending',
        admin_reply  TEXT,
        created_at   INT UNSIGNED NOT NULL DEFAULT (UNIX_TIMESTAMP()),
        FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Settings
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        `key`   VARCHAR(100) PRIMARY KEY,
        `value` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Admins
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name     VARCHAR(200)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default admin if not exists
    $row = $db->query("SELECT id FROM admins WHERE username='admin'")->fetch();
    if (!$row) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO admins (username, password, name) VALUES (?, ?, ?)")
           ->execute(['admin', $hash, 'Administrator']);
    }

    // Seed sample products if empty
    $count = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'];
    if ($count == 0) seedProducts($db);
}*/

/*function seedProducts(PDO $db): void {
    $products = [
        ['Calacatta Oro Supremo','Marble','Premium','White','QM-0421',340,340,0,28,'18 / 20','600×600, 800×800, 1200×600','48×48, 32×32','Carrara, Italy','Polished','Rare Italian marble with bold gold veining. Ideal for feature walls, flooring, and high-end countertops.',1,1,'["F5F0E8","E8D5B5","C4A96E"]'],
        ['Verde Alpi Classic','Marble','Classic','Exotic','QM-0318',180,165,15,15,'16','600×600, 900×600','24×24','Valle d\'Aosta, Italy','Polished / Honed','Dramatic alpine marble with rich forest green tones and white veining. A timeless luxury choice.',1,0,'["2D5A3D","4A8060","8EB89E"]'],
        ['Black Marquina Elite','Marble','Premium','Black','QM-0509',220,200,20,20,'18 / 20','600×600, 1200×600, 1200×1200','48×48','Markina-Xemein, Spain','Polished','Iconic Spanish marble with high contrast white veining. Commands attention in any space.',1,1,'["1A1A1A","2C2C2C","E8E8E8"]'],
        ['Silver Travertine Vein Cut','Travertine','Standard','Grey','QT-0201',410,390,20,35,'15 / 18','400×400, 600×400, 600×600','24×24','Tivoli, Italy','Unfilled / Filled','Cross-cut travertine revealing natural vein patterns. Warm and organic — perfect for spa and residential settings.',1,0,'["C4C0B8","A8A4A0","DEDAD6"]'],
        ['Honey Onyx Backlit','Onyx','Exotic','Exotic','QO-0102',95,95,0,8,'20','600×300, 1200×600','48×24','Iran','Polished','Translucent onyx — spectacular when backlit. Transforms any surface into a glowing focal point.',0,1,'["D4931A","E8B84A","F5D98A"]'],
        ['Rosa Portogallo Deluxe','Marble','Classic','Beige','QM-0612',260,240,20,22,'16 / 20','600×600, 900×600','24×24','Portugal','Polished / Brushed','Elegant Portuguese marble with soft blush tones. Adds warmth and femininity to luxury interiors.',1,0,'["E8CECE","D4A8A8","B88080"]'],
        ['Absolute Black Granite','Granite','Standard','Black','QG-0803',500,480,20,45,'18','600×600, 900×600, 1200×600','24×24, 48×24','Karnataka, India','Polished / Flamed','Indian absolute black granite. Zero visible grain — a bold statement for contemporary spaces.',1,0,'["0A0A0A","141414","282828"]'],
        ['Kashmir White Supreme','Granite','Premium','White','QG-0711',380,360,20,30,'18 / 20','600×600, 900×600','24×24','Jammu & Kashmir, India','Polished','Sought-after Indian granite with dramatic wine and golden crystal inclusions on a white base.',1,0,'["F0EDE8","D4C4B8","8B4563"]'],
        ['Pietra Grey Limestone','Limestone','Classic','Grey','QL-0305',145,130,15,12,'15 / 18','400×400, 600×400','16×16','Iran','Honed / Brushed','Iranian limestone with fossil inclusions. Understated and architectural — a designer favourite.',1,0,'["606060","787878","D0D0D0"]'],
        ['Silver Fox Quartzite','Quartzite','Premium','White','QM-0915',170,170,0,14,'20','1200×600, 1200×1200','48×24','Brazil','Polished / Leathered','Brazilian quartzite with dramatic movement. Hard-wearing and visually spectacular for flooring and statement walls.',1,1,'["E8E8E8","C0C0C0","8C8C8C"]'],
    ];
    $stmt = $db->prepare("INSERT INTO products
        (name,category,subcategory,color_subcategory,quarry_number,
         total_quantity,quantity_available,quantity_on_hold,pieces,
         thickness,sizes,cutter_size,origin,finish,description,in_stock,featured,palette)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($products as $p) $stmt->execute($p);
}*/
