<?php
/**
 * register.php - Registro de nuevos pacientes
 * Optimizado para la estructura de BD actualizada
 */
session_start();
require_once __DIR__ . '/db.php';

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$pdo = db();
$errors = [];
$success = false;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        $errors[] = 'Token de seguridad inválido';
    }

    // Capturar y limpiar datos
    $dni      = trim($_POST['dni'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $obra     = trim($_POST['obra_social'] ?? '');
    $libreta  = trim($_POST['libreta_sanitaria'] ?? '');

    // Validaciones de campos
    if (empty($dni)) {
        $errors[] = 'El DNI es obligatorio';
    } elseif (!preg_match('/^[0-9]{7,10}$/', $dni)) {
        $errors[] = 'El DNI debe tener entre 7 y 10 dígitos';
    }

    if (empty($nombre)) {
        $errors[] = 'El nombre es obligatorio';
    } elseif (!preg_match('/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s]{2,50}$/u', $nombre)) {
        $errors[] = 'El nombre solo puede contener letras (2-50 caracteres)';
    }

    if (empty($apellido)) {
        $errors[] = 'El apellido es obligatorio';
    } elseif (!preg_match('/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s]{2,50}$/u', $apellido)) {
        $errors[] = 'El apellido solo puede contener letras (2-50 caracteres)';
    }

    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del email es inválido';
    }

    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden';
    }

    if (empty($obra)) {
        $errors[] = 'La obra social es obligatoria';
    }

    if (empty($libreta)) {
        $errors[] = 'La libreta sanitaria es obligatoria';
    }

    // Si no hay errores, procesar registro
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Verificar si el email o DNI ya existen
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ? OR dni = ?");
            $stmt->execute([$email, $dni]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('El email o DNI ya están registrados');
            }

            // Hashear contraseña
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Insertar usuario
            $stmtUser = $pdo->prepare("
                INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) 
                VALUES (?, ?, ?, ?, ?, 'paciente')
            ");
            $stmtUser->execute([$nombre, $apellido, $dni, $email, $passwordHash]);
            $userId = (int)$pdo->lastInsertId();

            // Insertar paciente
            $stmtPaciente = $pdo->prepare("
                INSERT INTO paciente (Obra_social, Libreta_sanitaria, Id_usuario, Activo) 
                VALUES (?, ?, ?, 1)
            ");
            $stmtPaciente->execute([$obra, $libreta, $userId]);

            $pdo->commit();

            // Login automático
            $_SESSION['Id_usuario'] = $userId;
            $_SESSION['dni'] = $dni;
            $_SESSION['email'] = $email;
            $_SESSION['Nombre'] = $nombre;
            $_SESSION['Apellido'] = $apellido;
            $_SESSION['Rol'] = 'paciente';

            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);

            // Redirigir al inicio
            header('Location: index.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear cuenta - Turnos Médicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            background: linear-gradient(135deg, #0b1220 0%, #1a2332 100%);
            color: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 520px;
            width: 100%;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        h1 {
            color: #22d3ee;
            margin-bottom: 8px;
            font-size: 28px;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .errors {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
        }

        .errors ul {
            list-style: none;
            padding: 0;
        }

        .errors li {
            color: #ef4444;
            font-size: 14px;
            padding: 4px 0;
        }

        .errors li:before {
            content: "⚠️ ";
            margin-right: 6px;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        label .required {
            color: #ef4444;
        }

        input {
            width: 100%;
            padding: 12px;
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 10px;
            color: #e5e7eb;
            font-size: 15px;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #22d3ee;
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
        }

        input::placeholder {
            color: #6b7280;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #22d3ee;
            color: #001219;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .btn:hover {
            background: #0891b2;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 211, 238, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #1f2937;
        }

        .footer a {
            color: #22d3ee;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 24px;
            }
        }

        .password-strength {
            font-size: 12px;
            margin-top: 4px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>✨ Crear cuenta</h1>
            <p class="subtitle">Completá tus datos para registrarte</p>

            <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" action="register.php" id="registerForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="grid-2">
                    <div class="field">
                        <label for="nombre">Nombre <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Juan"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="apellido">Apellido <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="apellido" 
                            name="apellido" 
                            value="<?= htmlspecialchars($_POST['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Pérez"
                            autocomplete="family-name"
                            required
                        >
                    </div>
                </div>

                <div class="field">
                    <label for="dni">DNI <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="dni" 
                        name="dni" 
                        value="<?= htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="12345678"
                        inputmode="numeric"
                        pattern="[0-9]{7,10}"
                        maxlength="10"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="tu@email.com"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="field">
                    <label for="obra_social">Obra Social <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="obra_social" 
                        name="obra_social" 
                        value="<?= htmlspecialchars($_POST['obra_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="OSDE, Swiss Medical, PAMI..."
                        required
                    >
                </div>

                <div class="field">
                    <label for="libreta_sanitaria">Libreta Sanitaria <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="libreta_sanitaria" 
                        name="libreta_sanitaria" 
                        value="<?= htmlspecialchars($_POST['libreta_sanitaria'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Número o identificación"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">Contraseña <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Mínimo 6 caracteres"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >
                    <div class="password-strength" id="strengthMsg"></div>
                </div>

                <div class="field">
                    <label for="password2">Confirmar Contraseña <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password2" 
                        name="password2" 
                        placeholder="Repetí tu contraseña"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >
                </div>

                <button type="submit" class="btn">Crear cuenta</button>
            </form>

            <div class="footer">
                ¿Ya tenés cuenta? <a href="login.php">Iniciá sesión</a> · 
                <a href="index.php">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script src="register.js"></script>
</body>
</html>