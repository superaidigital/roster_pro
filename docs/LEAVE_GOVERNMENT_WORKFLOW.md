# Roster Pro — Government-style Leave Workflow (4 common categories)

## Source-based scope
The redesigned official-style form combines **ลาป่วย, ลากิจส่วนตัว, ลาพักผ่อน, ลาคลอดบุตร** in one interface and A4 print view. The original Thai government form can have distinct mandatory versions by leave category and staff type: this is an **adapted combined template**, not proof of legal acceptance by a government agency.

## Current implementation
- Preserve the existing leave request, leave-balance policy, overlap checks, holiday counting, approval, cancellations, permissions, notifications and Word/PDF template manager.
- New optional form details: contact address/telephone, delegate name/position/work. These are stored transactionally with the leave request so a partially saved form cannot survive if DB insertion fails.
- Four-category government-style A4 print page with requester, reason, dates, optional delegates, year-based leave statistics, reviewer, supervisor opinion and approval outcome. Other leave categories keep legacy print view.
- Authorized local/global managers can save HR review notes for PENDING requests. Approval remains in the existing workflow; optionally capture supervisor opinion at decision time.
- The old data is kept; older forms missing details print blank lines. No backfill and no removal of previous fields.

## Database
1. **Back up the existing DB**.
2. Apply `database/migrations/20261008_leave_official_form.sql` **once** using phpMyAdmin.
3. Retain all existing tables and PDF/DOCX template migrations.

## XAMPP
```bat
cd /d C:\xampp\htdocs\roster_pro
git fetch origin
git switch feature/leave-official-government-workflow
git pull --ff-only origin feature/leave-official-government-workflow
C:\xampp\php\php.exe -l controllers\LeaveController.php
C:\xampp\php\php.exe -l models\LeaveModel.php
C:\xampp\php\php.exe -l services\LeaveOfficialFormService.php
C:\xampp\php\php.exe -l views\leave\print_official.php
C:\xampp\php\php.exe -l views\leave\index.php
C:\xampp\php\php.exe -l views\leave\approvals.php
```

## Must pass before production
- Make a new request for each of the four categories; confirm details and leave balance persist, and rollback works on invalid information.
- Test special categories still use their existing flow; test PENDING, APPROVED, REJECTED, CANCEL_REQUESTED and CANCELLED printouts.
- Verify local manager cannot review another hospital; reviewer and approver names come from actual users and never from inferred roles.
- Check fiscal-year boundary, working days vs calendar days per category, leave summary across statuses and concurrent requests; old totals are based on already APPROVED requests completed before start day.
- Verify A4 printing across browsers, long Thai names/reasons, phone and delegate details, and that the final command/signature fields remain unsigned until legitimately signed.
- Verify DOCX/PDF template autogeneration still works; this print view is separate from those files.
- Confirm privacy restrictions for attachments and generated documents, security roles and audit logs.
- Check legal/human-resources policy compatibility before formally replacing prescribed leave forms.

**No XAMPP/MySQL/browser test executed by ChatGPT; change is proposed as PR only.**
