# 正式機部署：Laravel 12 + PHP 8.4

適用環境：Debian 11（arm64），PHP 由 packages.sury.org 安裝，直接在主機上執行（非 Docker）。

| 項目 | 部署前 | 部署後 |
|---|---|---|
| Laravel | 9（tag `pre-laravel-12`） | 12 |
| php-fpm | `php8.2-fpm` | `php8.4-fpm` |
| queue worker | Supervisor `/etc/supervisor/conf.d/bwcamp.conf`，`/usr/bin/php8.2 ... queue:listen` | 改為 `/usr/bin/php8.4` |
| 排程 | www-data 的 crontab，`/usr/bin/php8.2 artisan schedule:run` | 改為 `/usr/bin/php8.4` |
| `/usr/bin/php` | manual mode 指向 php8.2 | 不變（所有地方都寫明確版本） |

8.2 與 8.4 並存，退回時只要把 nginx、Supervisor、crontab 改回 8.2，並 checkout `pre-laravel-12`。

> 所有指令都在專案目錄 `/var/www/bwcamp` 執行。

---

## 一、部署前（可在上班時間進行，不影響網站）

### 1. 程式碼
- [ ] `fix-mail-config-per-camp`、`upgrade-laravel-12` 已合併進 master
- [ ] 本機已測：Google Sheets 匯入、網頁下載繳費單／分組名冊／報到通知單 PDF、event_admin 範圍內外

### 2. PHP 版本
```
sudo apt update
apt-cache policy php8.2-fpm php8.4-fpm | grep -E "^php|Installed"
```
- [ ] php8.4 為 **8.4.27 以上**（PHP2Shell 漏洞的修補版）；否則 `sudo apt install --only-upgrade 'php8.4*'`
- [ ] `diff <(php8.2 -m) <(php8.4 -m)` 沒有輸出（擴充套件相同）
- [ ] `update-alternatives --display php | head -3` 仍指向 php8.2

### 3. php8.4-fpm 設定與 8.2 一致
php8.4-fpm 目前是停用的。比對設定，把 8.2 改過的值搬到 8.4：
```
diff /etc/php/8.2/fpm/php.ini /etc/php/8.4/fpm/php.ini
diff /etc/php/8.2/fpm/pool.d/www.conf /etc/php/8.4/fpm/pool.d/www.conf
```
- [ ] `php.ini`：`memory_limit`、`upload_max_filesize`、`post_max_size`、`max_execution_time`、`date.timezone` 等
- [ ] `www.conf`：`user` / `group`（www-data）、`listen.owner` / `listen.group`、`pm.*`（`pm.max_children` 等）
- [ ] CLI 也比對：`diff /etc/php/8.2/cli/php.ini /etc/php/8.4/cli/php.ini`（worker 與排程用的是 CLI）

> 兩版的預設值本身就有差異，只要搬「8.2 特別改過的值」。

### 4. 找出要修改的設定檔位置（先記下來）
```
grep -rln "fastcgi_pass" /etc/nginx/          # nginx 站台設定檔
grep -rn "php8.2-fpm.sock" /etc/nginx/        # 目前的 socket 寫法
which composer                                 # composer 路徑，例如 /usr/local/bin/composer
stat -c %U /var/www/bwcamp/.git               # 平常用哪個使用者 git pull
```

---

## 二、部署（停機，請挑沒有營隊報名、沒有寄信的離峰時間）

避開排程時間：00:30–04:00（匯出／匯入）、16:30（`check:Accounting`）。

### 1. 備份
```
mysqldump -u <使用者> -p --single-transaction --routines <資料庫> | gzip > ~/backup_before_l12_$(date +%F).sql.gz
cp .env ~/.env.before_l12
git log --oneline -1                           # 記下目前的 commit
```

### 2. 進入維護模式、停止 worker
```
sudo -u www-data /usr/bin/php8.2 artisan down
sudo supervisorctl stop all
```
- 維護模式期間排程不會執行（Laravel 預設）。
- `supervisorctl stop` 最多會等 1 小時（`stopwaitsecs=3600`）讓正在跑的工作做完。若卡住，代表佇列中還有工作，等它完成。

### 3. 更新程式碼
用平常 `git pull` 的那個使用者執行：
```
git fetch origin
git checkout master
git pull
git log --oneline -1                           # 確認是合併升級後的 commit
```

### 4. 安裝套件（用 PHP 8.4）
```
php8.4 $(which composer) install --no-dev --optimize-autoloader
```
- 一定要用 `php8.4` 執行。若出現 PHP 版本不符的錯誤，代表用到了 8.2。
- 若 composer 要求登入 `satis.spatie.be`：升級後已不需要該套件庫，確認 `composer.json` 是最新版本。

### 5. 檔案權限
composer 會重新產生 `bootstrap/cache/*.php`，若用 root 執行，www-data 會無法覆寫：
```
sudo chown -R www-data:www-data storage bootstrap/cache
```

### 6. 清除快取、migration
```
sudo -u www-data /usr/bin/php8.4 artisan optimize:clear
sudo -u www-data /usr/bin/php8.4 artisan migrate --force
sudo -u www-data /usr/bin/php8.4 artisan view:cache
```
- **不要執行 `config:cache`、`route:cache`、`optimize`**：`routes/web.php` 直接呼叫 `env('THROTTLE_LIMIT')`，config 快取後 `env()` 會回傳 null，該組路由會出錯。
- 這次升級本身沒有新的 migration；若正式機還沒跑過 `user_camp_table_scopes`、`permission_templates`，會在這一步一起執行。

### 7. 網站切換到 php8.4-fpm
```
sudo systemctl enable --now php8.4-fpm
systemctl is-active php8.4-fpm                 # active
```
編輯步驟一-4 找到的 nginx 設定檔，把
```
fastcgi_pass unix:/run/php/php8.2-fpm.sock;
```
改為
```
fastcgi_pass unix:/run/php/php8.4-fpm.sock;
```
然後：
```
sudo nginx -t && sudo systemctl reload nginx
```

### 8. worker 與排程切換到 php8.4
```
sudo sed -i 's#/usr/bin/php8.2 /var/www#/usr/bin/php8.4 /var/www#' /etc/supervisor/conf.d/bwcamp.conf
grep ^command /etc/supervisor/conf.d/bwcamp.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start all
```
```
sudo crontab -u www-data -e
```
把 `/usr/bin/php8.2` 改為 `/usr/bin/php8.4`。

### 9. 離開維護模式
```
sudo -u www-data /usr/bin/php8.4 artisan up
```

---

## 三、部署後檢查

### 立即檢查
- [ ] `curl -sI https://<網域>/ | head -1` 回應 200 或 302
- [ ] 網頁上的 PHP 版本：後台任一頁正常即可；必要時暫放 `public/phpinfo.php`（`<?php phpinfo();`）確認是 8.4，**看完立即刪除**
- [ ] `ps aux | grep "queue:listen" | grep -v grep` 顯示 `/usr/bin/php8.4`
- [ ] `sudo -u www-data /usr/bin/php8.4 artisan schedule:list` 正常列出排程
- [ ] `tail -50 storage/logs/laravel.log` 沒有新的錯誤

### 功能檢查
- [ ] 登入後台；營隊列表、學員／義工名單、關懷員頁面
- [ ] 營隊管理：營隊、梯次、組織（superuser 與 event_admin）
- [ ] 匯出學員、義工 Excel
- [ ] 下載一份繳費單 PDF（中文字型正常）
- [ ] 寄一封信給自己（錄取信或自訂郵件），確認寄件人是該營隊的帳號
- [ ] 前台報名表可開啟、送出

### 隔天檢查
- [ ] 夜間排程（`export:Applicant` 等）有正常更新 Google Sheets
- [ ] `grep CRON /var/log/syslog | grep schedule | tail -3` 顯示 `(www-data)` 與 `php8.4`
- [ ] `storage/logs/` 沒有新的錯誤

---

## 四、退回（回到 Laravel 9 + PHP 8.2）

資料庫不需要還原（這次升級沒有修改資料表）。
```
sudo -u www-data /usr/bin/php8.4 artisan down
sudo supervisorctl stop all

git checkout pre-laravel-12
php8.2 $(which composer) install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data /usr/bin/php8.2 artisan optimize:clear
sudo -u www-data /usr/bin/php8.2 artisan view:cache
```
- nginx：`fastcgi_pass` 改回 `php8.2-fpm.sock`，`sudo nginx -t && sudo systemctl reload nginx`
- Supervisor：`bwcamp.conf` 改回 `/usr/bin/php8.2`，`sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all`
- crontab：`sudo crontab -u www-data -e` 改回 `/usr/bin/php8.2`
```
sudo -u www-data /usr/bin/php8.2 artisan up
```
問題修正後要再部署時，`git checkout master` 並重新執行「二、部署」。

---

## 五、穩定後（約 1～2 週）

- [ ] 停用 php8.2-fpm：`sudo systemctl stop php8.2-fpm && sudo systemctl disable php8.2-fpm`
- [ ] 再觀察一段時間後移除 8.2 與 7.4：`sudo apt remove 'php8.2*' 'php7.4*'`（先確認 `/usr/bin/php` 改指向 8.4：`sudo update-alternatives --set php /usr/bin/php8.4`）
- [ ] 解除 metapackage 鎖定：`sudo apt-mark unhold php-cli php-dev php-gd php-pear`
- [ ] 規劃作業系統升級：Debian 11 已無安全更新，建議以新主機（Debian 12/13）部署後切換 DNS

---

## 附註：已知但與升級無關的事項

- `php artisan route:list` 會失敗（`CampController` 建構子直接讀 `$request->route()`），只影響這個指令。
- `routes/web.php` 的 `env('THROTTLE_LIMIT')` 讓專案無法使用 `config:cache`；若要啟用 config 快取，需先把它移到 config 檔。
