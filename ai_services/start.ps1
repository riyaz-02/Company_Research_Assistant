# Start AI Service (PowerShell)
# Run this script to start the AI service

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

Write-Host "Starting AI Service on http://localhost:8001" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow

& "$scriptDir\venv\Scripts\python.exe" -m uvicorn app:app --reload --host 0.0.0.0 --port 8001
