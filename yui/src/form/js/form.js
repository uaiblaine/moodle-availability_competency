// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript form fragment for the availability_competency plugin.
 *
 * @module     moodle-availability_competency-form
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
M.availability_competency = M.availability_competency || {}; // eslint-disable-line camelcase

/**
 * @class M.availability_competency.form
 * @extends M.core_availability.plugin
 */
M.availability_competency.form = Y.Object(M.core_availability.plugin);

/**
 * Competencies linked to the course, as objects with id and an HTML-safe name, sorted by name.
 *
 * @property competencies
 * @type Array
 */
M.availability_competency.form.competencies = null;

/**
 * Competencies named by this item's stored conditions but not linked to the course, as objects
 * with id and an HTML-safe name, the name null when the competency no longer exists.
 *
 * @property stored
 * @type Array
 */
M.availability_competency.form.stored = null;

/**
 * CSS class of a select on this Moodle branch.
 *
 * @property selectClass
 * @type String
 */
M.availability_competency.form.selectClass = null;

/**
 * Initialises this plugin with the parameters of frontend::get_javascript_init_params().
 *
 * @method initInner
 * @param {Array} competencies Competencies linked to the course.
 * @param {Array} stored Competencies named by stored conditions but not linked.
 * @param {String} selectClass CSS class of a select.
 */
M.availability_competency.form.initInner = function(competencies, stored, selectClass) {
    this.competencies = competencies;
    this.stored = stored;
    this.selectClass = selectClass;
};

/**
 * Option label for a stored competency which is not linked to the course.
 *
 * Offering it keeps the condition intact when the item is edited, instead of the select falling
 * back to no value. Flagging such a competency comes from availability_competencies by ssystems
 * GmbH, which shows a badge; keeping it selectable is this plugin's own.
 *
 * @method getStoredLabel
 * @param {Number} competencyid Competency ID.
 * @return {String} HTML-safe label.
 */
M.availability_competency.form.getStoredLabel = function(competencyid) {
    for (var i = 0; i < this.stored.length; i++) {
        if (this.stored[i].id === competencyid && this.stored[i].name !== null) {
            return M.util.get_string('notlinked', 'availability_competency', this.stored[i].name);
        }
    }
    return M.util.get_string('missing', 'availability_competency');
};

/**
 * Gets the form node for one competency condition.
 *
 * @method getNode
 * @param {Object} json Stored condition data, empty for a new condition.
 * @return {Y.Node} The node.
 */
M.availability_competency.form.getNode = function(json) {
    var competencyid = json.competencyid !== undefined ? parseInt(json.competencyid, 10) : 0;
    var found = false;

    var html = '<label><span class="pe-3">' + M.util.get_string('competency', 'availability_competency') + '</span> ' +
            '<select name="competencyid" class="' + this.selectClass + '">' +
            '<option value="0">' + M.util.get_string('choosedots', 'moodle') + '</option>';
    for (var i = 0; i < this.competencies.length; i++) {
        var competency = this.competencies[i];
        html += '<option value="' + competency.id + '">' + competency.name + '</option>';
        if (competency.id === competencyid) {
            found = true;
        }
    }
    if (competencyid > 0 && !found) {
        html += '<option value="' + competencyid + '">' + this.getStoredLabel(competencyid) + '</option>';
    }
    html += '</select></label>';

    html += '<label><span class="ps-3 pe-3">' + M.util.get_string('proficient', 'availability_competency') + '</span> ' +
            '<select name="proficiency" class="' + this.selectClass + '">' +
            '<option value="1-course">' + M.util.get_string('proficient_course', 'availability_competency') + '</option>' +
            '<option value="1-global">' + M.util.get_string('proficient_global', 'availability_competency') + '</option>' +
            '<option value="0-course">' + M.util.get_string('notproficient_course', 'availability_competency') + '</option>' +
            '<option value="0-global">' + M.util.get_string('notproficient_global', 'availability_competency') + '</option>' +
            '</select></label>';

    var node = Y.Node.create('<span class="availability-competency">' + html + '</span>');

    node.one('select[name=competencyid]').set('value', '' + competencyid);
    if (json.proficient !== undefined) {
        var scope = json.scope === 'global' ? 'global' : 'course';
        node.one('select[name=proficiency]').set('value', (parseInt(json.proficient, 10) ? '1' : '0') + '-' + scope);
    }

    node.one('select[name=competencyid]').on('change', function() {
        M.core_availability.form.update();
    });
    node.one('select[name=proficiency]').on('change', function() {
        M.core_availability.form.update();
    });

    return node;
};

/**
 * Fills the condition data from the form node.
 *
 * @method fillValue
 * @param {Object} value Condition data to fill.
 * @param {Y.Node} node The form node.
 */
M.availability_competency.form.fillValue = function(value, node) {
    var competencyid = parseInt(node.one('select[name=competencyid]').get('value'), 10);
    var proficiency = node.one('select[name=proficiency]').get('value').split('-');
    value.competencyid = isNaN(competencyid) ? 0 : competencyid;
    value.proficient = parseInt(proficiency[0], 10);
    value.scope = proficiency[1];
};

/**
 * Reports a condition with no competency chosen.
 *
 * @method fillErrors
 * @param {Array} errors Error string identifiers to add to.
 * @param {Y.Node} node The form node.
 */
M.availability_competency.form.fillErrors = function(errors, node) {
    var competencyid = parseInt(node.one('select[name=competencyid]').get('value'), 10);
    if (isNaN(competencyid) || competencyid <= 0) {
        errors.push('availability_competency:error_selectcompetency');
    }
};
