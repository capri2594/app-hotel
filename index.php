<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <title>HabitApp - Iniciar Sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico" />
    
    <!-- CSS de Bootstrap 5 y principal -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/main.css" />
</head>

<body class="d-flex align-items-center vh-100" style="background-color: #680202;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 rounded-3 text-white" style="background-color: rgba(0, 0, 0, 0.6);">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="assets/images/logo/logo.png" alt="HabitApp Logo" class="img-fluid mb-3" style="max-height: 80px;">
                            <h4 class="fw-bold text-white">HabitApp</h4>
                            <p class="text-light">Sistema de Administración</p>
                        </div>
                        
                        <form method="POST" action="login.php">
                            <!-- Usuario -->
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required>
                                <label for="usuario" class="text-dark">Nombre de Usuario</label>
                            </div>

                            <!-- Contraseña -->
                            <div class="form-floating mb-4">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                                <label for="password" class="text-dark">Contraseña</label>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold mb-3">Ingresar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS de Bootstrap -->
    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>
