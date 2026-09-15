# Account Registration and Onboarding

## Implemented architecture (2026-09-14)

Guestory now has four deliberately separate identities:

- `SUPERADMIN`: platform administration and event-owner provisioning. The seeded `fitrahajah@gmail.com` and `fahrurrozirahmawan@gmail.com` accounts migrate to this role.
- `EVENT_OWNER`: self-registers or is provisioned, and can only operate events whose `owner_id` is their own user id.
- `RECEIVER`: invite-only and restricted to active rows in `event_receivers`.
- Guest: no user account; invitation and QR tokens remain the public identity.

The existing `/admin/*`, login, password-reset, event ownership, and billing contracts are retained. Admin routes accept both `EVENT_OWNER` and `SUPERADMIN`, but event records remain owner-scoped even for SUPERADMIN.

## Public owner registration

Frontend: `/register`.

1. `POST /api/auth/register` normalizes email to lowercase, validates password confirmation/strength, records terms/privacy consent version and timestamp, and creates a `PENDING_VERIFICATION` `EVENT_OWNER`.
2. A random one-time token is stored only as SHA-256 in `account_tokens`, purpose `VERIFY_EMAIL`, with a 30-minute expiry.
3. `/verify-email?token=...` calls `POST /api/auth/email/verify`. It marks the account verified and active and consumes the token.
4. Login rejects unverified or inactive users with the same invalid-credentials response.

Registration and resend return generic responses to reduce account enumeration. Registration has a honeypot field and both endpoints are rate-limited. Production should add an edge CAPTCHA/bot-control provider if abuse warrants it.

## SUPERADMIN provisioning

Frontend: `/superadmin/users`.

- `POST /api/superadmin/users` creates a passwordless `PENDING_ACTIVATION` `EVENT_OWNER` and emails a one-time 60-minute activation link.
- `POST /api/auth/activate` sets the owner-selected password, verifies/activates the account, consumes the token, and revokes old sessions.
- SUPERADMIN may list owner/platform accounts, resend pending activation links, and suspend/reactivate owners. Suspension revokes access tokens.
- Passwords and raw activation tokens are never returned by user-management APIs or stored in plaintext.

## Email delivery and safe local testing

Account links use Laravel Mail. Tests use `Mail::fake()` and no external email is sent. Local/test environments should use `MAIL_MAILER=array` or `log`. Production must configure a real mail transport, sender identity, queue/worker strategy if async delivery is introduced, and an externally reachable `APP_URL` so links resolve to the web app.

## Deployment requirements

1. Back up the production database.
2. Deploy API and web artifacts together (new roles are not understood by the old web login guard).
3. Set `APP_URL` to the public HTTPS web origin; configure and verify production mail transport/sender.
4. Run `php artisan migrate --force`. The migration makes password nullable for provisioned accounts, adds consent/activation metadata, migrates the two known accounts to `SUPERADMIN`, migrates other legacy `ADMIN` users to `EVENT_OWNER`, and creates `account_tokens`.
5. Clear application/config caches and restart the managed API process through the normal deployment mechanism.
6. Smoke-test SUPERADMIN login, owner registration verification, owner provisioning activation, owned-event access, cross-owner denial, receiver assignment denial, password reset, and billing.
7. Monitor failed mail/log entries and registration throttles. Do not expose raw account-token rows in logging or support tooling.
