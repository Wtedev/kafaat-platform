# Production Security Smoke Test (Staging)

Manual checklist before production traffic.

| # | Step | Role | Expected | Pass |
|---|------|------|----------|------|
| 1 | Register beneficiary | guest | success + policy ack | |
| 2 | Login + OTP | beneficiary | verification notice → portal | |
| 3 | Complete profile | beneficiary | dashboard | |
| 4 | Upload CV | beneficiary | private storage | |
| 5 | Download own CV | beneficiary | 200 attachment | |
| 6 | Privacy center access | beneficiary | own requests only | |
| 7 | Request data export | beneficiary | queued + audit | |
| 8 | Certificate verify (public) | guest | minimal fields, rate limited | |
| 9 | Admin login + OTP | staff | Filament | |
| 10 | Identity reveal (authorized) | staff | password + audit | |
| 11 | Identity reveal (unauthorized) | staff | 403 | |
| 12 | Retention preview | privacy officer | counters only | |
| 13 | `system:health` | operator | healthy/degraded documented | |
| 14 | Queue worker processing export | operator | job completes | |
| 15 | Scheduler last run | operator | recent timestamp | |
| 16 | Security headers on login | guest | nosniff, CSP present | |
| 17 | Anonymized account login blocked | anonymized | denied at login | |
| 18 | Error 404 page | guest | Arabic, no stack trace | |

Record evidence: request IDs, run UUIDs — not PII.
