# Guestory Development Plan

Brand: Guestory  
Tagline: Every Guest Has a Story

This plan tracks the MVP modules so the build does not miss a required product surface from the PRD.

## Current Baseline

Completed as the first foundation slice:

- Laravel API scaffold in `apps/api`.
- React/Vite frontend scaffold in `apps/web`.
- PostgreSQL local database `guestory`.
- Core migrations and models: users, events, guests, invitations, qr_tokens, check_ins, photos, event_receivers.
- Demo seed data for one event, admin, receiver, guests, invitation tokens, QR tokens, check-in, and photos.
- Database-backed API endpoints for brand, MVP blueprint, dashboard metrics, invitation lookup/open tracking, QR validation, and QR/manual check-in confirmation.
- Feature tests for invitation, QR validation, revoked QR, successful check-in, guest attendance update, and duplicate check-in rejection.
- PM2 process config in `ecosystem.config.cjs`.
- Graphify output in `graphify-out/graph.json`.

## MVP Milestones

### 1. Auth And Role Access

Goal: lock user identity before expanding CMS and receiver screens.

Status: completed in the first MVP auth slice.

Backend:

- Admin login/logout.
- Receiver login/logout.
- Bearer token strategy for React frontend.
- Role constants through `ADMIN` and `RECEIVER` user values.
- Middleware for authenticated admin routes.
- Middleware for receiver assigned-event access.
- Password hashing and seed credentials for local demo only.
- Auth tests for allowed and forbidden access.

Frontend:

- Login screen.
- Auth state store.
- Protected admin layout.
- Protected receiver layout.
- Logout action.

Done when:

- Admin can access CMS routes.
- Receiver can access only check-in routes.
- Guest invitation remains public and token-based.

### 2. Admin Event Management

Goal: allow one admin to manage many events without multi-tenant complexity.

Status: completed in the first admin event slice.

Backend:

- Event CRUD API.
- Publish and archive transitions.
- Owner scoping through `events.owner_id`.
- Event dashboard detail endpoint.
- Validation for event date, time, venue, status, and type.
- Tests for owner scoping and event status rules.

Frontend:

- Events list.
- Create/edit event form.
- Event detail shell.
- Status controls: draft, published, archived.
- Dashboard cards connected to API.

Done when:

- Admin can create, edit, publish, archive, and view multiple events.
- Draft events cannot be used for public check-in.

### 3. Admin Guest Management

Goal: make guest data the source for invitations, QR identity, attendance, and photo ownership.

Status: completed in the first admin guest slice.

Backend:

- Guest CRUD API scoped by event.
- Search and filters by name, category, RSVP, invitation, attendance, QR status.
- Import JSON rows and CSV files.
- Export CSV.
- Guest detail endpoint with invitation, QR, attendance, and photo count.
- Validation for `guest_count`, category, group, table number, phone, email.
- Tests for event isolation and guest CRUD.

Frontend:

- Guest table connected to API.
- Create/edit guest drawer or page.
- Guest detail page.
- Search and filter UI.
- Import/export controls.

Done when:

- Admin can manage guests end to end for each event.
- Guest lists never leak across events.

### 4. Invitation And QR Management

Goal: generate secure invitation tokens and QR tokens per guest.

Status: completed in the first invitation and QR management slice.

Backend:

- Generate invitation token per guest.
- Generate QR token per guest.
- QR SVG download endpoint and QR payload URL contract.
- Revoke QR.
- Activate QR.
- Regenerate QR with old token revoked.
- Database partial unique index for one active QR per guest.
- Invitation and QR list endpoints per event.
- Copy invitation URL contract.
- Tests for one active QR per guest.

Frontend:

- Invitation management list.
- Guest invitation preview.
- QR preview.
- Copy URL action.
- Download QR action.
- Revoke/regenerate QR controls.

Done when:

- Every guest can have one invitation URL and one active QR token.
- Regenerating QR does not break the invitation URL.

### 5. Guest Invitation Experience

Goal: deliver the mobile-first public guest flow.

Status: completed in the first public guest invitation slice.

Backend:

- Public invitation endpoint by invitation token.
- RSVP update endpoint.
- QR token payload endpoint.
- Public event album endpoint scoped by invitation token.
- Guard: guest can access only own invitation and own event public content.
- Tests for invalid token and cross-event blocking.

Frontend:

- Real route `/invite/:token`.
- Mobile invitation page.
- Personalized guest greeting.
- Event detail, date, time, venue, map link.
- Countdown.
- RSVP controls.
- Personal QR display.
- Photo upload entry point.
- Album view.
- Empty/error/loading states.

Done when:

- Guest can open invitation without account, RSVP, view QR, and access photo feature from one tokenized link.

### 6. Receiver Check-In App

Goal: make the entrance workflow fast, simple, and reliable.

Status: completed in the first receiver app slice.

Backend:

- Assigned event list for receiver.
- QR validation endpoint hardened.
- Confirm check-in endpoint hardened.
- Manual guest search endpoint.
- Manual check-in endpoint or shared confirm flow.
- Recent check-ins endpoint.
- Validation states: invalid, revoked, expired, invalid event, guest not found, already checked in, allowed.
- Tests for receiver event assignment and duplicate blocking.

Frontend:

- Receiver event select.
- QR camera scanner.
- Scan result screen.
- Confirm check-in with `actual_guest_count`.
- Already checked-in state.
- Error states in Bahasa Indonesia.
- Manual search fallback.
- Recent check-ins.

Done when:

- Receiver can scan repeatedly and check in guests within a few seconds.
- Receiver cannot check in guests from unassigned events.

### 7. Attendance And Digital Guest Book

Goal: make attendance visible and reliable for admin after receiver actions.

Status: completed in the first attendance and guest book slice.

Backend:

- Attendance list endpoint.
- Guest book endpoint derived from check-ins.
- Filters: checked in, not checked in, RSVP status, category, method.
- Sort by check-in time and guest name.
- Export attendance or guest book CSV.
- Tests for metrics and filter accuracy.

Frontend:

- Attendance CMS page.
- Guest book CMS page.
- Filter and sort controls.
- Export action.
- Recent check-in activity.

Done when:

- Admin can monitor attendance and guest book from database records, not hardcoded numbers.

### 8. Photo Upload And Album

Goal: connect event photos to event and guest identity.

Status: completed in the first photo upload and album slice.

Backend:

- Local storage setup with public serving path.
- Guest photo upload endpoint by invitation token.
- Photo records with event_id, guest_id, file path, status.
- Photo album endpoint for approved photos.
- Admin photo list endpoint.
- Approve, reject, delete photo.
- File validation for size and MIME type.
- Tests for event isolation and guest-photo ownership.

Frontend:

- Guest camera/file upload.
- Upload preview.
- Upload progress, success, and failure states.
- Guest album grid.
- Admin photo management page.
- Approve/reject/delete controls.

Done when:

- Guest can upload photos and admin can manage the event album.

### 9. WhatsApp Automation Via WAPI

Goal: send invitations and reminders without building a WA engine in Guestory.

Status: completed in the first WAPI automation slice.

Backend:

- WAPI client service.
- Send invitation message endpoint.
- Resend invitation endpoint.
- Bulk invitation send endpoint.
- Message template config.
- Delivery log table.
- Retry/error handling.
- Tests with mocked WAPI client.

Frontend:

- Send invitation action.
- Resend invitation action.
- Message status indicator.
- Bulk send selected guests.

Done when:

- Admin can send invitation links through existing WAPI integration.

### 10. Frontend API Integration And UX Completion

Goal: replace prototype/static UI with real app flows.

Status: completed in the first frontend API integration slice.

Frontend:

- API client with error handling.
- Admin layout navigation connected to real routes.
- Admin event create/status controls.
- Admin dashboard metrics connected to database.
- Admin guest list/search/create connected to database.
- Invitation and QR actions from guest rows.
- Receiver mobile layout.
- Guest mobile layout.
- Loading, empty, success, duplicate, forbidden, and network states.
- Responsive pass for desktop admin and mobile guest/receiver.
- Visual QA with Playwright screenshots.

Done when:

- Main screens use real API data and remain usable on desktop and mobile.

### 11. Production Readiness

Goal: prepare the app to run under PM2/Nginx without surprises.

Status: completed in the first production readiness slice.

Backend:

- Environment checklist in `docs/production-readiness.md`.
- Queue/mail placeholders if needed.
- Storage link and Nginx static file notes.
- Log channel review.
- Rate limiting for public token endpoints, login, and receiver validation.
- Backup and migration notes.
- API health endpoint at `/api/health`.

Frontend:

- Production build command.
- PM2 ecosystem review with production mode.
- Runtime API base URL config.

Ops:

- Nginx reverse proxy example in `deploy/nginx/guestory.conf.example`.
- PM2 start/restart commands.
- PostgreSQL database/user provisioning notes.
- Health check endpoints.

Done when:

- Guestory can be deployed consistently to the target server.

## Acceptance Coverage Checklist

- Admin can create event.
- Admin can add guest.
- System can create unique QR per guest.
- Guest can open personalized invitation.
- Invitation displays guest QR.
- Receiver can scan QR.
- System finds guest from QR.
- Invalid QR is rejected.
- Revoked QR is rejected.
- Duplicate check-in is rejected.
- Successful check-in is stored.
- Digital Guest Book is generated from check-ins.
- Admin can view attendance.
- Guest can upload photo.
- Photo is stored by event.
- Photo can be tied to guest.
- Receiver only accesses assigned events.
- Guest cannot access another guest's private data.
- Admin can view photo album.
- Admin can revoke and regenerate QR.

## Recommended Build Order

1. Auth and role access.
2. Admin event management.
3. Admin guest management.
4. Invitation and QR management.
5. Guest invitation route and RSVP.
6. Receiver scanner and manual fallback.
7. Attendance and guest book CMS.
8. Photo upload and album.
9. WAPI invitation sending.
10. Production PM2/Nginx hardening.

## Test Strategy

- Backend feature tests for every business rule in QR, invitation, receiver access, guest isolation, and photo isolation.
- Frontend build and lint on every milestone.
- Playwright screenshots for admin desktop, guest mobile, and receiver mobile after major UI work.
- API smoke tests after migrations and seeding.
- Graphify update after each completed milestone.
