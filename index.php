<!DOCTYPE html>
<html class="no-js" lang="zxx">

<!-- Start Header Area -->
<?php include 'header.php'; ?>

<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="preloader-inner">
            <div class="preloader-icon">
                <span></span>
                <span></span>
            </div>
        </div>
    </div>
    <!-- /End Preloader -->
         <!-- Start Header Area -->
    <header class="header navbar-area">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <div class="nav-inner">
                        <!-- Start Navbar -->
                        <nav class="navbar navbar-expand-lg">
                            <a class="navbar-brand" href="index.html">
                                <img src="assets/images/logo/logo.png" alt="Logo">
                            </a>
                            <button class="navbar-toggler mobile-menu-btn" type="button" data-bs-toggle="collapse"
                                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                                aria-expanded="false" aria-label="Toggle navigation">
                                <span class="toggler-icon"></span>
                                <span class="toggler-icon"></span>
                                <span class="toggler-icon"></span>
                            </button>
                        </nav>
                        <!-- End Navbar -->
                    </div>
                </div>
            </div> <!-- row -->
        </div> <!-- container -->
    </header>
    <!-- End Header Area -->
    <!-- Start Features Area -->
    <section id="features" class="hero-area">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="section-title">
                        <form method="POST" action="index1.php">
                            <!-- Name input -->
                            <div data-mdb-input-init class="form-outline mb-4">
                                <input type="text" id="usuario" class="form-control" name="usuario" placeholder="Nombre de Usuario"/>
                                <label class="form-label" for="usuario"></label>
                            </div>

                            <!-- Email input -->
                            <div data-mdb-input-init class="form-outline mb-4">
                                <input type="password" id="contrasena" class="form-control" name="contrasena" placeholder="Contraseña"/>
                                <label class="form-label" for="contrasena"></label>
                            </div>

                            <!-- Submit button -->
                            <button data-mdb-ripple-init type="submit" class="btn btn-success btn-block mb-4">Ingresar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- End Features Area -->
    <?php include 'footer.php'; ?>
</body>

</html>