# Required Credentials

This document lists all the essential credentials needed to run the Company Research Assistant application.

## ✅ Already Configured

1. **MongoDB Connection** ✅
   - URI: `mongodb+srv://riyazjisce:sg4Ua6VfYEztDCVC@companyresearchassistan.rfodcjr.mongodb.net/company_research_assistant?appName=CompanyResearchAssistant`
   - Database: `company_research_assistant`
   - Status: ✅ Configured in `.env`

2. **Google Gemini API** ✅
   - API Key: `AIzaSyA9C4XjQBgHad18Ndf7Rvan9UPB3jw9sGs`
   - Provider: `gemini`
   - Model: `gemini-1.5-pro`
   - Status: ✅ Configured in `.env`

## ⚠️ Required (Choose at least one)

### Search API (Required for web research functionality)

You need at least ONE of the following search APIs:

#### Option 1: SerpAPI (Recommended)
- **What it is**: Google Search API service
- **Why needed**: Allows the agent to search the web for company information
- **How to get**: 
  1. Visit https://serpapi.com/
  2. Sign up for a free account (100 searches/month free)
  3. Get your API key from the dashboard
- **Configuration**:
  ```
  SERPAPI_KEY=your_serpapi_key_here
  ```

#### Option 2: Bing Search API
- **What it is**: Microsoft Bing Search API
- **Why needed**: Alternative to SerpAPI for web searches
- **How to get**:
  1. Visit https://www.microsoft.com/en-us/bing/apis/bing-web-search-api
  2. Create an Azure account
  3. Create a Bing Search resource
  4. Get your API key
- **Configuration**:
  ```
  BING_API_KEY=your_bing_api_key_here
  BING_ENDPOINT=https://api.bing.microsoft.com/v7.0/search
  ```

## 📋 Summary

### Minimum Required:
- ✅ MongoDB (Already configured)
- ✅ LLM API - Gemini (Already configured)
- ⚠️ **Search API** - SerpAPI OR Bing (You need to add this)

### Optional but Recommended:
- None (all essential features work with the above)

## 🚀 Quick Setup

1. **Get a Search API Key** (Choose one):
   - **Easiest**: Sign up for SerpAPI (free tier: 100 searches/month)
   - **Alternative**: Use Bing Search API (free tier available)

2. **Add to `.env` file**:
   ```env
   # Add one of these:
   SERPAPI_KEY=your_key_here
   # OR
   BING_API_KEY=your_key_here
   ```

3. **Test the application**:
   ```bash
   php artisan serve
   ```

## 🔒 Security Note

- Never commit your `.env` file to version control
- The `.env` file is already in `.gitignore`
- Keep your API keys secure and rotate them periodically

## 📝 Current Configuration Status

| Credential | Status | Location |
|------------|--------|----------|
| MongoDB | ✅ Configured | `.env` |
| Gemini API | ✅ Configured | `.env` |
| SerpAPI | ⚠️ **Need to add** | `.env` |
| Bing API | ⚠️ **Need to add** | `.env` |

## 🆘 Troubleshooting

If you encounter errors:

1. **"LLM API key is not configured"**
   - Check that `LLM_API_KEY` is set in `.env`
   - Verify the key is correct (no extra spaces)

2. **"No search results found"**
   - Ensure either `SERPAPI_KEY` or `BING_API_KEY` is set
   - Verify the API key is valid and has remaining quota

3. **MongoDB connection errors**
   - Verify the MongoDB URI is correct
   - Check network connectivity to MongoDB Atlas

