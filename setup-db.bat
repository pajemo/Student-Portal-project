@echo off
setlocal

set MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe
set PHP_BIN=C:\xampp\php\php.exe
set PROJECT_ROOT=%~dp0
set SCHEMA=%PROJECT_ROOT%database\schema.sql
set SEEDS=%PROJECT_ROOT%database\seeds.sql
set MIGRATION=%PROJECT_ROOT%database\migrate_student_documents.php

if not exist "%MYSQL_BIN%" (
  echo [ERROR] MySQL client not found at: %MYSQL_BIN%
  echo Start XAMPP and confirm MySQL is installed.
  exit /b 1
)

if not exist "%SCHEMA%" (
  echo [ERROR] Missing schema file: %SCHEMA%
  exit /b 1
)

if not exist "%SEEDS%" (
  echo [ERROR] Missing seeds file: %SEEDS%
  exit /b 1
)

if not exist "%PHP_BIN%" (
  echo [ERROR] PHP CLI not found at: %PHP_BIN%
  echo Start XAMPP and confirm PHP is installed.
  exit /b 1
)

echo Applying schema...
"%MYSQL_BIN%" -u root < "%SCHEMA%"
if errorlevel 1 (
  echo [ERROR] Failed to apply schema.sql
  exit /b 1
)

echo Applying seed data...
"%MYSQL_BIN%" -u root < "%SEEDS%"
if errorlevel 1 (
  echo [ERROR] Failed to apply seeds.sql
  exit /b 1
)

if exist "%MIGRATION%" (
  echo Applying document migration...
  "%PHP_BIN%" "%MIGRATION%"
  if errorlevel 1 (
    echo [ERROR] Failed to apply student document migration
    exit /b 1
  )
)

echo.
echo [OK] Database setup complete.
echo You can now login at: http://localhost/admin/public/login.php
endlocal
