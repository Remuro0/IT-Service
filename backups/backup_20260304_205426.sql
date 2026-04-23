-- Backup of database `local`
-- Date: 2026-03-04 20:54:26
SET FOREIGN_KEY_CHECKS = 0;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_id` int(11) DEFAULT NULL,
  `backup_date` datetime DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `size_mb` decimal(10,2) DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  PRIMARY KEY (`id`),
  KEY `server_id` (`server_id`),
  CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `backups` (`id`, `server_id`, `backup_date`, `file_path`, `size_mb`, `status`) VALUES ('6', '1', '2025-10-24 22:00:00', '/backups/proddb_full_20251024.sql.gz', '1245.30', 'success');
INSERT INTO `backups` (`id`, `server_id`, `backup_date`, `file_path`, `size_mb`, `status`) VALUES ('7', '2', '2025-10-24 23:30:00', '/backups/webapp_daily_20251024.tar', '890.70', 'success');
INSERT INTO `backups` (`id`, `server_id`, `backup_date`, `file_path`, `size_mb`, `status`) VALUES ('8', '3', '2025-10-24 02:00:00', '/backups/backupserver_weekly_20251024.zip', '3450.10', 'failed');
INSERT INTO `backups` (`id`, `server_id`, `backup_date`, `file_path`, `size_mb`, `status`) VALUES ('9', '1', '2025-10-25 01:00:00', '/backups/proddb_incremental_20251025.diff', '210.50', 'success');
INSERT INTO `backups` (`id`, `server_id`, `backup_date`, `file_path`, `size_mb`, `status`) VALUES ('24', '4', '2025-12-08 23:20:16', '/backups/backup_20251208_172016.sql', '0.02', 'success');
INSERT INTO `backups` (`id`, `server_id`, `backup_date`, `file_path`, `size_mb`, `status`) VALUES ('26', '4', '2025-12-08 23:28:00', '/backups/backup_20251208_172804.sql', '0.02', 'success');

CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `type` enum('service','package') NOT NULL DEFAULT 'service',
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `change_type` enum('minor','major','emergency') DEFAULT 'minor',
  `status` enum('planned','approved','implemented','failed') DEFAULT 'planned',
  `planned_start` datetime DEFAULT NULL,
  `planned_end` datetime DEFAULT NULL,
  `actual_start` datetime DEFAULT NULL,
  `actual_end` datetime DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `changes_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `changes_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `changes` (`id`, `title`, `description`, `change_type`, `status`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `requested_by`, `approved_by`) VALUES ('6', 'Upgrade MySQL to 8.0', 'Повышение стабильности и безопасности', 'major', 'implemented', '2025-10-20 09:00:00', '2025-10-20 11:00:00', '2025-10-20 09:05:00', '2025-10-20 10:45:00', '2', '1');
INSERT INTO `changes` (`id`, `title`, `description`, `change_type`, `status`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `requested_by`, `approved_by`) VALUES ('7', 'Add SSL cert for WebFrontend', 'Установка Let\'s Encrypt сертификата', 'minor', 'approved', '2025-10-26 14:00:00', '2025-10-26 15:00:00', NULL, NULL, '3', '1');
INSERT INTO `changes` (`id`, `title`, `description`, `change_type`, `status`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `requested_by`, `approved_by`) VALUES ('8', 'Emergency patch for security bug', 'Критический патч для CVE-2025-XXXX', 'emergency', 'failed', '2025-10-22 03:00:00', '2025-10-22 04:00:00', '2025-10-22 03:05:00', '2025-10-22 04:15:00', '2', '1');
INSERT INTO `changes` (`id`, `title`, `description`, `change_type`, `status`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `requested_by`, `approved_by`) VALUES ('9', 'Migrate DB to new storage', 'Перенос БД на SSD массив', 'major', 'planned', '2025-11-05 10:00:00', '2025-11-05 16:00:00', NULL, NULL, '4', '1');
INSERT INTO `changes` (`id`, `title`, `description`, `change_type`, `status`, `planned_start`, `planned_end`, `actual_start`, `actual_end`, `requested_by`, `approved_by`) VALUES ('10', 'Update firewall rules', 'Добавить новые IP-разрешения', 'minor', '', '2025-10-25 13:00:00', '2025-10-25 14:00:00', '2025-10-25 13:00:00', NULL, '2', '3');

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `incidents` (`id`, `title`, `description`, `severity`, `status`, `created_at`, `resolved_at`, `assigned_to`) VALUES ('1', 'Database connection timeout', 'Сервер не отвечает на запросы', 'high', 'resolved', '2025-10-20 10:30:00', '2025-10-20 11:15:00', '2');
INSERT INTO `incidents` (`id`, `title`, `description`, `severity`, `status`, `created_at`, `resolved_at`, `assigned_to`) VALUES ('2', 'Website down for 5 min', 'Ошибка 502 Gateway Timeout', 'medium', 'closed', '2025-10-23 18:45:00', '2025-10-23 19:00:00', '3');
INSERT INTO `incidents` (`id`, `title`, `description`, `severity`, `status`, `created_at`, `resolved_at`, `assigned_to`) VALUES ('3', 'High CPU on Dev server', 'Процесс компиляции заблокировал сервер', 'low', 'open', '2025-10-25 07:20:00', NULL, '4');
INSERT INTO `incidents` (`id`, `title`, `description`, `severity`, `status`, `created_at`, `resolved_at`, `assigned_to`) VALUES ('4', 'Backup failed - Disk full', 'Не хватило места на диске резервного сервера', 'critical', 'in_progress', '2025-10-24 02:15:00', NULL, '2');
INSERT INTO `incidents` (`id`, `title`, `description`, `severity`, `status`, `created_at`, `resolved_at`, `assigned_to`) VALUES ('5', 'User login error', 'Некоторые пользователи не могут войти', 'medium', 'resolved', '2025-10-22 15:10:00', '2025-10-22 15:45:00', '1');

CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` enum('info','warning','error','critical') DEFAULT 'info',
  `message` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `source` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `logs` (`id`, `event_type`, `message`, `ip_address`, `user_agent`, `created_at`, `source`) VALUES ('1', 'info', 'Сервер Prod-DB-01 запущен успешно', NULL, NULL, '2025-10-25 08:00:00', 'system');
INSERT INTO `logs` (`id`, `event_type`, `message`, `ip_address`, `user_agent`, `created_at`, `source`) VALUES ('2', 'warning', 'Высокая загрузка CPU на Web-Frontend-02 (85%)', NULL, NULL, '2025-10-25 08:30:00', 'monitoring');
INSERT INTO `logs` (`id`, `event_type`, `message`, `ip_address`, `user_agent`, `created_at`, `source`) VALUES ('3', 'error', 'Ошибка подключения к базе данных', NULL, NULL, '2025-10-25 09:15:00', 'application');
INSERT INTO `logs` (`id`, `event_type`, `message`, `ip_address`, `user_agent`, `created_at`, `source`) VALUES ('4', 'critical', 'Сбой резервного копирования на Backup-Server', NULL, NULL, '2025-10-24 02:15:00', 'backup_service');
INSERT INTO `logs` (`id`, `event_type`, `message`, `ip_address`, `user_agent`, `created_at`, `source`) VALUES ('5', 'info', 'Пользователь john_engineer вошёл в систему', NULL, NULL, '2025-10-25 09:05:00', 'auth');

CREATE TABLE `network_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ip_address` varchar(15) NOT NULL,
  `device_type` enum('router','switch','firewall','access_point') DEFAULT NULL,
  `status` enum('up','down','maintenance') DEFAULT 'up',
  `last_checked` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `network_devices` (`id`, `name`, `ip_address`, `device_type`, `status`, `last_checked`) VALUES ('1', 'Core-Switch-01', '192.168.1.1', 'switch', 'up', '2025-10-25 08:00:00');
INSERT INTO `network_devices` (`id`, `name`, `ip_address`, `device_type`, `status`, `last_checked`) VALUES ('2', 'Main-Router', '192.168.0.1', 'router', 'up', '2025-10-25 08:05:00');
INSERT INTO `network_devices` (`id`, `name`, `ip_address`, `device_type`, `status`, `last_checked`) VALUES ('3', 'Firewall-Primary', '192.168.0.254', 'firewall', 'maintenance', '2025-10-24 23:30:00');
INSERT INTO `network_devices` (`id`, `name`, `ip_address`, `device_type`, `status`, `last_checked`) VALUES ('4', 'AP-WiFi-01', '192.168.1.100', 'access_point', 'down', '2025-10-25 07:00:00');
INSERT INTO `network_devices` (`id`, `name`, `ip_address`, `device_type`, `status`, `last_checked`) VALUES ('5', 'Edge-Switch-02', '192.168.1.2', 'switch', 'up', '2025-10-25 08:10:00');

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('system','order','discount','update') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `item_type` enum('service','package') NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `purchased_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `purchases` (`id`, `user_id`, `item_type`, `service_id`, `package_id`, `price`, `purchased_at`) VALUES ('1', '31', 'service', '5', NULL, '5000.00', '2025-11-03 01:29:40');
INSERT INTO `purchases` (`id`, `user_id`, `item_type`, `service_id`, `package_id`, `price`, `purchased_at`) VALUES ('2', '31', 'package', NULL, '2', '20000.00', '2025-11-03 01:29:40');

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `referral_code` varchar(255) NOT NULL,
  `bonus_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`),
  KEY `referral_code` (`referral_code`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ip_address` varchar(15) NOT NULL,
  `os` varchar(100) DEFAULT NULL,
  `cpu_usage` decimal(5,2) DEFAULT NULL,
  `memory_usage` decimal(5,2) DEFAULT NULL,
  `disk_space` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `description` text DEFAULT NULL,
  `last_checked` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `servers` (`id`, `name`, `ip_address`, `os`, `cpu_usage`, `memory_usage`, `disk_space`, `status`, `description`, `last_checked`) VALUES ('4', 'Prod-DB-01', '192.168.1.10', 'Ubuntu 24.04 LTS', '42.30', '78.10', '512.00', 'active', NULL, '2025-10-25 09:00:00');
INSERT INTO `servers` (`id`, `name`, `ip_address`, `os`, `cpu_usage`, `memory_usage`, `disk_space`, `status`, `description`, `last_checked`) VALUES ('5', 'Web-Frontend-02', '192.168.1.20', 'CentOS Stream 9', '28.70', '55.40', '256.00', 'active', NULL, '2025-10-25 09:05:00');
INSERT INTO `servers` (`id`, `name`, `ip_address`, `os`, `cpu_usage`, `memory_usage`, `disk_space`, `status`, `description`, `last_checked`) VALUES ('6', 'Backup-Server', '192.168.1.30', 'Debian 12', '15.20', '33.80', '1024.00', 'maintenance', NULL, '2025-10-25 08:50:00');
INSERT INTO `servers` (`id`, `name`, `ip_address`, `os`, `cpu_usage`, `memory_usage`, `disk_space`, `status`, `description`, `last_checked`) VALUES ('7', 'Dev-App-01', '192.168.1.40', 'Windows Server 2022', '65.00', '89.50', '768.00', 'inactive', NULL, '2025-10-24 23:00:00');
INSERT INTO `servers` (`id`, `name`, `ip_address`, `os`, `cpu_usage`, `memory_usage`, `disk_space`, `status`, `description`, `last_checked`) VALUES ('8', 'Monitoring-Node', '192.168.1.50', 'Alpine Linux', '8.10', '22.30', '128.00', 'active', NULL, '2025-10-25 09:10:00');

CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('1', 'Прокладка сетевого кабеля (1 точка)', '1200', '', '7');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('2', 'Настройка Wi-Fi точки доступа', '2500', '', '8');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('3', 'Установка и настройка сервера (1 шт)', '8000', '', '6');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('4', 'Миграция базы данных (до 10 ГБ)', '10000', '', '0');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('5', 'Апгрейд сервера (RAM / SSD / CPU)', '5000', 'от', '0');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('6', 'Настройка резервного копирования', '7000', '', '0');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('7', 'Аудит безопасности (1 день)', '15000', '', '0');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('8', 'Восстановление данных после сбоя', '12000', 'от', '0');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('9', 'Управление серверами', '0', 'Мониторинг, резервное копирование, обновления и оптимизация производительности ваших серверов.', '1');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('10', 'Кибербезопасность', '0', 'Анализ уязвимостей, защита от атак, настройка межсетевых экранов и систем обнаружения вторжений.', '2');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('11', 'Сетевое оборудование', '0', 'Настройка маршрутизаторов, коммутаторов, точек доступа и диагностика сетевых проблем.', '3');
INSERT INTO `services` (`id`, `name`, `price`, `description`, `sort_order`) VALUES ('12', 'Базы данных', '0', 'Резервное копирование, восстановление, оптимизация запросов и управление правами доступа.', '4');

CREATE TABLE `session_timeouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(20) NOT NULL,
  `timeout_seconds` int(11) NOT NULL DEFAULT 900,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `session_timeouts` (`id`, `role`, `timeout_seconds`, `updated_at`) VALUES ('1', 'user', '86400', '2025-12-09 16:20:38');
INSERT INTO `session_timeouts` (`id`, `role`, `timeout_seconds`, `updated_at`) VALUES ('2', 'engineer', '900', '2025-12-25 19:33:31');
INSERT INTO `session_timeouts` (`id`, `role`, `timeout_seconds`, `updated_at`) VALUES ('3', 'manager', '3600', '2025-12-09 14:39:24');
INSERT INTO `session_timeouts` (`id`, `role`, `timeout_seconds`, `updated_at`) VALUES ('4', 'db_admin', '300', '2025-12-09 14:39:24');
INSERT INTO `session_timeouts` (`id`, `role`, `timeout_seconds`, `updated_at`) VALUES ('5', 'admin', '3840', '2025-12-09 16:19:22');

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `system_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime DEFAULT current_timestamp(),
  `cpu_load` decimal(5,2) DEFAULT NULL,
  `memory_usage` decimal(5,2) DEFAULT NULL,
  `disk_usage` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tariff_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `price` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `is_recommended` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `tariff_plans` (`id`, `name`, `slug`, `price`, `description`, `features`, `is_recommended`, `sort_order`) VALUES ('1', 'Базовый', 'basic', '12000', 'Для небольших проектов', 'Мониторинг до 3 серверов
Резервное копирование (ежедневно)
Поддержка по email (в течение 24 ч)
Обновление ПО 1 раз в месяц', '0', '1');
INSERT INTO `tariff_plans` (`id`, `name`, `slug`, `price`, `description`, `features`, `is_recommended`, `sort_order`) VALUES ('2', 'Стандартный', 'standard', '20000', 'Для среднего бизнеса', 'До 5 серверов
Резервное копирование + восстановление
Поддержка 5×8 (рабочие дни)
Базовая настройка безопасности
Управление 5 таблицами БД', '0', '2');
INSERT INTO `tariff_plans` (`id`, `name`, `slug`, `price`, `description`, `features`, `is_recommended`, `sort_order`) VALUES ('3', 'Профессиональный', 'professional', '35000', 'Полный контроль и поддержка', 'До 10 серверов
SLA 99.9%
Поддержка 24/7
Аудит безопасности 1 раз в квартал
Управление до 20 таблиц БД
Настройка сетевого оборудования (до 5 устройств)', '1', '3');
INSERT INTO `tariff_plans` (`id`, `name`, `slug`, `price`, `description`, `features`, `is_recommended`, `sort_order`) VALUES ('4', 'Бизнес', 'business', '60000', 'Для крупных компаний', 'До 20 серверов
Выделенный инженер
Ежемесячный отчёт по безопасности
Репликация и кластеризация БД
Настройка VLAN, QoS, firewall', '0', '4');

CREATE TABLE `test` (
  `test1` int(11) NOT NULL,
  `test2` int(11) NOT NULL,
  `test3` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `test` (`test1`, `test2`, `test3`) VALUES ('122', '11212', '121');

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `referral_code` varchar(255) DEFAULT NULL,
  `role` enum('admin','engineer','manager','user','db_admin','guest') DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp(),
  `password_created_at` datetime DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT 'imang/default.png',
  `last_verification_code_sent_at` datetime DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `referral_code` (`referral_code`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('29', 'Егор', '$2y$10$jcMXodq/vWi4lxoq.yqhHeKyDrdyDpF8SXSPy.cAVujs6LI2TZNTK', 'no-email@example.com', NULL, 'admin', '2025-10-25 23:46:49', '2025-10-25 23:46:49', 'imang/user_29_1761502186.jpg', NULL, '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('31', '123456', '$2y$10$HP7LKSDaTGSp5uMlGen.TO4Y9gY6rtv8tbl1tmLoZIdiyz/bT7alK', 'yegor.tkachenko.05@mail.ru', '31-e10adc39', 'user', '2025-10-27 09:44:55', '2025-10-27 09:44:55', 'imang/default.png', '2025-11-03 01:29:40', '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('32', 'engineer', '$2y$10$mRNxxFOQ7r1Nt/PP.iiDXexoWaVqjKFKdh6Q17WovDT/F9IsMDPe.', 'no-email@example.com', '', 'engineer', '2025-11-03 03:50:09', '2025-11-03 03:50:09', 'imang/default.png', '0000-00-00 00:00:00', '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('33', 'manager', '$2y$10$Y5kfuSxuY/IKxbxM3LbEnuEilfA3ZcrFwWcKjd78ieVwOz2Te1Ms.', 'no-email@example.com', NULL, 'manager', '2025-11-03 06:50:27', '2025-11-03 06:50:27', 'imang/default.png', NULL, '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('34', 'db_admi', '$2y$10$fduHVFY3Fgky0jCz9dlQhuwcUuqDNy/O0w2D7HZzR/xN.9EZ7svhi', 'no-email@example.com', NULL, 'db_admin', '2025-11-03 12:49:20', '2025-11-03 12:49:20', 'imang/default.png', NULL, '0', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('35', 'User', '$2y$10$BPsW42m0n5tQBgjGg6lX.euXBho2D9AunWQlMal/kJ4M7c0dVcBqK', 'no-email@example.com', NULL, 'guest', '2026-01-14 09:06:16', '2026-01-14 09:06:16', 'imang/default.png', NULL, '2', NULL, '0');
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `referral_code`, `role`, `created_at`, `password_created_at`, `avatar`, `last_verification_code_sent_at`, `failed_attempts`, `lock_until`, `is_blocked`) VALUES ('36', 'bd_admi', '$2y$10$bef921oF1rC0tktTqhQFXuwT4zwjx/anU22eHqbj5z8odq1kVTkxW', 'no-email@example.com', NULL, 'guest', '2026-03-05 00:41:47', '2026-03-05 00:41:47', 'imang/default.png', NULL, '3', '2026-03-04 18:52:39', '0');

SET FOREIGN_KEY_CHECKS = 1;
