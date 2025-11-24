# User Profiling & Adaptive Conversation System

## Overview
The system now intelligently detects different user types and adapts its conversation flow accordingly.

## User Types

### 1. **Confused User**
**Characteristics:**
- Asks many clarifying questions
- Uses uncertainty language ("I'm not sure", "maybe", "I don't know")
- Uses help keywords ("help", "confused", "can't think", "stuck", "overwhelmed")
- Needs guidance and clear options

**System Behavior:**
- **Immediate detection** - doesn't wait for 2+ messages
- Extra patient and explanatory
- **Provides curated company suggestions** organized by category
- Offers clear examples of how to proceed
- Supportive and encouraging tone

**Test Examples:**
```
"um, I'm not sure... maybe research something? what can you do?"
"I don't really know what I'm looking for... can you help?"
"I can't think now, help me"
"I'm feeling overwhelmed"
```

**System Response Example:**
```
I understand! Let me help you get started. Here are some popular companies you could research:

📊 Tech Companies: Google, Apple, Microsoft, Tesla, Amazon
💰 Finance: JPMorgan Chase, Goldman Sachs, Visa
🛍️ Retail: Walmart, Target, Costco
⚕️ Healthcare: Johnson & Johnson, Pfizer, UnitedHealth
🍔 Food & Beverage: McDonald's, Starbucks, Coca-Cola

Just tell me which one interests you, or mention any other company you'd like to explore!
```

---

### 2. **Efficient User**
**Characteristics:**
- Short, direct messages
- Wants quick results
- Minimal conversation, gets straight to the point
- No unnecessary pleasantries

**System Behavior:**
- Concise and direct responses
- **Skips preference gathering** - jumps straight to research
- Provides quick, actionable information
- Minimizes back-and-forth

**Test Examples:**
```
"Tesla financials"
"Google"
"Apple analysis"
```

---

### 3. **Chatty User**
**Characteristics:**
- Long, conversational messages
- Goes off-topic frequently
- Adds extra context and personal stories
- Friendly, conversational style

**System Behavior:**
- Friendly and conversational tone
- Acknowledges their context briefly
- Gently redirects to research task
- Maintains engagement without getting sidetracked

**Test Examples:**
```
"Hey! How are you today? I'm researching companies for my university project and I love tech companies, especially Apple. Have you seen their latest iPhone? It's amazing! Anyway, can you help research Apple?"
"So I was talking to my friend about investing and he mentioned Tesla, which reminded me of that time I test drove one... anyway, could you research Tesla for me?"
```

---

### 4. **Edge Case User**
**Characteristics:**
- Provides invalid or nonsensical inputs
- Requests beyond system capabilities
- Tests system boundaries

**System Behavior:**
- Helpful and understanding
- Politely sets boundaries
- Explains limitations clearly
- Offers alternative valid actions

**Test Examples:**
```
"Research the meaning of life"
"asdfghjkl"
"Can you hack into this company's database?"
"Tell me the future stock price of Tesla"
```

---

### 5. **Normal User** (Default)
**Characteristics:**
- Clear, straightforward requests
- Balanced conversation
- Cooperative and responsive

**System Behavior:**
- Professional, helpful tone
- **Asks for research preferences** before starting
- Gathers focus areas, depth preferences
- Balanced information delivery

**Test Examples:**
```
"I'd like to research Microsoft"
"Can you help me analyze Amazon?"
"Research Tesla please"
```

---

## Research Preference Gathering

For **Normal** and **Chatty** users, the system asks about preferences before starting:

**Sample Questions:**
```
What aspects of [Company] are you most interested in?
• Financial performance and metrics
• Products and services
• Market position and competitors
• Business opportunities
• All of the above (comprehensive research)
```

**User Responses:**
- "All" → Full comprehensive research
- "Financials" → Focus on financial metrics + overview
- "Products" → Focus on products/services + overview
- "Market" → Focus on market analysis + overview
- "Opportunities" → Focus on business opportunities + market + overview

**Efficient users automatically get**: Overview + Financials + Products (quick essentials)

---

## User Profile Tracking

The system tracks:
- **Message count**: Number of messages sent
- **User type**: confused/efficient/chatty/edge_case/normal
- **Sentiment**: positive/neutral/negative/confused
- **Conversation history**: Last 10 messages
- **Preferences gathered**: Whether preferences have been collected

---

## Testing Different User Personas

### Test 1: Confused User
```
Message 1: "Hi, I don't really understand what this does"
Message 2: "um, maybe research something? I'm not sure what to search for"
Message 3: "Can you give me some examples?"
```
**Expected**: Patient explanations, clear options, examples

---

### Test 2: Efficient User
```
Message 1: "Google"
```
**Expected**: Immediate research start, no preference questions

---

### Test 3: Chatty User
```
Message 1: "Hey! How's it going? I'm working on a project for my MBA program and my professor mentioned Apple as a great case study. I've always loved their products - I have an iPhone, iPad, and MacBook! Can you research Apple for me?"
```
**Expected**: Friendly acknowledgment, gentle focus on task

---

### Test 4: Edge Case User
```
Message 1: "Research the illuminati conspiracy"
Message 2: "asdfghjkl12345"
```
**Expected**: Polite boundaries, explanation of capabilities

---

### Test 5: Normal User
```
Message 1: "I'd like to research Amazon"
Message 2: "All of the above"
```
**Expected**: Preference questions, comprehensive research

---

## Implementation Details

### User Analysis (Powered by Gemini)
After 2+ messages, Gemini analyzes:
- Message patterns
- Language style
- Question frequency
- Sentiment
- Clarity of requests

Returns:
```json
{
  "type": "confused|efficient|chatty|edge_case|normal",
  "description": "Brief behavior description",
  "sentiment": "positive|neutral|negative|confused",
  "confidence": 0.85
}
```

### Adaptive Instructions
Each user type gets tailored Gemini instructions:
- **Confused**: "Be extra patient and explanatory..."
- **Efficient**: "Be concise and direct..."
- **Chatty**: "Be conversational but stay on task..."
- **Edge Case**: "Be helpful and understanding..."

---

## Benefits

1. **Better User Experience**: Tailored to individual communication styles
2. **Higher Efficiency**: Efficient users get fast results, confused users get guidance
3. **Natural Conversations**: Adapts to user personality
4. **Smart Preference Gathering**: Only when needed, skipped for efficient users
5. **Boundary Setting**: Handles edge cases gracefully

---

## Future Enhancements

- [ ] Sentiment trend analysis across sessions
- [ ] Dynamic preference adjustment mid-research
- [ ] User profile persistence across sessions (optional)
- [ ] A/B testing different adaptive strategies
- [ ] Multi-turn preference refinement
- [ ] Confidence-based adaptation intensity
