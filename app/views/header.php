<?php
/**
 * Header Template
 */
$siteFavicon = APP_URL . '/assets/images/logo.png';
$siteTitle = !empty($isHomepage)
    ? 'EcoPick Lipa City - Recyclable Scrap Collection & Local Junkshops'
    : (isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - EcoPick' : 'EcoPick - Recyclable Scrap Collection in Lipa City');
$siteDescription = !empty($isHomepage)
    ? 'EcoPick Lipa City connects scrap sellers with verified local junkshops across Lipa City, Batangas. Schedule hassle-free recyclable collection and get paid fair prices.'
    : 'EcoPick connects sellers with verified junkshops and recyclable material services in Lipa City, Batangas.';
$siteUrl = APP_URL . '/';
$siteImage = APP_URL . '/assets/images/logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteTitle; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!empty($isHomepage)): ?>
        <meta name="keywords" content="ecopick lipa city, ecopick, junkshop lipa city, scrap collector lipa, recycling lipa city batangas, bakal bote lipa">
        <meta name="author" content="EcoPick Operations Team">
        <meta name="robots" content="index, follow">
        <meta name="geo.region" content="PH-BTG">
        <meta name="geo.placename" content="Lipa City, Batangas, Philippines">
        <meta name="geo.position" content="13.9419;121.1644">
        <meta name="ICBM" content="13.9419, 121.1644">
        <link rel="canonical" href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:type" content="website">
        <meta property="og:title" content="EcoPick Lipa City - Recyclable Scrap Collection & Junkshops">
        <meta property="og:description" content="Turn your household and business scrap into cash with verified junkshops in Lipa City, Batangas.">
        <meta property="og:url" content="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:image" content="<?php echo htmlspecialchars($siteImage, ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:locale" content="en_PH">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="EcoPick Lipa City - Recyclable Scrap Collection">
        <meta name="twitter:description" content="Book recyclable pickup with verified junkshops in Lipa City, Batangas.">
        <meta name="twitter:image" content="<?php echo htmlspecialchars($siteImage, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($homepageSchema)): ?>
            <script type="application/ld+json"><?php echo json_encode($homepageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
        <?php endif; ?>
    <?php endif; ?>
    <link rel="icon" type="image/png" sizes="256x256" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #0a8f5c;
            --primary-teal: #1a9e7a;
            --accent-warm: #e67e22;
            --light-bg: #f5f9f7;
            --dark-text: #2c3e50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
            background-color: #fff;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-green) !important;
        }

        .nav-link {
            color: var(--dark-text) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-green) !important;
        }

        .btn-primary {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
        }

        .btn-outline-primary {
            color: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(10, 143, 92, 0.25);
        }

        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container-lg">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/">
                <i class="bi bi-recycling"></i> EcoPick
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/#how-it-works">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/#materials">Materials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/#partners">Partners</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/#waste-guide">Waste Guide</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/#contact">Contact</a>
                    </li>
                    <?php if (Auth::check()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo Validator::escape(Auth::userName()); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php">Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/user-junkshop/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user-junkshop/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary btn-sm text-white" href="<?php echo APP_URL; ?>/user-junkshop/register.php">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
