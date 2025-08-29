# Leave Assistant (Chatbot) for OrangeHRM Leave Module

- Purpose: Guidance-only chatbot for employees about leave policies, balances, and workflows.
- Scope: No data writes, no approvals; answers FAQs and shows how to use built-in Leave features.

## Open-source tools used
- In-repo, rule-based assistant using:
  - PHP YAML (Symfony Yaml) to store FAQs: `plugins/orangehrmLeavePlugin/config/assistant-faq.yaml`.
  - Existing OrangeHRM services/APIs for non-sensitive info pointers (no direct reads of personal data shown).
- Optional (local LLM): Ollama (free/open-source) + small instruct model (e.g., Llama 3.2/8B). Not required.

## What was added
- API: `POST /api/v2/leave/assistant/chat` handled by `OrangeHRM\\Leave\\Api\\LeaveAssistantAPI`.
- Service: `OrangeHRM\\Leave\\Service\\LeaveAssistantService` (loads FAQ YAML, rule-based intents).
- Frontend widget: `src/client/src/orangehrmLeavePlugin/components/LeaveAssistantWidget.vue`.
- UI integration: widget mounted on `Apply Leave` and `My Leave List` screens.

## How to run
1) Build the client assets (if not already):
   - Requires Node 16+ and Yarn (repo already uses Yarn 4).
   - Windows PowerShell:
     ```powershell
     cd src/client; yarn install; yarn build
     ```
   - Built assets go to `web/dist`.
2) Ensure web app is running (Apache/PHP per your setup). Visit:
   - Leave > Apply Leave
   - Leave > My Leave
   You should see the "Leave Assistant" bubble at bottom-right.

## Using the assistant
- Try questions like:
  - "How do I apply for leave?"
  - "How can I cancel a leave request?"
  - "Where do I view my leave requests?"
  - "What is my leave balance?"
- The assistant replies with steps and links to the relevant screens. No data is modified.

## Configuration
- FAQs live at `plugins/orangehrmLeavePlugin/config/assistant-faq.yaml`.
  - Update/add entries to refine policy answers for your org.
- No external services are required by default.

## Optional: Local LLM via Ollama (advanced)
- This is optional and off by default. If enabled, the service can be extended to send the question to a local Ollama instance (http://localhost:11434) and combine with FAQs.
- Steps (optional):
  - Install Ollama and pull a small instruct model: `ollama pull llama3.2:latest`.
  - Extend `LeaveAssistantService::answer()` to call Ollama when available, with strict guardrails (no writes).
  - Keep this behind a config flag to preserve a no-external-dependency default.

## Notes and limitations
- Guidance-only by design; it doesn’t reveal personal balances directly, but points to where to find them.
- Keep `assistant-faq.yaml` up to date with your leave policy for best answers.
