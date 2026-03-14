YUI.add('moodle-availability_competency-form', function (Y, NAME) {

    M.availability_competency = M.availability_competency || {};

    M.availability_competency.form = Y.Object(M.core_availability.plugin);

    M.availability_competency.form.competencies = null;

    M.availability_competency.form.initInner = function (competencies) {
        this.competencies = competencies;
    };

    M.availability_competency.form.getNode = function (json) {
        var strCompetency = M.util.get_string('competency', 'availability_competency');
        var strProficient = M.util.get_string('proficient', 'availability_competency');
        var strYes = M.util.get_string('yes', 'availability_competency');
        var strNo = M.util.get_string('no', 'availability_competency');

        var selectHtml = '<label><span class="pr-3">' + strCompetency + '</span> ' +
            '<select name="competencyid" class="form-select">';
        selectHtml += '<option value="0">' + M.util.get_string('choosedots', 'moodle') + '</option>';
        for (var i = 0; i < this.competencies.length; i++) {
            var competency = this.competencies[i];
            selectHtml += '<option value="' + competency.id + '">' + competency.name + '</option>';
        }
        selectHtml += '</select></label>';

        var proficientHtml = '<label><span class="pr-3 pl-3">' + strProficient + '</span> ' +
            '<select name="proficient" class="form-select">' +
            '<option value="1">' + strYes + '</option>' +
            '<option value="0">' + strNo + '</option>' +
            '</select></label>';

        var node = Y.Node.create('<span class="availability-competency">' + selectHtml + proficientHtml + '</span>');

        if (json.competencyid !== undefined) {
            node.one('select[name=competencyid]').set('value', json.competencyid);
        }
        if (json.proficient !== undefined) {
            node.one('select[name=proficient]').set('value', json.proficient);
        }

        node.one('select[name=competencyid]').on('change', function () {
            M.core_availability.form.update();
        });
        node.one('select[name=proficient]').on('change', function () {
            M.core_availability.form.update();
        });

        return node;
    };

    M.availability_competency.form.fillValue = function (value, node) {
        var competencyid = parseInt(node.one('select[name=competencyid]').get('value'), 10);
        if (competencyid === 0) {
            value.competencyid = 0;
        } else {
            value.competencyid = competencyid;
            value.proficient = parseInt(node.one('select[name=proficient]').get('value'), 10);
        }
    };

    M.availability_competency.form.fillErrors = function (errors, node) {
        var competencyid = parseInt(node.one('select[name=competencyid]').get('value'), 10);
        if (competencyid === 0) {
            errors.push('availability_competency:missing');
        }
    };

}, '@VERSION@', { "requires": ["base", "node", "event", "moodle-core_availability-form"] });
