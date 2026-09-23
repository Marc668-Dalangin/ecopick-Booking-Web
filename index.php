<?php
/**
 * EcoPick Landing Page - Phase 1
 */

require_once __DIR__ . '/app/bootstrap.php';

$approvedJunkshops = Database::getInstance()->query(
    'SELECT jp.business_name, jp.complete_address, jp.operating_schedule,
        COUNT(t.id) AS transaction_count
         FROM junkshop_profiles jp
         INNER JOIN accounts a ON a.id = jp.account_id
     LEFT JOIN transactions t ON t.junkshop_id = a.id
         WHERE a.account_role = :role
             AND a.account_status = :account_status
             AND jp.approval_status = :approval_status
     GROUP BY jp.account_id, jp.business_name, jp.complete_address, jp.operating_schedule, jp.created_at
     ORDER BY transaction_count DESC, jp.created_at DESC
         LIMIT 3',
        [
                'role' => 'junkshop',
                'account_status' => 'active',
                'approval_status' => 'approved',
        ]
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = '';
$isHomepage = true;
$homepageSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'RecyclingCenter',
    'name' => 'EcoPick Lipa City',
    'url' => APP_URL . '/',
    'image' => APP_URL . '/assets/images/logo.png',
    'description' => 'EcoPick connects scrap sellers with verified local junkshops across Lipa City, Batangas.',
    'email' => 'ecopicklipacity@gmail.com',
    'areaServed' => [
        '@type' => 'City',
        'name' => 'Lipa City',
        'containedInPlace' => [
            '@type' => 'AdministrativeArea',
            'name' => 'Batangas, Philippines',
        ],
    ],
    'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => 'Lipa City',
        'addressRegion' => 'Batangas',
        'addressCountry' => 'PH',
    ],
    'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => 13.9419,
        'longitude' => 121.1644,
    ],
];
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
                        EcoPick - Recyclable Scrap Collection in Lipa City
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
                <h2 class="fw-bold mb-3 text-success">How EcoPick Recycling Works in Lipa City</h2>
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
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-success">Recyclable Material Categories</h2>
            <p class="text-muted fs-6">Explore the types of materials accepted and processed across registered junkshops.</p>
        </div>

        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-3 hover-shadow transition">
                    <div class="card-body">
                        <div class="badge bg-success-subtle text-success p-3 rounded-circle mb-3">
                            <i class="bi bi-box-seam fs-2"></i>
                        </div>
                        <h5 class="card-title fw-bold text-dark">Cardboard</h5>
                        <p class="card-text text-muted small">Corrugated boxes, packaging board, and clean paperboard suitable for bulk processing.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-3 hover-shadow transition">
                    <div class="card-body">
                        <div class="badge bg-primary-subtle text-primary p-3 rounded-circle mb-3">
                            <i class="bi bi-cup-straw fs-2"></i>
                        </div>
                        <h5 class="card-title fw-bold text-dark">Glass</h5>
                        <p class="card-text text-muted small">Intact and sorted glass bottles, jars, and glass containers across clear and colored varieties.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-3 hover-shadow transition">
                    <div class="card-body">
                        <div class="badge bg-warning-subtle text-warning p-3 rounded-circle mb-3">
                            <i class="bi bi-tools fs-2"></i>
                        </div>
                        <h5 class="card-title fw-bold text-dark">Metal</h5>
                        <p class="card-text text-muted small">Ferrous and non-ferrous scrap metals including aluminum cans, copper, brass, and iron items.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-3 hover-shadow transition">
                    <div class="card-body">
                        <div class="badge bg-info-subtle text-info p-3 rounded-circle mb-3">
                            <i class="bi bi-file-earmark-text fs-2"></i>
                        </div>
                        <h5 class="card-title fw-bold text-dark">Paper</h5>
                        <p class="card-text text-muted small">Clean office paper, newspapers, magazines, cartons, and sorted white ledger sheets.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-3 hover-shadow transition">
                    <div class="card-body">
                        <div class="badge bg-danger-subtle text-danger p-3 rounded-circle mb-3">
                            <i class="bi bi-recycle fs-2"></i>
                        </div>
                        <h5 class="card-title fw-bold text-dark">Plastic</h5>
                        <p class="card-text text-muted small">PET bottles, HDPE jugs, rigid plastic containers, and clean recyclable polymers.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 text-center p-3 hover-shadow transition">
                    <div class="card-body">
                        <div class="badge bg-secondary-subtle text-secondary p-3 rounded-circle mb-3">
                            <i class="bi bi-grid-3x3-gap fs-2"></i>
                        </div>
                        <h5 class="card-title fw-bold text-dark">Other Materials</h5>
                        <p class="card-text text-muted small">E-waste, rubber, scrap tires, batteries, and non-standard industrial recyclable items.</p>
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
                <h2 class="fw-bold mb-3 text-success">Verified Partner Junkshops</h2>
                <p class="text-muted fs-5">
                    Trust our verified partners to provide fair assessment and payment
                </p>
            </div>
        </div>

        <?php if (empty($approvedJunkshops)): ?>
            <div class="alert alert-info text-center">No registered and approved junkshops yet.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($approvedJunkshops as $junkshop): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="display-6 text-success"><i class="bi bi-shop"></i></div>
                                        <div>
                                            <h5 class="card-title fw-bold mb-1"><?php echo Validator::escape($junkshop['business_name'] ?? ''); ?></h5>
                                            <p class="text-muted small mb-0">Verified Partner</p>
                                        </div>
                                    </div>
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Verified</span>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">
                                        <i class="bi bi-geo-alt"></i> <?php echo Validator::escape($junkshop['complete_address'] ?? 'Address unavailable'); ?>
                                    </small>
                                    <?php if (!empty($junkshop['operating_schedule'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="bi bi-clock"></i> <?php echo Validator::escape($junkshop['operating_schedule']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <small class="text-muted d-block">
                                        <i class="bi bi-check-circle text-success"></i>
                                        <?php echo (int) ($junkshop['transaction_count'] ?? 0); ?> Completed Transactions
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- Waste Segregation Guide Section -->
<section id="waste-guide" class="py-5 py-md-7" style="background: var(--light-bg);">
    <div class="container-lg">
        <div class="row mb-5">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="fw-bold mb-3 text-success">Waste Segregation Tips</h2>
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
                        <h2 class="fw-bold mb-3 text-white">Ready to Start?</h2>
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
                <h2 class="fw-bold mb-3 text-success">Get in Touch</h2>
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
