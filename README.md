# Guestory

**Every Guest Has a Story**

Guestory is a web application for digital invitations, guest management, unique guest QR identity, receiver check-in, automatic digital guest book, event photo upload, and event photo albums.

## MVP Direction

- Admin CMS is desktop-first and responsive.
- Guest invitation is mobile-first and does not require login.
- Receiver check-in is mobile-first, fast, and scoped to assigned events.
- One admin can manage many events, but the MVP is not a full multi-tenant SaaS.
- Invitation token and QR token are separated.
- QR is the guest's digital identity for one event and one guest.
- Photos start on local storage and remain scoped by event and guest.
- WhatsApp automation can integrate with the existing WAPI project later.

## Development Plan

See `DEVELOPMENT_PLAN.md` for the MVP milestone checklist, module coverage, acceptance criteria tracker, and recommended build order.
See `docs/production-readiness.md` for the PM2/Nginx deployment checklist.
See `docs/domain-setup.md` for DNS, Nginx site rendering, SSL, and domain verification.
See `docs/invitation-builder.md` for invitation design persistence, API schema, CMS workflow, and compatibility behavior.

## Structure

- `apps/api` - Laravel API and data model.
- `apps/web` - React/Vite frontend, PM2-ready.
- `ecosystem.config.cjs` - Local PM2 process definitions.

## Database

Guestory uses PostgreSQL by default.

Expected local environment:

```env
DB_CONNECTION=pgsql
DB_URL=postgresql://cloud_wash_buddy:REPLACE_WITH_A_STRONG_PASSWORD@127.0.0.1:5432/guestory
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=guestory
DB_USERNAME=cloud_wash_buddy
DB_PASSWORD=REPLACE_WITH_A_STRONG_PASSWORD
```

## Local Commands

```sh
npm run api
npm run dev:web
npm run preview:web
npm run build:web
npm run lint:web
npm run test:api
npm run prod:build
npm run prod:clear
```

## Account architecture

- `/register` — event-owner self-registration with email verification
- `/verify-email` — consumes an expiring verification token
- `/activate-account` — provisioned owner sets their password through an expiring link
- `/superadmin/users` — SUPERADMIN event-owner provisioning and status management
- Guests remain accountless; receivers remain assignment-scoped; event owners remain scoped by `events.owner_id`.
- See [`docs/account-registration.md`](docs/account-registration.md) for API flow, security properties, and deployment requirements.

## Frontend Routes

- `/admin` - API-backed admin CMS for events, guests, invitations, QR, and dashboard metrics.
- `/admin/invitation-builder` - per-event invitation themes, content, sections, ordering, and live preview.
- `/admin/attendance` - attendance and digital guest book.
- `/admin/photos` - photo moderation and album management.
- `/admin/whatsapp` - WAPI invitation delivery log and send actions.
- `/receiver` - receiver check-in application.
- `/invite/{token}` - public guest invitation.

## API Preview

- `GET /api/brand`
- `GET /api/mvp-blueprint`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `GET /api/health`
- `GET /api/admin/events`
- `POST /api/admin/events`
- `GET /api/admin/events/{event}`
- `PATCH /api/admin/events/{event}`
- `DELETE /api/admin/events/{event}`
- `POST /api/admin/events/{event}/publish`
- `POST /api/admin/events/{event}/archive`
- `GET /api/admin/events/{event}/dashboard`
- `GET /api/admin/events/{event}/invitation-config`
- `PUT /api/admin/events/{event}/invitation-config`
- `GET /api/admin/events/{event}/attendance`
- `GET /api/admin/events/{event}/attendance/export`
- `GET /api/admin/events/{event}/guest-book`
- `GET /api/admin/events/{event}/guest-book/export`
- `GET /api/admin/events/{event}/photos`
- `POST /api/admin/events/{event}/photos/{photo}/approve`
- `POST /api/admin/events/{event}/photos/{photo}/reject`
- `DELETE /api/admin/events/{event}/photos/{photo}`
- `GET /api/admin/events/{event}/guests`
- `POST /api/admin/events/{event}/guests`
- `POST /api/admin/events/{event}/guests/import`
- `GET /api/admin/events/{event}/guests/export`
- `GET /api/admin/events/{event}/guests/{guest}`
- `PATCH /api/admin/events/{event}/guests/{guest}`
- `DELETE /api/admin/events/{event}/guests/{guest}`
- `GET /api/admin/events/{event}/whatsapp/messages`
- `POST /api/admin/events/{event}/guests/{guest}/whatsapp/invitation`
- `POST /api/admin/events/{event}/whatsapp/invitations/send`
- `POST /api/admin/events/{event}/whatsapp/messages/{message}/resend`
- `GET /api/admin/events/{event}/invitations`
- `GET /api/admin/events/{event}/qr-codes`
- `POST /api/admin/events/{event}/guests/{guest}/invitation/generate`
- `POST /api/admin/events/{event}/guests/{guest}/qr/generate`
- `POST /api/admin/events/{event}/guests/{guest}/qr/regenerate`
- `POST /api/admin/events/{event}/guests/{guest}/qr/revoke`
- `POST /api/admin/events/{event}/guests/{guest}/qr/activate`
- `GET /api/admin/events/{event}/guests/{guest}/qr/download`
- `GET /api/invite/{token}`
- `PATCH /api/invite/{token}/rsvp`
- `GET /api/invite/{token}/qr.svg`
- `GET /api/invite/{token}/album`
- `POST /api/invite/{token}/photos`
- `GET /api/g/{token}`
- `GET /api/receiver/events`
- `POST /api/receiver/events/{event}/check-in/validate`
- `GET /api/receiver/events/{event}/guests/search`
- `POST /api/receiver/events/{event}/check-ins/manual`
- `GET /api/receiver/events/{event}/check-ins/recent`
- `POST /api/receiver/check-in/validate`
- `POST /api/receiver/check-in/confirm`
