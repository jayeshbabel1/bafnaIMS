<?php
function ensureCategoryTables(): void {
    static $d=false; if($d)return; $db=getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS product_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        sort_order INT NOT NULL DEFAULT 0,
        created_at INT UNSIGNED NOT NULL,
        updated_at INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cnt=(int)$db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
    if($cnt===0){
        $now=time(); $i=0;
        $ins=$db->prepare("INSERT IGNORE INTO product_categories (name,sort_order,created_at,updated_at) VALUES (?,?,?,?)");
        foreach(['Marble','Travertino','Onyx','Quartzite'] as $c) $ins->execute([$c,$i++,$now,$now]);
    }
    $d=true;
}

function getAllCategories(): array {
    ensureCategoryTables();
    return getDB()->query("SELECT * FROM product_categories ORDER BY sort_order ASC, name ASC")->fetchAll();
}
function getCategoryNames(): array {
    return array_column(getAllCategories(),'name');
}
function getCategory(int $id): ?array {
    $st=getDB()->prepare("SELECT * FROM product_categories WHERE id=?"); $st->execute([$id]);
    return $st->fetch() ?: null;
}
function categoryProductCount(string $name): int {
    $st=getDB()->prepare("SELECT COUNT(*) FROM products WHERE category=?"); $st->execute([$name]);
    return (int)$st->fetchColumn();
}
function createCategory(array $d): array {
    $name=trim($d['name']??'');
    if(!$name) return ['success'=>false,'error'=>'Name required.'];
    $chk=getDB()->prepare("SELECT id FROM product_categories WHERE name=?"); $chk->execute([$name]);
    if($chk->fetch()) return ['success'=>false,'error'=>'Category already exists.'];
    $now=time();
    $max=(int)getDB()->query("SELECT COALESCE(MAX(sort_order),0) FROM product_categories")->fetchColumn();
    getDB()->prepare("INSERT INTO product_categories (name,sort_order,created_at,updated_at) VALUES (?,?,?,?)")
        ->execute([$name,$max+1,$now,$now]);
    return ['success'=>true];
}
function updateCategory(int $id, array $d): array {
    $name=trim($d['name']??'');
    if(!$name) return ['success'=>false,'error'=>'Name required.'];
    $old=getCategory($id);
    if(!$old) return ['success'=>false,'error'=>'Category not found.'];
    $chk=getDB()->prepare("SELECT id FROM product_categories WHERE name=? AND id<>?"); $chk->execute([$name,$id]);
    if($chk->fetch()) return ['success'=>false,'error'=>'Another category already uses that name.'];
    $db=getDB(); $db->beginTransaction();
    try{
        $db->prepare("UPDATE product_categories SET name=?, updated_at=? WHERE id=?")->execute([$name,time(),$id]);
        if($old['name']!==$name){
            $db->prepare("UPDATE products SET category=? WHERE category=?")->execute([$name,$old['name']]);
        }
        $db->commit();
    }catch(Throwable $e){ $db->rollBack(); return ['success'=>false,'error'=>$e->getMessage()]; }
    return ['success'=>true];
}
function deleteCategory(int $id): array {
    $c=getCategory($id);
    if(!$c) return ['success'=>false,'error'=>'Category not found.'];
    if(categoryProductCount($c['name'])>0) return ['success'=>false,'error'=>'Cannot delete — products are assigned to this category.'];
    getDB()->prepare("DELETE FROM product_categories WHERE id=?")->execute([$id]);
    return ['success'=>true];
}
// Import Excel helper — get-or-create by name, returns canonical name
function resolveCategoryByName(string $name): string {
    $name=trim($name);
    if($name==='') return '';
    ensureCategoryTables();
    $st=getDB()->prepare("SELECT name FROM product_categories WHERE name=?"); $st->execute([$name]);
    $row=$st->fetchColumn();
    if($row) return $row;
    createCategory(['name'=>$name]);
    return $name;
}
function ensureCategoryPermissions(): void {
    static $done=false; if($done)return; $done=true;
    try{
        $db=getDB();
        if(!$db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch()) return;
        $perms=[
            ['categories.view','View Product Categories'],
            ['categories.create','Create Product Categories'],
            ['categories.edit','Edit Product Categories'],
            ['categories.delete','Delete Product Categories'],
        ];
        $maxSort=(int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $chk=$db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $ins=$db->prepare("INSERT INTO admin_permissions (module,action,label,sort_order) VALUES ('Settings',?,?,?)");
        foreach($perms as $p){
            $chk->execute([$p[0]]);
            if(!$chk->fetch()) $ins->execute([$p[0],$p[1],++$maxSort]);
        }
    }catch(Throwable $e){ error_log('ensureCategoryPermissions: '.$e->getMessage()); }
}