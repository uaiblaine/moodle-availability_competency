# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0]

### Upgrading from 1.0.x or 1.1.0

The upgrade changes no stored data: restrictions saved by earlier versions have no scope and keep
reading the course rating, and the form shows them as "Yes – In this course" or "No – In this course".
Some restrictions do evaluate differently, and a few open to learners who did not have access before:

- A restriction on a competency no longer linked to the course was closed for everyone; it now
  follows the ratings, so learners rated proficient in the course get access.
- A restriction requiring **not** to be proficient in a competency that was deleted, or that names no
  competency (the form of earlier versions could save a restriction without one when its competency
  had been unlinked), was closed for everyone; it is now met by everyone.
- Pages that failed for guests, for roles without `moodle/competency:coursecompetencyview` and while
  competencies were disabled now load and evaluate the restrictions.
- Restoring, importing or duplicating now keeps competency restrictions instead of dropping them,
  which opened the item to everyone.

Recommended steps:

1. Back up the database; the plugin cannot be downgraded otherwise.
2. Keep a copy of the current restrictions, the only way to recover a competency the old form already
   lost, for example `SELECT id, course, availability FROM mdl_course_modules WHERE availability LIKE
   '%"type":"competency"%'` and the same on `mdl_course_sections` (adjust the table prefix).
3. Upgrade every site that exchanges course backups at the same time: earlier versions read a
   "Global" restriction as "In this course".
4. Upgrade in maintenance mode and run
   `php availability/condition/competency/cli/report_conditions.php` (under `public/` on Moodle 5.1
   and later). Review each restriction it lists as `nocompetency`, `missing` or `notlinked`, for
   example by choosing its competency again or linking the competency to the course, before leaving
   maintenance mode.
5. Decide whether to enable "Clean up restrictions on competency deletion" on the upgrade settings
   page. It stays disabled unless enabled, and deletions made before the upgrade are not revisited.

### Changes

- Feature: each condition now reads either the rating given in the course ("Yes – In this course",
  "No – In this course") or the learner's global proficiency ("Yes – Global", "No – Global"). The
  choice is stored as `scope` in the condition; conditions saved before this release carry no scope
  and keep reading the course rating. The restriction text says "in this course" for the course
  rating and nothing more for the global one ("You must be proficient in the competency X"). Idea of
  evaluating the global proficiency from ssystems-de/moodle-availability_competencies (ssystems GmbH).
- Bugfix: evaluating a restriction no longer goes through the competency API, which threw for guests,
  for roles without `moodle/competency:coursecompetencyview` and whenever competencies were disabled,
  breaking the whole course page. The stored ratings are read directly, which also stops a record being
  created in `competency_usercompcourse` on every first read. Approach from
  ssystems-de/moodle-availability_competencies (ssystems GmbH).
- Behaviour change: a rating keeps counting after its competency is unlinked from the course, after the
  course is moved to another category and while competencies are disabled; these only stop new ratings.
  A competency that no longer exists counts as not proficient, so a "not proficient" restriction on it
  is met.
- Bugfix: restoring or duplicating no longer drops the condition (which opened the item to everyone).
  The competency is remapped through the restore mapping; on another site without a mapping the
  condition points at no competency and the restore log says so. Approach from
  ssystems-de/moodle-availability_competencies (ssystems GmbH).
- Bugfix: editing an item whose competency is no longer linked to the course silently replaced the
  competency with none. The form now keeps the stored competency as an option marked as not linked, so
  saving the item never drops its condition. Flagging such a competency in the form, and reporting an
  unchosen competency with its own message ("Select a competency."), are ideas from
  ssystems-de/moodle-availability_competencies (ssystems GmbH), which flags it with a badge; keeping it
  selectable is this plugin's own.
- The competency list in the form is sorted alphabetically for the current language and built once
  per form. Idea from ssystems-de/moodle-availability_competencies (ssystems GmbH).
- Restriction descriptions defer formatting the competency name to core, as core requires for
  descriptions built while the course cache is populated. Also done in
  ssystems-de/moodle-availability_competencies (ssystems GmbH).
- The form uses the select class of each Moodle branch (`custom-select` on 4.5, `form-select` from
  5.0) and the Bootstrap 5 spacing utilities, which 4.5 bridges.
- Ratings are read once per user and course per request and refreshed when evidence is added, an
  idea from ssystems-de/moodle-availability_competencies (ssystems GmbH).
- A condition with a negative competency ID is rejected as corrupt; 0 stays valid, as it is what a
  restore stores for a competency it could not map.
- The description of the "Clean up restrictions on competency deletion" setting now says what an
  orphaned restriction does: nobody counts as proficient in a deleted competency.
- README: how the plugin works, its (absent) capabilities, the Moodle releases it supports, and a note
  on setting "Upon course completion" to "Do nothing" for competencies linked only to restrict access.
- New read-only command line report, `cli/report_conditions.php`, listing the restrictions whose
  competency is none (for example saved by the form of earlier versions), deleted or no longer linked
  to the course, with the link to edit each activity or section.
- CI now calls the moodle-an-hochschulen reusable workflow once per supported branch (4.05, 5.00,
  5.01, 5.02), with pushes limited to main and MOODLE_*_STABLE and superseded pull request runs
  cancelled.
- Behat: a data generator for activity restrictions, adapted from the one of
  ssystems-de/moodle-availability_competencies (ssystems GmbH), and a scenario checking that editing an
  activity keeps a restriction on a competency no longer linked to the course.
- Behaviour change of the cleanup on competency deletion: it removes only the conditions on the deleted
  competency that can never be met again (a "proficient" condition, or a "not proficient" one under a
  "must not" group), taking the negation of enclosing groups into account. Conditions the deleted
  competency now always meets stay: removing them, as 1.1.0 did, could make an item unavailable, for
  example "not proficient in X, or date", open to everyone, became "date". The task's log keeps each
  changed item's previous restriction. Restrictions already removed by 1.1.0 cannot be restored.
- The cleanup of restrictions on a deleted competency now runs in an ad hoc task, one per competency,
  instead of scanning every activity and section inside the request that deleted it (a framework
  deletion fired one site-wide scan per competency). It also no longer drops a nested restriction set
  that was already empty, which changed the access of items that never named the deleted competency.
- Ratings read during a request are kept for at most a minute and a thousand entries, so a long-lived
  process such as a cron runner sees ratings given meanwhile in another process.
- Tests: the condition is now tested against the database (scopes, unlinking, disabled competencies,
  missing competencies, guests, capability overrides, restore, duplication), plus the frontend, the
  cleanup setting (off when never saved, registered disabled by default), Behat scenarios for the form
  and a check of the form's Bootstrap class names. Wiping the static
  cache in setUp() and creating the backup_ids temporary table for the restore tests follow the tests
  of ssystems-de/moodle-availability_competencies (ssystems GmbH).

## [1.1.0]

- Bugfix (ported from moodle-an-hochschulen/moodle-availability_cohort): optionally remove orphaned
  competency restrictions when a competency is deleted, so that affected activities and sections do
  not stay hidden from everyone. Controlled by the new admin setting "Clean up restrictions on
  competency deletion" (disabled by default).
- Fix phpcs warnings (final test classes, coverage annotations, lang string ordering, inline comment)
- Rebuild the YUI form module build files from source with the core grunt toolchain

## [1.0.1]

- Add Privacy API support (null_provider — no personal data stored)
- Refactor YUI form module: add GPL header, JSDoc, and Moodle code style
- Add YUI module metadata file (yui/src/form/meta/form.json)

## [1.0]

- Initial stable release for Moodle 4.5 and 5.1
- Competency-based availability restrictions for activities and sections
- Support for single and multiple competency requirements
- Proficiency level matching
- YUI-based form interface for teachers
- Proper integration with Moodle's availability API

## [Previous]

- Bootstrap Update: Replace custom-select class with form-select
- Initial commit: Plugin structure and competency check
