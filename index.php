<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('SITE_NAME') ? SITE_NAME : 'Device Status'; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="overlay" onclick="toggleStats()"></div>

<div class="big-box">
    <div class="header-title"><?php echo defined('SITE_NAME') ? SITE_NAME : 'Device Status'; ?></div>
    
    <div id="device-list">
        <div style="color:#64748b">Connecting...</div>
    </div>

    <div class="footer" id="last-updated-time">--</div>
</div>

<button class="fab-btn" onclick="toggleStats()">📊</button>

<div class="stats-panel" id="ui-stats-panel">
    <div class="stats-header">24h Focus</div>
    <div class="stats-sub" id="ui-total-time">Active: 0h 0m</div>
    <div id="ui-stats-list" style="overflow-y: auto; flex: 1;"></div>
    <button class="btn-close" onclick="toggleStats()">Close Panel</button>
</div>

<script>
    const API_URL = 'api.php';
    const REFRESH_MS = <?php echo REFRESH_INTERVAL; ?>;
    const MAX_LEN = <?php echo MAX_TEXT_LENGTH; ?>;

    function toggleStats() {
        document.getElementById('ui-stats-panel').classList.toggle('active');
        document.querySelector('.overlay').classList.toggle('active');
    }

    function updateStatus() {
        fetch(API_URL).then(r => r.json()).then(render).catch(console.error);
    }

    function truncate(str, n) {
        if (!str) return "";
        return (str.length > n) ? str.slice(0, n-1) + '...' : str;
    }

    function render(data) {
        const list = document.getElementById('device-list');
        const footerTime = document.getElementById('last-updated-time');
        const devices = data.devices || [];

        const now = new Date();
        footerTime.innerText = "Updated: " + now.toLocaleString('zh-CN', {hour12: false});

        if (devices.length === 0) {
            list.innerHTML = '<div style="color:#64748b">No devices online.</div>';
            return;
        }

        let html = '';
        devices.forEach(dev => {
            const secondsAgo = parseInt(dev.seconds_ago);
            const isSleeping = dev.is_sleeping == 1;
            
            const isDead = secondsAgo > 3600;      // 1小时 -> 未知使用
            const isDisconnected = secondsAgo > 60;// 60秒 -> 连接中断

            let cssClass = "status-online";
            let text = "";
            
            // 1. 彻底离线 (>1小时)
            if (isDead) {
                cssClass = "status-unknown";
                text = "不知道在干什么"; 
            } 
            // 2. 暂时断连 (>60秒) [核心修改]
            else if (isDisconnected) {
                cssClass = "status-unknown"; // 变灰
                
                // 如果是挂机/熄屏状态断开，保留 "挂机/熄屏" 提示，因为这不算"打开的软件"
                if (isSleeping) {
                    let tag = (dev.app_name && dev.app_name.includes('熄屏')) ? "熄屏" : "挂机";
                    // 尝试保留电量前缀 (如果 app_name 是 [80%])
                    let prefix = "";
                    if (dev.app_name && dev.app_name.startsWith('[')) {
                         prefix = dev.app_name.split(']')[0] + "] ";
                    }
                    text = `${prefix}[${tag}] (无信号)`;
                } 
                // 如果是活跃状态断开，直接屏蔽内容，只显示连接中断
                else {
                    text = "无网络(连接中断)";
                }
            } 
            // 3. 正常在线 (<60秒)
            else {
                let appName = dev.app_name || "";
                let details = dev.details || "";
                let displayContent = "";

                // === 手机端逻辑 ===
                if (appName.startsWith('[') || appName.includes('熄屏')) {
                    cssClass = appName.includes('熄屏') ? "status-sleep" : "status-online";
                    displayContent = appName + " " + details;
                } 
                // === 电脑端逻辑 ===
                else {
                    if (isSleeping) {
                        cssClass = "status-sleep";
                        const match = details.match(/^(\[.*?\])\s*(.*)/);
                        if (match) {
                            displayContent = `${match[1]} [挂机] ${match[2] || "Away"}`;
                        } else {
                            displayContent = `[挂机] ${details || "Away"}`;
                        }
                    } else {
                        cssClass = "status-online";
                        const batMatch = details.match(/^(\[.*?\])\s*(.*)/);
                        if (batMatch) {
                            const batPrefix = batMatch[1];
                            const realDetails = batMatch[2];
                            if (realDetails && realDetails !== appName) {
                                displayContent = `${batPrefix} ${appName} - ${realDetails}`;
                            } else {
                                displayContent = `${batPrefix} ${appName}`;
                            }
                        } else {
                            if (details && details !== appName && details.trim() !== "") {
                                displayContent = `${appName} - ${details}`;
                            } else {
                                displayContent = appName;
                            }
                        }
                    }
                }
                
                // 只有在线时才执行截断
                text = truncate(displayContent, MAX_LEN);
            }

            html += buildRow(dev.device_name, cssClass, text);
        });

        if (list.innerHTML !== html) list.innerHTML = html;
        renderStats(data);
    }

    function buildRow(name, cssClass, text) {
        return `
            <div class="device-row">
                <div class="dev-name">${name}:</div>
                <div class="dev-status ${cssClass}">${text}</div>
            </div>
        `;
    }

    function renderStats(data) {
        const statsList = document.getElementById('ui-stats-list');
        const statsData = data.daily_stats || [];
        document.getElementById('ui-total-time').innerText = "Active: " + (data.total_active_time || "0m");

        let statsHtml = '';
        if (statsData.length > 0) {
            statsData.forEach(item => {
                let barColor = '#a78bfa'; 
                const type = item.type.toLowerCase();
                if (type.includes('cod')) barColor = '#38bdf8';
                else if (type.includes('game')) barColor = '#f87171';
                else if (type.includes('chat')) barColor = '#4ade80';
                else if (type.includes('mobile')) barColor = '#14b8a6';

                statsHtml += `
                    <div class="stats-item">
                        <div class="stats-info">
                            <span>${item.name}</span>
                            <span class="stats-percent">${item.time_str} (${item.percent}%)</span>
                        </div>
                        <div class="progress-bg">
                            <div class="progress-bar" style="width: ${item.percent}%; background-color: ${barColor}"></div>
                        </div>
                    </div>
                `;
            });
        } else {
            statsHtml = '<div style="color:#64748b; text-align:center; margin-top:50px;">No data yet</div>';
        }
        if (statsList.innerHTML !== statsHtml) statsList.innerHTML = statsHtml;
    }

    updateStatus();
    setInterval(updateStatus, REFRESH_MS);
</script>
<div class="page-bottom">
    <strong>By bilicapr</strong>
</div>
</body>
</html>