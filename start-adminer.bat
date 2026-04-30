@echo off
setlocal

cd /d "%~dp0"

echo Starting Better Adminer with Docker Compose...
echo.

docker compose -f compose.yaml up -d --build
if %errorlevel% neq 0 (
    echo.
    echo Docker Compose failed. Make sure Docker Desktop is running and Compose is installed.
    echo.
    pause
    exit /b %errorlevel%
)

echo.
echo Better Adminer is running at http://localhost:8080
echo.
pause
