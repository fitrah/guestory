# Approved Guest Photo Album

The public invitation's **Photos & Album** section uses guest-uploaded records from `photos` only. It does not use `invitation_design_assets`; those remain owner-managed visual assets for the separate invitation-design slideshow.

## Public API

`GET /api/invite/{token}/album` accepts validated `page` (minimum 1) and `per_page` (1–24, default 12). Results are scoped to the invitation's event, include only `APPROVED` photos, and use deterministic newest-first ordering by `uploaded_at DESC, id DESC`.

The existing top-level `photos` array remains available for compatibility and now contains the requested page. `meta` contains `current_page`, `last_page`, `per_page`, `total`, nullable `from`/`to`, `has_next_page`, `has_previous_page`, and nullable `next_page_url`/`previous_page_url`.

## Public experience

- The guest-photo carousel shows up to eight approved photos from the **currently selected gallery page**. This intentionally repeats those records in a larger highlight format before the grid; it does not mix in invitation design images or make an extra unbounded request.
- Previous/next buttons, dots, a visible count, Left/Right keyboard controls, carousel and pagination ARIA labels, and reduced-motion styling support accessible navigation.
- The active carousel image loads eagerly; inactive carousel images and all gallery thumbnails use native lazy loading.
- Pagination fetches JSON without a document reload, keeps the Photos & Album section at the same viewport position, and exposes loading, retryable error, empty-album, one-photo, and multi-page states.
- Relative storage URLs are resolved against the configured API origin; already absolute URLs remain absolute through URL resolution.

## Verification

From the repository root:

```sh
npm run test:api
npm run format:api
npm run lint:web
npm run build:web
```

Focused API coverage is in `apps/api/tests/Feature/GuestInvitationExperienceTest.php` and covers approval filtering, pagination validation and out-of-range pages, deterministic ordering, and event isolation.
