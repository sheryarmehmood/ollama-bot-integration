# Ollama Bot Integration

This project is a Laravel + Vue chat application that sends messages to an Ollama-compatible endpoint using the OpenAI-compatible API. It can be run on Windows, macOS, or Linux with the same basic workflow.

## Requirements

Make sure these are installed on your system:

- PHP 8.2 or newer
- Composer
- Node.js 18+ and npm
- Optional: Ollama installed locally with a model such as `phi3` or `llama3.2`

## 1. Clone and install dependencies

```bash
git clone <your-repository-url>
cd ollama-bot-itegration
composer install
npm install
```

If you are using PowerShell on Windows, the same commands work. If you prefer copying the example environment file manually, use:

```powershell
Copy-Item .env.example .env
```

On macOS/Linux, use:

```bash
cp .env.example .env
```

## 2. Configure environment variables

Create or update your `.env` file with the application key and AI provider settings:

```bash
php artisan key:generate
```

Then set these values in `.env`:

```env
OPENAI_API_KEY=ollama
OPENAI_BASE_URL=http://localhost:11434/v1
```

These values allow the app to talk to a local Ollama server. If you want to use OpenAI or another compatible provider instead, change the values accordingly.

## 3. Prepare the database

```bash
php artisan migrate
```

The project uses SQLite by default through the example environment file.

## 4. Start Ollama (optional but required for local models)

If you want the chatbot to use a local model, make sure Ollama is running:

```bash
ollama serve
```
some system runs ollama server continue in background, if it is installed on local

check in your system
ollama --version


You can replace `phi3` with any model you have installed.

## 5. Run the application

Open two terminals.

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Then open:

```text
http://localhost:8000
```

If you want the Laravel server to be reachable from other devices on your local network, you can run:

```bash
php artisan serve --host=0.0.0.0
```

## 6. Build for production

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

- If the page loads but styles or assets do not appear, run `npm run build`.
- If chat requests fail, confirm that Ollama is running and reachable at `http://localhost:11434`.
- If you see database errors, make sure the SQLite file is writable and that migrations completed successfully.

## Notes

This app is designed to work with any OpenAI-compatible API endpoint. The core code does not need to change when switching providers; only the `.env` values need to be updated.
