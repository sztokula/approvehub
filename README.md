# ApproveHub

ApproveHub is a document workflow platform for organizations with immutable versioning, multi-step approvals, and full auditability.

## Problem

Organizations handle critical documents (contracts, policies, offers) without a formal process:
- teams work on outdated versions
- decision ownership is unclear
- review and approval flow is inconsistent
- audit history is incomplete

ApproveHub addresses this through a domain-first model:
- `documents` + `document_versions` (immutable snapshots)
- `approval_workflows` + `approval_steps` (sequential approvals)
- `audit_logs` (append-only actor/action/target/metadata events)

## Key Features

- authentication + organizations + roles (`admin`, `editor`, `reviewer`, `viewer`)
- document statuses (`draft`, `in_review`, `approved`, `rejected`, `archived`)
- immutable versioning + restore as a new snapshot
- version-scoped review workflow
- workflow templates (role/user assignees + fallback)
- comments and attachments
- granular access model:
  - visibility (`private` / `organization`)
  - ownership
  - explicit per-document permissions (`view` / `review`)
- audit timeline + JSON/CSV/PDF export
- async PDF export (queue + polling token)
- reminder/escalation jobs for overdue review steps
- webhook events with HMAC signatures + delivery logs

## Stack

- PHP 8.4
- Laravel 12
- Blade + Alpine + Flux UI
- Queue: database
- DB: SQLite (dev), architecture ready for PostgreSQL
- Pest feature tests

## Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test --compact
```

## Architecture (Summary)

- Action/Service classes for business flows (submit review, approve/reject, restore version)
- Form Requests for validation and authorization
- Policies for RBAC + ownership + policy-based access
- Enums for statuses and decision states
- AuditLog as the central business event stream

## API v1 (Selected Endpoints)

- `POST /api/v1/documents`
- `POST /api/v1/documents/{document}/versions`
- `POST /api/v1/documents/{document}/review`
- `POST /api/v1/approval-steps/{step}/approve`
- `POST /api/v1/approval-steps/{step}/reject`
- `POST /api/v1/documents/{document}/versions/{version}/restore`
- `GET /api/v1/documents/{document}/audit`

## What I Learned

1. CRUD is not enough for document systems; state boundaries and transitions are core domain logic.
2. Versioning must be truly immutable; otherwise audit value collapses.
3. Workflow must be attached to a specific version, not to the document entity in general.
4. RBAC alone is insufficient; ownership and explicit permissions are required.
5. Audit trails work best as append-only event streams, including DB-level protection.
6. UX decisions (collapsible sections, local scrolling, quick navigation) strongly impact perceived product quality.
7. Focused feature tests are the best proof that business flow works end-to-end.

