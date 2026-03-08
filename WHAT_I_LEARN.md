# What I Learned

## Product and Domain
- Attaching approval to `document_version_id` closes a real audit gap: after approval, edits can no longer silently change what was actually accepted.
- A simple status enum is not enough; explicit transition guards are what prevent impossible flows like `approved -> draft` without a new version.
- Per-document permissions solved edge cases where role-level access was too broad for cross-team reviews.

## Architecture
- Moving write-heavy flows into action classes made controller code predictable and easier to test in isolation.
- Keeping Form Requests strict (including custom messages) reduced hidden validation logic in controllers.
- Splitting workflow tables (`approval_workflows`, `approval_steps`, `approval_decisions`) made escalation/reminder logic much cleaner than one oversized table.

## Security
- Webhook validation needed to handle more than URL format: private-network targets, non-standard ports, and credentials in URL were the main SSRF vectors.
- Rate limiting public share links per IP worked better operationally than global buckets during manual QA.
- Security headers were straightforward, but CSP remains a conscious compromise until nonce-based rendering is introduced.

## Frontend and UX
- Long document pages became usable only after separating quick actions from content and keeping navigation visible while scrolling.
- On mobile, wrapping action controls early prevented layout jitter and accidental horizontal scroll.
- Markdown preview was most reliable when treated as a separate concern from editor input state.

## Testing and Delivery
- Focused Pest feature tests gave faster feedback than broad full-suite runs while iterating on workflow changes.
- The most valuable tests were transition and authorization tests, because they fail exactly where business rules regress.
- Changelog discipline (small, dated releases) made it easier to communicate progress than large retrospective updates.
