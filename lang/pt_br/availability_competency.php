<?php
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
 * Portuguese (Brazil) language strings.
 *
 * @package availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cleanuponcompetencydeletion'] = 'Limpar restrições ao excluir competência';
$string['cleanuponcompetencydeletion_desc'] = 'Ninguém pode ser proficiente numa competência excluída: uma condição que exige proficiência nela nunca mais será cumprida, e uma que exige não ser proficiente será sempre cumprida. Se habilitado, uma tarefa em segundo plano remove das atividades e seções as condições que nunca mais serão cumpridas, para que deixem de bloquear o acesso; as demais permanecem e são exibidas como \'{$a}\'. O log da tarefa guarda a restrição anterior de cada item alterado. Se desabilitado, nada é removido.';
$string['competency'] = 'Competência do curso';
$string['description'] = 'Requer proficiência em uma competência específica.';
$string['error_selectcompetency'] = 'Selecione uma competência.';
$string['missing'] = '(Competência ausente)';
$string['notlinked'] = '{$a} (não vinculada a este curso)';
$string['notproficient_course'] = 'Não – Neste curso';
$string['notproficient_global'] = 'Não – Global';
$string['pluginname'] = 'Restrição por competência';
$string['privacy:metadata'] = 'O plugin Restrição por competência não armazena nenhum dado pessoal.';
$string['proficient'] = 'Proficiente';
$string['proficient_course'] = 'Sim – Neste curso';
$string['proficient_global'] = 'Sim – Global';
$string['requires_competency'] = 'Você deve ser proficiente na competência <strong>{$a}</strong> neste curso';
$string['requires_competency_global'] = 'Você deve ser proficiente na competência <strong>{$a}</strong>';
$string['requires_not_competency'] = 'Você não deve ser proficiente na competência <strong>{$a}</strong> neste curso';
$string['requires_not_competency_global'] = 'Você não deve ser proficiente na competência <strong>{$a}</strong>';
$string['task_remove_deleted_competency'] = 'Remover restrições de uma competência excluída';
$string['title'] = 'Competência';
