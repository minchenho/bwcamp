# bwcamp 營隊管理系統：系統規格說明書 (System Spec)

## 1. 專案概述 (Project Overview)
`bwcamp` 是一個高度客製化的營隊管理系統，旨在處理大規模營隊（如大專營、教師營、義工營等）的完整生命週期：從報名、審核、分組、後勤（住行）到現場報到與統計。

## 2. 使用者角色 (User Roles)
系統透過 **Laratrust** 實作細粒度的權限管理，主要角色包括：
*   **系統管理員 (Super Admin):** 負責全系統設定、營隊與梯次的新增、權限分配及系統日誌監控。
*   **營隊行政人員 (Camp Manager):** 負責特定營隊的學員審核、名單匯入/匯出、郵件發送。
*   **小組關懷員 (Carer/Volunteer):** 負責特定組別學員的聯繫、紀錄與基本資訊瀏覽。
*   **現場報到人員 (Check-in Staff):** 負責營隊當天的 QR Code 掃描與報到狀態更改。
*   **一般報名者 (Applicant):** 外部使用者，進行營隊報名、查詢進度及下載通知單。

## 3. 系統功能模組 (Functional Modules)

### A. 報名與前端管理 (Registration & Front-end)
*   **多樣化報名表單:** 針對不同營隊（如教師營、企業營）顯示不同的欄位與邏輯。
*   **報名進度查詢:** 提供身分證字號或報名序號查詢錄取狀態。
*   **繳費管理:** 顯示繳費資訊，並提供現場手動銷帳或虛擬帳號對帳。
*   **文件下載:** 自動產生報到通知單（PDF）與個人報到 QR Code。

### B. 營隊基礎建設 (Camp Infrastructure)
*   **營隊與梯次設定:** 定義營隊的基本資訊、活動日期、報名起訖日及存取期限。
*   **組織架構 (Camp Org):** 可自訂營隊的層級（如：區、大隊、中隊、小組），支援動態分組。
*   **自訂統計連結:** 整合 Google Sheets，允許管理員在後台直接嵌入外部試算表統計。

### C. 學員審核與處理 (Applicant Processing)
*   **報名審核 (Admission):**
    *   手動錄取、備取設定。
    *   批次錄取處理。
*   **分組與編號:**
    *   自動或手動分配組別。
    *   產生錄取序號（Admitted SN）。
*   **名單操作:** 支援學員資料的批次匯入（Excel）與多種維度（學員/義工）的匯出。

### D. 後勤與住行管理 (Logistics)
*   **住宿管理 (Lodging):** 分配房間、床位，記錄特殊住宿需求（如：早到、晚走）。
*   **交通管理 (Traffic):** 管理接駁車接送地點、車次及乘車紀錄。
*   **聯絡日誌 (Contact Logs):** 紀錄關懷員與學員的互動過程，支援標籤分類。

### E. 現場營隊作業 (On-site Operations)
*   **QR Code 報到:** 支援行動裝置掃描 QR Code 進行即時報到。
*   **批次報到 (Mass Check-in):** 提供管理員介面快速勾選多人報到。
*   **即時統計:** 儀表板顯示當前各梯次的到報率與性別/區域分佈統計。

## 4. 關鍵技術架構 (Technical Architecture)

### A. 資料架構 (Data Architecture)
*   **多型關聯:** `Applicant` 模型作為核心，透過與具體的營隊模型（如 `Acamp`, `Tcamp`）進行一對一關聯來擴充特定欄位。
*   **資源導向權限:** 透過 `canAccessResource` 實作「某使用者是否能對某學員執行某動作」的動態權限檢查。

### B. 動態配置 (Dynamic Configuration)
*   **多 SMTP 設定:** 透過 `EmailConfiguration` Trait，根據目前處理的營隊動態切換 `mail.mailers.smtp` 的主機、帳號與密碼，支援 AWS SES 整合。
*   **核心類別覆寫:** 覆寫 `Illuminate\Queue\Jobs\Job` 以確保非同步工作（Queues）在發送郵件時也能繼承正確的營隊環境。

## 5. 非功能性需求 (Non-functional Requirements)
*   **安全性:** 使用 AWS Cognito 整合身份驗證（部分功能）；敏感操作受限於嚴格的頻率限制（Throttle）。
*   **擴展性:** 系統透過 Service Pattern（如 `ApplicantService`, `BackendService`）將商業邏輯與控制器分離。
*   **效能優化需求:** 現有系統在大數據量下需依賴 `batchCanAccessResources` 來緩解 N+1 查詢問題；需整合 `laravel-model-caching` 以減少資料庫讀取。

## 6. 整合介面 (Integrations)
*   **Google Sheets:** 用於動態統計資料同步。
*   **PDF/Excel Engine:** 用於報單產生與報名資料交換。
*   **Sentry/Telescope:** 用於錯誤追蹤與效能監控。
