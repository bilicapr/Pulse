<?php
// config.php

// 数据库配置
define('DB_HOST', 'xxxxxxx');
define('DB_NAME', 'sleepy');
define('DB_USER', 'sleepy');
define('DB_PASS', 'sleepy');

// API 通信密钥
define('API_SECRET', 'xxxxxxxx');

// 网页前端刷新频率 (毫秒)
define('REFRESH_INTERVAL', 1000); 

// 你的名字或网站名称
define('SITE_NAME', 'XXXXX Status');

// [新增] 最大显示字数 (超过这个长度会显示 "...")
define('MAX_TEXT_LENGTH', 50);

// 连接数据库
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("数据库连接错误"); 
}