<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esports News</title>
    <style>
        body {
            font-family: "Poppins", sans-serif;
            top: 0;
            margin: 0;
            padding: 0;
            background-image: url(all_background.jpg);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: rgba(7, 166, 188, 0.75);
            color: #fff;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(7, 166, 188, 0.2);
        }

        h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 600;
        }

        .home-button {
            position: absolute;
            right: 20px;
            top: 20px;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            background-color: rgba(7, 166, 188, 0.4);
            transition: all 0.3s ease;
        }

        .home-button:hover {
            background: #07a6bc;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(7, 166, 188, 0.3);
        }

        .news-section {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }

        .news-item {
            position: relative;
            width: calc(33.333% - 20px);
            height: 350px;
            background-size: cover;
            background-position: center;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 15px rgba(7, 166, 188, 0.2);
        }

        .news-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(7, 166, 188, 0.3);
        }

        .news-content {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0));
            color: #fff;
            padding: 30px 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .news-title {
            font-size: 22px;
            margin-bottom: 12px;
            font-weight: 600;
            line-height: 1.3;
        }

        .news-date {
            font-size: 14px;
            opacity: 0.8;
        }

        footer {
            background-color: rgba(7, 166, 188, 0.75);
            color: #fff;
            text-align: center;
            padding: 15px;
            margin-top: auto;
            box-shadow: 0 -2px 10px rgba(7, 166, 188, 0.2);
        }

        @media (max-width: 768px) {
            .news-item {
                width: calc(50% - 20px);
            }
        }

        @media (max-width: 480px) {
            .news-item {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>Latest Esports News</h1>
    <a href="index.php" class="home-button">Home</a>
</header>

<section class="news-section">
    <a href="https://esportsworldcup.com/en/news/FalconsChampions-EWC" class="news-item" style="background-image: url('assets/images/falcons news.jpg');">
        <div class="news-content">
            <h2 class="news-title">Falcons takes the Esports World Cup Home!</h2>
            <p class="news-date">Published on August 20, 2024</p>
        </div>
    </a>

    <a href="tournaments.php" class="news-item" style="background-image: url('assets/images/new tourney.jpg');">
        <div class="news-content">
            <h2 class="news-title">Upcoming Tournaments Up For Grabs</h2>
            <p class="news-date">Published on September 18, 2024</p>
        </div>
    </a>

    <a href="https://www.example.com/crac-cloud-tournament" class="news-item" style="background-image: url('assets/images/concluded tourney.jpg');">
        <div class="news-content">
            <h2 class="news-title">Crac Cloud Concludes A Successful Tournament</h2>
            <p class="news-date">Published on September 15, 2024</p>
        </div>
    </a>
</section>

<footer>
    <p>&copy; 2024 Crac Cloud. All Rights Reserved.</p>
</footer>

</body>
</html>
