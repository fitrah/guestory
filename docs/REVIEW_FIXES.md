# Guestory review fixes (2026-09-14)

No deployment or live message/email sending is part of this change.

1. **Landing demo buttons** — both links now use the seeded, tested invitation token `/invite/invite-demo-budi` instead of the nonexistent `/invite/demo-budi`.
2. **Neutral login** — removed the real email prefill from admin, superadmin, attendance, photos, WhatsApp, and forgot-password screens. Receiver demo credentials remain receiver-specific test data.
3. **Invite / QR and account identity** — actions call the existing owner-scoped endpoints, refresh row state, open the generated invitation in a new safe tab, and retain QR generate/regenerate/SVG download feedback. Added Profile Settings with name and WhatsApp sender/account number, persisted by `PATCH /api/auth/me` and visible in normalized `+62…` form.
4. **Publish / Archive** — buttons call the existing owner-scoped lifecycle endpoints, refresh events/workspace, report API errors, and disable the action matching the current status or when no event is selected.
5. **Lost navigation** — Attendance, Photos, and WhatsApp authenticated views now render the shared full admin sidebar; Settings is included. Existing Billing and Receiver-management sidebars remain navigable to Dashboard.
6. **Add Guest / Create Event** — replaced dense inline forms with labelled modal dialogs, descriptions, cancel/close controls, and explicit submit text.
7. **WhatsApp country code** — UI separates `+62` from a numeric local number and strips non-digits/leading `0` safely. API stores canonical `62…`, validates length/prefix, and WAPI has defensive Indonesian normalization at its boundary.
8. **Guest edit/delete** — added edit modal backed by the existing scoped PATCH endpoint and confirmed delete backed by DELETE, followed by list/metrics refresh.
9. **QR/SVG interoperability investigation** — code/tests establish that QR encodes an absolute `/api/g/{token}` URL, receiver scanner extracts tokens from both `/g/` and `/api/g/`, and public resolver redirects to the invitation/check-in flow. The identifiable defect was origin ambiguity when web and API hosts differ; QR now uses `GUESTORY_API_URL` and invitations use `GUESTORY_WEB_URL`, with regression coverage. SVG remains standards-compliant `image/svg+xml` from Endroid and downloadable. Physical-device/camera scanning cannot be proven in this repository alone; deployment smoke testing across the configured public origins is still required.

## Deployment / smoke checklist (not executed)

1. Set `GUESTORY_WEB_URL` to the public web origin and `GUESTORY_API_URL` to the public API origin; confirm `APP_URL` remains the API origin fallback.
2. Run DB backup, then `php artisan migrate --force` in `apps/api`.
3. Build and release web/API through the normal pipeline; clear/rebuild Laravel config cache.
4. Log in with both SUPERADMIN and EVENT_OWNER; verify each sees only owned events and receiver multi-event assignments remain intact.
5. Create a draft event and guest using `+62 | local number`; edit and delete a disposable guest.
6. Publish, generate/open invitation, generate/regenerate/download SVG, then scan the downloaded SVG and on-screen guest QR with two physical devices.
7. Confirm `/api/g/{token}` resolves from the public API origin and Receiver validates only an assigned event. Archive the disposable event and verify its invitation becomes unavailable.
8. Keep WAPI in dry-run/test mode during smoke; do not send a real message until explicitly approved.
