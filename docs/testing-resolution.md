# Tester resolution — Tests 001 and 002

## Test 002

- **SUPERADMIN/admin inputs lose focus after one word:** root cause was `Modal`'s focus-management effect depending on an inline `onClose` callback. Every controlled-input render changed that callback, ran effect cleanup, restored prior focus, and re-focused the first field. The effect now mounts once and reads the latest callback through a ref. The camera scanner similarly stores callback refs so receiver state renders do not restart the hardware scanner.
- **Invitation / QR actions:** invitation generation now reports progress and popup blocking with the usable URL; QR regeneration explicitly warns that the old token is revoked, reports progress, reloads the guest state, and SVG download uses a DOM-attached anchor with delayed object-URL cleanup and success feedback. API owner/event/guest checks and transactional single-active-token behavior remain unchanged.
- **Receiver / staff:** Validate is disabled without event/token and while validating, progress is visible, scanner failures distinguish denied permission, missing camera, and HTTPS/device contention. QR parsing accepts plain tokens, `/g/`, `/api/g/`, `/invite/`, `/qr/`, and `?token=` URL payloads. API validation continues to reject another event, revoked/unknown tokens, inactive events, unauthorized receivers, and duplicates before confirm.
- **Navigation / lists:** sidebar now has Overview, Events, and Guests. Overview limits event cards to 3 and guest records to 5 with links to dedicated full-list routes; dedicated routes preserve management controls.
- **Selected event:** the single conspicuous picker below the shared sidebar logo persists a validated numeric event id in localStorage and synchronizes it across tabs/components. All duplicate page-level event selectors were removed. Every admin page and the invitation builder consumes the shared selection; missing/inaccessible selections fall back to the first API-scoped event returned for that authenticated owner/superadmin.

## Test 001 regression coverage

Existing feature suites continue to cover authentication/account lifecycle, owner and event isolation, event/guest CRUD, invitation and QR generation/download/revoke/regenerate, receiver assignment/activation, receiver cross-event and duplicate check-in protection, attendance/guest book, photos, billing, WhatsApp dry-run/contracts, public invitation/RSVP, and invitation design.

## Device-only checks remaining

A physical secure-context browser is still required to verify rear-camera selection, permission prompts after denial/reset, autofocus/low-light scan behavior, and scanning printed/screenshotted QR codes on representative Android/iOS devices. No real WhatsApp/email operation is part of this resolution.
