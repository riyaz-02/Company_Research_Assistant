# Quick Start Guide

## Setup Steps

1. **Add your Gemini API keys to .env**
   ```
   GEMINI_API_KEYS=your-key-1,your-key-2,your-key-3
   ```

2. **Start the service**
   
   **Windows (PowerShell):**
   ```powershell
   .\start.ps1
   ```
   
   **Windows (CMD):**
   ```cmd
   start.bat
   ```
   
   **Manual start:**
   ```powershell
   .\venv\Scripts\Activate.ps1
   uvicorn app:app --reload --host 0.0.0.0 --port 8000
   ```

3. **Test the service**
   
   Open browser: http://localhost:8000/health
   
   Or use curl:
   ```powershell
   curl http://localhost:8000/health
   ```

## API Endpoints

- `GET /health` - Health check
- `POST /synthesize-section` - Synthesize research content
- `POST /detect-conflicts` - Detect data conflicts
- `POST /process-step` - Process complete research step
- `POST /generate-final-plan` - Generate executive summary
- `POST /clean-text` - Clean and format text
- `POST /clear-cache/{session_id}` - Clear session cache

## Laravel Integration

Update your Laravel `.env`:
```
AI_SERVICE_URL=http://localhost:8000
AI_SERVICE_TIMEOUT=120
```

See README.md for complete Laravel integration code.

## Troubleshooting

**Port already in use:**
```powershell
# Change port in start script or use:
uvicorn app:app --reload --port 8001
```

**Redis not available:**
The service will automatically fall back to in-memory cache.

**Import errors:**
Make sure virtual environment is activated:
```powershell
.\venv\Scripts\Activate.ps1
pip list  # Verify packages are installed
```
