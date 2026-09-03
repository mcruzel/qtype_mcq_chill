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
 * French strings for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allornothing'] = 'Tout ou rien';
$string['allornothing_help'] = 'Si cette option est activée, la note complète n\'est attribuée que si toutes les bonnes réponses sont cochées et aucune mauvaise. Toute autre réponse vaut zéro, diminué des points négatifs pour chaque mauvaise case cochée (avec les comportements « Rétroaction a posteriori » et « Rétroaction immédiate » seulement ; les autres comportements ne font jamais descendre une question sous zéro).';
$string['choiceno'] = 'Réponse {$a}';
$string['correctanswer'] = 'Bonne réponse';
$string['errcorrectblank'] = 'Une réponse vide ne peut pas être marquée comme bonne réponse.';
$string['errnocorrectanswer'] = 'Au moins une réponse doit être marquée comme bonne réponse.';
$string['gradingoptions'] = 'Notation';
$string['negativemarking'] = 'Points négatifs par mauvaise case cochée';
$string['negativemarking_help'] = 'Part de la note de la question retirée pour chaque mauvaise case cochée par l\'étudiant. « Aucun » signifie que les mauvaises cases sont simplement ignorées ; -100 % signifie qu\'une seule mauvaise case annule toute la question.

Hors mode « tout ou rien », chaque bonne case cochée rapporte une part égale de la note. Avec les comportements « Rétroaction a posteriori » et « Rétroaction immédiate », la note d\'une question peut descendre jusqu\'à -100 % de sa valeur (le carnet de notes n\'enregistre jamais une note de test inférieure à sa note minimale) ; les comportements « Interactif » et « Adaptatif » ne font jamais descendre une question sous zéro : les points négatifs y réduisent seulement le crédit partiel.';
$string['notenoughchoices'] = 'Ce type de question requiert au moins {$a} réponses.';
$string['pluginname'] = 'QCM Chill';
$string['pluginname_help'] = 'Un QCM à réponses multiples simplifié : saisissez les réponses, cochez les bonnes, choisissez les points négatifs appliqués à chaque mauvaise case cochée et, si vous le souhaitez, exigez le mode « tout ou rien ».';
$string['pluginnameadding'] = 'Ajout d\'une question QCM Chill';
$string['pluginnameediting'] = 'Modification d\'une question QCM Chill';
$string['pluginnamesummary'] = 'Un QCM à réponses multiples simple, avec une case à cocher par réponse, des points négatifs pour les mauvaises réponses et un mode « tout ou rien » facultatif.';
$string['privacy:preference:allornothing'] = 'Indique si l\'option « Tout ou rien » était activée dans la dernière question que vous avez créée.';
$string['privacy:preference:defaultmark'] = 'La note par défaut définie pour une question donnée.';
$string['privacy:preference:negativemarking'] = 'Les points négatifs définis dans la dernière question que vous avez créée.';
$string['privacy:preference:shuffleanswers'] = 'Indique si les réponses étaient mélangées dans la dernière question que vous avez créée.';
$string['shuffleanswers'] = 'Mélanger les réponses ?';
$string['shuffleanswers_help'] = 'Si cette option est activée, l\'ordre des réponses est mélangé aléatoirement à chaque tentative, à condition que le réglage « Mélanger les éléments des questions » soit aussi activé dans l\'activité.';
