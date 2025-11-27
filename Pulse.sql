/*
 Date: 2025-11-27
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. 实时状态表 (user_status)
-- 用于存储所有设备的当前状态
-- id: 用于固定排序
-- device_name: 唯一索引，防止重复插入
-- ----------------------------
DROP TABLE IF EXISTS `user_status`;
CREATE TABLE `user_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_name` varchar(100) NOT NULL,
  `is_sleeping` tinyint(1) DEFAULT 0,
  `activity_type` varchar(50) DEFAULT 'Idling',
  `app_name` varchar(100) DEFAULT '',
  `details` varchar(255) DEFAULT '',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_name` (`device_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- 2. 统计表 (stats_hourly)
-- 用于存储过去 24 小时的应用使用时长
-- ----------------------------
DROP TABLE IF EXISTS `stats_hourly`;
CREATE TABLE `stats_hourly` (
  `hour_key` datetime NOT NULL,
  `app_name` varchar(100) NOT NULL,
  `activity_type` varchar(50) DEFAULT 'Working',
  `duration_seconds` int(11) DEFAULT 0,
  PRIMARY KEY (`hour_key`, `app_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;