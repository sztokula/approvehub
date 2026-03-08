# ApproveHub Documentation

## 1. Product Overview
ApproveHub is a document workflow platform for organizations that need:
- immutable document versioning
- comments and collaboration
- multi-step approvals
- strict access control
- complete auditability

The platform is designed for real business flows (contracts, policies, offers, internal documents), not demo CRUD.

## 2. Core Domain Model
- `organizations`
- `users`
- `roles`
- `documents`
- `document_versions`
- `approval_workflows`
- `approval_steps`
- `approval_step_assignees`
- `approval_decisions`
- `comments`
- `attachments`
- `document_permissions`
- `public_share_links`
- `audit_logs`
- `webhook_deliveries`

## 3. Document Lifecycle
State transitions are controlled by domain rules:
- `draft -> in_review`
- `in_review -> approved`
- `in_review -> rejected`
- `rejected -> draft` (via new version)
- `approved -> archived`

Invalid shortcuts (for example `approved -> draft` without a new version) are intentionally blocked.

## 4. Versioning Strategy
- `documents` represent business entities.
- `document_versions` store immutable content snapshots.
- Any meaningful content update creates a new version.
- Restoring old content creates a new snapshot version, never mutates history.

## 5. Approval Workflow Strategy
Workflows are attached to a **specific document version**, not to the document entity.

Why:
- approval must match exact approved content
- prevents logical drift after post-approval edits
- keeps audit and compliance trace consistent

## 6. Access Control Model
ApproveHub combines:
- Role-based access (RBAC): `admin`, `editor`, `reviewer`, `viewer`
- Ownership (`owner_id`)
- Policy-based authorization
- Per-document explicit permissions (`view`, `review`)

This avoids over-reliance on a global admin bypass and supports enterprise-style access boundaries.

## 7. Auditability
Every critical action is recorded as append-only audit events with:
- actor
- action
- target type and id
- metadata
- occurred timestamp
- optional request context (IP, user-agent, request id)

Typical events:
- `document.created`
- `version.created`
- `review.submitted`
- `approval.approved`
- `approval.rejected`
- `comment.added`
- `attachment.uploaded`

## 8. Security Hardening
Implemented:
- security response headers middleware
- rate limiting on sensitive routes (including public share links)
- webhook URL hardening (scheme, port, private host restrictions)
- private attachment storage by default
- authenticated response cache hardening

Trade-off:
- CSP currently allows inline scripts/styles for Flux appearance compatibility.

## 9. Main User Flow
1. User creates a document in `draft`.
2. System creates `v1` version snapshot.
3. User adds comments, attachments, and permissions.
4. User submits current version for review (manual reviewers or template).
5. Approval steps run in sequence.
6. Approvers decide approve/reject with notes.
7. Full audit trail is generated.
8. User can inspect history and restore older content as a new version.

## 10. Technical Stack
- Laravel 12
- PHP 8.4
- Livewire 4 + Flux UI
- PostgreSQL/SQLite (dev)
- Pest 4 for automated tests
- Vite for frontend assets

## 11. Architecture Conventions
- Thin controllers
- Action/Service classes for business use-cases
- Form Request validation
- Policies for authorization
- Enums for domain state
- Focused feature tests for workflow correctness

## 12. Implemented Portfolio Features
- document versioning with restore
- multi-step approval workflows
- comments and attachments
- granular document permissions
- public read-only share links
- version diffing
- audit export (JSON/CSV)
- async PDF export flow
- reminders/escalations for overdue review steps

## 13. Testing Strategy
- Feature tests for:
  - authorization and access boundaries
  - workflow transitions
  - document UX and routes
  - webhook hardening
  - public share link behavior
- Focused test execution for fast development feedback.

## 14. Known Limitations
- No real-time collaboration (by design for MVP timeline).
- Rich text editor currently uses Trix HTML format (not full markdown fidelity).
- CSP is pragmatic, not nonce-based strict mode.
