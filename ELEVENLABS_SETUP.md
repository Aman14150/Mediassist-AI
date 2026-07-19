# ElevenLabs voice-agent setup

Use the complete agent prompt in [ELEVENLABS_SYSTEM_PROMPT.md](ELEVENLABS_SYSTEM_PROMPT.md). Upload only the five curated files in the `elevenlabs-knowledge-base` directory; do not upload older copies because they can conflict with live fees and current routing.

Use **server-side Webhook tools**, not client tools. Replace `https://hospital.example.com` below with the HTTPS origin in `APP_URL`. No custom header is required: these routes use rate limits, signed short-lived slot confirmation tokens, explicit patient confirmation, and database constraints. Never paste application secrets into the website JavaScript or knowledge-base files.

An MCP server is not required for this project. The booking backend currently exposes two authenticated REST endpoints, so Webhook tools are the direct and lower-complexity integration. Do not attach a third-party MCP server to patient booking data. Build a dedicated authenticated MCP server only if a future integration specifically requires MCP and has received a separate privacy/security review.

ElevenLabs' current dashboard flow is Agent → Tools → Add Tool → Webhook. Configure the request body content type as `application/json`, leave request headers empty, and set a response timeout around 10 seconds.

## Tool 1: `check_appointment_availability`

- Description: `Checks live Medicare Hospital appointment slots. Call only after the patient has confirmed a department, date, and preferred time or time window. Include a doctor only when requested. Match the preferred time and offer at most two relevant returned choices; never read every doctor or slot. This tool never books.`
- Method: `POST`
- URL: `https://hospital.example.com/api/elevenlabs/check-availability.php`
- Authentication: none; leave request headers empty
- Body parameters:

| Identifier | Type | Required | LLM description |
|---|---|---:|---|
| `department` | string | Yes | Exact department name or slug from the knowledge base, for example `General Physician` or `general-physician`. |
| `date` | string | Yes | Appointment date in `YYYY-MM-DD` format. Resolve relative dates before calling. Sundays are closed. |
| `doctor` | string | No | Exact doctor name or numeric doctor ID. Omit if the patient has no preference; the tool returns up to three doctors. |

Example body:

```json
{
  "department": "General Physician",
  "date": "2026-07-20",
  "doctor": "Dr. Nitin Patel"
}
```

Each returned slot has `time`, `display_time`, and `confirmation_token`. Retain the token for the patient's chosen slot; it is required by the create tool and expires after 10 minutes.

## Tool 2: `create_appointment`

- Description: `Creates one Medicare Hospital appointment. After an exact slot is selected, read back patient name, department, doctor, date, time, and live fee, then ask the final confirmation question. Call only after a new explicit yes to that summary. Earlier words such as book, okay, slot selection, or contact details are not confirmation. Pass the exact slot confirmation_token.`
- Method: `POST`
- URL: `https://hospital.example.com/api/elevenlabs/create-appointment.php`
- Authentication: none; leave request headers empty
- Body parameters:

| Identifier | Type | Required | LLM description |
|---|---|---:|---|
| `patient_name` | string | Yes | Patient's full name. |
| `phone` | string | Yes | Patient phone number, including country code when available. |
| `email` | string | No | Valid email address; omit if not provided. |
| `department` | string | Yes | Same department used for the availability check. |
| `doctor` | string | Yes | Exact doctor name or ID selected from availability results. |
| `date` | string | Yes | Confirmed date in `YYYY-MM-DD`. |
| `time` | string | Yes | Confirmed returned `time` in 24-hour `HH:MM` format, not `display_time`. |
| `confirmation_token` | string | Yes | Opaque token from the exact chosen availability result; copy unchanged. |
| `confirmed` | boolean | Yes | Set to `true` only after the patient explicitly confirms the complete booking summary. Never infer confirmation. |
| `notes` | string | No | Non-sensitive administrative note, maximum 500 characters. Do not collect diagnosis or payment credentials. |

## Agent prompt addition

Add this after the existing hospital knowledge/routing instructions:

```text
APPOINTMENT BOOKING WORKFLOW
1. Help the patient choose only a listed department. This is routing, not diagnosis.
2. Collect a date and preferred time or time window before calling check_appointment_availability. Never claim a slot is free from the knowledge base alone.
3. Offer at most two relevant doctors or slots returned by the tool. If the preferred time is unavailable, offer at most two nearest alternatives. Keep the confirmation_token for the chosen slot private; never read it aloud.
4. Collect full name and phone; email is optional. Do not ask for card, UPI, bank, OTP, PIN, or medical-record details.
5. Read back: patient name, department, doctor, date, time, the `consultation_fee_inr` returned by the availability tool, that the amount is payable at the hospital, and that no payment is due now. Never use a memorized fee. Ask: "Would you like me to confirm this appointment?"
6. Wait for a new explicit yes after the complete read-back and final question before calling create_appointment. Earlier words such as book or okay do not count. Set confirmed=true. Silence, uncertainty, a request to change details, or a disconnected call is not confirmation.
7. On success, read the booking_reference slowly. On HTTP 409 or slot errors, apologize, call check_appointment_availability again, and offer new slots. Never retry create_appointment blindly.
8. For emergencies, direct the patient to +91 2143658790 or the nearest emergency department. Do not delay emergency care to book an appointment.
```

## Test checklist

1. Ask for a Monday General Physician slot. Verify the agent calls availability before stating times.
2. Say “no” at the final summary. Verify no appointment appears in the database using the approved local or server-side administration method.
3. Confirm a booking. Verify the spoken reference matches the stored appointment record.
4. Attempt the same doctor/date/time again. Verify the agent receives a conflict and offers remaining slots.
5. Try the same patient phone at the same date/time with another doctor. Verify duplicate-patient protection returns a conflict.
6. Try Sunday and an unlisted time. Verify both are rejected.
7. Remove or alter the bearer secret. Verify both tool endpoints return HTTP 401.

For the website agent itself, ElevenLabs recommends signed URLs for authenticated client sessions or a hostname allowlist for public agents; do not expose an ElevenLabs API key in HTML/JavaScript. Add the widget only after choosing that access model and obtaining the real agent ID.

Because calls can contain patient contact details, enable the agent privacy controls appropriate to your account, including conversation-history redaction for names, phone numbers, and email addresses where available. Set a retention policy with the hospital before real patient use.
