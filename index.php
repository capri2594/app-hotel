<?php
session_start();

// Si ya está logueado, redirigir al Dashboard de inmediato
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Capturar error si existe
$error_msg = "";
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <title>Iniciar Sesión - HabitApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico" />
    
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <!-- LineIcons 2.0 -->
    <link rel="stylesheet" href="assets/css/LineIcons.2.0.css" />
    <!-- Google Fonts - Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #2b0000 0%, #680202 50%, #1a0000 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
            position: relative;
        }

        /* Efecto de orbes decorativos en el fondo */
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
            opacity: 0.3;
        }
        .orb-1 {
            top: 10%;
            left: 15%;
            width: 300px;
            height: 300px;
            background: #ff3e3e;
        }
        .orb-2 {
            bottom: 10%;
            right: 15%;
            width: 350px;
            height: 350px;
            background: #ff8c00;
        }

        .login-container {
            z-index: 2;
            width: 100%;
            max-width: 450px;
            padding: 15px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.45);
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .brand-subtitle {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            margin-bottom: 30px;
            font-weight: 300;
        }

        .form-label {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 22px;
        }

        .input-group-custom i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.45);
            font-size: 1.15rem;
            transition: color 0.3s ease;
            z-index: 10;
        }

        .form-control-custom {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: rgba(255, 255, 255, 0.08);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: #ff4747;
            box-shadow: 0 0 15px rgba(255, 71, 71, 0.25);
            outline: none;
            color: #fff;
        }

        .form-control-custom:focus + i {
            color: #ff4747;
        }

        .form-control-custom::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        /* Estilo del botón */
        .btn-login {
            background: linear-gradient(90deg, #ff4747 0%, #e60000 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            color: #fff;
            font-weight: 600;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(230, 0, 0, 0.3);
            margin-top: 10px;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #ff5e5e 0%, #ff1a1a 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230, 0, 0, 0.5);
            color: #fff;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Alertas */
        .alert-custom {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff858f;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.45);
            font-size: 0.8rem;
            font-weight: 300;
        }

        .footer-text a {
            color: rgba(255, 255, 255, 0.65);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-text a:hover {
            color: #fff;
        }
    </style>
</head>
<body>

    <!-- Orbes de fondo -->
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="login-container">
        <div class="login-card text-center">
            <!-- Título / Marca -->
            <h1 class="brand-logo">
                <i class="lni lni-apartment"></i> HabitApp
            </h1>
            <p class="brand-subtitle">Gestión de Reservas y Hotelería</p>

            <!-- Alerta de error -->
            <?php if (!empty($error_msg)): ?>
                <div class="alert-custom text-start">
                    <i class="lni lni-warning fs-5"></i>
                    <div><?= htmlspecialchars($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulario de Login -->
            <form action="login.php" method="POST">
                <div class="text-start">
                    <div class="mb-1">
                        <label class="form-label">Nombre de Usuario</label>
                        <div class="input-group-custom">
                            <input type="text" class="form-control-custom" name="usuario" placeholder="Ej. admin" required autocomplete="username" />
                            <i class="lni lni-user"></i>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group-custom">
                            <input type="password" class="form-control-custom" name="password" placeholder="••••••••" required autocomplete="current-password" />
                            <i class="lni lni-lock"></i>
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-login">
                        <i class="lni lni-enter me-2"></i>Iniciar Sesión
                    </button>
                </div>
            </form>

            <div class="footer-text">
                &copy; <?= date('Y') ?> HabitApp. Todos los derechos reservados.<br>
                <a href="cliente/index.php" target="_blank"><i class="lni lni-link me-1"></i>Ir al portal de reservas de huéspedes</a>
            </div>
        </div>
    </div>

</body>
</html>