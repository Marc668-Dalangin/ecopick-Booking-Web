<?php
/**
 * Footer Template
 */
?>
    <!-- Footer -->
    <footer class="site-footer mt-5 py-5">
        <div class="container-lg">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3 brand-mark">
                        <i class="bi bi-recycling"></i> EcoPick
                    </h5>
                    <p class="small mb-3">
                        Connecting sellers of recyclable materials with verified local junkshops in Lipa City.
                    </p>
                    <p class="small mb-0">
                        EcoPick is a facilitator platform. Junkshops handle collection, weighing, and payment.
                    </p>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3 site-footer-heading">Quick Links</h5>
                    <ul class="list-unstyled small mb-0">
                        <li><a href="<?php echo APP_URL; ?>/#how-it-works" class="footer-link">How It Works</a></li>
                        <li><a href="<?php echo APP_URL; ?>/#materials" class="footer-link">Recyclable Materials</a></li>
                        <li><a href="<?php echo APP_URL; ?>/#waste-guide" class="footer-link">Waste Segregation</a></li>
                        <li><a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="footer-link">Login</a></li>
                        <li><a href="<?php echo APP_URL; ?>/user-junkshop/register.php" class="footer-link">Register</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3 site-footer-heading">Contact</h5>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2">
                            <i class="bi bi-envelope-fill"></i>
                            <a href="mailto:ecopicklipacity@gmail.com" class="footer-link ms-2">ecopicklipacity@gmail.com</a>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-telephone-fill"></i>
                            <span class="ms-2">(Local only)</span>
                        </li>
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <span class="ms-2">Lipa City</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="site-footer-divider my-4">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="small mb-0 footer-copy">
                        &copy; 2026 EcoPick. All rights reserved. Local development only.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/live-updates.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/password-toggle.js" defer></script>
    
    <!-- Custom JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js" defer></script>
</body>
</html>
