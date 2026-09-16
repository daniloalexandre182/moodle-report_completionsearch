# Changelog

All notable changes to `report_completionsearch` are documented in this file.

## [1.0.0] - 2026-09-16 (version 2026091600)

### Added
- Course report page `report/completionsearch/index.php?course=ID`, listed in the course reports
  selector (`/report/view.php?courseid=ID`).
- Filter form: search in full name, email or username (email/username only for users with
  `moodle/site:viewuseridentity`), search text and report type (course completion by default or
  activity completion).
- No users are loaded until the search is submitted; an empty search lists every tracked user.
- Ports of the core "Course completion" (`report_completion`) and "Activity completion"
  (`report_progress`) reports, keeping groups, sorting, paging, CSV/Excel export, manual
  completion override and "mark as complete" links while preserving the filter.
- Capability `report/completionsearch:view`, `report_viewed` event, privacy null provider.
- English and Brazilian Portuguese language strings.
- PHPUnit and Behat tests, GitHub Actions workflow using moodle-plugin-ci.
