@echo off
setlocal
cd /d "%~dp0apps\windows-player"

if not exist node_modules (
    echo Installing the Windows player...
    call npm install
    if errorlevel 1 exit /b 1
)

echo Starting DigSignage Windows player...
echo Controls: F11 toggle fullscreen, Ctrl+Q quit
echo.
call npm start -- --server=http://127.0.0.1:8000
