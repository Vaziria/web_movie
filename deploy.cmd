@echo off
rem Double-click or run "deploy.cmd" (add --dry-run to test without uploading). Uses Git Bash.
setlocal
set "BASH=%ProgramFiles%\Git\bin\bash.exe"
if not exist "%BASH%" set "BASH=bash"
"%BASH%" "%~dp0deploy\deploy.sh" %*
set "CODE=%ERRORLEVEL%"
echo.
if "%CODE%"=="0" (echo Done.) else (echo Deploy failed with code %CODE%.)
pause
exit /b %CODE%
