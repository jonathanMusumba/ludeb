@echo off
SET PHP_PATH=C:\xampp\php\php.exe
SET SCRIPT_PATH=C:\xampp\htdocs\ludeb\admin\backup_database.php
SET LOG_FILE=C:\xampp\htdocs\ludeb\logs\backup_execution.log

echo [%DATE% %TIME%] Starting backup process >> %LOG_FILE%
%PHP_PATH% %SCRIPT_PATH% >> %LOG_FILE% 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo [%DATE% %TIME%] Backup failed with error code %ERRORLEVEL% >> %LOG_FILE%
    exit /b %ERRORLEVEL%
)
echo [%DATE% %TIME%] Backup completed successfully >> %LOG_FILE%
exit /b 0