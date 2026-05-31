<?php
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS estudiantes (
        id BIGSERIAL PRIMARY KEY,
        nombre TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        carrera TEXT NOT NULL,
        creado_en TIMESTAMPTZ DEFAULT NOW()
    );";
    
    $pdo->exec($sql);
    echo "✅ Tabla creada exitosamente en Supabase!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>