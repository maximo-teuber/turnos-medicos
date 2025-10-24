<?php
/**
 * login.php - Inicio de sesión
 * Optimizado para la estructura de BD actualizada
 */
session_start();
require_once __DIR__ . '/db.php';

$pdo = db();
$error = '';

// Si ya está logueado, redirigir
if (!empty($_SESSION['Id_usuario'])) {
    header('Location: index.php');
    exit;
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validaciones básicas
    if (empty($dni)) {
        $error = 'El DNI es obligatorio';
    } elseif (empty($password)) {
        $error = 'La contraseña es obligatoria';
    } else {
        try {
            // Buscar usuario por DNI
            $stmt = $pdo->prepare("
                SELECT Id_usuario, Nombre, Apellido, dni, email, Rol, Contraseña 
                FROM usuario 
                WHERE dni = ? 
                LIMIT 1
            ");
            $stmt->execute([$dni]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['Contraseña'])) {
                // Login exitoso - Guardar datos en sesión
                $_SESSION['Id_usuario'] = (int)$user['Id_usuario'];
                $_SESSION['dni'] = $user['dni'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['Nombre'] = $user['Nombre'];
                $_SESSION['Apellido'] = $user['Apellido'];
                $_SESSION['Rol'] = $user['Rol'];

                // Regenerar ID de sesión por seguridad
                session_regenerate_id(true);

                // Redirigir según el rol
                if ($user['Rol'] === 'medico' || $user['Rol'] === 'secretaria') {
                    header('Location: admin.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'DNI o contraseña incorrectos';
                
                // Agregar pequeña demora para prevenir ataques de fuerza bruta
                sleep(1);
            }
        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Error al procesar el inicio de sesión. Intenta nuevamente.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Iniciar sesión - Turnos Médicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            max-width: 420px;
            width: 100%;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-icon {
            font-size: 48px;
            margin-bottom: 8px;
        }

        h1 {
            color: #22d3ee;
            margin-bottom: 8px;
            font-size: 28px;
            text-align: center;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            color: #ef4444;
            font-size: 14px;
            text-align: center;
        }

        .error:before {
            content: "⚠️ ";
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

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <div class="logo-icon">🏥</div>
            </div>
            
            <h1>Bienvenido</h1>
            <p class="subtitle">Ingresá tus datos para continuar</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" id="loginForm" autocomplete="on">
                <div class="field">
                    <label for="dni">DNI</label>
                    <input 
                        type="text" 
                        id="dni" 
                        name="dni" 
                        value="<?= htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ingresá tu DNI"
                        inputmode="numeric"
                        pattern="[0-9]{7,10}"
                        maxlength="10"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Ingresá tu contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn">Iniciar sesión</button>
            </form>

            <div class="footer">
                ¿No tenés cuenta? <a href="register.php">Crear cuenta</a> · 
                <a href="index.php">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>