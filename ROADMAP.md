# 4WP Responsive — Roadmap (Updated)

## Implemented
- Plugin scaffold + editor/frontend assets.
- Breakpoints: theme.json-first, fallback defaults, admin view + custom fields when theme.json is missing.
- Per-breakpoint spacing (padding/margin) for core blocks.
- Responsive visibility (hide/show only) per breakpoint.
- Responsive font size per breakpoint (theme.json presets + custom values).
- Responsive text alignment per breakpoint.
- Editor preview sync with device mode + dim hidden blocks (instead of removing).
- Single generated CSS file with hash + CSS variables for custom values.

## Remaining (Short-Term)
- UX helpers: copy values between devices, reset to theme defaults.
- Alignment for flex containers (justify-content) where relevant.
- Editor polish: optional icon-only alignment buttons.

## SEO / Rendering Considerations
- Do not hide critical content on mobile by default.
- If device-aware rendering is needed, add an **optional** server-side mode:
  - Detect device at render time.
  - Vary cache by device.
  - Avoid user-agent cloaking (same content for bots).
  - Explicit opt-in per block or per page.

