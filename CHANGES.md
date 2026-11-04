# Changelog

All notable changes to `report_completionsearch` are documented in this file.

## [1.1.0] - 2026-11-04 (version 2026110400)

### Changed
- Moodle 5.2 support (branch `MOODLE_502_STABLE`, requires 5.2, PHP 8.3 – 8.4). The 4.1 version stays on
  `MOODLE_401_STABLE`.
- Activity completion view ported from the Moodle 5.2 `report_progress`:
  - "Section" selector (`activitysection`), filtering the activities by course section.
  - Group selector that works in every group mode: outside separate groups the `group` parameter is honoured
    (only groups of the course are accepted) and the active group is shown as selected.
  - Download through the dataformat selector (`dataformat`) instead of the removed CSV/Excel links
    (`report_progress\output\renderer::render_download_buttons()` is deprecated since 5.1).
  - Core 5.2 table classes and "Nothing to display" notification.
- Course completion view: dates in the CSV exports use `%F %T`, "Nothing to display" notification and
  `table-hover`, as the Moodle 5.2 `report_completion`.
- GitHub Actions matrix moved to `MOODLE_502_STABLE` (PHP 8.3/PostgreSQL 16 and PHP 8.4/MariaDB 10.11).

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
