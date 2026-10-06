@echo off
setlocal EnableExtensions EnableDelayedExpansion

title Roster Pro Release QA
cd /d "%~dp0\.."

echo ============================================================
echo Roster Pro - Release QA
echo ============================================================
echo.

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
    where php >nul 2>&1
    if errorlevel 1 (
        echo [FAIL] PHP was not found.
        echo        Expected: C:\xampp\php\php.exe
        exit /b 1
    )
    set "PHP_EXE=php"
)

echo [1/6] Git branch and worktree
git branch --show-current
git status --short
if errorlevel 1 exit /b 1

echo.
echo [2/6] Searching for unresolved merge markers
set "CONFLICT_FOUND=0"
for /r %%F in (*.php *.js *.css *.html) do (
    findstr /n /c:"<<<<<<< " "%%F" >nul 2>&1 && (
        echo [FAIL] Conflict marker: %%F
        set "CONFLICT_FOUND=1"
    )
    findstr /n /c:">>>>>>> " "%%F" >nul 2>&1 && (
        echo [FAIL] Conflict marker: %%F
        set "CONFLICT_FOUND=1"
    )
)
if "!CONFLICT_FOUND!"=="1" exit /b 1
echo [PASS] No merge conflict markers found.

echo.
echo [3/6] PHP syntax lint
set "PHP_FAIL=0"
for /r %%F in (*.php) do (
    "%PHP_EXE%" -l "%%F" >nul 2>&1
    if errorlevel 1 (
        echo [FAIL] PHP syntax: %%F
        "%PHP_EXE%" -l "%%F"
        set "PHP_FAIL=1"
    )
)
if "!PHP_FAIL!"=="1" exit /b 1
echo [PASS] PHP syntax is valid.

echo.
echo [4/6] Checking LINE Messaging API migration
findstr /s /n /i /c:"notify-api.line.me" controllers\*.php services\*.php > "%TEMP%\roster_line_notify.txt" 2>nul
if exist "%TEMP%\roster_line_notify.txt" (
    for %%A in ("%TEMP%\roster_line_notify.txt") do if %%~zA GTR 0 (
        echo [FAIL] Obsolete LINE Notify endpoint still exists:
        type "%TEMP%\roster_line_notify.txt"
        exit /b 1
    )
)
if not exist "services\LineMessagingService.php" (
    echo [FAIL] Missing services\LineMessagingService.php
    exit /b 1
)
if not exist "services\NotificationService.php" (
    echo [FAIL] Missing services\NotificationService.php
    exit /b 1
)
if not exist "controllers\LinewebhookController.php" (
    echo [FAIL] Missing controllers\LinewebhookController.php
    exit /b 1
)
echo [PASS] LINE Messaging API service and webhook files found.

echo.
echo [5/6] Checking hard-coded legacy cron secret
findstr /s /n /i /c:"ROSTER_PRO_CRON_2026" *.php *.js *.md > "%TEMP%\roster_cron_secret.txt" 2>nul
if exist "%TEMP%\roster_cron_secret.txt" (
    for %%A in ("%TEMP%\roster_cron_secret.txt") do if %%~zA GTR 0 (
        echo [FAIL] Legacy cron secret is still present:
        type "%TEMP%\roster_cron_secret.txt"
        exit /b 1
    )
)
echo [PASS] Legacy cron secret not found.

echo.
echo [6/6] Important release files
for %%F in (
    "public\css\roster-tokens.css"
    "public\css\roster-components.css"
    "public\css\roster-responsive.css"
    "public\css\roster-accessibility.css"
    "public\js\roster-qa.js"
    "views\components\ui.php"
) do (
    if not exist %%F (
        echo [FAIL] Missing: %%F
        exit /b 1
    )
)
echo [PASS] Design System release files found.

echo.
echo ============================================================
echo Static QA completed.
echo Perform browser smoke tests listed in RELEASE_CHECKLIST_DESIGN_SYSTEM_V2.md
echo If LINE delivery is enabled, verify webhook reception and a test push message.
echo ============================================================

exit /b 0
