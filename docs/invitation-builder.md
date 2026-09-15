# Invitation Builder

Guestory stores one invitation design per event in `invitation_configs`. The event relationship and the existing event owner check keep designs owner-scoped. Deleting an event cascades to its design.

## CMS

Open `/admin/invitation-builder` after signing in as an event owner. The builder provides:

- Three bundled, asset-free themes: Classic, Garden, and Midnight.
- Native HTML drag-and-drop section ordering.
- Enable/disable and reorder controls for Opening, Event details, Photo slideshow, Personal QR, RSVP, and Photos.
- A dependency-free slideshow with autoplay, previous/next buttons, slide dots, pause/resume, and reduced-motion support.
- Editable, length-limited copy fields.
- Responsive mobile/desktop live preview.
- Explicit **Save & publish design** persistence; no external communication is performed.

The saved design is immediately used by existing published guest invitations for that event. It does not publish a draft event or generate/send invitations.

## API

Authenticated owner endpoints:

- `GET /api/admin/events/{event}/invitation-config`
- `PUT /api/admin/events/{event}/invitation-config`

The PUT body contains `theme`, exactly six uniquely identified `sections` with unique order values 0–5, and the documented `content` fields. Themes and section identifiers are allow-listed; content has server-side maximum lengths. Slideshow copy is stored in `slideshow_heading` and `slideshow_message`.

`GET /api/invite/{token}` adds `invitation_config` to the existing response. Existing keys, RSVP, personal QR, album, and upload endpoints are unchanged. Events without a saved row receive a complete Classic default generated from event data, preserving invitations created before this migration.

## Data and compatibility

- Migration: `2026_09_15_000000_create_invitation_configs_table.php`
- One row per event (`event_id` unique).
- Structured JSON is used only for the bounded section and copy schema; event facts remain canonical in `events`.
- The public renderer falls back to the same default configuration if it receives an older API payload without `invitation_config`.
- Persisted five-section configurations are normalized on read by appending the enabled slideshow section. Their stored JSON does not need a migration and can subsequently be saved in the six-section format.
- Hidden interactive sections are not rendered, but their content remains persisted for later restoration.
- Slideshow images are owner-managed invitation design assets stored in `invitation_design_assets`, scoped to one event and entirely separate from guest-uploaded `photos` and moderation status.
- The authenticated builder API supports listing, multi-file upload, complete-list reordering, cover/first-slide selection, and deletion at `/api/admin/events/{event}/invitation-design-assets`. Uploads allow JPEG, PNG, and WebP only, with a 5 MB per-file limit and 12 assets per event. Ownership is checked for the event and nested asset.
- Stored files live on the public disk under `events/{event}/invitation-design/`. Failed database writes remove newly stored files; deletion removes storage before deleting the database row so a failed storage deletion leaves the record retryable.
- `GET /api/invite/{token}` exposes `slideshow_assets` with only `id`, public `url`, display `order`, and `is_cover`. It does not expose storage paths, filenames, MIME metadata, sizes, or guest album records.
- Builder preview and the public slideshow consume only `slideshow_assets`. The Photos & Album section continues to consume the approved album endpoint and guest uploads continue to enter moderation unchanged.
- Zero slideshow assets renders a quiet fallback and remains compatible with existing events/configs. One asset renders without autoplay or redundant navigation controls.

## Verification

Run from the repository root:

```sh
npm run test:api
npm run format:api
npm run lint:web
npm run build:web
```

No deployment or real email, WhatsApp, or other external service is required by this feature.
