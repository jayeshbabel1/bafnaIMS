<?php
require_once __DIR__ . '/../config/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    setupSchema($pdo);
    return $pdo;
}

function setupSchema(PDO $db): void {
    $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        email       TEXT UNIQUE NOT NULL,
        password    TEXT NOT NULL,
        phone       TEXT,
        firm        TEXT,
        city        TEXT,
        role        TEXT,
        experience  TEXT,
        verified    INTEGER DEFAULT 1,
        reset_token TEXT,
        reset_expires INTEGER,
        created_at  INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS products (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        name              TEXT NOT NULL,
        category          TEXT,
        subcategory       TEXT,
        color_subcategory TEXT,
        quarry_number     TEXT,
		total_quantity     REAL DEFAULT 0,
        quantity_available REAL DEFAULT 0,
        quantity_on_hold   REAL DEFAULT 0,
        pieces            INTEGER DEFAULT 0,
        thickness         TEXT,
        sizes             TEXT,
        cutter_size       TEXT,
        origin            TEXT,
        finish            TEXT,
        description       TEXT,
        in_stock          INTEGER DEFAULT 1,
        featured          INTEGER DEFAULT 0,
        palette           TEXT DEFAULT '[\"F2F0EC\",\"D8CFC4\",\"BFB0A0\"]',
        measurement_sheet TEXT,
        dna_report        TEXT,
        sort_order        INTEGER DEFAULT 0,
        created_at        INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS product_photos (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id  INTEGER NOT NULL,
        filename    TEXT NOT NULL,
        sort_order  INTEGER DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS shortlist (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL,
        product_id  INTEGER NOT NULL,
        created_at  INTEGER DEFAULT (strftime('%s','now')),
        UNIQUE(user_id, product_id),
        FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS inquiries (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER NOT NULL,
        product_id  INTEGER NOT NULL,
        message     TEXT,
        status      TEXT DEFAULT 'pending',
        admin_reply TEXT,
        created_at  INTEGER DEFAULT (strftime('%s','now')),
        FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS settings (
        key   TEXT PRIMARY KEY,
        value TEXT
    );

    CREATE TABLE IF NOT EXISTS admins (
        id       INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        name     TEXT
    );
    ");

    // Seed default admin if not exists
    $row = $db->query("SELECT id FROM admins WHERE username='admin'")->fetch();
    if (!$row) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO admins (username,password,name) VALUES (?,?,?)")
           ->execute(['admin', $hash, 'Administrator']);
    }

    // Seed sample products if empty
    $count = $db->query("SELECT COUNT(*) as c FROM products")->fetch()['c'];
    if ($count == 0) seedProducts($db);
}

function seedProducts(PDO $db): void {
    $products = [
        ['Calacatta Oro Supremo','Marble','Premium','White','QM-0421',340,28,'18 / 20','600×600, 800×800, 1200×600','48×48, 32×32','Carrara, Italy','Polished','Rare Italian marble with bold gold veining. Ideal for feature walls, flooring, and high-end countertops.',1,1,'["F5F0E8","E8D5B5","C4A96E"]'],
        ['Verde Alpi Classic','Marble','Classic','Exotic','QM-0318',180,15,'16','600×600, 900×600','24×24','Valle d\'Aosta, Italy','Polished / Honed','Dramatic alpine marble with rich forest green tones and white veining. A timeless luxury choice.',1,0,'["2D5A3D","4A8060","8EB89E"]'],
        ['Black Marquina Elite','Marble','Premium','Black','QM-0509',220,20,'18 / 20','600×600, 1200×600, 1200×1200','48×48','Markina-Xemein, Spain','Polished','Iconic Spanish marble with high contrast white veining. Commands attention in any space.',1,1,'["1A1A1A","2C2C2C","E8E8E8"]'],
        ['Silver Travertine Vein Cut','Travertine','Standard','Grey','QT-0201',410,35,'15 / 18','400×400, 600×400, 600×600','24×24','Tivoli, Italy','Unfilled / Filled','Cross-cut travertine revealing natural vein patterns. Warm and organic — perfect for spa and residential settings.',1,0,'["C4C0B8","A8A4A0","DEDAD6"]'],
        ['Honey Onyx Backlit','Onyx','Exotic','Exotic','QO-0102',95,8,'20','600×300, 1200×600','48×24','Iran','Polished','Translucent onyx — spectacular when backlit. Transforms any surface into a glowing focal point.',0,1,'["D4931A","E8B84A","F5D98A"]'],
        ['Rosa Portogallo Deluxe','Marble','Classic','Beige','QM-0612',260,22,'16 / 20','600×600, 900×600','24×24','Portugal','Polished / Brushed','Elegant Portuguese marble with soft blush tones. Adds warmth and femininity to luxury interiors.',1,0,'["E8CECE","D4A8A8","B88080"]'],
        ['Absolute Black Granite','Granite','Standard','Black','QG-0803',500,45,'18','600×600, 900×600, 1200×600','24×24, 48×24','Karnataka, India','Polished / Flamed','Indian absolute black granite. Zero visible grain — a bold statement for contemporary spaces.',1,0,'["0A0A0A","141414","282828"]'],
        ['Kashmir White Supreme','Granite','Premium','White','QG-0711',380,30,'18 / 20','600×600, 900×600','24×24','Jammu & Kashmir, India','Polished','Sought-after Indian granite with dramatic wine and golden crystal inclusions on a white base.',1,0,'["F0EDE8","D4C4B8","8B4563"]'],
        ['Pietra Grey Limestone','Limestone','Classic','Grey','QL-0305',145,12,'15 / 18','400×400, 600×400','16×16','Iran','Honed / Brushed','Iranian limestone with fossil inclusions. Understated and architectural — a designer favourite.',1,0,'["606060","787878","D0D0D0"]'],
        ['Silver Fox Quartzite','Quartzite','Premium','White','QM-0915',170,14,'20','1200×600, 1200×1200','48×24','Brazil','Polished / Leathered','Brazilian quartzite with dramatic movement. Hard-wearing and visually spectacular for flooring and statement walls.',1,1,'["E8E8E8","C0C0C0","8C8C8C"]'],
    ];
    $stmt = $db->prepare("INSERT INTO products (name,category,subcategory,color_subcategory,quarry_number,quantity,pieces,thickness,sizes,cutter_size,origin,finish,description,in_stock,featured,palette) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($products as $p) $stmt->execute($p);
}
