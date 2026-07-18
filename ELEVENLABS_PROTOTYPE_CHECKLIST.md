# MediAssist ElevenLabs Prototype Configuration

## Knowledge Base

Delete the seven older uploaded documents. Upload only the five `.txt` files in `elevenlabs-knowledge-base`.

- Set every document to **Auto/RAG**, not Prompt.
- Wait for indexing to finish before testing.
- Do not upload the old files alongside these files; duplicated facts can produce inconsistent answers and use more context.

### RAG configuration

- Embedding model: **English optimized**
- Character limit: **6000**
- Chunk limit: **5**
- Vector distance limit: **0.5**
- ANN candidates evaluated: **100**
- Query rewrite prompt: **keep the ElevenLabs default unchanged**

## Agent

- First message: `Hello, welcome to Medicare Hospital. I am MediAssist AI. Would you like to book an appointment or ask a hospital question?`
- System prompt: paste the complete contents of `ELEVENLABS_SYSTEM_PROMPT.md`.
- Preferred LLM: **Gemini 2.5 Flash Lite**. If tool tests are unreliable, use **Gemini 2.5 Flash**.
- Temperature: **0.2**.
- Reasoning/thinking effort: **None/Disabled**.
- Backup LLMs: **Default**.
- Response style: one or two short sentences; one question at a time.
- Language: English. Enable language detection only when multilingual support is actually tested.

## Voice and conversation flow

- Use a current multilingual voice model; do not use removed v1 models.
- Turn model: **turn_v3**.
- Take turn after silence: **8 seconds**.
- Soft timeout: **Disabled** for the free-tier prototype. A generated filler can add unnecessary inference.
- Allow normal caller interruptions, but disable interruptions while a booking tool is running.

## Tools

Keep exactly two webhook tools:

1. `check_appointment_availability`
   - POST `/api/elevenlabs/check-availability.php`
   - JSON body: department and date required; doctor optional
   - Timeout: 20 seconds
   - No custom headers

2. `create_appointment`
   - POST `/api/elevenlabs/create-appointment.php`
   - JSON body: patient name, phone, department, doctor, date, time, confirmation token, and `confirmed=true`; email and notes optional
   - Use the exact confirmation token from the selected live slot
   - Call only after the caller explicitly confirms the complete read-back
   - No custom headers

Enable only the **End conversation** system tool. Keep transfer, voicemail, DTMF, skip-turn, and update-state tools off for this web prototype.

## Privacy and widget

- Widget starts collapsed, compact, bottom-right.
- Require acceptance of prototype terms before a call.
- Terms must say it is an AI prototype, not emergency care, not medical diagnosis or treatment, and voice/transcript data may be processed by ElevenLabs.
- Do not collect age, address, diagnosis, records, identity documents, or payment credentials.
- Disable audio saving if available.
- Set transcript and audio retention to **0 days** for maximum-privacy prototype testing, unless conversation review is required. If reviewing tests, use the shortest practical retention and never enter real patient data.

## Required tests before publishing

1. Hospital hours and services question.
2. Psychology routing for overthinking.
3. Migraine routing to Neurology.
4. Fever routing to General Physician and child fever to Pediatrics.
5. Chest pain emergency response with no booking attempt.
6. Suicide/self-harm emergency response with no routine booking.
7. Successful availability check and explicit-confirmation booking.
8. Caller says no at confirmation; no booking is created.
9. Tool failure; agent does not invent a slot or fee.
10. Verify the created booking in Azure SQL.

Publish only after all ten tests pass.
