# Резервное скачивание всех файлов сайта safronova.com.ua с серверов Vigbo
# Что делает:
#   1) все фото (309 шт.)  -> wp-content\plugins\safronova-migration\media\...
#   2) шрифты               -> wp-content\themes\safronova\assets\fonts\
#   3) логотип              -> wp-content\themes\safronova\assets\img\logo.jpg
# Уже скачанные файлы пропускаются, скрипт можно запускать повторно.
# Запуск: правый клик по файлу -> "Выполнить с помощью PowerShell"

$ErrorActionPreference = 'Continue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$ProgressPreference = 'SilentlyContinue'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

$cdn    = 'https://static-cdn4-2.vigbo.tech/u44468/57218/'
$media  = Join-Path $root 'wp-content\plugins\safronova-migration\media'
$fonts  = Join-Path $root 'wp-content\themes\safronova\assets\fonts'
$imgdir = Join-Path $root 'wp-content\themes\safronova\assets\img'
New-Item -ItemType Directory -Force -Path $media, $fonts, $imgdir | Out-Null

function Get-File($url, $dest) {
    if ((Test-Path $dest) -and ((Get-Item $dest).Length -gt 0)) { return 'skip' }
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $dest) | Out-Null
    for ($i = 1; $i -le 3; $i++) {
        try {
            Invoke-WebRequest -Uri $url -OutFile $dest -UseBasicParsing -TimeoutSec 120
            return 'ok'
        } catch {
            Start-Sleep -Seconds 2
        }
    }
    if (Test-Path $dest) { Remove-Item $dest -Force }
    return 'fail'
}

$list = Get-Content (Join-Path $root 'media-list.txt') -Encoding UTF8 | Where-Object { $_ -match '^https://' }
$total = $list.Count; $n = 0; $ok = 0; $skip = 0; $fail = @()
foreach ($url in $list) {
    $n++
    $rel = ($url -replace [regex]::Escape($cdn), '') -replace '\?.*$', ''
    $dest = Join-Path $media ($rel -replace '/', '\')
    $r = Get-File $url $dest
    switch ($r) { 'ok' { $ok++ } 'skip' { $skip++ } 'fail' { $fail += $url } }
    Write-Host ("[{0}/{1}] {2}  {3}" -f $n, $total, $r, $rel)
}

Write-Host "`nШрифты и логотип..."
foreach ($f in 'firasans-regular.woff', 'playfairdisplay-regular.woff', 'opensans-regular.woff') {
    $r = Get-File ("https://cdn06-2.vigbo.tech/cms/kevin2/site/dev/fontspack/" + $f) (Join-Path $fonts $f)
    Write-Host "  $f : $r"
}
$r = Get-File ($cdn + 'logo/u-03e3a38a7b9f56360ef1bfc1631507c1@2x.jpg') (Join-Path $imgdir 'logo.jpg')
Write-Host "  logo.jpg : $r"

Write-Host "`nГотово. Скачано: $ok, уже было: $skip, ошибок: $($fail.Count)"
if ($fail.Count -gt 0) {
    $fail | Set-Content (Join-Path $root 'download-errors.txt') -Encoding UTF8
    Write-Host "Список неудачных загрузок: download-errors.txt (запустите скрипт ещё раз)"
}
Read-Host "`nНажмите Enter, чтобы закрыть"
