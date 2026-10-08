# Additional leave-form placeholders

| Purpose | Placeholder | Source |
| --- | --- | --- |
| ชื่อ–สกุลผู้ยื่น | `{{applicant_name}}` | `users.name` for `leave_requests.user_id` |
| ตำแหน่งผู้ยื่น | `{{applicant_position}}` | `users.position` for the applicant |
| ชื่อ–สกุลผู้อนุมัติ | `{{approver_full_name}}` | `users.name` joined through `leave_requests.approved_by` |
| ตำแหน่งผู้อนุมัติ | `{{approver_position}}` | `users.position` for the approver |

Approval placeholders are **blank** unless request status is APPROVED or CANCEL_REQUESTED and there is an assigned approved_by user. They do not infer a director from the organizational hierarchy. Legacy placeholders `{{employee_name}}`, `{{position}}` and `{{approver_name}}` stay available unchanged.

## XAMPP check

```bat
cd /d C:\xampp\htdocs\roster_pro
C:\xampp\php\php.exe -l services\LeaveDocumentService.php
```

Test DOCX placeholder replacement and PDF field-palette + rendered PDF on a PENDING and APPROVED leave. Confirm approver position is from the actual approving account and both approval fields are blank for pending leave. No database migration is required.

This PR is not yet verified against the user's XAMPP/MySQL runtime.