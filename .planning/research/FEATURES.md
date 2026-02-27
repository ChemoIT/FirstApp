# Feature Landscape

**Domain:** Signature Dispatch / E-Signing Web Application
**Project:** FirstApp (Learning Project)
**Researched:** 2026-02-27
**Overall Confidence:** HIGH — E-signing feature space is mature and well-understood; categorization based on established UX conventions and the scoped learning context.

---

## Framing: Two Lenses

This project sits at the intersection of two categories:
1. **Signature Dispatch** — the sender's side (login, trigger SMS, track completion)
2. **E-Signature Capture** — the recipient's side (open link, draw, confirm)

The features below are categorized for BOTH roles. Because this is a learning project, "table stakes" means "required for the core loop to work at all," not "required to compete commercially."

---

## Table Stakes

Features the app cannot function without. Missing any of these = the app is broken.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Login / Auth gate | Dispatch page must be protected — anyone could trigger paid SMS | Low | Single hardcoded user (Sharonb/1532) via PHP session. No registration flow needed. |
| Dispatch trigger button | The entire sender-side value in one action | Low | POST to PHP script that calls Micropay API. |
| SMS with unique link | Recipient must receive a URL to the signing page | Low | PHP generates a URL (can be static for now), sends via Micropay GET request. |
| Mobile signature canvas | Touch/finger drawing is the core UX on recipient side | Medium | HTML5 Canvas + touch events. Pointer events API handles both mouse and touch. |
| Clear / redo action | Users will make mistakes drawing; no clear = frustrating | Low | Single JS function to clear canvas context. |
| Submit / save signature | The signature must actually be captured server-side | Medium | Canvas.toDataURL() → POST base64 → PHP decodes and saves as PNG. |
| Confirmation to recipient | Recipient needs to know the signature was received | Low | Static "thank you" page shown after successful POST, or alert. |
| Confirmation SMS to sender | Sender needs to know the signature was captured | Low | PHP sends second SMS ("המסמך נחתם") after file save succeeds. |
| Error handling (visible) | Failed SMS / failed save must surface to user, not silently fail | Low | PHP returns JSON; JS shows error message in UI. |
| Logout | Session must be clearable | Low | Single link calls session_destroy(). |

**Dependency chain:**
```
Login → [session] → Dispatch Page → [POST to PHP] → SMS with link → [recipient opens]
→ Signature Canvas → [submit] → PHP saves PNG → Confirmation SMS → Sender sees confirmation
```

Every item in this chain is table stakes. Breaking any link = broken loop.

---

## Differentiators

Features that would improve the experience but are NOT required for the core loop. Reasonable to add as stretch goals for a learning project — each one teaches a specific concept.

| Feature | Value Proposition | Complexity | What It Teaches |
|---------|-------------------|------------|-----------------|
| Timestamp on saved PNG | Audit trail — when was the signature captured | Low | PHP date() functions, file naming conventions |
| Signature preview before submit | Reduces "oops I submitted a bad signature" frustration | Low | JS canvas snapshot, conditional UI state |
| Pen thickness / color selector | Makes drawing feel more polished | Low | JS state management, Canvas strokeStyle |
| Success page with signature image | Recipient sees what was saved — closes the loop visually | Low | PHP serving image file, base64 or direct URL |
| Sender sees signature in browser | Sharon can view the saved PNG without FTP | Medium | PHP file listing + img tag, simple file manager |
| Signing link expiry | Link becomes invalid after N minutes or after use | Medium | PHP timestamp in URL, server-side validation |
| Unique token per dispatch | Each dispatch gets its own link — prevents replay | Medium | PHP uniqid(), token stored in flat file or session |
| Responsive layout (CSS) | Page works on both desktop and mobile cleanly | Low | CSS media queries, flexbox/grid basics |
| Loading state on buttons | Buttons disable + show spinner during async operations | Low | JS disabled attribute, CSS animation |
| Log file of dispatches | Flat file (CSV or JSON) recording who sent what when | Medium | PHP file append, structured data |

**Recommended stretch goals for this learning project (in order):**
1. Signature preview before submit — teaches JS state, low effort, big UX win
2. Timestamp in saved filename — teaches PHP date, zero complexity
3. Unique token per dispatch — teaches security thinking, medium complexity

---

## Anti-Features

Things to explicitly NOT build in this project. These are either too complex for a learning context, out of scope by design, or solve problems this app doesn't have.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Database (MySQL) | Adds a full new layer of complexity (schema, queries, connection) for zero learning benefit here | Flat files (PNG per signature, maybe a CSV log) |
| User registration / multiple users | Auth flows, password hashing, email verification — none of this is needed for one user | Single hardcoded credential |
| Password reset / forgot password | Requires email or SMS second factor, backend token management | Not needed for single user |
| Document upload / PDF overlay | PDF rendering, coordinate mapping, legal compliance — an entirely separate product | Signature capture only; no document context needed |
| Digital certificate / PKI | Legal-grade signing (DocuSign-level) requires CA integration, X.509 certs | Out of scope; this is a learning project |
| Audit trail / compliance logging | SOC2, eIDAS, ESIGN Act compliance — enterprise-grade requirements | Not needed; not a legal signing product |
| Email notifications | Adds SMTP configuration, another API credential to manage | SMS only via Micropay |
| Multi-recipient dispatch | Queue management, status tracking per recipient — a workflow engine | Single recipient hardcoded; one dispatch at a time |
| Real-time status (WebSockets) | Requires persistent connection, server push — advanced async concept | Poll or just wait for confirmation SMS |
| Admin dashboard | Reporting, analytics, user management — a product layer above the core | Not needed; single operator |
| Mobile app (native) | Swift/Kotlin — completely different tech stack | Mobile browser is sufficient for touch signature |
| Rate limiting / abuse protection | Important for production; adds complexity without teaching benefit here | cPanel/.htaccess can handle basic limits if needed later |
| GDPR / data deletion flows | Regulatory compliance layer | Out of scope for learning project |
| Signature verification / tamper detection | Hash-based integrity checking, cryptographic signatures | Out of scope; file system is trusted |
| Multi-language | Internationalization (i18n) adds complexity | Hebrew only, RTL layout |

---

## Feature Dependencies

```
# Core chain — every item requires the item above it
Login (PHP session)
  └── Dispatch Page (session_check)
        └── SMS Dispatch (Micropay API call)
              └── Signing Link (URL in SMS body)
                    └── Signature Canvas (HTML5 Canvas + touch events)
                          └── Save to Server (PHP base64 decode + file_put_contents)
                                └── Confirmation SMS (second Micropay call)

# Independent additions (no blockers)
Logout           ← needs: Login
Clear Button     ← needs: Signature Canvas
Error Messages   ← needs: any PHP endpoint

# Stretch goals (layered on top of core)
Signature Preview       ← needs: Signature Canvas
Timestamp in filename   ← needs: Save to Server
Unique token per link   ← needs: SMS Dispatch + Signing Link
Sender views PNG        ← needs: Save to Server
```

---

## MVP Recommendation

For a learning project, the MVP is exactly the core loop. Nothing more.

**Build in this order:**
1. Login page + PHP session check (teaches: forms, server-side auth, sessions)
2. Dispatch page + SMS send button (teaches: PHP API calls, HTTP GET, credentials)
3. Signing page + Canvas + touch events (teaches: HTML5 Canvas, JavaScript events, mobile UX)
4. Save signature as PNG (teaches: base64 decode, file I/O, PHP POST handler)
5. Confirmation SMS after save (teaches: conditional logic, chained API calls)
6. Error handling visible in UI (teaches: JSON responses, JS fetch, error states)

**Defer until core loop works:**
- Signature preview (nice polish, add after step 3 works)
- Unique tokens per dispatch (add after step 5 works — teaches security)
- Log file of dispatches (add last — teaches data persistence patterns)

**Never build (for this project):**
- Anything in the Anti-Features table above

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Table stakes | HIGH | Core loop is defined by the PROJECT.md; no ambiguity |
| Differentiators | HIGH | Standard e-signing UX patterns; well-established by industry |
| Anti-features | HIGH | Complexity is objectively higher; scope is clearly defined in PROJECT.md |
| Feature ordering | HIGH | Dependencies are deterministic — each step enables the next |

WebSearch was unavailable during this research session. However, the e-signing domain is mature (DocuSign, Adobe Sign, HelloSign have existed 10+ years), and the feature taxonomy is stable. The learning project scope further reduces uncertainty — the PROJECT.md already makes explicit what is and is not in scope. No significant uncertainty remains.

---

## Sources

- PROJECT.md (primary source of truth for scope and constraints)
- E-signing domain knowledge: HTML5 Canvas API (MDN standard), Micropay SMS API (GET-based token auth), PHP session management (standard PHP documentation patterns)
- Training data: DocuSign, Adobe Sign, HelloSign, PandaDoc feature sets (industry-established by 2024, stable)
- Confidence: HIGH based on mature domain + explicit PROJECT.md scope definition
