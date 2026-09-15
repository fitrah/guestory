# Approved Guest Photo Album

The public invitation's **Photos & Album** section uses guest-uploaded records from `photos` only. It does not use `invitation_design_assets`; those remain owner-managed visual assets for the separate invitation-design slideshow.

## Public API

`GET /api/invite/{token}/album` accepts validated `page` (minimum 1) and `per_page` (1–24, default 12). Results are scoped to the invitation's event, include only `APPROVED` photos, and use deterministic newest-first ordering by `uploaded_at DESC, id DESC`.

The existing top-level `photos` array remains available for compatibility and now contains the requested page. `meta` contains `current_page`, `last_page`, `per_page`, `total`, nullable `from`/`to`, `has_next_page`, `has_previous_page`, and nullable `next_page_url`/`previous_page_url`.

## Public experience

- Album viewing keeps the existing invitation/event rules and still returns only approved photos.
- Upload authorization is stricter: the exact invitation guest must have a `check_ins` row scoped to the same event, and the invitation must still be `PUBLISHED`. A status flag on the guest record alone is not sufficient. This includes walk-in guests because their creation flow writes the invitation and same-event check-in atomically.
- An unchecked or revoked invitation receives HTTP `403` with code `PHOTO_UPLOAD_CHECK_IN_REQUIRED`. Authorization runs before file validation/storage and before the photo transaction, so rejection creates no stored file and no `photos` row.
- Checked-in guests get separate **Ambil Foto** and **Pilih dari Galeri** controls. The camera input uses `accept="image/*" capture="environment"`; the gallery input uses `accept="image/*"` without `capture`. Both preview the selected image and submit through the same `/photos` endpoint and validation pipeline.
- Browsers may treat `capture` as a hint. If direct capture is unsupported, camera permission is denied, or capture is cancelled, the invitation explains the state and keeps the gallery fallback available. Guestory intentionally does not open a continuous WebRTC camera stream.

- The guest-photo carousel shows up to eight approved photos from the **currently selected gallery page**. This intentionally repeats those records in a larger highlight format before the grid; it does not mix in invitation design images or make an extra unbounded request.
- The carousel autoplays every five seconds when it has at least two photos. It wraps safely and provides explicit pause/resume, previous/next buttons, dots, a visible count, and Left/Right keyboard controls.
- Hover, focus within the carousel, touch, and manual navigation pause or reset autoplay as appropriate. Zero/one-photo states never start a timer, and `prefers-reduced-motion: reduce` disables autoplay and visual transitions.
- Carousel/slide labels, current-state attributes, focus indication, and controlled live-region behavior keep navigation accessible without announcing every automatic transition.
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

Frontend regressions in `apps/web/src/guestPhotoUpload.test.ts` preserve the separate camera and gallery input semantics. `apps/web/src/UserGuide.test.ts` keeps the check-in prerequisite and choices documented.
