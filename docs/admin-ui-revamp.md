# Admin UI/UX revamp

## Decisions

- Kept the existing React/Vite architecture, routes, API contracts, local-storage sessions, role checks, and per-event request scoping unchanged.
- Consolidated all event-owner admin pages onto one compact shared sidebar with grouped **Workspace** and **Operations** navigation, persistent active states, and a direct Receiver App entry.
- Replaced the previous tablet horizontal navigation with an accessible off-canvas mobile drawer and backdrop so labels remain legible and touch targets remain at least 42px.
- Added a small token layer for surfaces, ink, borders, brand colors, radii, and shadows; reused it across cards, metrics, tables/lists, forms, billing plans, status messages, and authentication panels.
- Strengthened hierarchy with quieter page backgrounds, bordered top bars, compact uppercase context labels, consistent event selectors, and restrained teal/amber accents.
- Improved modal accessibility with Escape-to-close, focus entry, focus trapping, focus restoration, body scroll lock, labelled descriptions, backdrop close, and visible focus rings.
- Preserved the visual character of landing, guest invitation, and receiver surfaces while allowing shared controls, color tokens, and responsive refinements to keep them compatible.

## Scope notes

The backend was not changed. Existing SUPERADMIN/EVENT_OWNER authorization, event ownership isolation, multi-event receiver assignment, invitations/QR, attendance, photo review, billing, and WhatsApp behavior remain backed by the same endpoints and were verified by the complete API suite.
