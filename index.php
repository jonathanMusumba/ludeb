<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>Results Management System - Educational Resources & School Portal | Luuka Examination Board</title>
    <meta name="description" content="Access educational resources, examination results, and comprehensive school management solutions. Primary & secondary resources, ERP systems for educational institutions in Uganda.">
    <meta name="keywords" content="results management system, educational resources, school portal, examination results, primary education, secondary education, school ERP, Uganda education, Luuka examination board">
    <meta name="author" content="ILABS UGANDA LIMITED - Jonathan Musumba">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Results Management System - Educational Resources & School Portal">
    <meta property="og:description" content="Comprehensive educational platform offering resources, results management, and school ERP solutions for educational institutions.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://yoursite.com">
    <meta property="og:image" content="https://yoursite.com/static/img/og-image.jpg">
    <meta property="og:site_name" content="Luuka Examination Board">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Results Management System - Educational Resources">
    <meta name="twitter:description" content="Access educational resources, examination results, and school management solutions.">
    <meta name="twitter:image" content="https://yoursite.com/static/img/twitter-card.jpg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://yoursite.com">
    
    <!-- External Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "Luuka Examination Board",
        "description": "Educational platform providing resources, results management, and school ERP solutions",
        "url": "https://yoursite.com",
        "telephone": "+256777115678",
        "email": "jmprossy@gmail.com",
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "Uganda",
            "addressRegion": "Central Region",
            "addressLocality": "Kampala"
        },
        "sameAs": [],
        "offers": {
            "@type": "Offer",
            "category": "Educational Services"
        }
    }
    </script>

    <style>
        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffc107;
            --accent-blue: #0066cc;
            --success-green: #28a745;
            --dark-overlay: rgba(0, 0, 0, 0.7);
            --light-overlay: rgba(255, 255, 255, 0.1);
            --shadow-light: rgba(255, 215, 0, 0.3);
            --shadow-dark: rgba(0, 0, 0, 0.5);
            --gradient-primary: linear-gradient(135deg, var(--primary-gold) 0%, var(--secondary-gold) 100%);
            --gradient-blue: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Navigation */
        .navbar {
            background: rgba(0, 0, 0, 0.9) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-gold) !important;
            font-size: 1.5rem;
        }

        .navbar-nav .nav-link {
            color: #fff !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-gold) !important;
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-gold);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        /* Hero Section */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, var(--shadow-light) 0%, transparent 70%),
                        radial-gradient(circle at 70% 80%, var(--shadow-light) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            0% { opacity: 0.3; }
            100% { opacity: 0.6; }
        }

        .hero-content {
            text-align: center;
            color: #fff;
            z-index: 2;
            position: relative;
            max-width: 900px;
            padding: 2rem;
        }

        .logo-container {
            margin-bottom: 2rem;
            animation: fadeInScale 1s ease-out both;
        }

        .logo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid var(--primary-gold);
            padding: 8px;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            object-fit: contain;
            box-shadow: 0 15px 40px var(--shadow-dark),
                        0 0 0 8px rgba(255, 215, 0, 0.2);
            transition: all 0.3s ease;
            margin: 0 auto;
            display: block;
        }

        .logo:hover {
            transform: scale(1.05);
            box-shadow: 0 20px 50px var(--shadow-dark),
                        0 0 0 12px rgba(255, 215, 0, 0.3);
        }

        .logo-placeholder {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid var(--primary-gold);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #000;
            font-weight: 700;
            box-shadow: 0 15px 40px var(--shadow-dark),
                        0 0 0 8px rgba(255, 215, 0, 0.2);
            transition: all 0.3s ease;
            margin: 0 auto;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--primary-gold);
            text-shadow: 2px 2px 8px var(--shadow-dark);
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: #e9ecef;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out 0.5s both;
        }

        .hero-description {
            font-size: 1.1rem;
            color: #adb5bd;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 1s ease-out 0.7s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.9s both;
        }

        .btn-primary-custom {
            background: var(--gradient-primary);
            border: none;
            color: #000;
            font-weight: 600;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-secondary-custom {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--primary-gold);
            color: #fff;
            font-weight: 600;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
            color: #000;
        }

        .btn-secondary-custom:hover {
            background: rgba(255, 215, 0, 0.1);
            transform: translateY(-3px);
            color: var(--primary-gold);
        }

        /* Features Section */
        .features-section {
            padding: 5rem 0;
            background: #fff;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
            color: #333;
        }

        .section-subtitle {
            text-align: center;
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .feature-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #000;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .feature-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .feature-link {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .feature-link:hover {
            color: var(--primary-gold);
            transform: translateX(5px);
        }

        /* Resources Section */
        .resources-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .resource-category {
            background: #fff;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .resource-category:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .resource-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: var(--gradient-blue);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .resource-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .resource-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1.5rem 0;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-blue);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* CTA Section */
        .cta-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: #fff;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            padding: 3rem 0 1rem;
        }

        .footer-section {
            margin-bottom: 2rem;
        }

        .footer-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-gold);
        }

        .footer-link {
            color: #adb5bd;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .footer-link:hover {
            color: var(--primary-gold);
            transform: translateX(5px);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            text-align: center;
            color: #adb5bd;
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: #adb5bd;
        }

        .contact-info i {
            color: var(--primary-gold);
            width: 20px;
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

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                opacity: 1;
                transform: scale(1);
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

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .logo, .logo-placeholder {
                width: 120px;
                height: 120px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary-custom,
            .btn-secondary-custom {
                width: 100%;
                max-width: 280px;
            }

            .resource-stats {
                gap: 1rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .cta-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero-content {
                padding: 1rem;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .feature-card {
                padding: 1.5rem 1rem;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
        }

        /* Scroll indicator */
        .scroll-indicator {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            color: #fff;
            font-size: 2rem;
            animation: bounce 2s infinite;
            z-index: 2;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }

        /* Loading animation */
        .loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #000 0%, #1a1a1a 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }

        .loader.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            border: 4px solid rgba(255, 215, 0, 0.3);
            border-top: 4px solid var(--primary-gold);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Floating particles */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary-gold);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
            opacity: 0.6;
        }

        @keyframes float {
            0%, 100% { transform: translateY(100vh) scale(0); }
            50% { transform: translateY(-10px) scale(1); }
        }
    </style>
</head>
<body>
    <!-- Loader -->
    <div class="loader" id="loader">
        <div class="text-center">
            <div class="spinner mb-3"></div>
            <div style="color: var(--primary-gold); font-weight: 600;">Loading Educational Portal...</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <i class="fas fa-graduation-cap me-2"></i>
                Luuka Board
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#resources">Resources</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">School Portal</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="particles">
            <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="particle" style="left: 20%; animation-delay: 1s;"></div>
            <div class="particle" style="left: 30%; animation-delay: 2s;"></div>
            <div class="particle" style="left: 40%; animation-delay: 3s;"></div>
            <div class="particle" style="left: 50%; animation-delay: 4s;"></div>
            <div class="particle" style="left: 60%; animation-delay: 5s;"></div>
            <div class="particle" style="left: 70%; animation-delay: 0.5s;"></div>
            <div class="particle" style="left: 80%; animation-delay: 1.5s;"></div>
            <div class="particle" style="left: 90%; animation-delay: 2.5s;"></div>
        </div>
        <div class="hero-overlay"></div>
        
        <div class="hero-content">
            <div class="logo-container">
                <div class="logo-placeholder">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>
            
            <h1 class="hero-title">Luuka Examination Board</h1>
            <h2 class="hero-subtitle">Results Management System</h2>
            <p class="hero-description">
                Your comprehensive educational platform for accessing examination results, educational resources, 
                and comprehensive school management solutions for primary and secondary institutions.
            </p>
            
            <div class="hero-buttons">
                <a href="#resources" class="btn-primary-custom">
                    <i class="fas fa-book-open"></i>
                    Explore Resources
                </a>
                <a href="login.php" class="btn-secondary-custom">
                    <i class="fas fa-school"></i>
                    School Portal
                </a>
            </div>
        </div>
        
        <div class="scroll-indicator">
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="features-section">
        <div class="container">
            <h2 class="section-title animate-on-scroll">Our Services</h2>
            <p class="section-subtitle animate-on-scroll">
                Comprehensive educational solutions designed to meet the needs of students, teachers, and educational institutions
            </p>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="feature-title">Results Management</h3>
                        <p class="feature-description">
                            Secure and efficient examination results processing, storage, and distribution system for educational institutions.
                        </p>
                        <a href="login.php" class="feature-link">
                            Access Portal <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <h3 class="feature-title">Educational Resources</h3>
                        <p class="feature-description">
                            Access a vast library of educational materials, past papers, and study guides for primary and secondary education.
                        </p>
                        <a href="#resources" class="feature-link">
                            Browse Resources <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="feature-title">School ERP System</h3>
                        <p class="feature-description">
                            Complete enterprise resource planning solution for schools including student management, staff records, and operations.
                        </p>
                        <a href="#contact" class="feature-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Resources Section -->
    <section id="resources" class="resources-section">
        <div class="container">
            <h2 class="section-title animate-on-scroll">Educational Resources</h2>
            <p class="section-subtitle animate-on-scroll">
                Comprehensive collection of educational materials for primary and secondary education
            </p>
            
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="resource-category animate-on-scroll">
                        <div class="resource-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <h3 class="resource-title">Primary Education Resources</h3>
                        <p class="feature-description">
                            Study materials, worksheets, and educational content for Primary 1-7 students across all subjects.
                        </p>
                        <div class="resource-stats">
                            <div class="stat-item">
                                <div class="stat-number">500+</div>
                                <div class="stat-label">Study Materials</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">50+</div>
                                <div class="stat-label">Past Papers</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="resources/primary" class="btn btn-outline-primary">Free Resources</a>
                            <a href="resources/primary/premium" class="btn btn-primary">Premium Content</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="resource-category animate-on-scroll">
                        <div class="resource-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="resource-title">Secondary Education Resources</h3>
                        <p class="feature-description">
                            Advanced study materials, revision guides, and examination papers for O-Level and A-Level students.
                        </p>
                        <div class="resource-stats">
                            <div class="stat-item">
                                <div class="stat-number">800+</div>
                                <div class="stat-label">Study Materials</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">200+</div>
                                <div class="stat-label">Past Papers</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="resources/secondary" class="btn btn-outline-primary">Free Resources</a>
                            <a href="resources/secondary/premium" class="btn btn-primary">Premium Content</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resource Categories -->
            <div class="row g-4 mt-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h4 class="feature-title">Past Papers</h4>
                        <p class="feature-description">
                            Complete collection of previous examination papers with marking schemes.
                        </p>
                        <a href="resources/past-papers" class="feature-link">
                            Download <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);">
                            <i class="fas fa-book"></i>
                        </div>
                        <h4 class="feature-title">Study Guides</h4>
                        <p class="feature-description">
                            Comprehensive study guides and revision materials for all subjects.
                        </p>
                        <a href="resources/study-guides" class="feature-link">
                            Browse <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                            <i class="fas fa-video"></i>
                        </div>
                        <h4 class="feature-title">Video Tutorials</h4>
                        <p class="feature-description">
                            Interactive video lessons and tutorials for enhanced learning experience.
                        </p>
                        <a href="resources/videos" class="feature-link">
                            Watch <i class="fas fa-play"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h4 class="feature-title">Practice Tests</h4>
                        <p class="feature-description">
                            Online practice tests and quizzes to assess your knowledge and preparation.
                        </p>
                        <a href="resources/practice-tests" class="feature-link">
                            Start Test <i class="fas fa-play-circle"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="animate-on-scroll">
                        <h3 class="display-4 fw-bold mb-2">50+</h3>
                        <p class="lead">Partner Schools</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="animate-on-scroll">
                        <h3 class="display-4 fw-bold mb-2">10,000+</h3>
                        <p class="lead">Students Served</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="animate-on-scroll">
                        <h3 class="display-4 fw-bold mb-2">1,500+</h3>
                        <p class="lead">Resources Available</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="animate-on-scroll">
                        <h3 class="display-4 fw-bold mb-2">5+</h3>
                        <p class="lead">Years Experience</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="animate-on-scroll">
                <h2 class="cta-title">Ready to Get Started?</h2>
                <p class="cta-description">
                    Join thousands of students and educators who trust our platform for their educational needs
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="register.php" class="btn-primary-custom">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </a>
                    <a href="login.php" class="btn-secondary-custom">
                        <i class="fas fa-sign-in-alt"></i>
                        School Login
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
                            Luuka Examination Board
                        </h4>
                        <p style="color: #adb5bd; line-height: 1.6;">
                            Leading provider of educational services, examination management, and comprehensive 
                            school solutions across Uganda.
                        </p>
                        <div class="contact-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Kampala, Central Region, Uganda</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Quick Links</h5>
                        <a href="#home" class="footer-link">Home</a>
                        <a href="#resources" class="footer-link">Resources</a>
                        <a href="#services" class="footer-link">Services</a>
                        <a href="login.php" class="footer-link">School Portal</a>
                        <a href="about.php" class="footer-link">About Us</a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="footer-section">
                        <h5 class="footer-title">Resources</h5>
                        <a href="resources/primary" class="footer-link">Primary Resources</a>
                        <a href="resources/secondary" class="footer-link">Secondary Resources</a>
                        <a href="resources/past-papers" class="footer-link">Past Papers</a>
                        <a href="resources/study-guides" class="footer-link">Study Guides</a>
                        <a href="help.php" class="footer-link">Help Center</a>
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
                            <i class="fas fa-globe"></i>
                            <span>www.luukaboard.ug</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6 text-md-start text-center">
                        <p class="mb-0">
                            &copy; 2025 Luuka Examination Board. All rights reserved.
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced page load handling
        window.addEventListener('load', function() {
            const minLoadTime = 1500;
            const startTime = performance.now();
            
            setTimeout(() => {
                const elapsed = performance.now() - startTime;
                const remainingTime = Math.max(0, minLoadTime - elapsed);
                
                setTimeout(() => {
                    document.getElementById('loader').classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }, remainingTime);
            }, 100);
        });

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
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(0, 0, 0, 0.95)';
            } else {
                navbar.style.background = 'rgba(0, 0, 0, 0.9)';
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

        // Add ripple effect to buttons
        document.querySelectorAll('.btn-primary-custom, .btn-secondary-custom').forEach(button => {
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
                ripple.style.background = 'rgba(255, 255, 255, 0.5)';
                ripple.style.pointerEvents = 'none';
                ripple.style.animation = 'ripple 0.6s ease-out';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Enhanced particle system
        function createParticles() {
            const particlesContainer = document.querySelector('.particles');
            const particleCount = window.innerWidth < 768 ? 5 : 9;
            
            particlesContainer.innerHTML = '';
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 8 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 6) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

        // Recreate particles on window resize
        window.addEventListener('resize', createParticles);

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    transform: scale(0);
                    opacity: 1;
                }
                100% {
                    transform: scale(1);
                    opacity: 0;
                }
            }
            
            .btn-primary-custom,
            .btn-secondary-custom {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

        // Preload images for better performance
        const preloadImages = [
            'static/img/background.jpg',
            'static/img/og-image.jpg'
        ];

        preloadImages.forEach(src => {
            const img = new Image();
            img.src = src;
        });

        // Add keyboard navigation support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const focused = document.activeElement;
                if (focused.classList.contains('feature-card') || focused.classList.contains('resource-category')) {
                    const link = focused.querySelector('.feature-link, a');
                    if (link) {
                        link.click();
                    }
                }
            }
        });

        // Add focus support for cards
        document.querySelectorAll('.feature-card, .resource-category').forEach(card => {
            card.setAttribute('tabindex', '0');
            card.style.cursor = 'pointer';
            
            card.addEventListener('click', function() {
                const link = this.querySelector('.feature-link, a');
                if (link) {
                    link.click();
                }
            });
        });

        // Performance optimization: Reduce animations on mobile
        if (window.innerWidth < 768) {
            document.documentElement.style.setProperty('--animation-duration', '0.3s');
        }

        // Add error handling for images
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                const placeholder = this.parentNode.querySelector('.logo-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
            });
        });

        // Add scroll to top functionality
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                if (!document.querySelector('.scroll-to-top')) {
                    const scrollBtn = document.createElement('button');
                    scrollBtn.className = 'scroll-to-top';
                    scrollBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                    scrollBtn.style.cssText = `
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
                        animation: fadeInUp 0.3s ease;
                    `;
                    
                    scrollBtn.addEventListener('click', () => {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                    
                    scrollBtn.addEventListener('mouseenter', () => {
                        scrollBtn.style.transform = 'scale(1.1)';
                    });
                    
                    scrollBtn.addEventListener('mouseleave', () => {
                        scrollBtn.style.transform = 'scale(1)';
                    });
                    
                    document.body.appendChild(scrollBtn);
                }
            } else {
                const scrollBtn = document.querySelector('.scroll-to-top');
                if (scrollBtn) {
                    scrollBtn.remove();
                }
            }
        });
    </script>
</body>
</html>