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
$string['cleanuponcompetencydeletion_desc'] = 'Se habilitado, quando uma competência é excluída, as condições de restrição sobre ela que nunca mais poderão ser cumpridas são removidas de todas as atividades e seções logo depois, por uma tarefa em segundo plano. Isso evita que itens restritos a quem é proficiente na competência fiquem indisponíveis para sempre. As condições que a competência excluída agora sempre cumpre, como uma que exige que o aluno não seja proficiente, permanecem e são exibidas como \'{$a}\', porque removê-las poderia tornar um item indisponível. O log da tarefa guarda a restrição anterior de cada item alterado.<br />Se desabilitado, todas as restrições permanecem e são exibidas como \'{$a}\'; ninguém conta como proficiente numa competência excluída, então uma restrição que a exige continua fechada e uma que exige a sua ausência é sempre cumprida.';
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
$string['requires_competency_global'] = 'Você deve ser proficiente na competência <strong>{$a}</strong> (proficiência global)';
$string['requires_not_competency'] = 'Você não deve ser proficiente na competência <strong>{$a}</strong> neste curso';
$string['requires_not_competency_global'] = 'Você não deve ser proficiente na competência <strong>{$a}</strong> (proficiência global)';
$string['task_remove_deleted_competency'] = 'Remover restrições de uma competência excluída';
$string['title'] = 'Competência';
