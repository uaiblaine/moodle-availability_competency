moodle-availability_competency
=============================

[![ci](https://github.com/uaiblaine/moodle-availability_competency/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-availability_competency/actions/workflows/ci.yml?query=branch%3Amain)

An availability condition plugin for Moodle that restricts access to activities and course sections based on competency proficiency. Teachers can require students to have achieved a certain level of proficiency in one or more competencies before accessing an activity or section.

If your teachers want to release activities, resources, or sections only to students who have reached specific competencies in a course, this plugin is for you.

Take a look at an example:

- The Moodle site tracks competencies for each training pathway.
- Ana Teacher is an editing teacher in course A.
- Ana would like to publish an advanced assignment only for students who have already demonstrated the competency “Problem Solving—Intermediate.”
- With core Moodle conditions, she cannot directly gate that activity by competency proficiency.
- With availability_competency, Ana can add a competency condition to the activity or section and require a target proficiency level.
- Only students who meet that competency requirement get access.

Important: a competency has to be linked to the course (Course administration -> Competencies) before it can be chosen in a restriction. Linking is what narrows a site's competencies down to a list the restriction form can offer, and core's course competency picker decides which frameworks a course may use.

When a competency is linked to a course only to be used in restrictions, consider setting its "Upon course completion" rule to "Do nothing" on the course competencies page: core's default, "Attach evidence", adds evidence to every learner who completes the course.


How this plugin works
---------------------

Each restriction names one competency and one of four requirements:

- **Yes – In this course**: the learner has been rated proficient in the competency in this course.
- **Yes – Global**: the learner is proficient in the competency site-wide, whichever course, learning plan or evidence of prior learning the rating came from.
- **No – In this course** and **No – Global**: the opposite.

Ratings are read as they are stored. A rating keeps counting after the competency is unlinked from the course, after the course is moved to another category and while competencies are disabled on the site: these only stop new ratings, so a learner who was rated proficient keeps access. A competency that no longer exists counts as not proficient.

When a course is restored, the competency is remapped through core's competency mapping. On another site where the competency cannot be mapped, the restriction points at no competency and the restore log says so.

Note that a "Global" restriction lets a teacher who can see which learners have access infer their global proficiency, which by default only managers can view directly.


Settings
--------

To further configure the plugin and its behaviour, please visit: Site administration -> Plugins -> Availability restrictions -> Restriction by competency

There, you find a setting section:

### 1. Clean up restrictions on competency deletion

With this setting, you can control whether the plugin removes the restriction conditions on a competency that can never be met again once this competency is deleted. Nobody counts as proficient in a deleted competency, so a condition requiring proficiency in it would keep the affected activity or section unavailable forever; with the setting enabled, a background task removes such conditions shortly after the deletion. Conditions that the deleted competency now always meets, such as one requiring the learner not to be proficient, are left in place, because removing them could make an item unavailable. The task's log keeps the previous restriction of every item it changes, the only record of it, so this setting is disabled by default and has to be enabled explicitly on the plugin settings page. If it is disabled, every restriction on a deleted competency stays in place and is shown as "(Competency missing)".


Finding restrictions that need attention
-----------------------------------------

A read-only command line report lists the restrictions by competency, in every activity and section of the site, whose competency cannot be used as intended: restrictions that name no competency (a restore could not map it, or an earlier version of the form saved it without one), restrictions on a competency that no longer exists, and restrictions on a competency no longer linked to the course. Each line carries the link to edit the activity or section.

    php availability/condition/competency/cli/report_conditions.php --help


Capabilities
------------

This plugin does not add any capabilities. Whoever can edit an activity's or section's restrictions can add a competency restriction.


Requirements
------------

- Moodle 4.5 or later (tested up to Moodle 5.2)
- Core competencies enabled (`tool_lp`)


Moodle release support
----------------------

This plugin is maintained for Moodle 4.5 LTS up to Moodle 5.2 from a single branch. The restriction form uses the select style of each Moodle release (Bootstrap 4 on 4.5, Bootstrap 5 from 5.0).


Installation
------------

Install the plugin like any other plugin to folder `/availability/condition/competency`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Plugin repositories
-------------------

This plugin is not yet published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/uaiblaine/moodle-availability_competency


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/uaiblaine/moodle-availability_competency/issues


Feature proposals
-----------------

Please issue feature proposals on Github:
https://github.com/uaiblaine/moodle-availability_competency/issues

Please create pull requests on Github:
https://github.com/uaiblaine/moodle-availability_competency/pulls


Translating this plugin
-----------------------

This Moodle plugin is shipped with English language pack by default. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by which they will become part of Moodle's official language pack.


Privacy
-------

This plugin does not store any persistent personal profile data. It only reads competency framework data and user proficiency information from Moodle's core competency system.


Contributors
------------

- Anderson Blaine


Credits
-------

Several ideas in this plugin come from [moodle-availability_competencies](https://github.com/ssystems-de/moodle-availability_competencies) by ssystems GmbH (Alexander Bias, Dennis Pfahl), also licensed under the GNU GPL v3 or later:

- evaluating the learner's global proficiency, offered here as the "Global" requirement;
- reading the stored ratings directly instead of through the competency API, and reading all of a user's ratings at once, refreshed when evidence is added;
- remapping the competency after a restore through core's mapping and logging what could not be restored;
- flagging, in the form, a stored competency the course no longer offers (ssystems shows a badge; keeping it selectable so that saving never drops the condition is this plugin's own), and a dedicated "Select a competency." message;
- sorting the competency list with the collator and building it once per form;
- deferring the formatting of the competency name in restriction descriptions.
- in the tests, wiping the condition's static cache in setUp() and creating the backup_ids temporary table to test restores;
- the Behat data generator for activity restrictions, adapted from theirs.

The optional cleanup of restrictions when a competency is deleted is ported from [moodle-availability_cohort](https://github.com/moodle-an-hochschulen/moodle-availability_cohort) by moodle-an-hochschulen e.V.


Copyright
---------

The copyright of this plugin is held by Anderson Blaine.
