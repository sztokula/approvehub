# Changelog

All notable changes to `ApproveHub` are documented in this file.

## [2.4.0] - 2026-02-04
### Added
- Hardened security headers: `CSP`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- No-store cache policy for authenticated responses (`Cache-Control: no-store, private`).

### Changed
- Public share link throttling changed to per-IP buckets.

### Security
- Webhook hardening: URL scheme/port validation, private-host blocking, and credential-in-URL blocking.

## [2.3.0] - 2026-02-03
### Added
- `SecurityHeaders` middleware.
- Additional security tests for webhooks and public share links.

### Changed
- `X-Request-Id` handling hardened with validation and safe fallback.

## [2.2.0] - 2026-02-02
### Added
- Configurable public share link rate limit (`APPROVEHUB_PUBLIC_SHARE_LINKS_PER_MINUTE`).

### Changed
- Default attachment storage moved to private disk (`local`).

## [2.1.0] - 2026-02-01
### Added
- Async PDF export with polling endpoint.
- Webhook delivery logging (`webhook_deliveries`).

## [2.0.0] - 2026-01-31
### Added
- Public read-only share links with expiration.
- Organization workflow templates.
- Document version diffing.

### Changed
- Expanded permission model (ownership + per-document permissions).

## [1.9.0] - 2026-01-30
### Added
- Dashboard with document and review metrics.
- Document filters (status, owner, reviewer, date range).

## [1.8.0] - 2026-01-30
### Added
- Review notifications (submitted, activated, rejected, reminders, escalations).
- Console commands for reminders and escalation workflows.

## [1.7.0] - 2026-01-29
### Added
- Attachments for documents and document versions.
- Authorized attachment download and deletion.

## [1.6.0] - 2026-01-29
### Added
- Audit export to JSON and CSV.
- Request context enrichment (`X-Request-Id`, IP, user id).

## [1.5.0] - 2026-01-28
### Added
- Append-only audit log event model.
- Append-only trigger enforcement for audit logs.

## [1.4.0] - 2026-01-28
### Added
- Granular document permissions (`view`, `review`, per-user).
- UI for managing document-level permissions.

## [1.3.0] - 2026-01-27
### Added
- Multi-step approval workflow with assignees.
- Approve/reject decisions with notes.

### Changed
- Workflow model split into dedicated entities: `approval_workflows`, `approval_steps`, `approval_step_assignees`, `approval_decisions`.

## [1.2.0] - 2026-01-27
### Added
- Immutable version history (`document_versions` as snapshots).
- Restore older versions as new snapshots.

## [1.1.0] - 2026-01-26
### Added
- Comments on documents and specific versions.
- Initial authorization policies for core resources.

## [1.0.0] - 2026-01-26
### Added
- MVP: organizations, roles, documents, versioning, review workflow, audit log.
- Document statuses: `draft`, `in_review`, `approved`, `rejected`, `archived`.

## [0.9.0] - 2026-01-26
### Added
- Registration and login with Fortify.
- User membership in organizations with assigned roles.

## [0.8.0] - 2026-01-25
### Added
- Initial domain controllers and request classes.
- Base Blade views for document flows.

## [0.7.0] - 2026-01-25
### Added
- Domain enums (`DocumentStatus`, user roles, approval statuses).
- Action/service classes for business logic.

## [0.6.0] - 2026-01-25
### Added
- Eloquent domain models and relationships.
- Test factories for key entities.

## [0.5.0] - 2026-01-25
### Added
- Base migrations for core domain entities.

## [0.4.0] - 2026-01-25
### Added
- Web and API routing structure.
- Initial application shell layout.

## [0.3.0] - 2026-01-25
### Added
- Livewire + Flux UI integration.
- Initial account settings components.

## [0.2.0] - 2026-01-25
### Added
- Laravel 12 project setup.
- Pest testing setup.

## [0.1.0] - 2026-01-25
### Added
- `ApproveHub` project bootstrap and initial scaffolding.
