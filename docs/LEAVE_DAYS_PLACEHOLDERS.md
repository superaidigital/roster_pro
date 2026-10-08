# Leave-form day summary placeholders

The 3-column leave summary in the supplied form:

| ลามาแล้ว | ลาครั้งนี้ | รวมเป็น |
|---|---|---|
| `{{leave_days_previous}}` | `{{leave_days_current}}` | `{{leave_days_total}}` |

Example: prior approved leave = 4 days; new leave = 2 days; cumulative = 6 days.

**Calculation:** The same user, same leave type, same fiscal year (October 1 to September 30). Prior leave must have APPROVED status and end strictly before the current request starts. CANCELLED, PENDING, REJECTED and the current request are not counted as prior leave. The current request's saved `num_days` is used in "ลาครั้งนี้" regardless of approval state; "รวมเป็น" is prior + current. The prior total is a historical snapshot as of the request's start and may change if older approvals are subsequently edited.

**Units:** These placeholders reflect Roster Pro's saved `leave_requests.num_days`. Normal working-day leave uses days excluding applicable weekends/holidays; some special leave types use calendar days. Do not label every leave type "วันทำการ" without checking its `leave_quotas.calculation_type` policy. The text of the provided government form may remain "(วันทำการ)" where relevant.

## DOCX
Place `{{leave_days_previous}}`, `{{leave_days_current}}` and `{{leave_days_total}}` in the three Word table cells respectively; keep the token text in a single Word run so replacement works.

## PDF
These keys are also exposed through `LeaveDocumentService::placeholderCatalog()` in the field palette. Coordinate mapping is available, but filled PDF generation is a separate unfinished capability. Do not claim export is populated yet.

## QA / release
- Confirm previous approved leave with the same employee, same type, earlier end date, same fiscal year is included.
- Confirm a different leave type/user, pending, cancelled, rejected and the same request are excluded.
- Confirm FY transition on October 1 and matching special leave day policies.
- Confirm generated DOCX substitutes all 3 tokens, decimal day values and Thai labels render correctly.
- Run `C:\xampp\php\php.exe -l services\LeaveDocumentService.php`, then generate a sample document on a staging copy of the DB.

No migration required; existing `leave_requests` fields are queried read-only.
