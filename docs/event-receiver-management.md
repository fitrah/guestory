# Multi-event receiver management

## Authorization model

Guestory uses additive capabilities rather than treating `users.role` as the complete authorization model:

- `SUPERADMIN` is a platform-level capability only.
- Event management is authorized by `events.owner_id`; an event owner remains an owner even when assigned as a receiver elsewhere.
- Receiver access is authorized by an `ACTIVE` row in `event_receivers` for the requested event.
- The legacy `RECEIVER` role remains for receiver-only accounts and backward compatibility, but it does not grant access without an active event assignment.
- A normalized user email identifies one reusable account. Assigning an existing SUPERADMIN or EVENT_OWNER as receiver never changes its role.
- Guests remain records in `guests`, not users. Guest email and identity may repeat across events; existing per-event guest code and invitation constraints still apply.

## Owner APIs

All endpoints require an authenticated `EVENT_OWNER` or `SUPERADMIN` and verify `events.owner_id` against the authenticated user.

- `GET /api/admin/events/{event}/receivers` — list active/revoked assignments and account activation status.
- `POST /api/admin/events/{event}/receivers` — normalize email, reuse an existing user or create a pending receiver account, and assign it to one or more selected events owned by the caller.
- `DELETE /api/admin/events/{event}/receivers/{user}` — revoke only that event assignment.
- `POST /api/admin/events/{event}/receivers/{user}/activation/resend` — rotate and resend activation for an unactivated newly-created account.

New accounts receive a hashed, expiring, single-use activation token through the existing account activation flow and set their own password. API responses never expose the token or a plaintext password. Existing accounts are not reactivated or sent activation mail.

## UI

Open **Admin → Event → Petugas** at `/admin/receivers`. Select the current event, enter an email (and a name for a new account), optionally select several owned events, then add the assignment. The list shows account role/status, assignment status, activation requirement, resend, and revoke actions.

## Email safety

Tests use `Mail::fake()`. Local validation must not configure or exercise a real external mail transport. Deployment should configure the normal Guestory mail provider before operators use invite/resend in production.
