<!-- eslint-disable -->
<template>
  <div class="ohrm-leave-assistant">
    <button class="assistant-toggle" @click="open = !open">
      💬 Leave Assistant
    </button>
    <div v-if="open" class="assistant-panel">
      <div class="assistant-header">
        <strong>Leave Assistant</strong>
        <span class="spacer"></span>
        <button class="assistant-close" @click="open = false">✖</button>
      </div>
      <div class="assistant-body" ref="scrollRef">
        <div v-for="(m, i) in messages" :key="i" :class="['msg', m.role]">
          <div class="bubble">
            <div v-html="m.content" />
          </div>
        </div>
        <div v-if="loading" class="loading">Thinking…</div>
      </div>
      <div class="assistant-input">
  <input v-model="input" :placeholder="placeholder" @keyup.enter="send" />
        <button :disabled="!input || loading" @click="send">Send</button>
  <button class="secondary" :disabled="loading" @click="clearChat">Clear</button>
      </div>
      <div class="assistant-footer">
        <small>Local, guidance-only. No data changes.</small>
      </div>
    </div>
  </div>
</template>

<script>
import { APIService } from "@ohrm/core/util/services/api.service";

export default {
  name: "LeaveAssistantWidget",
  props: {
    placeholder: {
      type: String,
      default: "Ask about leave balance, how to apply, cancel or view leave…",
    },
  },
  data() {
    return {
      open: false,
      input: "",
      loading: false,
      messages: [
        {
          role: "assistant",
          content:
            "Hi! I can help with leave policies, balances, and how to apply or cancel. What do you need?",
        },
      ],
      // Ensure API calls include the app base path (e.g., /colonyxt)
      api: new APIService(
        window.appGlobal?.baseUrl || "/",
        "/api/v2/leave/assistant/chat"
      ),
    };
  },
  methods: {
    async send() {
      const text = this.input.trim();
      if (!text) return;
      // quick local reset command
      if (text.toLowerCase() === "/clear") {
        this.clearChat();
        this.input = "";
        return;
      }
      this.messages.push({ role: "user", content: this.escape(text) });
      this.input = "";
      this.loading = true;
      try {
        const res = await this.api.create({ message: text });
        const answer =
          res.data?.data?.answer || "Sorry, I could not find that.";
        this.messages.push({
          role: "assistant",
          content: this.linkify(this.escape(answer)),
        });
      } catch (e) {
        this.messages.push({
          role: "assistant",
          content: "Error reaching assistant API.",
        });
      } finally {
        this.loading = false;
        this.$nextTick(() => this.scrollToEnd());
      }
    },
    clearChat() {
      this.messages = [
        {
          role: "assistant",
          content:
            "Chat cleared. Hi! I can help with leave policies, balances, and how to apply or cancel. What do you need?",
        },
      ];
      this.$nextTick(() => this.scrollToEnd());
    },
    scrollToEnd() {
      const el = this.$refs.scrollRef;
      if (el) el.scrollTop = el.scrollHeight;
    },
    escape(s) {
      return s
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
    },
    linkify(s) {
      // turn simple /leave/... into links
      return s.replace(
        /(\s\/leave\/[\w/-]+)/g,
        (m) => ` <a href="${m.trim()}">${m.trim()}</a>`
      );
    },
  },
};
</script>

<style scoped>
.ohrm-leave-assistant {
  position: fixed;
  right: 16px;
  bottom: 16px;
  z-index: 1000;
}

.assistant-toggle {
  background: #ff7f32;
  color: #fff;
  border: none;
  border-radius: 20px;
  padding: 10px 14px;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.assistant-panel {
  width: 360px;
  height: 460px;
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
}

.assistant-header {
  padding: 10px;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: center;
}

.assistant-header .spacer {
  flex: 1;
}

.assistant-close {
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 14px;
}

.assistant-body {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
  background: #fafafa;
}

.assistant-input {
  display: flex;
  gap: 8px;
  padding: 8px;
  border-top: 1px solid #eee;
}

.assistant-input input {
  flex: 1;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
}

.assistant-input button {
  background: #ff7f32;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
}

.assistant-input button.secondary {
  background: #f0f0f0;
  color: #333;
}

.assistant-footer {
  padding: 6px 10px;
  text-align: center;
  color: #666;
  font-size: 12px;
}

.msg {
  display: flex;
  margin: 6px 0;
}

.msg.user {
  justify-content: flex-end;
}

.msg.assistant {
  justify-content: flex-start;
}

.bubble {
  max-width: 80%;
  padding: 8px 10px;
  border-radius: 12px;
  white-space: pre-wrap;
  word-break: break-word;
}

.msg.user .bubble {
  background: #d1e7ff;
  color: #1b3a57;
  border-bottom-right-radius: 4px;
}

.msg.assistant .bubble {
  background: #fff;
  border: 1px solid #eee;
  color: #333;
  border-bottom-left-radius: 4px;
}

.loading {
  text-align: center;
  color: #888;
  font-style: italic;
}
</style>
