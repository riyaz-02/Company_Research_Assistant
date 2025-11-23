@echo off
REM Start AI Service Script

cd /d "%~dp0"

echo Starting AI Service...
python -m uvicorn app:app --reload --host 0.0.0.0 --port 8000

pause
