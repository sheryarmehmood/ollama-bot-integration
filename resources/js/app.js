import './bootstrap';
import { createApp } from 'vue';
import ChatBot from './components/ChatBot.vue';

const app = createApp({});
app.component('chat-bot', ChatBot);
app.mount('#app');
