param(
    [string]$BackupRoot = "C:\Users\marcv\Documents\1G-SLS-Backups",
    [string]$ProjectRoot = "C:\laragon\www\1g-sls",
    [string]$MysqlDump = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe",
    [string]$Database = "oneg_sls",
    [string]$DbUser = "root"
)

$ErrorActionPreference = "Stop"

$stamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
$backupDir = Join-Path $BackupRoot $stamp
$dbDir = Join-Path $backupDir "database"
$filesDir = Join-Path $backupDir "files"

New-Item -ItemType Directory -Force -Path $dbDir, $filesDir | Out-Null

$dbDumpPath = Join-Path $dbDir "$Database-$stamp.sql"
& $MysqlDump --user=$DbUser --single-transaction --routines --events --databases $Database | Out-File -FilePath $dbDumpPath -Encoding utf8

$manifest = [ordered]@{
    created_at = (Get-Date).ToString("s")
    backup_root = $BackupRoot
    project_root = $ProjectRoot
    database = $Database
    database_dump = $dbDumpPath
    included = @(
        ".env",
        "app",
        "bootstrap",
        "config",
        "database",
        "public",
        "resources",
        "routes",
        "scripts",
        "storage/app",
        "storage/framework/views",
        "composer.json",
        "composer.lock",
        "package.json",
        "vite.config.js",
        "artisan",
        "README.md",
        "apache-local.conf"
    )
    excluded = @(
        "vendor",
        "node_modules",
        "tools",
        "storage/logs",
        "storage/framework/cache",
        "storage/framework/sessions",
        "storage/framework/testing",
        "storage/framework/views/*.php"
    )
    note = "This backup is local to the laptop. Copy this folder off-device for protection from disk failure, ransomware, or laptop loss."
}

$pathsToCopy = @(
    ".env",
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "scripts",
    "storage\app",
    "storage\framework\views",
    "composer.json",
    "composer.lock",
    "package.json",
    "vite.config.js",
    "artisan",
    "README.md",
    "apache-local.conf"
)

foreach ($relativePath in $pathsToCopy) {
    $source = Join-Path $ProjectRoot $relativePath
    if (-not (Test-Path -LiteralPath $source)) {
        continue
    }

    $destination = Join-Path $filesDir $relativePath
    $destinationParent = Split-Path -Parent $destination
    New-Item -ItemType Directory -Force -Path $destinationParent | Out-Null

    if ((Get-Item -LiteralPath $source).PSIsContainer) {
        Copy-Item -LiteralPath $source -Destination $destinationParent -Recurse -Force
    } else {
        Copy-Item -LiteralPath $source -Destination $destination -Force
    }
}

$manifestPath = Join-Path $backupDir "backup-manifest.json"
$manifest | ConvertTo-Json -Depth 4 | Set-Content -Path $manifestPath -Encoding UTF8

$requiredBackupPaths = @(
    ".env",
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "scripts",
    "storage\app",
    "composer.json",
    "composer.lock",
    "artisan"
)

$missingBackupPaths = @()
foreach ($relativePath in $requiredBackupPaths) {
    $target = Join-Path $filesDir $relativePath
    if (-not (Test-Path -LiteralPath $target)) {
        $missingBackupPaths += $relativePath
    }
}

$dumpInfo = Get-Item -LiteralPath $dbDumpPath
$dumpHead = Get-Content -LiteralPath $dbDumpPath -TotalCount 100
$dumpTail = Get-Content -LiteralPath $dbDumpPath -Tail 25
$expectedUseStatement = 'USE `' + $Database + '`;'
$copiedFiles = Get-ChildItem -LiteralPath $filesDir -Recurse -File
$copiedFileBytes = ($copiedFiles | Measure-Object -Property Length -Sum).Sum

$verification = [ordered]@{
    verified_at = (Get-Date).ToString("s")
    success = $true
    backup_folder = $backupDir
    checks = [ordered]@{
        manifest_exists = Test-Path -LiteralPath $manifestPath
        database_dump_exists = Test-Path -LiteralPath $dbDumpPath
        database_dump_bytes = $dumpInfo.Length
        database_dump_has_create_database = [bool]($dumpHead -match "CREATE DATABASE")
        database_dump_has_use_statement = [bool]($dumpHead -match [regex]::Escape($expectedUseStatement))
        database_dump_completed_marker = [bool]($dumpTail -match "Dump completed")
        copied_file_count = $copiedFiles.Count
        copied_file_bytes = $copiedFileBytes
        required_paths_present = $missingBackupPaths.Count -eq 0
        missing_required_paths = $missingBackupPaths
    }
}

foreach ($check in $verification.checks.GetEnumerator()) {
    if ($check.Value -is [bool] -and -not $check.Value) {
        $verification.success = $false
    }
}

$verificationPath = Join-Path $backupDir "backup-verification.json"
$verification | ConvertTo-Json -Depth 5 | Set-Content -Path $verificationPath -Encoding UTF8

if (-not $verification.success) {
    throw "Backup verification failed. See $verificationPath"
}

Write-Host "1G-SLS backup completed"
Write-Host "Backup folder: $backupDir"
Write-Host "Database dump: $dbDumpPath"
Write-Host "Manifest: $manifestPath"
Write-Host "Verification: $verificationPath"
