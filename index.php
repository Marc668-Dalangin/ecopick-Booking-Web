<?php
/**
 * EcoPick Landing Page - Phase 1
 */

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = '';
?>
<?php require_once __DIR__ . '/app/views/header.php'; ?>

<!-- Hero Section -->
<section id="home" class="py-5 py-md-7 text-center" style="background: linear-gradient(135deg, rgba(10, 143, 92, 0.05) 0%, rgba(26, 158, 122, 0.05) 100%);">
    <div class="container-lg">
        <div class="row justify-content-center align-items-center min-vh-75">
            <div class="col-lg-8">
                <div class="mb-4">
                    <h1 class="display-4 fw-bold mb-4" style="color: var(--dark-text);">
                        <i class="bi bi-recycling" style="color: var(--primary-green);"></i> 
                        Connect Your Recyclables with Trusted Junkshops
                    </h1>
                    <p class="fs-5 text-muted mx-auto mb-5" style="max-width: 600px;">
                        EcoPick connects you with verified local junkshops in Lipa City. Sell your recyclable materials easily and get paid fairly.
                    </p>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center mb-5">
                    <a href="<?php echo APP_URL; ?>/user-junkshop/register-seller.php" class="btn btn-primary btn-lg" style="background-color: var(--primary-green); border-color: var(--primary-green);">
                        <i class="bi bi-person-plus"></i> Register as Seller
                    </a>
                    <a href="<?php echo APP_URL; ?>/user-junkshop/register-junkshop.php" class="btn btn-outline-primary btn-lg" style="color: var(--primary-green); border-color: var(--primary-green);">
                        <i class="bi bi-shop"></i> Register Your Junkshop
                    </a>
                </div>

                <p class="text-muted mb-4">
                    <small><strong>Note:</strong> EcoPick is a facilitator platform. Registered junkshops handle collection, weighing, assessment, and payment.</small>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="py-5 py-md-7">
    <div class="container-lg">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold mb-3">How EcoPick Works</h2>
                <p class="text-muted fs-5">
                    A simple, transparent process that benefits both sellers and verified junkshops
                </p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-5 mb-3" style="color: var(--primary-green);">
                            <i class="bi bi-1-circle-fill"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-3">Submit Pickup Request</h5>
                        <p class="text-muted mb-0">
                            As a seller, provide details about your recyclable materials and location
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-5 mb-3" style="color: var(--primary-teal);">
                            <i class="bi bi-2-circle-fill"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-3">EcoPick Matches Junkshops</h5>
                        <p class="text-muted mb-0">
                            Our platform matches your request with suitable verified partner junkshops
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-5 mb-3" style="color: var(--primary-green);">
                            <i class="bi bi-3-circle-fill"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-3">Junkshop Accepts & Schedules</h5>
                        <p class="text-muted mb-0">
                            Verified junkshops accept your request and arrange convenient pickup time
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-5 mb-3" style="color: var(--primary-teal);">
                            <i class="bi bi-4-circle-fill"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-3">Weighing & Assessment</h5>
                        <p class="text-muted mb-0">
                            Junkshop weighs and assesses the condition of your recyclable materials
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-5 mb-3" style="color: var(--primary-green);">
                            <i class="bi bi-5-circle-fill"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-3">Fair Payment</h5>
                        <p class="text-muted mb-0">
                            Get paid based on actual accepted materials, weight, and market rates
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-5 mb-3" style="color: var(--primary-teal);">
                            <i class="bi bi-6-circle-fill"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-3">Transaction Complete</h5>
                        <p class="text-muted mb-0">
                            Everything is recorded and tracked for transparency and accountability
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Recyclable Materials Section -->
<section id="materials" class="py-5 py-md-7" style="background: var(--light-bg);">
    <div class="container-lg">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold mb-3">Recyclable Materials We Accept</h2>
                <p class="text-muted fs-5">
                    Learn what materials you can recycle and why they matter
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: #3498db;">
                            <i class="bi bi-cup"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">Plastic</h5>
                        <p class="text-muted small">
                            PET bottles, HDPE containers, and various plastic materials can be recycled and repurposed
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: #8b7355;">
                            <i class="bi bi-newspaper"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">Paper & Cardboard</h5>
                        <p class="text-muted small">
                            Newspapers, magazines, cardboard boxes, and paper products are highly recyclable
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: #c0392b;">
                            <i class="bi bi-box"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">Metal</h5>
                        <p class="text-muted small">
                            Aluminum cans, steel cans, and other metal materials have significant value
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: #16a085;">
                            <i class="bi bi-cup-hot"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">Glass</h5>
                        <p class="text-muted small">
                            Glass bottles and containers can be recycled indefinitely without loss of quality
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: #7f8c8d;">
                            <i class="bi bi-cpu"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">E-Waste</h5>
                        <p class="text-muted small">
                            Electronics and electrical equipment contain valuable materials and require proper disposal
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: #2c3e50;">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">Other Materials</h5>
                        <p class="text-muted small">
                            Textiles, rubber, and other recyclable materials can be properly processed
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Partner Junkshops Section -->
<section id="partners" class="py-5 py-md-7">
    <div class="container-lg">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold mb-3">Verified Partner Junkshops</h2>
                <p class="text-muted fs-5">
                    Trust our verified partners to provide fair assessment and payment
                </p>
            </div>
        </div>

        <div class="row g-4">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h5 class="card-title fw-bold mb-1">Eco Traders <?php echo $i; ?></h5>
                                <p class="text-muted small mb-2">Verified Partner</p>
                            </div>
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle-fill"></i> Verified
                            </span>
                        </div>

                        <p class="text-muted small mb-3">
                            Professional junkshop with years of experience in recyclable materials collection and fair pricing.
                        </p>

                        <div class="mb-3">
                            <small class="text-muted d-block">
                                <i class="bi bi-geo-alt"></i> Lipa City, Barangay <?php echo ['Poblacion', 'San Juan', 'Maligaya'][array_rand([0,1,2])]; ?>
                            </small>
                            <small class="text-muted d-block">
                                <i class="bi bi-clock"></i> Mon-Sun 8:00 AM - 5:00 PM
                            </small>
                        </div>

                        <div class="pt-3 border-top">
                            <p class="text-muted small mb-0">
                                <i class="bi bi-star-fill" style="color: #f39c12;"></i>
                                <i class="bi bi-star-fill" style="color: #f39c12;"></i>
                                <i class="bi bi-star-fill" style="color: #f39c12;"></i>
                                <i class="bi bi-star-fill" style="color: #f39c12;"></i>
                                <i class="bi bi-star-fill" style="color: #f39c12;"></i>
                                <span class="ms-2">(45 transactions)</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

    </div>
</section>

<!-- Waste Segregation Guide Section -->
<section id="waste-guide" class="py-5 py-md-7" style="background: var(--light-bg);">
    <div class="container-lg">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold mb-3">Waste Segregation Tips</h2>
                <p class="text-muted fs-5">
                    Learn how to properly separate and prepare your recyclable materials
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-3">
                            <i class="bi bi-check-square" style="color: var(--primary-green);"></i> Clean Your Materials
                        </h5>
                        <ul class="text-muted small">
                            <li>Rinse plastic and metal containers</li>
                            <li>Remove food residue and labels</li>
                            <li>Dry materials before collection</li>
                            <li>This improves quality and value</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-3">
                            <i class="bi bi-check-square" style="color: var(--primary-green);"></i> Sort by Type
                        </h5>
                        <ul class="text-muted small">
                            <li>Keep plastic, metal, and paper separate</li>
                            <li>Remove caps and lids from bottles</li>
                            <li>Flatten cardboard boxes to save space</li>
                            <li>Group similar materials together</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-3">
                            <i class="bi bi-check-square" style="color: var(--primary-green);"></i> Store Properly
                        </h5>
                        <ul class="text-muted small">
                            <li>Keep materials in a clean, dry place</li>
                            <li>Avoid mixing contaminated items</li>
                            <li>Pack materials safely for transport</li>
                            <li>Ensure proper ventilation if storing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="py-5 py-md-7">
    <div class="container-lg">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card border-0" style="background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-teal) 100%);">
                    <div class="card-body p-5 text-center text-white">
                        <h2 class="fw-bold mb-3">Ready to Start?</h2>
                        <p class="fs-5 mb-5">
                            Join EcoPick today and turn your recyclable materials into value
                        </p>

                        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                            <a href="<?php echo APP_URL; ?>/user-junkshop/register-seller.php" class="btn btn-light btn-lg fw-bold">
                                <i class="bi bi-person-plus"></i> Register as Seller
                            </a>
                            <a href="<?php echo APP_URL; ?>/user-junkshop/register-junkshop.php" class="btn btn-outline-light btn-lg fw-bold">
                                <i class="bi bi-shop"></i> Register Your Junkshop
                            </a>
                        </div>

                        <p class="text-white-50 small mt-4 mb-0">
                            Already have an account? <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="text-white fw-bold">Login here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="py-5 py-md-7" style="background: var(--light-bg);">
    <div class="container-lg">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold mb-3">Get in Touch</h2>
                <p class="text-muted fs-5">
                    Have questions? We'd love to hear from you
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: var(--primary-green);">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Email</h5>
                        <p class="text-muted mb-0">
                            <a href="mailto:ecopicklipacity@gmail.com" class="text-decoration-none">
                                ecopicklipacity@gmail.com
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: var(--primary-green);">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Location</h5>
                        <p class="text-muted mb-0">
                            Lipa City<br>
                            <small>(Local platform only)</small>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="display-4 mb-3" style="color: var(--primary-green);">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Support</h5>
                        <p class="text-muted mb-0">
                            <a href="#" class="text-decoration-none">
                                FAQ & Help
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .min-vh-75 {
        min-height: 75vh;
    }
</style>

<?php require_once __DIR__ . '/app/views/footer.php'; ?>
