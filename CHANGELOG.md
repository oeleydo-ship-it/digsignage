# Changelog

Every release gets a section headed `## [x.y.z] - YYYY-MM-DD`. Bullets under
`### Added` are shown as new features on the Super admin → Updates page and in
the GitHub release. Bump `VERSION` to the same number before tagging `vx.y.z`.

## [1.0.0] - 2026-09-26

### Added

- Super admin general settings: platform name, custom logo and favicon, Stripe payment gateway wired to each plan's price, SMTP email, sign-up control and a setup checklist
- Super admin updates: version history, install from GitHub or an uploaded package, zero-downtime switch and one-click rollback
- Room booking with meeting rooms, public booking form, confirmation emails and Microsoft 365 calendar sync
- Room status and room availability widgets, plus four ready-made room booking templates
- Queue Configuration with tabbed settings for priorities, alerts, voice and customer notifications
- Platform storage backends: Amazon S3, Wasabi, DigitalOcean Spaces, Cloudflare R2, Backblaze B2, MinIO and local disks
- Designer alignment, distribution and layer ordering, searchable widget palette and catalog artwork

### Changed

- Faster page loads in development and production for pages with designs and widgets
