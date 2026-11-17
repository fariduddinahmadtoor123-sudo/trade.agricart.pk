@echo off
cd /d "%~dp0"
set "PY=%LOCALAPPDATA%\agricart-mt5-bridge\venv\Scripts\python.exe"
if not exist "%PY%" (
    echo Python is not ready on this PC yet.
    pause
    exit /b 1
)
"%PY%" "%~dp0connect-mt5.py"
echo.
echo Ye window band na karein. MetaTrader 5 login rehna chahiye.
echo Jab snapshot sent likha aaye, rates site par ja rahe hain.
pause
