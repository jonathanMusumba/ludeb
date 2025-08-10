<?php
// Simple session for guest tracking (optional)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mark as guest access
$_SESSION['access_type'] = 'guest';
$_SESSION['guest_access_time'] = time();

// You can add database connection here if needed for fetching public resources
// For now, we'll use sample data
$board_name = "Luuka Examination Board";
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>Educational Resources & Study Materials | <?php echo htmlspecialchars($board_name); ?></title>
    <meta name="description" content="Access free and premium educational resources, past papers, study guides, and examination materials for primary and secondary education in Uganda.">
    <meta name="keywords" content="educational resources, study materials, past papers, primary education, secondary education, examination materials, Uganda curriculum, free resources, premium content">
    <meta name="author" content="ILABS UGANDA LIMITED - Jonathan Musumba">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Educational Resources Portal - <?php echo htmlspecialchars($board_name); ?>">
    <meta property="og:description" content="Comprehensive educational resources for students, teachers, and parents. Free and premium study materials available.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="static/img/resources-og.jpg">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($board_name); ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Educational Resources Portal">
    <meta name="twitter:description" content="Access comprehensive educational materials and study resources for Uganda curriculum.">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://yoursite.com/public/">
    
    <!-- External Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "<?php echo htmlspecialchars($board_name); ?>",
        "description": "Educational platform providing comprehensive resources and examination services",
        "url": "https://yoursite.com/public/",
        "telephone": "+256777115678",
        "email": "jmprossy@gmail.com",
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "Uganda",
            "addressRegion": "Central Region",
            "addressLocality": "Kampala"
        },
        "offers": [
            {
                "@type": "Offer",
                "name": "Free Educational Resources",
                "description": "Free access to study materials and past papers"
            },
            {
                "@type": "Offer",
                "name": "Premium Educational Content",
                "description": "Advanced study materials and comprehensive examination resources"
            }
        ]
    }
    </script>

    <style>
        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffc107;
            --accent-blue: #0066cc;
            --success-green: #28a745;
            --danger-red: #dc3545;
            --info-cyan: #17a2b8;
            --dark-blue: #1e3c72;
            --light-blue: #2a5298;
            --gradient-primary: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            --gradient-blue: linear-gradient(135deg, var(--dark-blue) 0%, var(--light-blue) 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --gradient-info: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --shadow-light: rgba(255, 215, 0, 0.3);
            --shadow-dark: rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }

        /* Navigation */
        .navbar {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-gold) !important;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-nav .nav-link {
            color: #fff !important;
            font-weight: 500;
            margin: 0 0.3rem;
            padding: 0.5rem 1rem !important;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-gold) !important;
            background: rgba(255, 215, 0, 0.1);
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link.active {
            background: rgba(255, 215, 0, 0.2);
            color: var(--primary-gold) !important;
        }

        .navbar-toggler {
            border: none;
            color: var(--primary-gold);
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        /* Hero Section */
        .hero-section {
            background: var(--gradient-blue);
            padding: 6rem 0 4rem;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(255, 215, 0, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(255, 215, 0, 0.1) 0%, transparent 50%);
            animation: heroGlow 6s ease-in-out infinite alternate;
        }

        @keyframes heroGlow {
            0% { opacity: 0.3; }
            100% { opacity: 0.7; }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--primary-gold);
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease-out both;
        }

        .hero-subtitle {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .hero-description {
            font-size: 1.1rem;
            opacity: 0.8;
            max-width: 600px;
            margin: 0 auto 2rem;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            animation: fadeInUp 1s ease-out 0.6s both;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-gold);
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Resource Categories Section */
        .categories-section {
            padding: 5rem 0;
            background: #fff;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title {
            font-size: 2.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .category-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .category-card:hover::before {
            transform: scaleX(1);
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .category-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
            transition: all 0.3s ease;
            position: relative;
        }

        .category-card:hover .category-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .primary-icon {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        .secondary-icon {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
        }

        .category-title {
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .category-description {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .resource-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .resource-stat {
            text-align: center;
        }

        .resource-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-blue);
            display: block;
        }

        .resource-label {
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .category-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-outline-primary-custom {
            border: 2px solid var(--accent-blue);
            color: var(--accent-blue);
            background: transparent;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-outline-primary-custom:hover {
            background: var(--accent-blue);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-primary-custom {
            background: var(--gradient-primary);
            color: #000;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            color: #000;
        }

        /* Resource Types Section */
        .resource-types-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .resource-type-card {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .resource-type-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .resource-type-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-blue);
            transition: all 0.3s ease;
        }

        .resource-type-card:hover .resource-type-icon {
            transform: scale(1.1);
            color: var(--primary-gold);
        }

        .resource-type-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .resource-type-description {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .resource-count {
            background: rgba(255, 215, 0, 0.1);
            color: var(--accent-blue);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .resource-link {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.3s ease;
        }

        .resource-link:hover {
            color: var(--primary-gold);
            transform: translateX(5px);
        }

        /* Featured Resources Section */
        .featured-section {
            padding: 5rem 0;
            background: #fff;
        }

        .featured-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .featured-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite;
            pointer-events: none;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .featured-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 60px rgba(102, 126, 234, 0.4);
        }

        .featured-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--primary-gold);
        }

        .featured-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .featured-description {
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn-featured {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid #fff;
            color: #fff;
            padding: 0.8rem 2rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-featured:hover {
            background: #fff;
            color: #333;
            transform: translateY(-2px);
        }

        /* Results Section */
        .results-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .results-card {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            text-align: center;
        }

        .results-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .results-icon {
            font-size: 3rem;
            color: var(--info-cyan);
            margin-bottom: 1rem;
        }

        .results-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            padding: 4rem 0 1rem;
        }

        .footer-section {
            margin-bottom: 2rem;
        }

        .footer-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-gold);
        }

        .footer-link {
            color: #adb5bd;
            text-decoration: none;
            display: block;
            margin-bottom: 0.8rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .footer-link:hover {
            color: var(--primary-gold);
            transform: translateX(5px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            text-align: center;
            color: #6c757d;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
            color: #adb5bd;
            font-size: 0.95rem;
        }

        .contact-info i {
            color: var(--primary-gold);
            width: 20px;
            font-size: 1rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Back to main button */
        .back-to-main {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            border: none;
            padding: 0.8rem 1.2rem;
            border-radius: 25px;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            z-index: 1000;
        }

        .back-to-main:hover {
            background: rgba(255, 215, 0, 0.9);
            color: #000;
            transform: translateX(-3px);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .hero-stats {
                gap: 1.5rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .category-card {
                padding: 2rem 1.5rem;
                margin-bottom: 2rem;
            }
            
            .category-icon {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            
            .category-buttons {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .btn-outline-primary-custom,
            .btn-primary-custom {
                width: 100%;
                text-align: center;
            }

            .back-to-main {
                top: 10px;
                left: 10px;
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }

            .resource-stats {
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .category-card {
                padding: 1.5rem 1rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
        }

        /* Scroll to top button */
        .scroll-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            border: none;
            color: #000;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(100px);
        }

        .scroll-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .scroll-to-top:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.5);
        }

        /* Loading states */
        .loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid var(--primary-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Back to Main Button -->
    <a href="../index.php" class="back-to-main">
        <i class="fas fa-arrow-left"></i>
        Back to Main
    </a>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <i class="fas fa-graduation-cap"></i>
                <?php echo htmlspecialchars($board_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars" style="color: var(--primary-gold);"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#resources">Resources</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#results">Results</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            Account
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="register.php"><i class="fas fa-user-plus me-2"></i>Register</a></li>
                            <li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Login</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../login.php"><i class="fas fa-school me-2"></i>School Portal</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Educational Resources Portal</h1>
                <h2 class="hero-subtitle">Access Quality Learning Materials</h2>
                <p class="hero-description">
                    Discover comprehensive educational resources for primary and secondary education. 
                    Free and premium content designed to enhance learning outcomes across Uganda's curriculum.
                </p>
                
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number">1,500+</span>
                        <span class="stat-label">Resources Available</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">50+</span>
                        <span class="stat-label">Partner Schools</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">10,000+</span>
                        <span class="stat-label">Students Served</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Resource Categories Section -->
    <section id="resources" class="categories-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2 class="section-title">Educational Resources</h2>
                <p class="section-subtitle">
                    Comprehensive learning materials designed for different educational levels and learning needs
                </p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="category-card animate-on-scroll">
                        <div class="category-icon primary-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <h3 class="category-title">Primary Education</h3>
                        <p class="category-description">
                            Comprehensive study materials, worksheets, and interactive content for Primary 1-7 students. 
                            Covering all core subjects in the Uganda primary curriculum.
                        </p>
                        <div class="resource-stats">
                            <div class="resource-stat">
                                <span class="resource-number">600+</span>
                                <span class="resource-label">Study Materials</span>
                            </div>
                            <div class="resource-stat">
                                <span class="resource-number">150+</span>
                                <span class="resource-label">Past Papers</span>
                            </div>
                            <div class="resource-stat">
                                <span class="resource-number">8</span>
                                <span class="resource-label">Subjects</span>
                            </div>
                        </div>
                        <div class="category-buttons">
                            <a href="resources/primary" class="btn-outline-primary-custom">
                                <i class="fas fa-unlock me-1"></i>
                                Free Resources
                            </a>
                            <a href="resources/primary/premium" class="btn-primary-custom">
                                <i class="fas fa-crown me-1"></i>
                                Premium Content
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="category-card animate-on-scroll">
                        <div class="category-icon secondary-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="category-title">Secondary Education</h3>
                        <p class="category-description">
                            Advanced study materials, revision guides, and examination papers for O-Level (S1-S4) 
                            and A-Level (S5-S6) students across all subject combinations.
                        </p>
                        <div class="resource-stats">
                            <div class="resource-stat">
                                <span class="resource-number">900+</span>
                                <span class="resource-label">Study Materials</span>
                            </div>
                            <div class="resource-stat">
                                <span class="resource-number">300+</span>
                                <span class="resource-label">Past Papers</span>
                            </div>
                            <div class="resource-stat">
                                <span class="resource-number">25+</span>
                                <span class="resource-label">Subjects</span>
                            </div>
                        </div>
                        <div class="category-buttons">
                            <a href="resources/secondary" class="btn-outline-primary-custom">
                                <i class="fas fa-unlock me-1"></i>
                                Free Resources
                            </a>
                            <a href="resources/secondary/premium" class="btn-primary-custom">
                                <i class="fas fa-crown me-1"></i>
                                Premium Content
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Resource Types Section -->
    <section class="resource-types-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2 class="section-title">Resource Types</h2>
                <p class="section-subtitle">
                    Multiple formats and types of educational content to suit different learning preferences
                </p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="resource-type-card animate-on-scroll">
                        <div class="resource-type-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h4 class="resource-type-title">Past Papers</h4>
                        <div class="resource-count">450+ Papers</div>
                        <p class="resource-type-description">
                            Complete collection of previous examination papers with detailed marking schemes and solutions.
                        </p>
                        <a href="resources/past-papers" class="resource-link">
                            Download Papers <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="resource-type-card animate-on-scroll">
                        <div class="resource-type-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h4 class="resource-type-title">Study Guides</h4>
                        <div class="resource-count">300+ Guides</div>
                        <p class="resource-type-description">
                            Comprehensive revision notes, topic summaries, and structured study materials for all subjects.
                        </p>
                        <a href="resources/study-guides" class="resource-link">
                            Browse Guides <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="resource-type-card animate-on-scroll">
                        <div class="resource-type-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h4 class="resource-type-title">Video Lessons</h4>
                        <div class="resource-count">200+ Videos</div>
                        <p class="resource-type-description">
                            Interactive video tutorials and recorded lessons by experienced educators for better understanding.
                        </p>
                        <a href="resources/videos" class="resource-link">
                            Watch Videos <i class="fas fa-play"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="resource-type-card animate-on-scroll">
                        <div class="resource-type-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h4 class="resource-type-title">Practice Tests</h4>
                        <div class="resource-count">100+ Tests</div>
                        <p class="resource-type-description">
                            Online assessment tools and practice examinations to test knowledge and track progress.
                        </p>
                        <a href="resources/practice-tests" class="resource-link">
                            Take Test <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Resources Section -->
    <section class="featured-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2 class="section-title">Featured This Month</h2>
                <p class="section-subtitle">
                    Specially curated content and recently updated materials
                </p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="featured-card animate-on-scroll">
                        <div class="featured-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="featured-title">2024 Past Papers</h3>
                        <p class="featured-description">
                            Latest examination papers from 2024 with comprehensive marking schemes and examiner reports.
                        </p>
                        <a href="resources/past-papers/2024" class="btn-featured">
                            <i class="fas fa-download"></i>
                            Download Now
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="featured-card animate-on-scroll">
                        <div class="featured-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h3 class="featured-title">Top Performers Guide</h3>
                        <p class="featured-description">
                            Study strategies and tips from top-performing students and experienced educators.
                        </p>
                        <a href="resources/study-guides/top-performers" class="btn-featured">
                            <i class="fas fa-book-reader"></i>
                            Read Guide
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="featured-card animate-on-scroll">
                        <div class="featured-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <h3 class="featured-title">New Video Series</h3>
                        <p class="featured-description">
                            Mathematics and Sciences video tutorials covering complex topics with step-by-step explanations.
                        </p>
                        <a href="resources/videos/new-series" class="btn-featured">
                            <i class="fas fa-play"></i>
                            Watch Series
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Public Results Section -->
    <section id="results" class="results-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <h2 class="section-title">Public Results Access</h2>
                <p class="section-subtitle">
                    Check examination results and academic performance statistics
                </p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="results-card animate-on-scroll">
                        <div class="results-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3 class="results-title">PLE Results</h3>
                        <p class="resource-type-description">
                            Primary Leaving Examination results and statistics for current and previous years.
                        </p>
                        <a href="results/ple" class="resource-link">
                            View Results <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="results-card animate-on-scroll">
                        <div class="results-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="results-title">UCE Results</h3>
                        <p class="resource-type-description">
                            Uganda Certificate of Education results and performance analysis by subject and school.
                        </p>
                        <a href="results/uce" class="resource-link">
                            View Results <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="results-card animate-on-scroll">
                        <div class="results-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3 class="results-title">UACE Results</h3>
                        <p class="resource-type-description">
                            Uganda Advanced Certificate of Education results with university admission guidelines.
                        </p>
                        <a href="results/uace" class="resource-link">
                            View Results <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5" style="background: #fff;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="animate-on-scroll">
                        <h2 class="section-title text-start">About Our Platform</h2>
                        <p class="lead mb-4">
                            <?php echo htmlspecialchars($board_name); ?> is committed to providing quality educational 
                            services and resources that enhance learning outcomes across Uganda.
                        </p>
                        <p class="mb-4">
                            Our comprehensive platform serves students, teachers, parents, and educational institutions 
                            with tools and resources designed to support academic excellence and administrative efficiency.
                        </p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Quality Assured Content</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Regular Updates</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Curriculum Aligned</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <span>Expert Reviewed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="animate-on-scroll text-center">
                        <div class="p-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 20px;">
                            <i class="fas fa-graduation-cap" style="font-size: 5rem; color: var(--accent-blue); margin-bottom: 1rem;"></i>
                            <h4 class="mb-3">Join Thousands of Learners</h4>
                            <p class="text-muted">
                                Experience quality education with our comprehensive resource library and 
                                innovative learning tools designed for success.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Access Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="container">
            <div class="text-center animate-on-scroll">
                <h2 class="mb-4">Quick Access Tools</h2>
                <div class="row g-4">
                    <div class="col-lg-3 col-md-6">
                        <a href="tools/calculator" class="text-decoration-none text-white">
                            <div class="p-4 rounded" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); transition: all 0.3s ease;">
                                <i class="fas fa-calculator fa-3x mb-3"></i>
                                <h5>Grade Calculator</h5>
                                <p class="small mb-0">Calculate your grades and GPA</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="tools/timetable" class="text-decoration-none text-white">
                            <div class="p-4 rounded" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); transition: all 0.3s ease;">
                                <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                                <h5>Study Timetable</h5>
                                <p class="small mb-0">Create personalized study schedules</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="tools/progress" class="text-decoration-none text-white">
                            <div class="p-4 rounded" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); transition: all 0.3s ease;">
                                <i class="fas fa-chart-line fa-3x mb-3"></i>
                                <h5>Progress Tracker</h5>
                                <p class="small mb-0">Monitor your learning progress</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="tools/subjects" class="text-decoration-none text-white">
                            <div class="p-4 rounded" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); transition: all 0.3s ease;">
                                <i class="fas fa-list-alt fa-3x mb-3"></i>
                                <h5>Subject Guide</h5>
                                <p class="small mb-0">Explore curriculum requirements</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white;">
        <div class="container text-center">
            <div class="animate-on-scroll">
                <h2 class="mb-4">Ready to Start Learning?</h2>
                <p class="lead mb-4">
                    Join thousands of students and educators who trust our platform for their educational journey
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="register.php" class="btn-primary-custom">
                        <i class="fas fa-user-plus"></i>
                        Create Free Account
                    </a>
                    <a href="login.php" class="btn-outline-primary-custom" style="border-color: white; color: white;">
                        <i class="fas fa-sign-in-alt"></i>
                        Login to Account
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-section">
                        <h4 class="footer-title">
                            <i class="fas fa-graduation-cap me-2"></i>
                            <?php echo htmlspecialchars($board_name); ?>
                        </h4>
                        <p style="color: #adb5bd; line-height: 1.6; margin-bottom: 1.5rem;">
                            Leading provider of educational services, examination management, and comprehensive 
                            school solutions across Uganda.
                        </p>
                        <div class="contact-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Kampala, Central Region, Uganda</span>
                        </div>
                        <div class="contact-info">
                            <i class="fas fa-globe"></i>
                            <span>Serving Uganda's Educational Community</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Resources</h5>
                        <a href="resources/primary" class="footer-link">Primary Resources</a>
                        <a href="resources/secondary" class="footer-link">Secondary Resources</a>
                        <a href="resources/past-papers" class="footer-link">Past Papers</a>
                        <a href="resources/study-guides" class="footer-link">Study Guides</a>
                        <a href="resources/videos" class="footer-link">Video Lessons</a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Services</h5>
                        <a href="results/ple" class="footer-link">PLE Results</a>
                        <a href="results/uce" class="footer-link">UCE Results</a>
                        <a href="results/uace" class="footer-link">UACE Results</a>
                        <a href="../login.php" class="footer-link">School Portal</a>
                        <a href="about.php" class="footer-link">About Us</a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Contact Information</h5>
                        <div class="contact-info">
                            <i class="fas fa-phone"></i>
                            <span>+256 777 115 678</span>
                        </div>
                        <div class="contact-info">
                            <i class="fas fa-envelope"></i>
                            <span>jmprossy@gmail.com</span>
                        </div>
                        <div class="contact-info">
                            <i class="fas fa-clock"></i>
                            <span>Mon - Fri: 8:00 AM - 6:00 PM</span>
                        </div>
                        <div class="contact-info">
                            <i class="fas fa-calendar"></i>
                            <span>Academic Year <?php echo $current_year; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6 text-md-start text-center">
                        <p class="mb-0">
                            &copy; <?php echo $current_year; ?> <?php echo htmlspecialchars($board_name); ?>. All rights reserved.
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end text-center">
                        <p class="mb-0">
                            <i class="fas fa-code me-1"></i>
                            Developed by <strong>ILABS UGANDA LIMITED</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const navHeight = document.querySelector('.navbar').offsetHeight;
                    const targetPosition = target.offsetTop - navHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Update active nav link
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            const scrollToTop = document.getElementById('scrollToTop');
            
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(30, 60, 114, 0.98)';
                scrollToTop.classList.add('visible');
            } else {
                navbar.style.background = 'rgba(30, 60, 114, 0.95)';
                scrollToTop.classList.remove('visible');
            }
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Scroll to top functionality
        document.getElementById('scrollToTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Add hover effects to quick access tools
        document.querySelectorAll('[href^="tools/"]').forEach(tool => {
            tool.addEventListener('mouseenter', function() {
                this.firstElementChild.style.transform = 'scale(1.05)';
                this.firstElementChild.style.background = 'rgba(255, 255, 255, 0.2)';
            });
            
            tool.addEventListener('mouseleave', function() {
                this.firstElementChild.style.transform = 'scale(1)';
                this.firstElementChild.style.background = 'rgba(255, 255, 255, 0.1)';
            });
        });

        // Add ripple effect to buttons
        function addRippleEffect(button) {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.4)';
                ripple.style.pointerEvents = 'none';
                ripple.style.animation = 'ripple 0.6s ease-out';
                ripple.style.zIndex = '1';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        }

        // Apply ripple effect to buttons
        document.querySelectorAll('.btn-primary-custom, .btn-outline-primary-custom, .btn-featured').forEach(addRippleEffect);

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    transform: scale(0);
                    opacity: 1;
                }
                100% {
                    transform: scale(2);
                    opacity: 0;
                }
            }
            
            .btn-primary-custom,
            .btn-outline-primary-custom,
            .btn-featured {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

        // Track navigation events (analytics placeholder)
        function trackNavigation(page, source) {
            console.log(`Navigation to ${page} from ${source}`);
            // Add your analytics tracking code here
        }

        // Track resource access
        document.querySelectorAll('a[href^="resources/"]').forEach(link => {
            link.addEventListener('click', function() {
                trackNavigation(this.href, 'resource_link');
            });
        });

        // Track result access
        document.querySelectorAll('a[href^="results/"]').forEach(link => {
            link.addEventListener('click', function() {
                trackNavigation(this.href, 'results_link');
            });
        });

        // Add loading states for external links
        document.querySelectorAll('a[href^="resources/"], a[href^="results/"], a[href^="tools/"]').forEach(link => {
            link.addEventListener('click', function(e) {
                // Add loading state for better UX
                const originalHtml = this.innerHTML;
                this.style.pointerEvents = 'none';
                this.style.opacity = '0.7';
                
                if (this.querySelector('i:last-child')) {
                    this.querySelector('i:last-child').className = 'fas fa-spinner fa-spin';
                }
                
                // Restore original state after timeout (fallback)
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                    this.style.pointerEvents = 'auto';
                    this.style.opacity = '1';
                }, 3000);
            });
        });

        // Optimize for mobile performance
        if (window.innerWidth < 768) {
            // Reduce animation complexity on mobile
            document.documentElement.style.setProperty('--animation-duration', '0.4s');
            
            // Simplify hover effects on touch devices
            if ('ontouchstart' in window) {
                document.querySelectorAll('.category-card, .resource-type-card, .results-card').forEach(card => {
                    card.addEventListener('touchstart', function() {
                        this.style.transform = 'scale(0.98)';
                    });
                    
                    card.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 200);
                    });
                });
            }
        }

        // Enhanced accessibility
        document.addEventListener('keydown', function(e) {
            // Quick keyboard shortcuts
            if (e.altKey) {
                switch(e.key.toLowerCase()) {
                    case 'r':
                        e.preventDefault();
                        document.getElementById('resources').scrollIntoView({ behavior: 'smooth' });
                        break;
                    case 'h':
                        e.preventDefault();
                        document.getElementById('home').scrollIntoView({ behavior: 'smooth' });
                        break;
                    case 'c':
                        e.preventDefault();
                        document.getElementById('contact').scrollIntoView({ behavior: 'smooth' });
                        break;
                }
            }
        });

        // Add focus indicators for better accessibility
        document.querySelectorAll('a, button').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.outline = '3px solid rgba(255, 215, 0, 0.5)';
                this.style.outlineOffset = '2px';
            });
            
            element.addEventListener('blur', function() {
                this.style.outline = 'none';
            });
        });

        // Preload critical resources
        const preloadLinks = [
            'resources/primary',
            'resources/secondary',
            'register.php',
            'login.php',
            '../login.php'
        ];

        preloadLinks.forEach(href => {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = href;
            document.head.appendChild(link);
        });

        // Add intersection observer for navigation highlighting
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link[href^="#"]');

        const sectionObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const activeLink = document.querySelector(`.nav-link[href="#${entry.target.id}"]`);
                    navLinks.forEach(link => link.classList.remove('active'));
                    if (activeLink) {
                        activeLink.classList.add('active');
                    }
                }
            });
        }, {
            threshold: 0.3,
            rootMargin: '-100px 0px -100px 0px'
        });

        sections.forEach(section => {
            sectionObserver.observe(section);
        });

        // Add performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Page loaded in ${Math.round(loadTime)}ms`);
            
            // Track load time if analytics is available
            if (typeof trackEvent === 'function') {
                trackEvent('performance', 'page_load_time', Math.round(loadTime));
            }
        });

        // Error handling for resource links
        document.querySelectorAll('a[href^="resources/"], a[href^="results/"]').forEach(link => {
            link.addEventListener('click', function(e) {
                // You can add validation here to check if the resource exists
                // For now, we'll just ensure the link works
                if (!this.href || this.href === '#') {
                    e.preventDefault();
                    alert('This resource is currently being updated. Please try again later.');
                }
            });
        });

        // Add smooth fade transitions
        window.addEventListener('beforeunload', function() {
            document.body.style.opacity = '0.8';
            document.body.style.transition = 'opacity 0.3s ease';
        });

        // Initialize tooltips if needed
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>

    <!-- Additional Analytics Scripts (if needed) -->
    <script>
        // Google Analytics placeholder
        // window.dataLayer = window.dataLayer || [];
        // function gtag(){dataLayer.push(arguments);}
        // gtag('js', new Date());
        // gtag('config', 'GA_MEASUREMENT_ID');
        
        // Track guest session
        sessionStorage.setItem('access_type', 'guest');
        sessionStorage.setItem('session_start', new Date().toISOString());
    </script>
</body>
</html>