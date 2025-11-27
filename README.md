# Pulse

**Pulse** 是一个极简、实时的多设备状态监控系统。它能将你当前的活动状态（如正在写代码、玩游戏、听音乐或休息）实时同步到 Web 端展示，并生成可视化的 SVG 状态卡片，方便嵌入 GitHub 个人主页。

> **Status**: 🟢 Alive | 📡 Real-time | 🛡️ Privacy-First

<img width="1675" height="922" alt="image" src="https://github.com/user-attachments/assets/7402e77c-da7c-4288-97a5-3d932e1358d7" />

## ✨ 功能亮点 (Features)

* **📱 多端同步 (Multi-Device)**：支持同时监控 Windows PC 和 Android 手机，自动在网页端以响应式网格排列展示。
* **⚡ 实时心跳 (Real-time)**：基于毫秒级的心跳机制，实现 Web 端状态的秒级无感刷新（防抖动设计）。
* **🧠 智能状态判定**：
    * **自动识别**：根据应用名称自动归类活动（Coding, Gaming, Watching, etc.）。
    * **离线检测**：区分“熄屏/挂机”（紫色）与“断网/离线”（灰色）。
    * **断连提示**：精准识别设备是否因网络波动导致的心跳丢失 (连接中断)。
* **🔋 硬件信息集成**：实时显示笔记本和手机的电量及充电状态。
* **🛡️ 隐私与脱敏**：
    * **黑名单机制**：服务端自动拦截“任务切换”、“搜索框”等无效状态。
    * **敏感信息清洗**：自动隐藏浏览器标题中的 IP 地址、私有路径，将敏感应用（如 TG/微信）替换为通用描述。
* **📊 24小时统计**：自动记录并计算过去 24 小时内的应用使用占比，生成可视化进度条。
* **🎨 动态 SVG 卡片**：提供 `card.php` 接口，生成可嵌入 GitHub Profile 的实时状态徽章。

## 🛠️ 技术栈 (Tech Stack)

* **服务端 (Server)**: Native PHP 7.4+, MariaDB / MySQL
* **前端 (Web)**: HTML5, CSS3 (Grid/Flexbox), Vanilla JS, [Pico.css](https://picocss.com/)
* **客户端 (Clients)**:
    * **Windows**: Python (`requests`, `psutil`, `ctypes`)
    * **Android**: AutoX.js

## 🚀 部署指南 (Deployment)

### 1. 服务端设置 (Server)

1.  **导入数据库**：
    将 `db.sql` 文件导入到你的 MariaDB/MySQL 数据库中。
    ```sql
    -- 确保你的表结构包含 id, device_name 等字段 (见 db.sql)
    ```

2.  **配置环境**：
    修改 `config.php` 文件，填入数据库信息和 API 密钥。
    ```php
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', 'your_password');
    define('API_SECRET', 'your_secure_key'); // 务必修改此密钥
    ```

3.  **上传文件**：
    将所有 PHP、CSS 文件上传到你的 Web 服务器目录。

### 2. Windows 客户端

1.  安装依赖：
    ```bash
    pip install requests psutil
    ```
2.  编辑 `sleepy.py`：
    * 修改 `API_URL` 为你的服务器地址。
    * 修改 `API_SECRET` 与服务端一致。
3.  运行脚本：
    ```bash
    python sleepy.py
    # 建议使用 pythonw sleepy.py 后台运行
    ```

### 3. Android 客户端

1.  在手机上安装 **AutoX.js** (或 Auto.js Pro)。
2.  新建脚本，将 `autoxjs_client.js` 的内容复制进去。
3.  修改脚本顶部的配置 (`API_URL`, `API_SECRET`, `DEVICE_NAME`)。
4.  开启 **无障碍服务** 权限并运行脚本。

## ⚙️ 配置与自定义 (Configuration)

* **修改网页标题**：在 `config.php` 中修改 `SITE_NAME`。
* **屏蔽应用**：在 `api.php` 顶部的 `$BLOCK_LIST` 数组中添加你想屏蔽的关键词（如 "任务切换", "Overlay"）。
* **隐私保护**：在 `sleepy.py` (Windows) 或 `autoxjs_client.js` (Android) 中的 `PRIVACY_MAP` 修改敏感应用的显示名称。

## 🖼️ 状态徽章 (Status Badge)

你可以将实时的状态卡片嵌入到你的 GitHub README 或个人博客中。

**双设备卡片 (推荐):**
```markdown
[![My Status](https://sleepy.0112520.xyz/card.php?device=MyPC,APhone)](https://sleepy.0112520.xyz/)
