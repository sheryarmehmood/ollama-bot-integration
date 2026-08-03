# AI ChatBot — Technical Documentation

## Table of Contents
1. [Laravel Package for AI Integration](#1-laravel-package-for-ai-integration)
2. [Can This Package Work With All AI APIs?](#2-can-this-package-work-with-all-ai-apis)
3. [How Ollama API Is Being Used (Code Walkthrough)](#3-how-ollama-api-is-being-used)
4. [Why Vue Instead of Blade? (Architecture Explanation)](#4-why-vue-instead-of-blade)
5. [Endpoints — What We Used & What Else Is Available](#5-endpoints)

---

## 1. Laravel Package for AI Integration

### Package: `openai-php/laravel` (v0.19.1)

We use **two packages** that work together:

| Package | Role |
|---|---|
| `openai-php/client` | The core PHP client library that makes HTTP requests to any OpenAI-compatible API |
| `openai-php/laravel` | A Laravel wrapper that provides a Facade (`OpenAI::`) and reads config from `.env` |

**Install command used:**
```bash
composer require openai-php/laravel
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

This published `config/openai.php` which reads these `.env` variables:
```
OPENAI_API_KEY=ollama           # API key (Ollama doesn't need a real key)
OPENAI_BASE_URL=http://localhost:11434/v1   # Points to local Ollama server
```

### Why This Package?
- It's the **official community PHP client** for OpenAI's API format
- Provides a clean Laravel Facade: `OpenAI::chat()->create([...])`
- Handles HTTP transport, error handling, response parsing automatically
- The `base_uri` config option lets us redirect requests to ANY OpenAI-compatible server (like Ollama)

---

## 2. Can This Package Work With All AI APIs?

### Yes — for any API that follows the OpenAI API format.

The `openai-php/client` works with any service that implements the **OpenAI-compatible API specification**:

| Provider | Compatible? | How to Use |
|---|---|---|
| **OpenAI** (GPT-4, GPT-3.5) | ✅ Native | Set `OPENAI_API_KEY=sk-...` and remove `OPENAI_BASE_URL` |
| **Ollama** (local models) | ✅ Via base_uri | Set `OPENAI_BASE_URL=http://localhost:11434/v1` |
| **Azure OpenAI** | ⚠️ Partial | Needs custom HTTP client config (different auth headers) |
| **Groq** | ✅ Via base_uri | Set `OPENAI_BASE_URL=https://api.groq.com/openai/v1` |
| **Together AI** | ✅ Via base_uri | Set `OPENAI_BASE_URL=https://api.together.xyz/v1` |
| **LM Studio** | ✅ Via base_uri | Set `OPENAI_BASE_URL=http://localhost:1234/v1` |
| **Anthropic (Claude)** | ❌ Different format | Needs a different package (`anthropic-php`) |
| **Google Gemini** | ❌ Different format | Needs Google's SDK or REST calls |

### Switching Between Providers

To switch from Ollama to OpenAI, you only change `.env`:
```env
OPENAI_API_KEY=sk-your-real-openai-key
# OPENAI_BASE_URL=        ← remove or comment out (defaults to api.openai.com)
```

To switch from Ollama to Groq:
```env
OPENAI_API_KEY=gsk-your-groq-key
OPENAI_BASE_URL=https://api.groq.com/openai/v1
```

**The PHP code stays identical.** Only `.env` and the model name change.

---

## 3. How Ollama API Is Being Used

### Architecture Flow

```
User (Browser)
    │
    ▼
Vue ChatBot.vue ──POST /api/chat──▶ Laravel ChatController
                                        │
                                        ▼
                                   OpenAI Facade
                                        │
                                        ▼
                                   HTTP Request to
                                   http://localhost:11434/v1/chat/completions
                                        │
                                        ▼
                                   Ollama (running phi3 model locally)
                                        │
                                        ▼
                                   JSON response back through the chain
```


DETAILED FLOW

1. Browser sends message to Laravel
   POST /api/chat with { "message": "hello" }

2. ChatController::send() receives it
   
3. Code runs: OpenAI is a format, ollama also uses it
   $response = OpenAI::chat()->create([
       'model' => 'phi3',
       'messages' => [...]
   ]);

4. openai-php/laravel client checks:
   "Where should I send this?"
   Looks at config/openai.php:
   'base_uri' => env('OPENAI_BASE_URL')  ← reads from .env
   
5. Your .env says:
   OPENAI_BASE_URL=http://localhost:11434/v1
   
6. Client sends HTTP request to:
   POST http://localhost:11434/v1/chat/completions
   ↓
   This goes to YOUR LOCAL OLLAMA SERVER
   (Not to OpenAI's cloud!)

7. Ollama receives request
   - Loads phi3 model into memory
   - Generates response (30-40 seconds)
   - Returns JSON

8. Laravel parses response
   → Returns to browser
   → Browser shows message


### File-by-File Walkthrough

#### `config/openai.php` — Configuration
```php
'api_key'    => env('OPENAI_API_KEY'),        // = "ollama"
'base_uri'   => env('OPENAI_BASE_URL'),       // = "http://localhost:11434/v1"
'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
```
This tells the OpenAI PHP client to send all requests to Ollama's local server instead of OpenAI's cloud.

#### `app/Http/Controllers/ChatController.php` — The Core Logic
```php
use OpenAI\Laravel\Facades\OpenAI;

public function send(Request $request)
{
    // 1. Validate input (max 2000 chars)
    $request->validate(['message' => 'required|string|max:2000']);

    // 2. Build conversation history for context
    $messages = $request->input('history', []);
    $messages[] = ['role' => 'user', 'content' => $request->input('message')];

    // 3. Call Ollama via the OpenAI-compatible API
    $response = OpenAI::chat()->create([
        'model' => 'phi3',                    // The Ollama model to use
        'messages' => array_merge(
            [['role' => 'system', 'content' => 'You are a helpful AI assistant.']],
            $messages                          // Full conversation for context
        ),
    ]);

    // 4. Extract and return the reply
    $reply = $response->choices[0]->message->content;
    return response()->json(['reply' => $reply]);
}
```

**How it works under the hood:**
- `OpenAI::chat()->create([...])` makes a `POST` request to `http://localhost:11434/v1/chat/completions`
- The request body follows OpenAI's format: `{ model, messages[] }`
- Ollama implements this same endpoint format, so it responds identically
- The PHP client parses the JSON response into structured objects (`$response->choices[0]->message->content`)

#### `routes/api.php` — API Endpoint
```php
Route::post('/chat', [ChatController::class, 'send']);
```
This registers `POST /api/chat` (the `/api` prefix is automatic for routes in `api.php`).

---

## 4. Why Vue Instead of Blade?

### The Problem With Blade for a ChatBot

Blade is **server-rendered**. Every interaction requires a full page reload:

```
Blade approach:
User types → Form submit → Full page reload → See response → Type again → Reload...
```

This is terrible for a chatbot because:
- **Every message causes a page reload** (flicker, scroll position lost)
- **No typing indicators** (can't show "bot is thinking...")
- **No real-time updates** (can't update the UI while waiting)
- **Chat history resets** unless you store it in sessions/database

### Why Vue Solves This

Vue is **client-side reactive**. The page never reloads:

```
Vue approach:
User types → AJAX request → UI updates instantly → Chat stays smooth
```

| Feature | Blade | Vue |
|---|---|---|
| Page reload on send | ✅ Yes (bad) | ❌ No (good) |
| Typing indicator | ❌ Very hard | ✅ Easy (`v-if="loading"`) |
| Scroll to bottom | ❌ Manual JS hacks | ✅ `nextTick()` + ref |
| Message history | ❌ Needs DB/session | ✅ Stays in memory |
| Error handling | ❌ Redirect + flash | ✅ Inline error bar |
| Disable input while loading | ❌ Custom JS needed | ✅ `:disabled="loading"` |

### Vue Files Used & Why

#### `resources/js/app.js` — Vue Entry Point
```js
import { createApp } from 'vue';
import ChatBot from './components/ChatBot.vue';

const app = createApp({});
app.component('chat-bot', ChatBot);
app.mount('#app');
```
**Why:** Initializes Vue and registers the ChatBot component so it can be used as `<chat-bot>` in HTML.

#### `resources/js/components/ChatBot.vue` — The Chat Component (Single File Component)
This is a **Vue Single File Component (SFC)** containing:
- `<template>` — The HTML structure (message list, input form, error bar)
- `<script>` — The logic (send messages, handle errors, manage state)
- `<style scoped>` — CSS scoped only to this component (won't leak)

**Key Vue features used:**
```html
v-for="(msg, index) in messages"    <!-- Reactively renders all messages -->
v-if="loading"                       <!-- Shows/hides typing indicator -->
v-model="userMessage"                <!-- Two-way binding on input -->
@submit.prevent="sendMessage"        <!-- Handles form without page reload -->
:disabled="loading"                  <!-- Disables input while waiting -->
ref="messagesContainer"              <!-- DOM reference for scroll control -->
```

**Why a Single File Component?** Everything for the chatbot (HTML + JS + CSS) lives in one file, making it self-contained and reusable.

#### `resources/views/chat.blade.php` — The Bridge
```html
<div id="app">
    <chat-bot></chat-bot>
</div>
```
**Why still a Blade file?** Laravel serves the initial HTML page. Blade handles:
- CSRF token meta tag (security)
- `@vite()` directive to load compiled JS/CSS
- Providing the `<div id="app">` mount point for Vue

**This is a hybrid approach:** Blade delivers the page shell, Vue takes over the interactive part.

---

## 5. Endpoints

### Endpoints We Used

| Method | URL | Purpose | File |
|---|---|---|---|
| `GET /` | Web route | Serves the chat page (Blade + Vue) | `routes/web.php` |
| `POST /api/chat` | API route | Sends message to Ollama, returns AI reply | `routes/api.php` |

#### `POST /api/chat` — Request/Response Format

**Request:**
```json
{
    "message": "Hello, how are you?",
    "history": [
        { "role": "user", "content": "What is Laravel?" },
        { "role": "assistant", "content": "Laravel is a PHP framework..." }
    ]
}
```

**Response (success):**
```json
{
    "reply": "I'm doing well! How can I help you today?"
}
```

**Response (error):**
```json
{
    "error": "Failed to get response from AI. Make sure Ollama is running.",
    "details": "Connection refused"
}
```

### Other OpenAI-Compatible Endpoints Available

The `openai-php/client` package supports many more endpoints that Ollama also partially implements:

| Endpoint | Laravel Facade Call | Ollama Support | Use Case |
|---|---|---|---|
| **Chat Completions** | `OpenAI::chat()->create([...])` | ✅ Full | Conversational AI (what we use) |
| **Completions** (legacy) | `OpenAI::completions()->create([...])` | ✅ Full | Text completion (older API) |
| **Embeddings** | `OpenAI::embeddings()->create([...])` | ✅ Full | Convert text to vectors for search/RAG |
| **Models List** | `OpenAI::models()->list()` | ✅ Full | List available models |
| **Images** | `OpenAI::images()->create([...])` | ❌ No | Generate images (DALL-E) |
| **Audio/TTS** | `OpenAI::audio()->speech([...])` | ❌ No | Text-to-speech |
| **Audio/Transcription** | `OpenAI::audio()->transcribe([...])` | ❌ No | Speech-to-text (Whisper) |
| **Moderations** | `OpenAI::moderations()->create([...])` | ❌ No | Content moderation |
| **Files** | `OpenAI::files()->upload([...])` | ❌ No | Upload training data |
| **Fine-tuning** | `OpenAI::fineTuning()->create([...])` | ❌ No | Fine-tune models |
| **Assistants** | `OpenAI::assistants()->create([...])` | ❌ No | Stateful assistants |

### Example: Adding an Embeddings Endpoint

If you wanted to add semantic search, you could add:
```php
// In a new controller method:
$response = OpenAI::embeddings()->create([
    'model' => 'nomic-embed-text',    // Ollama embedding model
    'input' => 'Your search text',
]);
$vector = $response->embeddings[0]->embedding;  // Array of floats
```

### Example: Listing Available Ollama Models

```php
$models = OpenAI::models()->list();
foreach ($models->data as $model) {
    echo $model->id;  // "phi3", "llama3", "mistral", etc.
}
```

---

## Summary

| Aspect | Our Choice | Why |
|---|---|---|
| **AI Package** | `openai-php/laravel` | Clean Facade, OpenAI-compatible, works with Ollama |
| **AI Provider** | Ollama (local) | Free, private, no API key needed |
| **Model** | phi3 | Lightweight, runs on modest hardware |
| **Frontend** | Vue 3 | Reactive UI, no page reloads, perfect for chat |
| **API Pattern** | Laravel API route + JSON | Decoupled frontend/backend |
| **Bridge** | Blade serves shell, Vue runs chat | Best of both worlds |
