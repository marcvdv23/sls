$ErrorActionPreference = 'Continue'

$loopScript = 'C:\laragon\www\1g-sls\scripts\windows-scheduler-loop.ps1'

Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -match 'powershell(\.exe)?$' -and
        $_.CommandLine -like "*$loopScript*"
    } |
    ForEach-Object {
        try {
            Stop-Process -Id $_.ProcessId -Force
        } catch {
            # Ignore stale processes that exit while the restart script is running.
        }
    }

Start-Process `
    -FilePath 'powershell.exe' `
    -ArgumentList '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $loopScript `
    -WindowStyle Hidden

Write-Output '1G-SLS scheduler loop restarted.'
