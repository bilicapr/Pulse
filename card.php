<?php
require 'config.php';

// === 1. 设置 Header ===
header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// === 2. 解析 URL 参数 ===
$targets = [];
if (isset($_GET['device']) && !empty($_GET['device'])) {
    $targets = explode(',', $_GET['device']);
    $targets = array_map('trim', $targets); // 去除空格
}

// === 3. 状态计算函数 ===
function calculateStatus($data, $devName) {
    // 设备未找到,默认离线
    if (!$data) {
        return [
            'name' => htmlspecialchars($devName), 
            'color' => '#64748b', 
            'text' => 'Device Not Found'
        ];
    }

    $secondsAgo = time() - strtotime($data['last_updated']);
    $isSleeping = $data['is_sleeping'] == 1;
    $isDead = $secondsAgo > 3600;       
    $isDisconnected = $secondsAgo > 60; 

    $color = "#10b981";
    $statusText = "";
    $appName = $data['app_name'] ?? '';
    $details = $data['details'] ?? '';

    // 状态逻辑
    if ($isDead) {
        $color = "#64748b";
        $statusText = "未知使用";
    } elseif ($isDisconnected) {
        $color = "#64748b"; 
        if ($isSleeping) {
            $tag = (strpos($appName, '熄屏') !== false) ? "熄屏" : "挂机";
            $prefix = "";
            if (strpos($appName, '[') === 0) $prefix = explode(']', $appName)[0] . '] ';
            $statusText = "{$prefix}[{$tag}] Zzz...(无信号)";
        } else {
            $statusText = "连接中断";
        }
    } else {
        if (strpos($appName, '[') === 0 || strpos($appName, '熄屏') !== false) {
            $color = (strpos($appName, '熄屏') !== false) ? "#a78bfa" : "#10b981";
            $statusText = $appName . " " . $details;
        } elseif ($isSleeping) {
            $color = "#a78bfa"; 
            if (preg_match('/^(\[.*?\])\s*(.*)/', $details, $matches)) {
                $statusText = "{$matches[1]} [挂机] " . ($matches[2] ?: "Away");
            } else {
                $statusText = "[挂机] " . ($details ?: "Away");
            }
        } else {
            $color = "#10b981"; 
            if (preg_match('/^(\[.*?\])\s*(.*)/', $details, $matches)) {
                $prefix = $matches[1];
                $realDetail = $matches[2];
                $statusText = ($realDetail && $realDetail !== $appName) ? "$prefix $appName - $realDetail" : "$prefix $appName";
            } else {
                $statusText = ($details && $details !== $appName) ? "$appName - $details" : $appName;
            }
        }
    }

    return [
        'name' => htmlspecialchars($data['device_name']),
        'color' => $color,
        'text' => htmlspecialchars(mb_strimwidth($statusText, 0, 45, "...", "UTF-8"))
    ];
}

// === 4. 数据库查询 ===
try {
    if (!empty($targets)) {
        // 有指定参数，按参数顺序查询
        $placeholders = str_repeat('?,', count($targets) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM user_status WHERE device_name IN ($placeholders)");
        $stmt->execute($targets);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 重建关联数组，便于按参数顺序访问
        $dbData = [];
        foreach($rows as $row) {
            $dbData[$row['device_name']] = $row;
        }
        
        // 按参数顺序生成最终列表
        $finalList = [];
        foreach($targets as $t) {
            // 如果数据库中没有该设备，则传入 null
            $finalList[] = calculateStatus($dbData[$t] ?? null, $t);
        }
        
    } else {
        // 无参数，默认查询前两个设备
        $stmt = $pdo->query("SELECT * FROM user_status ORDER BY id ASC LIMIT 2");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $finalList = [];
        foreach($rows as $row) {
            $finalList[] = calculateStatus($row, $row['device_name']);
        }
    }
} catch (Exception $e) {
    die('<svg width="200" height="50" xmlns="http://www.w3.org/2000/svg"><text x="10" y="30" fill="red">DB Error</text></svg>');
}

// === 5. 计算 SVG 高度 ===
$rowHeight = 65; // 每行高度
$paddingTop = 5; // 顶部内边距
$count = count($finalList); // 行数
$totalHeight = ($count * $rowHeight) + 10; // 总高度
$bgHeight = $totalHeight - 2; // 背景高度

// === 6. 输出 SVG 头部 ===
echo <<<SVG_HEADER
<svg width="350" height="{$totalHeight}" viewBox="0 0 350 {$totalHeight}" xmlns="http://www.w3.org/2000/svg">
    <style>
        .bg { fill: #1e293b; stroke: #334155; stroke-width: 1px; }
        .text-name { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-weight: bold; font-size: 15px; fill: #f8fafc; }
        .text-status { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 13px; fill: #94a3b8; }
        .separator { stroke: #334155; stroke-width: 1px; stroke-dasharray: 4 2; opacity: 0.5; }
    </style>
    
    <rect x="1" y="1" width="348" height="{$bgHeight}" rx="12" class="bg" />

SVG_HEADER;

// === 7. 输出每个设备的状态 ===
foreach ($finalList as $index => $dev) {
    $yOffset = $paddingTop + ($index * $rowHeight);
    // 计算位置
    $cy = $yOffset + 20;
    $nameY = $yOffset + 25;
    $textY = $yOffset + 48;
    
    // 分隔线
    $lineSvg = "";
    if ($index < $count - 1) {
        $lineY = $yOffset + 60;
        $lineSvg = "<line x1='15' y1='{$lineY}' x2='335' y2='{$lineY}' class='separator' />";
    }

    echo <<<SVG_ITEM
    <g>
        <circle cx="25" cy="{$cy}" r="5" fill="{$dev['color']}">
             <animate attributeName="opacity" values="1;0.6;1" dur="2s" repeatCount="indefinite" begin="{$index}s" />
        </circle>
        
        <text x="40" y="{$nameY}" class="text-name">{$dev['name']}</text>
        <text x="25" y="{$textY}" class="text-status">{$dev['text']}</text>
        
        {$lineSvg}
    </g>
SVG_ITEM;
}

echo "</svg>";
?>