# Roster Pro — Digital Health System Analysis & Roadmap

> Scope: Roster/HR application extended with practical primary-care field-work support.
> Principle: support staff workflow without turning Roster Pro into a full HIS/EMR unless a separate clinical-governance project is approved.

## 1. Current architecture

- PHP MVC + MariaDB/MySQL
- Bootstrap 5 + Bootstrap Icons
- CSS Grid/Flexbox + responsive card-table enhancement
- Vanilla JavaScript progressive enhancement
- PWA/service-worker shell
- CSRF protection, role checks, secure session handling, audit logs
- CI: schema contract, code-quality audit, PHP lint, MariaDB runtime smoke

### Current functional domains

- Staff/users/HR profiles
- Roster and roster approval
- Leave and leave approval
- Shift swap
- Notifications
- Reports
- Settings / backup / logs
- Field work / home visit

## 2. Field Work — implemented foundation

### Implemented

1. Mobile-first home-visit form
2. Responsive 3-step wizard:
   - General information
   - Vital signs / symptoms
   - Assessment / confirmation
3. GPS capture with accuracy
4. Photo capture/upload:
   - JPG/PNG/WebP only
   - max 3 photos
   - max 5 MB each
   - permission/consent confirmation required
   - files stored outside public web root
   - photo access passes application authorization
5. Offline draft:
   - IndexedDB
   - 8-hour TTL
   - excludes photos
   - encrypted at rest with AES-GCM using a session-bound key
6. Online/offline indicator
7. Server-side DRAFT / COMPLETED workflow
8. Edit existing server DRAFT
9. Risk level:
   - ROUTINE
   - WATCH
   - HIGH
   - URGENT
10. Follow-up date/status
11. Referral-required flag and referral note
12. Scoped CSV export with spreadsheet-formula injection protection
13. Field-work summary:
   - visits today
   - server drafts
   - completed
   - total
   - high-risk/urgent
   - due follow-up
14. Dedicated Follow-up Queue:
   - overdue first
   - due today
   - next 7 days
   - high-risk/urgent prioritized
   - close follow-up by POST + CSRF
15. Duplicate visit warning:
   - checks patient/household reference + visit date
   - client advisory warning before submit
   - server-side duplicate guard repeated before save
   - explicit confirmation required when a duplicate is intentional
16. Role/hospital data scope

## 3. Field Work — recommended next features

### Phase F1 — operational safety / usability

- Visit detail page and chronological patient/household visit timeline
- Explicit referral workflow:
  - pending referral
  - referred
  - acknowledged/closed
- Attach referral destination and contact/coordination note
- Task assignment to another staff member
- Configurable duplicate policy by visit type/organization
- Configurable required fields by visit type
- Image metadata stripping and optional resize/compression before permanent storage
- Audit log for photo viewing/download
- Device-offline policy banner and administrative control for whether offline drafts are allowed

### Phase F2 — field productivity

- Route/day-plan list for assigned home visits
- Optional map view for authorized staff
- QR/Barcode scan for household/patient reference
- Speech-to-text as optional browser enhancement
- Signature/acknowledgement workflow where organizational policy permits
- Appointment handoff:
  - schedule next visit
  - print/share appointment reference
- Structured checklist templates by visit type:
  - chronic follow-up
  - wound care
  - elderly
  - maternal/child
- Configurable clinical observation templates rather than hard-coding diagnosis rules

### Phase F3 — offline sync

A true offline-sync queue is different from an offline draft.

Recommended architecture:
- local encrypted queue with client-generated UUID
- explicit Sync button + automatic retry when online
- server idempotency key to prevent duplicate submissions
- conflict detection using updated_at/version
- user-visible states: Local / Syncing / Synced / Conflict / Failed
- never silently overwrite a server record after conflict

## 4. Office Setting — recommended features

### Phase O1 — daily operations

- Follow-up work queue dashboard
- High-risk/urgent field-work dashboard
- Referral queue
- Daily/monthly visit totals
- Visit-type breakdown
- Staff workload
- Filters by hospital/staff/date/risk/status
- Export CSV/Excel-ready datasets

### Phase O2 — reports/documents

- Server-generated PDF summary for a home visit
- Monthly PDF report by health facility
- Excel export with separate sheets:
  - summary
  - visits
  - follow-ups
  - referrals
- Printable appointment slip
- Printable referral/coordination summary

### Phase O3 — management dashboard

- Follow-up completion rate
- overdue follow-up count
- high-risk cases awaiting action
- referrals awaiting closure
- visit workload by staff
- visit workload by service unit
- trend charts by day/week/month

## 5. UI/UX architecture

### Bootstrap 5 usage

Use Bootstrap layout primitives as the structural baseline:
- container-fluid
- row / g-*
- col-12 / col-md-* / col-lg-*
- offcanvas
- modal
- form-control / form-select
- cards / badges / alerts

Custom CSS is limited to:
- design tokens
- field-work layout
- theme/color modes
- wizard/progress
- application shell
- responsive stacked tables

### Breakpoints

- Phone: < 640px
- Tablet / compact field device: 640–1023px
- Desktop: >= 1024px
- Large desktop: >= 1440px

### Touch targets

- Mobile primary controls: >= 48px
- Avoid icon-only destructive actions unless they have labels/accessible names
- Sticky action area for long multi-step forms

### Data tables

- Simple information tables: convert to stacked cards on phone
- Complex matrix tables (roster/calendar): contained horizontal scrolling
- Never force body-level horizontal scroll

## 6. Progress & loading UX

### Global progress

- same-origin page navigation
- form submission
- fetch/XHR

### Multi-step forms

Desktop:
- horizontal stepper
- step number + step title
- complete/current states

Mobile:
- compact “step X of Y”
- percentage bar
- current step label
- sticky Previous / Next actions

### Loading

Use:
- button spinner for writes
- skeleton screen for loading lists/cards
- inline status for GPS/network/sync operations

Avoid:
- blocking full-page spinner for every minor action
- enabling duplicate submit while a request is in progress

## 7. Light / Dark mode

Implementation:
- Bootstrap 5 `data-bs-theme`
- application `data-theme`
- persisted user-device preference in localStorage
- pre-paint script avoids theme flash

Light:
- high contrast
- bright surface
- dark text
- stronger boundaries for outdoor use

Dark:
- dark slate surfaces, not pure black
- reduced glare
- maintain readable contrast
- semantic status colors remain distinguishable

## 8. Privacy and security priorities

Field-work information can contain sensitive health information.

Required controls before broad production rollout:
- least-privilege role scope
- HTTPS in production
- secure cookies/session configuration
- server-side authorization for every record/photo
- audit logging
- encrypted backups
- controlled backup access
- documented retention/deletion policy
- device lock requirements for field devices
- remote logout/session expiry policy
- avoid putting sensitive identifiers in URLs
- review whether patient names are necessary for each report/export
- do not use browser localStorage for plaintext health records

## 9. Integration boundary

Before connecting to external clinical systems, define:
- authoritative patient identifier
- consent/legal basis
- data ownership
- data minimization
- error reconciliation
- interface specification
- audit requirements

Do not directly couple roster tables to a clinical HIS database.

Prefer a documented integration layer/API when external integration is approved.

## 10. Recommended delivery sequence

1. Stabilize current Roster/Leave/HR baseline
2. Complete Field Work pilot
3. Pilot with one or two service units
4. Collect usability/error feedback
5. Add follow-up/referral office queue
6. Add PDF/Excel reports
7. Add configurable templates
8. Evaluate true offline sync
9. Evaluate external clinical-system integration separately

## 11. Definition of Done for production candidate

- PHP lint: pass
- code-quality audit: pass
- schema contract: pass
- MariaDB runtime smoke: pass
- migration tested against a copy of production schema
- desktop 1366x768 tested
- desktop 1920x1080 tested
- tablet portrait/landscape tested
- phone 360/375/390/430 widths tested
- Light/Dark mode tested on all primary screens
- keyboard navigation tested
- no body-level horizontal overflow
- no state-changing GET actions
- CSRF enabled on writes
- authorization verified for cross-hospital/cross-user access
- backup/restore procedure tested
- field-work privacy review completed


## 12. Current implementation status

### Production-candidate baseline already implemented

- Responsive Bootstrap 5 application shell
- Phone/tablet/desktop breakpoints
- mobile stacked data tables
- dark clinical navigation
- Light/Dark mode using Bootstrap 5 `data-bs-theme`
- responsive wizard/progress component
- button/global loading indicators
- skeleton utility for asynchronous content
- Field Work / Home Visit module
- GPS capture
- encrypted IndexedDB offline draft
- controlled photo upload outside public web root
- risk/follow-up/referral metadata
- scoped CSV export
- Follow-up Queue
- duplicate-visit warning
- CSRF and role/hospital scoping
- runtime smoke coverage for core field-work model flows

### Recommended next office features

1. PDF visit summary and monthly PDF report
2. Real Excel workbook export with Summary / Visits / Follow-ups / Referrals sheets
3. Printable appointment slip from follow-up date
4. Referral work queue with acknowledgement/closure status
5. Dashboard trends by day/week/month
6. Staff workload heatmap
7. Scheduled follow-up reminders
8. Visit-detail timeline
9. Configurable clinical checklist templates
10. True offline sync queue with idempotency/conflict resolution

### Recommended governance before wider clinical rollout

- define retention/deletion rules for field photos and visit records
- confirm organizational/legal basis for storing patient names and photographs
- require HTTPS and managed device lock for field use
- audit photo view/download
- encrypt backup media
- document incident response and access revocation
- pilot with a small number of service units before province-wide rollout
