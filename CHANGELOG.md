# Changelog

All notable changes to this project will be documented in this file.

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
