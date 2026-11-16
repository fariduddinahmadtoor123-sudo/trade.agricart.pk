@echo off
setlocal
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-mt5-bridge.ps1"
set ERR=%ERRORLEVEL%
if not "%ERR%"=="0" (
    echo.
    echo The MT5 bridge did not start. The messages above say why.
    echo.
    pause
)
exit /b %ERR%
