import requests
import time
import ctypes
from ctypes import wintypes
import socket
import re
import psutil

# ================= 配置区域 =================
# 你的 api 完整地址
API_URL = "https://*******/api.php" 
# 你的 API 密钥
API_SECRET = "********" 
# 多少秒无操作视为"挂机"
IDLE_THRESHOLD = 300 
# 推送频率 (秒)
PUSH_INTERVAL = 1 
# 设备名称 (默认自动获取socket.gethostname() ，也可手动修改为 "My Laptop")
DEVICE_NAME = socket.gethostname()
# 隐私保护名单 (本地脱敏)
PRIVACY_MAP = {
    "Telegram.exe": "Telegram",
    "WeChat.exe": "WeChat",
    "Weixin.exe": "WeChat",
    "QQ.exe": "QQ",
    "TIM.exe": "TIM",
    "Discord.exe": "Discord",
    "DingTalk.exe": "DingTalk",
    "Lark.exe": "Lark"
}
# ===========================================
user32 = ctypes.windll.user32
kernel32 = ctypes.windll.kernel32
psapi = ctypes.windll.psapi
PROCESS_QUERY_INFORMATION = 0x0400
PROCESS_VM_READ = 0x0010

class LASTINPUTINFO(ctypes.Structure):
    _fields_ = [("cbSize", ctypes.c_uint), ("dwTime", ctypes.c_ulong)]

def get_idle_duration():
    """获取用户无操作时长 (秒)"""
    lii = LASTINPUTINFO()
    lii.cbSize = ctypes.sizeof(LASTINPUTINFO)
    if user32.GetLastInputInfo(ctypes.byref(lii)):
        millis = kernel32.GetTickCount() - lii.dwTime
        return millis / 1000.0
    return 0

def get_active_window_info():
    """获取当前窗口标题和进程名"""
    hwnd = user32.GetForegroundWindow()
    length = user32.GetWindowTextLengthW(hwnd)
    buff = ctypes.create_unicode_buffer(length + 1)
    user32.GetWindowTextW(hwnd, buff, length + 1)
    window_title = buff.value
    
    pid = ctypes.c_ulong()
    user32.GetWindowThreadProcessId(hwnd, ctypes.byref(pid))
    
    exe_name = ""
    try:
        h_process = kernel32.OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ, False, pid)
        if h_process:
            exe_buff = ctypes.create_unicode_buffer(1024)
            if psapi.GetModuleBaseNameW(h_process, 0, exe_buff, 1024):
                exe_name = exe_buff.value
            kernel32.CloseHandle(h_process)
    except Exception:
        pass
    return window_title, exe_name

def get_battery_info():
    try:
        battery = psutil.sensors_battery()
        if battery:
            plugged = "⚡" if battery.power_plugged else ""
            return f"[{int(battery.percent)}%{plugged}] "
        return ""
    except:
        return ""

def sanitize_title(text):
    if not text: return text
    if "phpMyAdmin" in text: return "Database Management"
    text = re.sub(r'\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?', '[Server]', text)
    return text

def guess_activity(app_name, exe_name):
    app_lower = app_name.lower()
    exe_lower = exe_name.lower()
    
    if not app_name: return "Idling"
    if "code" in exe_lower or "php" in exe_lower: return "Coding"
    if "chrome" in exe_lower or "edge" in exe_lower: return "Browsing"
    if "telegram" in exe_lower or "wechat" in exe_lower or "qq" in exe_lower: return "Chatting"
    if "steam" in exe_lower or "game" in exe_lower: return "Gaming"
    if "potplayer" in exe_lower or "vlc" in exe_lower: return "Watching"
    
    if any(x in app_lower for x in ["code", "php", "py", "studio", "git", "bash"]): return "Coding"
    if any(x in app_lower for x in ["game", "steam", "mc", "原神", "启动"]): return "Gaming"
    if any(x in app_lower for x in ["video", "player", "movie"]): return "Watching"
    
    return "Working"

def main():
    print(f"[*] Client Started on {DEVICE_NAME}")
    print(f"[*] Target: {API_URL}")
    
    headers = {
        "Authorization": f"Bearer {API_SECRET}",
        "Content-Type": "application/json"
    }

    while True:
        try:
            idle_seconds = get_idle_duration()
            is_sleeping = idle_seconds > IDLE_THRESHOLD
            battery_text = get_battery_info()
            
            if is_sleeping:
                app_name = "Away"
                details = f"{battery_text}Idle for {int(idle_seconds // 60)} mins"
                activity = "Sleeping"
            else:
                full_title, exe_name = get_active_window_info()
                
                if exe_name in PRIVACY_MAP:
                    app_name = PRIVACY_MAP[exe_name]
                    details = f"{battery_text}In a private chat"
                    activity = "Chatting"
                else:
                    if " - " in full_title:
                        parts = full_title.split(" - ")
                        app_name = parts[-1]
                        raw_details = " - ".join(parts[:-1])
                        clean_details = sanitize_title(raw_details)
                        if not clean_details.strip(): clean_details = sanitize_title(full_title)
                    else:
                        app_name = full_title
                        clean_details = sanitize_title(full_title)
                    
                    details = f"{battery_text}{clean_details}"
                    activity = guess_activity(app_name, exe_name)

            payload = {
                "is_sleeping": 1 if is_sleeping else 0,
                "activity_type": activity,
                "app_name": app_name,
                "details": details,
                "device_name": DEVICE_NAME
            }

            resp = requests.post(API_URL, json=payload, headers=headers, timeout=5)
            
            status_symbol = "OK" if resp.status_code == 200 else f"ERR {resp.status_code}"
            print(f"\r[{status_symbol}] {activity} | {app_name[:15]} - {details[:30]}", end="")

        except Exception as e:
            print(f"\r[Wait] Error: {str(e)[:20]}", end="")
            pass
        
        time.sleep(PUSH_INTERVAL)

if __name__ == "__main__":
    main()