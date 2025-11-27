/*
Date: 2025-11-27
*/

// ================= 配置区域 =================
const CONFIG = {
    // 你的 api.php 完整地址
    API_URL: 'https://*******/api.php', 
    
    // 必须和 config.php 里的 API_SECRET 一致
    API_SECRET: '********', 
    
    // 设备名称
    DEVICE_NAME: 'APhone', 
    
    // 屏幕亮着时的推送间隔 (毫秒)
    INTERVAL_ACTIVE: 1000, 
    
    // 屏幕关闭时的推送间隔 (毫秒，省电模式)
    INTERVAL_IDLE: 10000 
};
// ===========================================

auto.waitFor(); // 必须开启无障碍服务才能获取当前应用名

// 简单的活动类型猜测
function guessActivity(appName) {
    if (!appName) return "Mobile Usage";
    const lower = appName.toLowerCase();
    
    if (lower.includes("微信") || lower.includes("wechat") || lower.includes("qq") || lower.includes("telegram") || lower.includes("discord")) return "Chatting";
    if (lower.includes("抖音") || lower.includes("youtube") || lower.includes("bilibili") || lower.includes("视频")) return "Watching";
    if (lower.includes("chrome") || lower.includes("浏览器") || lower.includes("edge") || lower.includes("via")) return "Browsing";
    if (lower.includes("音乐") || lower.includes("网易云") || lower.includes("spotify")) return "Listening";
    if (lower.includes("游戏") || lower.includes("原神") || lower.includes("王者") || lower.includes("game")) return "Gaming";
    if (lower.includes("相机") || lower.includes("相册")) return "Photos";
    
    return "Mobile Usage";
}

function getStatus() {
    let isScreenOn = device.isScreenOn();
    let battery = device.getBattery();
    let isCharging = device.isCharging();
    let chargeText = isCharging ? "⚡" : "";
    
    // 默认状态 (黑屏/睡觉)
    let payload = {
        "device_name": CONFIG.DEVICE_NAME,
        "is_sleeping": 1,
        "activity_type": "Sleeping",
        "app_name": "Away",
        "details": `Screen Off (Bat: ${battery}%${chargeText})`
    };

    if (isScreenOn) {
        // 如果屏幕亮着
        let packageName = currentPackage();
        let appName = getAppName(packageName); // 获取应用名称
        
        // 如果获取不到名称，就用包名
        if (!appName) appName = packageName;
        // 过滤掉桌面启动器 (通常不视为在运行什么软件)
        if (appName.includes("桌面") || appName.includes("Launcher") || appName.includes("UI")) {
             appName = "Home Screen";
        }

        let activity = guessActivity(appName);

        payload = {
            "device_name": CONFIG.DEVICE_NAME,
            "is_sleeping": 0,
            "activity_type": activity,
            "app_name": appName,
            "details": `Battery: ${battery}%${chargeText}`
        };
    }

    return payload;
}

function sendHeartbeat() {
    let data = getStatus();
    let isScreenOn = (data.is_sleeping === 0);

    // 打印日志
    console.log(`[${CONFIG.DEVICE_NAME}] ${data.activity_type} | ${data.app_name}`);

    try {
        let res = http.postJson(CONFIG.API_URL, data, {
            headers: {
                "Authorization": "Bearer " + CONFIG.API_SECRET
            },
            timeout: 5000 // 5秒超时
        });

        if (res.statusCode !== 200) {
            console.error("Server Error: " + res.body.string());
        }
    } catch (e) {
        console.error("Connection Failed: " + e.message);
    }

    return isScreenOn;
}

// === 主循环 ===
console.show(); // 显示控制台 (调试用，稳定后可以注释掉)
console.log("Client Started...");

while (true) {
    let active = false;
    try {
        active = sendHeartbeat();
    } catch (e) {
        console.error(e);
    }

    // 根据屏幕状态决定休息多久
    let sleepTime = active ? CONFIG.INTERVAL_ACTIVE : CONFIG.INTERVAL_IDLE;
    sleep(sleepTime);
}