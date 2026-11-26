@echo off
rem Wrapper for deploy.ps1 - runs it bypassing the execution policy.
rem Usage:
rem   deploy.cmd -User u1234567 -Server u1234567.beget.tech
rem   deploy.cmd -User u1234567 -Server u1234567.beget.tech -DryRun

setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy.ps1" %*
set "RC=%errorlevel%"
endlocal & exit /b %RC%
