<?php
// update_db_siigo.php

require_once __DIR__ . '/app/Core/config.php';
require_once __DIR__ . '/app/Core/Database.php';

try {
    $db = \App\Core\Database::getInstance()->getConnection();
    echo "Conectado a la base de datos.\n";

    // 1. Update EMPRESAS table
    echo "Actualizando tabla 'empresas'...\n";
    $stm = $db->query("SHOW COLUMNS FROM empresas LIKE 'siigo_user'");
    if ($stm->rowCount() == 0) {
        $sql = "ALTER TABLE empresas 
                ADD COLUMN siigo_user VARCHAR(255) NULL DEFAULT NULL AFTER estado_suscripcion,
                ADD COLUMN siigo_api_key TEXT NULL DEFAULT NULL AFTER siigo_user,
                ADD COLUMN siigo_partner_id VARCHAR(255) NULL DEFAULT NULL AFTER siigo_api_key";
        $db->exec($sql);
        echo "- Columnas de Siigo agregadas a 'empresas'.\n";
    } else {
        echo "- Tabla 'empresas' ya actualizada.\n";
    }

    // 2. Update CLIENTES table
    echo "Actualizando tabla 'clientes'...\n";
    $stm = $db->query("SHOW COLUMNS FROM clientes LIKE 'siigo_id'");
    if ($stm->rowCount() == 0) {
        $sql = "ALTER TABLE clientes ADD COLUMN siigo_id VARCHAR(255) NULL DEFAULT NULL AFTER dv";
        $db->exec($sql);
        echo "- Columna 'siigo_id' agregada a 'clientes'.\n";
    } else {
        echo "- Tabla 'clientes' ya actualizada.\n";
    }

    // 3. Update PRODUCTOS table
    echo "Actualizando tabla 'productos'...\n";
    $stm = $db->query("SHOW COLUMNS FROM productos LIKE 'siigo_id'");
    if ($stm->rowCount() == 0) {
        $sql = "ALTER TABLE productos ADD COLUMN siigo_id VARCHAR(255) NULL DEFAULT NULL AFTER codigo_producto";
        $db->exec($sql);
        echo "- Columna 'siigo_id' agregada a 'productos'.\n";
    } else {
        echo "- Tabla 'productos' ya actualizada.\n";
    }

    // 4. Update FACTURAS table
    echo "Actualizando tabla 'facturas'...\n";
    $stm = $db->query("SHOW COLUMNS FROM facturas LIKE 'siigo_id'");
    if ($stm->rowCount() == 0) {
        $sql = "ALTER TABLE facturas 
                ADD COLUMN siigo_id VARCHAR(255) NULL DEFAULT NULL AFTER uuid,
                ADD COLUMN cufe VARCHAR(255) NULL DEFAULT NULL AFTER siigo_id,
                ADD COLUMN url_pdf_dian TEXT NULL DEFAULT NULL AFTER cufe";
        $db->exec($sql);
        echo "- Columnas de Siigo agregadas a 'facturas'.\n";
    } else {
        echo "- Tabla 'facturas' ya actualizada.\n";
    }

    echo "\n¡Actualización de base de datos completada con éxito!\n";

} catch (PDOException $e) {
    die("Error de Base de Datos: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error General: " . $e->getMessage() . "\n");
}
