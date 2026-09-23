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


Frequently asked questions
--------------------------

### What is the difference between "In this course" and "Global"?

Moodle keeps two ratings for each learner and competency. The **course rating** belongs to one course: it is what the course's Competencies page and the competency breakdown report show. The **global rating** is the learner's site-wide proficiency: it is what learning plans show. "In this course" reads the course rating of the course the restricted activity or section belongs to; "Global" reads the global rating, wherever it came from.

### How does a learner get a rating in this course?

Only through something that happens in this course:

- an activity linked to the competency whose "Upon activity completion" rule is "Complete the competency", once the learner completes the activity;
- the course's "Upon course completion" rule set to "Complete the competency", once the learner completes the course;
- a teacher using "Rate" on the learner's competency page in the course, or in the competency breakdown report;
- restoring the course with user data.

"Attach evidence" and "Send for review" do not rate. Neither do learning plans, evidence of prior learning or rules on parent competencies: they only change the global rating.

"Complete the competency" gives the **default rating** of the competency's scale. If that rating is not marked as proficient in the scale configuration, completing gives a rating that is not proficient and an "In this course" restriction stays closed.

### The learning plan says the competency is achieved, but the restriction stays closed. Why?

The learning plan shows the global rating, which may come from another course, a plan review or evidence of prior learning, before or after anything happened in this course. An "In this course" restriction only looks at the rating given in this course, which the learner may not have yet. On the course's Competencies page such a competency has no rating badge, and on the learner's competency page in the course it reads "Proficient: No, Rating: -". The restriction text says "in this course" for this reason.

If a rating from anywhere should be enough, use "Global", or combine "Yes – In this course" and "Yes – Global" in a restriction set that requires any of them.

### What does "Override existing competency grade when completed." change?

The option belongs to an activity's "Complete the competency" rule, and it only matters when a rating **already exists**:

- Without it, completing the activity rates the competency in the course only when the learner has no course rating yet, even if they are already proficient globally, and it leaves an existing global rating unchanged.
- With it, completing the activity always rewrites the course rating with the default rating and, if the course pushes its ratings to learning plans, the global rating too. That can lower a higher rating given by a teacher.

The course's "Upon course completion" rule has no such option: it never replaces an existing rating.

### A learner is stuck: rated not proficient in the course, and completing activities changes nothing. What can be done?

That is the expected result of an existing course rating plus rules without the override option. A teacher can rate the learner again with "Rate", or the override option can be enabled on an activity the learner then completes. Completions that already happened are not replayed when the rule changes later.

### Does "Push course ratings to individual learning plans" matter?

For "Global" restrictions, yes. When a course pushes its ratings, completing a competency or a teacher's rating in that course also updates the global rating, unless the learner already had one and no override applies. A teacher's "Rate" always overwrites the global rating when the course pushes, so it can close "Global" restrictions in other courses. The course's Competencies page says whether the course pushes its ratings.

### What happens after a course reset?

The course reset option "Competency ratings" deletes the course ratings of that course and nothing else. "In this course" restrictions close again for everyone, while learning plans still show the competencies as achieved. Leave the option unticked when a course reuses its learners and relies on these restrictions.

### Why must a competency be linked to the course to be chosen?

A site can hold thousands of competencies: the list of the course's competencies is what the restriction form offers, and core's course competency picker decides which frameworks a course may use (those of the course's category and its parents). Once a restriction is saved, the link is not checked again.

### What happens when a competency is unlinked, the course is moved, or competencies are disabled?

Ratings keep counting. None of these removes a rating, so a learner who was rated proficient keeps access. A learner who was not rated yet can only be rated again in the course once the competency is linked again. Editing the activity keeps the competency, shown as "(not linked to this course)".

### What happens when a competency is deleted?

Nobody counts as proficient in it, so a restriction requiring proficiency stays closed and one requiring its absence is met; both are shown with "(Competency missing)". With the setting "Clean up restrictions on competency deletion" enabled, a background task removes the conditions that can never be met again and logs each item's previous restriction.

### What happens on backup and restore, import or duplication?

The restriction is kept. When the backup includes the course's competencies, the competency is found by its framework and ID number; within the same site the competency stays as it is. On another site without a matching competency, the restriction points at no competency and the restore log says so.

### Can a teacher learn a learner's global proficiency through a "Global" restriction?

Indirectly, yes: whoever can see which learners have access to the item can infer it, although by default only managers can view global ratings directly. Keep this in mind before using "Global".

### Can a site running version 1.0.x or 1.1.0 use a backup containing "Global" restrictions?

Those versions ignore the scope and read such a restriction as "In this course". Upgrade every site that exchanges course backups together; see the upgrade notes in CHANGELOG.md.


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
