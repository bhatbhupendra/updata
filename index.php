<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Grow Bridges | Connecting to Grow</title>
    <style>
    .hero {
        padding: 0.5rem 2rem;
        background: #e6f0ff;
        text-align: center;

    }

    .hero h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    .services {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        padding: 2rem;
        gap: 1.5rem;
    }

    .service-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        width: 250px;
        text-align: center;
    }

    .service-card h3 {
        color: #004080;
        margin-bottom: 1rem;
    }

    section.objective {
        max-width: 1200px;
        margin: 3rem auto;
        padding: 2rem;
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    section.objective h2 {
        color: #003366;
        margin-bottom: 1rem;
    }

    section.objective p {
        line-height: 1.7;
        font-size: 1.1rem;
    }

    footer {
        background: #004080;
        color: white;
        text-align: center;
        padding: 1rem;
        margin-top: 2rem;
    }

    .info-section {
        background-color: #ffffff;
        padding: 3rem 1rem;
    }

    .info-container {
        display: flex;
        flex-wrap: wrap;
        max-width: 1200px;
        margin: auto;
        align-items: center;
        gap: 2rem;
    }

    .info-image img {
        width: 100%;
        max-width: 450px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .info-text {
        flex: 1;
        min-width: 280px;
    }

    .info-text h2 {
        color: #003366;
        margin-bottom: 1rem;
    }

    .info-text p {
        font-size: 1.05rem;
        line-height: 1.6;
        color: #444;
    }

    @media (max-width: 768px) {
        .info-container {
            flex-direction: column;
            text-align: center;
        }

        .info-image img {
            max-width: 100%;
        }
    }

    .why-choose-section {
        background-color: #ffffff;
        padding: 3rem 1rem;
    }

    .why-container {
        max-width: 1200px;
        margin: auto;
        text-align: center;
    }

    .why-container h2 {
        color: #003366;
        margin-bottom: 2rem;
        font-size: 2rem;
    }

    .why-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
    }

    .why-card {
        background-color: #f4f8fc;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .why-icon {
        font-size: 2rem;
        margin-bottom: 1rem;
    }

    .why-card h3 {
        color: #003366;
        margin-bottom: 0.5rem;
    }

    .why-card p {
        color: #444;
        font-size: 1rem;
        line-height: 1.5;
    }

    .contact-section {
        background-color: #f0f6ff;
        padding: 0.5rem 1rem;
        text-align: center;
    }

    .contact-container {
        max-width: 1200px;
        margin: auto;
    }

    .contact-section h2 {
        color: #003366;
        margin-bottom: 0.5rem;
    }

    .contact-section p {
        color: #444;
        margin-bottom: 2rem;
    }

    .contact-content {
        display: flex;
        flex-wrap: wrap;
        gap: 2rem;
        justify-content: center;
        align-items: flex-start;
    }

    .contact-form {
        flex: 1;
        min-width: 280px;
        max-width: 500px;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .contact-form input,
    .contact-form textarea {
        padding: 0.75rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 1rem;
    }

    .contact-form button {
        background-color: #003366;
        color: white;
        padding: 0.75rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .contact-form button:hover {
        background-color: #0055aa;
    }

    .contact-info {
        flex: 1;
        min-width: 250px;
        text-align: center;
    }

    .contact-info h3 {
        margin-bottom: 1rem;
        color: #003366;
    }

    .contact-info p {
        margin-bottom: 0.5rem;
        font-size: 1rem;
        color: #333;
    }

    @media (max-width: 768px) {
        .contact-content {
            flex-direction: column;
            align-items: center;
        }

        .contact-info {
            text-align: center;
        }
    }

    .map-container iframe {
        width: 100%;
        height: 250px;
        border: 0;
        border-radius: 10px;
    }
    </style>
    <link rel="stylesheet" href="assets/gallary.css">
    <link rel="stylesheet" href="assets/body.css">

</head>

<body>

    <header>
        <img src="assets/home/logo.png" alt="Grow" class="logo">
        <div>
            <h1>Grow Bridges</h1>
            <p>"Connecting to Grow"</p>
        </div>
    </header>

    <section class="hero">
        <h2>Welcome to GrowBridges Pvt. Ltd.</h2>
        <p>We support international students from South Asia in preparing for a successful academic and professional
            life in
            Japan. <br />With guidance at every step—from school selection to job placement—we make your transition to
            Japan
            smooth,
            confident, and future-ready.</p>
    </section>

    <div class="features">
        <div class="features-content">
            <h1>Preparing International Students for Life in Japan</h1>
            <p>
                Our objective is to prepare and support international students for a successful transition into life in
                Japan, both as learners and future professionals. We aim to equip them with essential knowledge of
                Japanese working culture, communication skills and workplace expectations. Through training, counselling
                guidance, we help students adapt smoothly to Japan.
            </p>
            <ul>
                <li><b> Support to find the best Japanese language school in Japan</b></li>
                <li><b> Support to find a company for you for SSW visa</b></li>
                <li><b> Support to find a placement for 特定技能 visa</b></li>
                <li><b> All in one solution. From starting to Airport pickup arrangement in Japan</b></li>
                <li><b> Dormitory services in Tokyo</b></li>
                <li><b> All the documentation and guidance step by step</b></li>
            </ul>
        </div>

        <div class="images">
            <img src="assets/home/i1.png" alt="Counseling">
            <img src="assets/home/i2.png" alt="Businessmen">
        </div>
    </div>

    <section class="why-choose-section">
        <div class="why-container">
            <h2>Why Choose Grow Bridges?</h2>
            <div class="why-grid">
                <div class="why-card">
                    <div class="why-icon">🤝</div>
                    <h3>Team Support</h3>
                    <p>Local teams in both Nepal and Japan </p>
                </div>
                <div class="why-card">
                    <div class="why-icon">🇯🇵</div>
                    <h3>Language Expertise</h3>
                    <p>Multilingual support (Nepali, English, Hindi, Japanese)</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">🌐</div>
                    <h3>Team Goal</h3>
                    <p>Personalized counseling and ongoing aftercare, Affordable and transparent services</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">📈</div>
                    <h3>Experience</h3>
                    <p>Experienced with over 100+ student transitions</p>
                </div>
            </div>
        </div>
    </section>



    <section class="hero">
        <h2>About Grow Bridge</h2>
        <p>"Connecting to grow"</p>
    </section>
    <section class="objective">
        <h2>About Us</h2>
        <p>
            GrowBridges Pvt. Ltd. is a Nepal–Japan based support and placement company dedicated to international
            students.
            Founded with a vision to empower students and working professionals from Nepal, India, Sri Lanka,
            Bangladesh, and
            other countries, our team has years of experience in the Japanese education and employment system. We work
            closely
            with: Japanese Language Schools SSW-registered employers HR agents and coordinators in Japan Local families
            and
            hostels </p>
    </section>

    <section class="gallery-section">
        <h2>Grow Gallery</h2>
        <div class="gallery-grid">
            <div class="gallery-item">
                <img src="assets/home/g1.jpg" alt="Counseling">
                <div class="description"><b>1-on-1 Counseling Session for Study in Japan</b></div>
            </div>
            <div class="gallery-item">
                <img src="assets/home/g2.jpg" alt="Job Placement">
                <div class="description"><b>Placement Assistance for Skilled Students</b></div>
            </div>
            <div class="gallery-item">
                <img src="assets/home/g3.jpg" alt="Dormitory">
                <div class="description"><b>Clean and Affordable Dormitory in Tokyo</b></div>
            </div>
            <div class="gallery-item">
                <img src="assets/home/g4.jpg" alt="Airport Pickup">
                <div class="description"><b>Airport Pickup and Initial Setup Support</b></div>
            </div>
        </div>
    </section>

    <section class="info-section">
        <div class="info-container">
            <div class="info-image">
                <img src="assets/home/japan1.jpeg" alt="Students in Japan" />
            </div>
            <div class="info-text">
                <h2>Supporting Every Step of the Journey</h2>
                <p>
                    From your first inquiry to your first job in Japan, Grow Bridges is your trusted partner.
                    We offer hands-on support for documentation, cultural training, and academic preparation.
                </p>
                <p>
                    Our expert team understands the challenges international students face, and we are dedicated
                    to making your move to Japan smooth, enriching, and successful.
                </p>
            </div>
        </div>
    </section>


    <section class="contact-section">
        <div class="contact-container">
            <h2>Contact Us</h2>
            <p>Have questions or need support? Reach out to us—we're here to help!</p>

            <div class="contact-content">
                <div class="contact-info">
                    <h3>Nepal Office</h3>
                    <p>Putalisadak, Kathmandu, Nepal </p>
                    <p>Phone: +977-9812345678 </p>
                    <p>Email: support@growbridges.com </p>
                </div>

            </div>
            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3532.456052049919!2d85.3200843749229!3d27.70320212567527!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39eb19a64b5f13e1%3A0x28b2d0eacda46b98!2sPutalisadak%2C%20Kathmandu%2044600!5e0!3m2!1sen!2snp!4v1750953739850!5m2!1sen!2snp"
                    width="100%" height="250" style="border:0; border-radius: 10px; margin-top: 1rem;"
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            <div class="contact-content">
                <div class="contact-info">
                    <h3>Japan Office</h3>
                    <p>Warabi, Saitama, Japan </p>
                    <p>Phone: +81-80-1234-5678 </p>
                    <p>Email: info@growbridges.com</p>
                </div>

            </div>
            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d25879.945375153737!2d139.6658191231056!3d35.824643239329156!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x6018eb33e6d023c9%3A0x2d42c69f22c9f458!2sWarabi%2C%20Saitama%2C%20Japan!5e0!3m2!1sen!2snp!4v1750953639168!5m2!1sen!2snp"
                    width="100%" height="250" style="border:0; border-radius: 10px; margin-top: 1rem;"
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </section>



    <footer>
        &copy; 2025 Grow Bridges. All rights reserved. <a
            href="https://growbridges.com/admin/dashboard.php">Admin</a>,<a
            href="https://growbridges.com/user/dashboard.php">Agent Dashboard</a>
    </footer>

</body>

</html>