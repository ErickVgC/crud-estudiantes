<?php
// index.php - Sistema CRUD completo de estudiantes
require_once 'config/database.php';

// Inicializar variables
$mensaje = '';
$tipo_mensaje = ''; // 'exito' o 'error'
$estudiante_editar = null;

// Obtener parámetros de paginación y búsqueda
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$por_pagina = 5;
$offset = ($pagina - 1) * $por_pagina;

try {
    $pdo = getDBConnection();
    
    // === CREATE: Insertar estudiante ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['accion']) && $_POST['accion'] === 'crear') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $carrera = trim($_POST['carrera']);
            
            // Validaciones
            $errores = [];
            if (strlen($nombre) < 3 || strlen($nombre) > 100) {
                $errores[] = "El nombre debe tener entre 3 y 100 caracteres";
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El email no es válido";
            }
            if (strlen($carrera) < 3 || strlen($carrera) > 100) {
                $errores[] = "La carrera debe tener entre 3 y 100 caracteres";
            }
            
            if (empty($errores)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO estudiantes (nombre, email, carrera) VALUES (:nombre, :email, :carrera)");
                    $stmt->execute([
                        ':nombre' => htmlspecialchars($nombre),
                        ':email' => $email,
                        ':carrera' => htmlspecialchars($carrera)
                    ]);
                    $mensaje = "✅ Estudiante registrado exitosamente";
                    $tipo_mensaje = "exito";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'duplicate key') !== false) {
                        $mensaje = "❌ Ya existe un estudiante con ese email";
                    } else {
                        $mensaje = "❌ Error al registrar: " . $e->getMessage();
                    }
                    $tipo_mensaje = "error";
                }
            } else {
                $mensaje = "❌ " . implode("<br>", $errores);
                $tipo_mensaje = "error";
            }
            header("Location: index.php?pagina=" . $pagina . ($buscar ? "&buscar=" . urlencode($buscar) : ""));
            exit;
        }
        
        // === UPDATE: Actualizar estudiante ===
        if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $nombre = trim($_POST['nombre']);
            $carrera = trim($_POST['carrera']);
            
            $errores = [];
            if (strlen($nombre) < 3 || strlen($nombre) > 100) {
                $errores[] = "El nombre debe tener entre 3 y 100 caracteres";
            }
            if (strlen($carrera) < 3 || strlen($carrera) > 100) {
                $errores[] = "La carrera debe tener entre 3 y 100 caracteres";
            }
            
            if (empty($errores)) {
                $stmt = $pdo->prepare("UPDATE estudiantes SET nombre = :nombre, carrera = :carrera WHERE id = :id");
                $stmt->execute([
                    ':nombre' => htmlspecialchars($nombre),
                    ':carrera' => htmlspecialchars($carrera),
                    ':id' => $id
                ]);
                $mensaje = "✅ Estudiante actualizado exitosamente";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "❌ " . implode("<br>", $errores);
                $tipo_mensaje = "error";
            }
            header("Location: index.php?pagina=" . $pagina . ($buscar ? "&buscar=" . urlencode($buscar) : ""));
            exit;
        }
    }
    
    // === DELETE: Eliminar estudiante ===
    if (isset($_GET['eliminar'])) {
        $id = (int)$_GET['eliminar'];
        $stmt = $pdo->prepare("DELETE FROM estudiantes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $mensaje = "✅ Estudiante eliminado exitosamente";
        $tipo_mensaje = "exito";
        header("Location: index.php?pagina=" . $pagina . ($buscar ? "&buscar=" . urlencode($buscar) : ""));
        exit;
    }
    
    // === EDIT: Obtener datos para editar ===
    if (isset($_GET['editar'])) {
        $id = (int)$_GET['editar'];
        $stmt = $pdo->prepare("SELECT * FROM estudiantes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $estudiante_editar = $stmt->fetch();
        if (!$estudiante_editar) {
            header("Location: index.php");
            exit;
        }
    }
    
    // === READ: Listar estudiantes con paginación y búsqueda ===
    if (!empty($buscar)) {
        // Con búsqueda
        $sql_count = "SELECT COUNT(*) as total FROM estudiantes WHERE nombre ILIKE :buscar OR carrera ILIKE :buscar";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute([':buscar' => "%$buscar%"]);
        $total = $stmt_count->fetch()['total'];
        
        $sql = "SELECT * FROM estudiantes WHERE nombre ILIKE :buscar OR carrera ILIKE :buscar 
                ORDER BY creado_en DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':buscar', "%$buscar%");
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    } else {
        // Sin búsqueda
        $stmt_count = $pdo->query("SELECT COUNT(*) as total FROM estudiantes");
        $total = $stmt_count->fetch()['total'];
        
        $sql = "SELECT * FROM estudiantes ORDER BY creado_en DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $estudiantes = $stmt->fetchAll();
    $total_paginas = ceil($total / $por_pagina);
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema CRUD - Gestión de Estudiantes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #FFFFFF 0%, #FFFFFF 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .mensaje {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .mensaje.exito {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .mensaje.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .btn-cancelar {
            background: #6c757d;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 5px;
            color: white;
            display: inline-block;
            margin-left: 10px;
            font-weight: bold;
        }
        
        .btn-cancelar:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .search-section {
            margin-bottom: 30px;
        }
        
        .search-bar {
            display: flex;
            gap: 10px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-bar button {
            background: #28a745;
            padding: 12px 24px;
        }
        
        .search-bar button:hover {
            background: #218838;
        }
        
        .btn-limpiar {
            background: #6c757d;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .acciones {
            display: flex;
            gap: 8px;
        }
        
        .btn-editar {
            background: #ffc107;
            color: #333;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .btn-editar:hover {
            background: #e0a800;
        }
        
        .btn-eliminar {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .btn-eliminar:hover {
            background: #c82333;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #667eea;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .pagination span {
            background: #667eea;
            color: white;
            border: none;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .info {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .acciones {
                flex-direction: column;
            }
            
            .search-bar {
                flex-direction: column;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sistema de Gestión de Estudiantes</h1>
            <p>CRUD completo con PHP, PostgreSQL y Supabase</p>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="mensaje <?= $tipo_mensaje ?>">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulario de Registro/Edición -->
            <div class="form-section">
                <h2><?= $estudiante_editar ? ' Editar Estudiante' : 'Registrar Nuevo Estudiante' ?></h2>
                <form method="POST">
                    <input type="hidden" name="accion" value="<?= $estudiante_editar ? 'actualizar' : 'crear' ?>">
                    <?php if ($estudiante_editar): ?>
                        <input type="hidden" name="id" value="<?= $estudiante_editar['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Nombre completo:</label>
                        <input type="text" name="nombre" required 
                               value="<?= $estudiante_editar ? htmlspecialchars($estudiante_editar['nombre']) : '' ?>"
                               placeholder="Ej: Juan Pérez">
                    </div>
                    
                    <?php if (!$estudiante_editar): ?>
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" name="email" required 
                                   placeholder="Ej: juan@ejemplo.com">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Carrera:</label>
                        <input type="text" name="carrera" required 
                               value="<?= $estudiante_editar ? htmlspecialchars($estudiante_editar['carrera']) : '' ?>"
                               placeholder="Ej: Ingeniería Informática">
                    </div>
                    
                    <button type="submit"><?= $estudiante_editar ? 'Actualizar Estudiante' : 'Guardar Estudiante' ?></button>
                    
                    <?php if ($estudiante_editar): ?>
                        <a href="index.php?pagina=<?= $pagina ?><?= $buscar ? '&buscar=' . urlencode($buscar) : '' ?>" class="btn-cancelar">Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Barra de Búsqueda -->
            <div class="search-section">
                <form method="GET" style="background: none; padding: 0;">
                    <div class="search-bar">
                        <input type="text" name="buscar" placeholder="Buscar por nombre o carrera..." 
                               value="<?= htmlspecialchars($buscar) ?>">
                        <button type="submit">Buscar</button>
                        <?php if ($buscar): ?>
                            <a href="index.php" class="btn-cancelar btn-limpiar">🗑️ Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Estudiantes -->
            <h2 style="margin-bottom: 15px;">Lista de Estudiantes 
                <span style="font-size: 14px; color: #666;">(<?= $total ?> <?= $buscar ? 'resultados encontrados' : 'registros totales' ?>)</span>
            </h2>
            
            <?php if (count($estudiantes) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Carrera</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $e): ?>
                                <tr>
                                    <td><?= $e['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($e['nombre']) ?></strong></td>
                                    <td><?= htmlspecialchars($e['email']) ?></td>
                                    <td><?= htmlspecialchars($e['carrera']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($e['creado_en'])) ?></td>
                                    <td class="acciones">
                                        <a href="?editar=<?= $e['id'] ?>&pagina=<?= $pagina ?><?= $buscar ? '&buscar=' . urlencode($buscar) : '' ?>" 
                                           class="btn-editar">✏️ Editar</a>
                                        <a href="?eliminar=<?= $e['id'] ?>&pagina=<?= $pagina ?><?= $buscar ? '&buscar=' . urlencode($buscar) : '' ?>" 
                                           class="btn-eliminar" 
                                           onclick="return confirm('¿Estás seguro de eliminar a <?= htmlspecialchars($e['nombre']) ?>?')">🗑️ Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina > 1): ?>
                            <a href="?pagina=<?= $pagina - 1 ?><?= $buscar ? '&buscar=' . urlencode($buscar) : '' ?>">« Anterior</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <?php if ($i == $pagina): ?>
                                <span><?= $i ?></span>
                            <?php else: ?>
                                <a href="?pagina=<?= $i ?><?= $buscar ? '&buscar=' . urlencode($buscar) : '' ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?pagina=<?= $pagina + 1 ?><?= $buscar ? '&buscar=' . urlencode($buscar) : '' ?>">Siguiente »</a>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        Página <?= $pagina ?> de <?= $total_paginas ?> | 
                        Mostrando <?= count($estudiantes) ?> de <?= $total ?> registros
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>📭 No hay estudiantes registrados aún</p>
                    <p style="margin-top: 10px; font-size: 14px;">Completa el formulario de arriba para agregar tu primer estudiante</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>