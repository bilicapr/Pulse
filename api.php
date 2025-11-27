<?php
require 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 黑名单配置 (支持模糊匹配)
$BLOCK_LIST = [
    '任务切换', 
    'Task Switching',
    '截图',
    'Snipping Tool',
    'SearchUI',
    'TextInputHost',
    'Overlay',
    'NVIDIA',
    'Volume Control',
    '系统设置',
    '系统管家服务',
    '负一屏',
    '系统托盘溢出窗口',
    '近期的下载记录'

];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = trim(str_replace('Bearer ', '', $auth_header));
    // 1. 验证密钥
    if ($token !== API_SECRET) {
        http_response_code(403); echo json_encode(['error' => 'Invalid Key']); exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { http_response_code(400); exit; }

    $is_sleeping = $input['is_sleeping'] ?? 0;
    $activity_type = $input['activity_type'] ?? 'Idling';
    $app_name = $input['app_name'] ?? '';
    $details = $input['details'] ?? '';
    $device_name = $input['device_name'] ?? 'Unknown Device';
    // 2. 黑名单过滤
    foreach ($BLOCK_LIST as $keyword) {
        if (stripos($app_name, $keyword) !== false || stripos($details, $keyword) !== false) {
            echo json_encode(['status' => 'ignored', 'reason' => "Blocked keyword: $keyword"]);
            exit; 
        }
    }

    // 3. 更新状态 (实时展示)
    $sql = "INSERT INTO user_status (device_name, is_sleeping, activity_type, app_name, details, last_updated)
            VALUES (:dev, :s, :t, :a, :d, NOW())
            ON DUPLICATE KEY UPDATE 
            is_sleeping = VALUES(is_sleeping),
            activity_type = VALUES(activity_type),
            app_name = VALUES(app_name),
            details = VALUES(details),
            last_updated = NOW()";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':dev' => $device_name,
            ':s' => $is_sleeping,
            ':t' => $activity_type,
            ':a' => $app_name,
            ':d' => $details
        ]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => 'DB Error']); exit;
    }

    // 4. 更新统计
    $should_record = true;
    $stats_name = $app_name; 

    if (strpos($app_name, '[') === 0) {
        if (!empty($details) && $details !== 'Zzz...') {
            $stats_name = $details;
        } else {
            $should_record = false; 
        }
    }

    if (strpos($app_name, '熄屏') !== false) {
        $should_record = false;
    }

    if (!$is_sleeping && $should_record && !empty($stats_name)) {
        $currentHour = date('Y-m-d H:00:00');
        $statSql = "INSERT INTO stats_hourly (hour_key, app_name, activity_type, duration_seconds) 
                    VALUES (:hour, :app, :type, 1) 
                    ON DUPLICATE KEY UPDATE duration_seconds = duration_seconds + 1, activity_type = VALUES(activity_type)";
        
        $pdo->prepare($statSql)->execute([
            ':hour' => $currentHour, 
            ':app' => $stats_name, 
            ':type' => $activity_type
        ]);
        
        if (rand(1, 100) === 1) $pdo->query("DELETE FROM stats_hourly WHERE hour_key < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    }

    echo json_encode(['status' => 'success']);

} else {
    try {
        $stmt = $pdo->query("SELECT * FROM user_status ORDER BY id ASC");
        $devices = $stmt->fetchAll();

        foreach ($devices as &$dev) {
            $dev['seconds_ago'] = time() - strtotime($dev['last_updated']);
        }

        $statsSql = "SELECT app_name, activity_type, SUM(duration_seconds) as total_seconds 
                     FROM stats_hourly 
                     WHERE hour_key >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                     GROUP BY app_name ORDER BY total_seconds DESC LIMIT 10";
        $stats = $pdo->query($statsSql)->fetchAll();

        $totalActive = 0;
        $formattedStats = [];
        foreach ($stats as $row) $totalActive += $row['total_seconds'];
        
        foreach ($stats as $row) {
            $seconds = $row['total_seconds'];
            $percent = $totalActive > 0 ? round(($seconds / $totalActive) * 100, 1) : 0;
            $h = floor($seconds / 3600); $m = floor(($seconds % 3600) / 60);
            $formattedStats[] = [
                'name' => $row['app_name'],
                'type' => $row['activity_type'],
                'percent' => $percent,
                'time_str' => $h > 0 ? "{$h}h {$m}m" : "{$m}m"
            ];
        }

        $th = floor($totalActive / 3600); $tm = floor(($totalActive % 3600) / 60);
        
        echo json_encode([
            'devices' => $devices,
            'daily_stats' => $formattedStats,
            'total_active_time' => $th > 0 ? "{$th}h {$tm}m" : "{$tm}m"
        ]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => 'DB Error']);
    }
}
?>
