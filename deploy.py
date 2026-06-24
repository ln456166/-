#!/usr/bin/env python3
import paramiko
import os
import sys

HOST = "47.115.225.217"
USER = "root"
PASS = "Long8023"
REMOTE_PATH = "/www/wwwroot/jerryhtom.cn/"

FILES_TO_UPLOAD = [
    "VideoReplace.php",
    "parsers/BaseParser.php",
    "replace.php",
]

LOCAL_PATH = "/workspace/"

def main():
    print(f"🔌 连接服务器 {HOST}...")
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=PASS, timeout=30)
    print("✅ SSH连接成功\n")

    sftp = ssh.open_sftp()

    # 1. 检查远程路径
    print(f"📂 检查远程目录: {REMOTE_PATH}")
    try:
        files = sftp.listdir(REMOTE_PATH)
        print(f"   目录存在，共 {len(files)} 个文件")
    except Exception as e:
        print(f"❌ 目录不存在: {e}")
        return

    # 2. 备份旧文件
    print("\n📦 备份旧文件...")
    backup_dir = REMOTE_PATH + "backup_" + str(int(__import__('time').time())) + "/"
    stdin, stdout, stderr = ssh.exec_command(f"mkdir -p {backup_dir}")
    stdout.channel.recv_exit_status()
    
    for f in FILES_TO_UPLOAD:
        remote_file = REMOTE_PATH + f
        backup_file = backup_dir + f.replace("/", "_")
        try:
            sftp.stat(remote_file)
            sftp.rename(remote_file, backup_file)
            print(f"   ✅ 已备份: {f}")
        except IOError:
            print(f"   ⚠️  文件不存在，跳过备份: {f}")

    # 3. 上传新文件
    print("\n📤 上传新文件...")
    for f in FILES_TO_UPLOAD:
        local_file = LOCAL_PATH + f
        remote_file = REMOTE_PATH + f
        
        # 确保远程目录存在
        remote_dir = os.path.dirname(remote_file)
        if remote_dir != REMOTE_PATH.rstrip('/'):
            stdin, stdout, stderr = ssh.exec_command(f"mkdir -p {remote_dir}")
            stdout.channel.recv_exit_status()
        
        sftp.put(local_file, remote_file)
        size = os.path.getsize(local_file)
        print(f"   ✅ 已上传: {f} ({size} bytes)")

    # 4. 设置权限
    print("\n🔐 设置文件权限...")
    for f in FILES_TO_UPLOAD:
        remote_file = REMOTE_PATH + f
        ssh.exec_command(f"chmod 644 {remote_file}")
    ssh.exec_command(f"chown -R www:www {REMOTE_PATH}")
    print("   ✅ 权限设置完成")

    # 5. 验证文件
    print("\n🔍 验证上传文件...")
    all_ok = True
    for f in FILES_TO_UPLOAD:
        remote_file = REMOTE_PATH + f
        try:
            stat = sftp.stat(remote_file)
            local_size = os.path.getsize(LOCAL_PATH + f)
            if stat.st_size == local_size:
                print(f"   ✅ {f}: 大小匹配 ({stat.st_size} bytes)")
            else:
                print(f"   ❌ {f}: 大小不匹配 (本地:{local_size}, 远程:{stat.st_size})")
                all_ok = False
        except Exception as e:
            print(f"   ❌ {f}: {e}")
            all_ok = False

    sftp.close()
    ssh.close()

    print("\n" + "="*50)
    if all_ok:
        print("🎉 所有文件上传成功！")
    else:
        print("⚠️  部分文件上传有问题")
    print("="*50)

if __name__ == "__main__":
    main()
