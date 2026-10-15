@echo off
rem Mo link cong khai tam thoi (Cloudflare Quick Tunnel, khong can tai khoan) cho Farm2Home.
rem Can bat Laragon (MySQL) truoc. Nhan phim bat ky trong cua so nay de tat link.
setlocal
title Farm2Home - link tam thoi
cd /d "%~dp0"

set "PHP=E:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
set "CF=%USERPROFILE%\.local\bin\cloudflared.exe"
set "PORT=8765"
set "LOG=%TEMP%\f2h-tunnel.log"
set PHP_CLI_SERVER_WORKERS=4

if exist public\hot echo [!] Dang chay "npm run dev": tat no va chay "npm run build", neu khong dien thoai se mat giao dien.

del "%LOG%" 2>nul
start "F2H-server" /min "%PHP%" artisan serve --host=127.0.0.1 --port=%PORT%
start "F2H-tunnel" /min "%CF%" tunnel --no-autoupdate --url http://127.0.0.1:%PORT% --logfile "%LOG%"

echo Dang tao link...
set /a TRIES=0
:wait
timeout /t 1 >nul
set /a TRIES+=1
for /f "delims=" %%u in ('powershell -NoProfile -Command "(Select-String -Path $env:LOG -Pattern 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com' -ErrorAction SilentlyContinue | Select-Object -First 1).Matches.Value"') do set "URL=%%u"
if defined URL goto ready
if %TRIES% lss 45 goto wait
echo [X] Khong tao duoc link. Xem log: %LOG%
goto stop

:ready
rem Link moi can vai giay de DNS chay, mo som se bi loi 502.
timeout /t 8 >nul
echo %URL%| clip
start "" "%URL%"
echo.
echo   Link: %URL%
echo   (da copy vao clipboard - dan vao Zalo de gui)
echo.
echo Nhan phim bat ky de TAT link...
pause >nul

:stop
taskkill /fi "WINDOWTITLE eq F2H-server*" /t /f >nul 2>&1
taskkill /fi "WINDOWTITLE eq F2H-tunnel*" /t /f >nul 2>&1
