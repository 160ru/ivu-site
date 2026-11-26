<#
.SYNOPSIS
  Выкладка статики сайта на виртуальный хостинг Beget по SSH.

.DESCRIPTION
  Загружает только то, что нужно на хостинге: страницы, assets, .htaccess,
  robots.txt и sitemap.xml. Скриншоты, README и server.mjs остаются локально.

.EXAMPLE
  .\deploy.ps1 -User u1234567 -Server u1234567.beget.tech

.EXAMPLE
  .\deploy.ps1 -User u1234567 -Server u1234567.beget.tech -DryRun
#>
[CmdletBinding()]
param(
  [Parameter(Mandatory = $true)][string]$User,
  [Parameter(Mandatory = $true)][string]$Server,
  [string]$Domain = "ivu.ru",
  [string]$RemoteDir = "",
  [int]$Port = 22,
  [switch]$DryRun
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command scp -ErrorAction SilentlyContinue)) {
  throw "Не найден scp. Установите клиент OpenSSH: Параметры → Приложения → Дополнительные компоненты."
}

# На Beget сайт лежит в ~/<домен>/public_html
if ([string]::IsNullOrWhiteSpace($RemoteDir)) {
  $RemoteDir = "~/$Domain/public_html"
}

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$files = @(
  "index.html",
  "404.html",
  "privacy.html",
  "requisites.html",
  "robots.txt",
  "sitemap.xml",
  ".htaccess",
  "assets"
)

Push-Location $root
try {
  foreach ($item in $files) {
    if (-not (Test-Path $item)) { throw "Не найден обязательный файл: $item" }
  }

  $target = "$User@$Server`:$RemoteDir/"
  Write-Host "Куда: $target" -ForegroundColor Cyan
  Write-Host "Файлов: $($files.Count)" -ForegroundColor Cyan

  if ($DryRun) {
    Write-Host "`nРежим проверки — ничего не отправлено. Отправилось бы:" -ForegroundColor Yellow
    $files | ForEach-Object { Write-Host "  $_" }
    return
  }

  & scp -P $Port -r -C @files $target
  if ($LASTEXITCODE -ne 0) { throw "scp завершился с кодом $LASTEXITCODE" }

  Write-Host "`nГотово. Проверьте https://$Domain/" -ForegroundColor Green
}
finally {
  Pop-Location
}
