# 專案分析報告：bwcamp 營隊管理系統

## 1. 技術棧 (Technology Stack)
*   **後端框架:** Laravel 9.42 (PHP 8.0+)
*   **前端工具:** Vite, Vue 3, Bootstrap 4, jQuery
*   **資料庫與快取:** 使用 Doctrine DBAL, Redis (Predis)
*   **核心套件:**
    *   **權限管理:** Laratrust (實作多角色的權限控管)
    *   **動態介面:** Livewire
    *   **文件處理:** DomPDF (產生 PDF), Maatwebsite Excel (匯入/匯出 Excel)
    *   **雲端整合:** AWS Cognito (身份驗證), Google Sheets API
    *   **監控:** Sentry, Laravel Telescope

## 2. 核心模組與功能
*   **營隊報名系統:** 提供多種營隊的報名頁面，包含狀態查詢、繳費管理及報到通知單下載。
*   **後台管理系統:**
    *   **營隊與梯次管理:** 可動態新增營隊、設定梯次及組織架構。
    *   **報名審核 (Admission):** 處理申請人的錄取、備取狀態。
    *   **分組管理:** 提供自動或手動分組功能。
    *   **後勤作業:** 管理住宿 (Lodging) 與交通 (Traffic) 資訊。
    *   **報到系統:** 提供 QR Code 掃描報到及即時統計功能。
*   **動態郵件系統:** 專案實作了一套動態切換郵件設定的機制（見 `app/Traits/EmailConfiguration.php`），允許不同的營隊使用各自的 SMTP 或 AWS SES 帳號發送郵件。

## 3. 架構特色
*   **多樣化的模型 (Models):** 系統中定義了大量以 `camp` 或 `vcamp` (義工營) 為後綴的模型（如 `Acamp`, `Tcamp`, `Vcamp`），顯示系統需處理多種異質的營隊資料結構。
*   **服務層抽象:** 關鍵邏輯封裝在 `app/Services` 中，例如 `ApplicantService` (處理申請人邏輯)、`CampDataService` (營隊資料存取) 等，有助於降低 Controller 的複雜度。
*   **核心類別覆寫 (Overrides):** 為了支援多營隊的動態需求，開發者覆寫了 Laravel 的 `Job` 類別，確保非同步任務執行時也能正確套用對應營隊的郵件設定。

## 4. 檔案規模與維護
*   **BackendController.php:** 超過 3000 行，承載了大部分後台作業邏輯，是系統中最核心但也最複雜的部分。
*   **Applicant.php & User.php:** 包含大量關聯與業務邏輯。
*   **維護建議:** 由於系統複雜度高，建議持續將過大的 Controller 邏輯抽離至 Service 層，並加強單元測試覆蓋率。
