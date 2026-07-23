Set shell = CreateObject("WScript.Shell")
shell.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""C:\laragon\www\1g-sls\scripts\windows-schedule-run-once.ps1""", 0, False
