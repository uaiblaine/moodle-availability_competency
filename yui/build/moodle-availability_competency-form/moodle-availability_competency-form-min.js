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
YUI.add("moodle-availability_competency-form",function(p,e){M.availability_competency=M.availability_competency||{},M.availability_competency.form=p.Object(M.core_availability.plugin),M.availability_competency.form.competencies=null,M.availability_competency.form.initInner=function(e){this.competencies=e},M.availability_competency.form.getNode=function(e){var i,t,n=M.util.get_string("competency","availability_competency"),a=M.util.get_string("proficient","availability_competency"),c=M.util.get_string("yes","availability_competency"),o=M.util.get_string("no","availability_competency"),l='<label><span class="pr-3">'+n+'</span> <select name="competencyid" class="form-select">';for(l+='<option value="0">'+M.util.get_string("choosedots","moodle")+"</option>",i=0;i<this.competencies.length;i++)l+='<option value="'+(t=this.competencies[i]).id+'">'+t.name+"</option>";return n=p.Node.create('<span class="availability-competency">'+(l+="</select></label>")+('<label><span class="pr-3 pl-3">'+a+'</span> <select name="proficient" class="form-select"><option value="1">'+c+'</option><option value="0">'+o+"</option></select></label>")+"</span>"),e.competencyid!==undefined&&n.one("select[name=competencyid]").set("value",e.competencyid),e.proficient!==undefined&&n.one("select[name=proficient]").set("value",e.proficient),n.one("select[name=competencyid]").on("change",function(){M.core_availability.form.update()}),n.one("select[name=proficient]").on("change",function(){M.core_availability.form.update()}),n},M.availability_competency.form.fillValue=function(e,i){var t=parseInt(i.one("select[name=competencyid]").get("value"),10);0===t?e.competencyid=0:(e.competencyid=t,e.proficient=parseInt(i.one("select[name=proficient]").get("value"),10))},M.availability_competency.form.fillErrors=function(e,i){0===parseInt(i.one("select[name=competencyid]").get("value"),10)&&e.push("availability_competency:missing")}},"@VERSION@",{requires:["base","node","event","moodle-core_availability-form"]});