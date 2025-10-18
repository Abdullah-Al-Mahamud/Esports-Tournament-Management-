-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `Esports_Tournament`
--

DROP DATABASE IF EXISTS `Esports_Tournament`;
CREATE DATABASE IF NOT EXISTS `Esports_Tournament` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `Esports_Tournament`;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `team_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_name` varchar(255) NOT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `platform` varchar(100) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `number_of_players` int(11) NOT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_name` (`game_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `game_type` varchar(255) NOT NULL,
  `entry_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prize_money` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tournament_date` datetime NOT NULL,
  `registration_deadline` datetime NOT NULL,
  `slots` int(11) NOT NULL,
  `min_teams` int(11) NOT NULL DEFAULT 4,
  `max_teams` int(11) NOT NULL DEFAULT 16,
  `picture` varchar(255) DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `game_type` (`game_type`),
  CONSTRAINT `tournaments_ibfk_1` FOREIGN KEY (`game_type`) REFERENCES `games` (`game_name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `team_name` varchar(255) NOT NULL,
    `manager_email` varchar(255) NOT NULL,
    `matches_played` int(11) DEFAULT 0,
    `matches_won` int(11) DEFAULT 0,
    `matches_lost` int(11) DEFAULT 0,
    `matches_drawn` int(11) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `team_name` (`team_name`),
    KEY `manager_email` (`manager_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `team_registrations`
--

CREATE TABLE `team_registrations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tournament_id` int(11) NOT NULL,
    `team_id` int(11) NOT NULL,
    `manager_name` varchar(255) NOT NULL,
    `manager_email` varchar(255) NOT NULL,
    `manager_phone` varchar(20) NOT NULL,
    `payment_status` enum('pending','processing','confirmed') NOT NULL DEFAULT 'pending',
    `payment_method` enum('cash','bkash','nagad','card') DEFAULT NULL,
    `payment_details` JSON DEFAULT NULL,
    `transaction_id` varchar(255) DEFAULT NULL,
    `payment_date` datetime DEFAULT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `tournament_id` (`tournament_id`),
    KEY `team_id` (`team_id`),
    CONSTRAINT `team_registrations_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `team_registrations_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `fixtures`
--

CREATE TABLE `fixtures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `team1_id` int(11) DEFAULT NULL,
  `team2_id` int(11) DEFAULT NULL,
  `match_date` datetime DEFAULT NULL,
  `round` varchar(50) NOT NULL,
  `match_number` int(11) NOT NULL,
  `team1_score` int(11) DEFAULT NULL,
  `team2_score` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `status` enum('pending','ongoing','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  KEY `team1_id` (`team1_id`),
  KEY `team2_id` (`team2_id`),
  KEY `winner_id` (`winner_id`),
  CONSTRAINT `fixtures_tournament_fk` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fixtures_team1_fk` FOREIGN KEY (`team1_id`) REFERENCES `team_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixtures_team2_fk` FOREIGN KEY (`team2_id`) REFERENCES `team_registrations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixtures_winner_fk` FOREIGN KEY (`winner_id`) REFERENCES `team_registrations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_rules`
--

CREATE TABLE `tournament_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `rule_title` varchar(255) NOT NULL,
  `rule_description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  CONSTRAINT `tournament_rules_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_players`
--

CREATE TABLE `team_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `player_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `team_players_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `team_registrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Data for table `games` - Valorant
--

INSERT INTO `games` (`game_name`, `genre`, `platform`, `number_of_players`, `description`) VALUES
('Valorant', 'Tactical FPS', 'PC', 5, 'Valorant is a free-to-play first-person tactical hero shooter developed and published by Riot Games.');

-- --------------------------------------------------------

--
-- Data for table `tournaments` - Summer Championship 2025
--

INSERT INTO `tournaments` (`tournament_name`, `description`, `game_type`, `entry_fee`, `prize_money`, `tournament_date`, `registration_deadline`, `slots`, `min_teams`, `max_teams`, `status`, `picture`) VALUES
('Summer Championship 2025', 'Join our exciting Summer Championship 2025! This tournament features intense Valorant matches with teams competing for glory and prizes. Register your team now to secure your spot in this prestigious event.', 'Valorant', 500.00, 10000.00, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 2 WEEK), DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 DAY), 16, 8, 16, 'upcoming', '../assets/images/Esports Tournament.jpg');

COMMIT;
