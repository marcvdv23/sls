$ErrorActionPreference = 'Continue'

$projectDir = 'C:\laragon\www\1g-sls'
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
Set-Location $projectDir

while ($true) {
    try {
        & $php artisan schedule:run *> $null
    } catch {
        # Keep the loop alive even if a scheduled command fails.
    }

    Start-Sleep -Seconds 60
}
