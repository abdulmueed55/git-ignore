@echo off
REM FG Liaquat Ali Degree College ERP - one-click local demo for Windows
REM Needs PHP 8.3, Composer and Node.js (Laragon includes all three).
cd /d "%~dp0"
call composer install --no-interaction || goto :error
call composer run demo || goto :error
echo.
echo  Demo ready. Open http://localhost:8000  (password for all demo accounts: password)
echo.
php artisan serve
goto :eof
:error
echo Setup failed - see the message above.
pause
