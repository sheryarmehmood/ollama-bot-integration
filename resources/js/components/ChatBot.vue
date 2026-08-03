<template>
    <div class="chat-container">
        <div class="chat-header">
            <h1>🤖 AI ChatBot</h1>
            <p>Powered by Ollama (Local LLM)</p>
        </div>

        <div class="chat-messages" ref="messagesContainer">
            <div v-if="messages.length === 0" class="empty-state">
                <p>👋 Hello! Ask me anything.</p>
            </div>

            <div
                v-for="(msg, index) in messages"
                :key="index"
                :class="['message', msg.role]"
            >
                <div class="message-avatar">
                    {{ msg.role === 'user' ? '👤' : '🤖' }}
                </div>
                <div class="message-content">
                    <div class="message-text" v-html="formatMessage(msg.content)"></div>
                </div>
            </div>

            <div v-if="loading" class="message assistant">
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    <div class="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="error" class="error-bar">
            {{ error }}
            <button @click="error = null">✕</button>
        </div>

        <form class="chat-input" @submit.prevent="sendMessage">
            <input
                v-model="userMessage"
                type="text"
                placeholder="Type your message..."
                :disabled="loading"
                ref="inputField"
                maxlength="2000"
            />
            <button type="submit" :disabled="loading || !userMessage.trim()">
                <span v-if="!loading">Send</span>
                <span v-else>...</span>
            </button>
        </form>
    </div>
</template>

<script>
import { ref, nextTick, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'ChatBot',
    setup() {
        const messages = ref([]);
        const userMessage = ref('');
        const loading = ref(false);
        const error = ref(null);
        const messagesContainer = ref(null);
        const inputField = ref(null);

        const scrollToBottom = async () => {
            await nextTick();
            if (messagesContainer.value) {
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
        };

        const formatMessage = (text) => {
            return text
                .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        };

        const sendMessage = async () => {
            const msg = userMessage.value.trim();
            if (!msg || loading.value) return;

            error.value = null;
            messages.value.push({ role: 'user', content: msg });
            userMessage.value = '';
            loading.value = true;
            scrollToBottom();

            try {
                const history = messages.value
                    .filter(m => m.role !== 'system')
                    .map(m => ({ role: m.role, content: m.content }));

                const response = await axios.post('/api/chat', {
                    message: msg,
                    history: history.slice(0, -1),
                });

                messages.value.push({
                    role: 'assistant',
                    content: response.data.reply,
                });
            } catch (err) {
                const errorMsg = err.response?.data?.error || 'Something went wrong. Is Ollama running?';
                error.value = errorMsg;
            } finally {
                loading.value = false;
                scrollToBottom();
                inputField.value?.focus();
            }
        };

        onMounted(() => {
            inputField.value?.focus();
        });

        return {
            messages,
            userMessage,
            loading,
            error,
            messagesContainer,
            inputField,
            sendMessage,
            formatMessage,
        };
    },
};
</script>

<style scoped>
.chat-container {
    max-width: 800px;
    margin: 0 auto;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #1a1a2e;
    color: #eee;
    font-family: 'Segoe UI', system-ui, sans-serif;
}

.chat-header {
    padding: 20px;
    text-align: center;
    background: #16213e;
    border-bottom: 1px solid #0f3460;
}

.chat-header h1 {
    margin: 0;
    font-size: 1.5rem;
}

.chat-header p {
    margin: 4px 0 0;
    font-size: 0.85rem;
    color: #888;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #666;
    font-size: 1.2rem;
}

.message {
    display: flex;
    gap: 12px;
    max-width: 85%;
    animation: fadeIn 0.3s ease;
}

.message.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.message.assistant {
    align-self: flex-start;
}

.message-avatar {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.message-content {
    padding: 12px 16px;
    border-radius: 16px;
    line-height: 1.5;
}

.message.user .message-content {
    background: #0f3460;
    border-bottom-right-radius: 4px;
}

.message.assistant .message-content {
    background: #222;
    border-bottom-left-radius: 4px;
}

.message-text :deep(pre) {
    background: #111;
    padding: 10px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 8px 0;
}

.message-text :deep(code) {
    font-family: 'Fira Code', monospace;
    font-size: 0.9em;
}

.error-bar {
    padding: 10px 16px;
    background: #d32f2f;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.error-bar button {
    background: none;
    border: none;
    color: #fff;
    cursor: pointer;
    font-size: 1rem;
}

.chat-input {
    display: flex;
    padding: 16px;
    gap: 10px;
    background: #16213e;
    border-top: 1px solid #0f3460;
}

.chat-input input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #0f3460;
    border-radius: 24px;
    background: #1a1a2e;
    color: #eee;
    font-size: 1rem;
    outline: none;
    transition: border-color 0.2s;
}

.chat-input input:focus {
    border-color: #e94560;
}

.chat-input button {
    padding: 12px 24px;
    background: #e94560;
    color: #fff;
    border: none;
    border-radius: 24px;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
}

.chat-input button:hover:not(:disabled) {
    background: #c73650;
}

.chat-input button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 4px 0;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #666;
    border-radius: 50%;
    animation: bounce 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

@keyframes bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
