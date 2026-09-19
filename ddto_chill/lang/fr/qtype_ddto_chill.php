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
 * French strings for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addanswer'] = '+ Ajouter un distracteur';
$string['allornothing'] = 'Tout ou rien';
$string['allornothing_help'] = 'Si cette option est activée, la note complète n\'est attribuée que si chaque trou est rempli avec le choix attendu. Toute autre réponse vaut zéro, diminué des points négatifs pour chaque trou mal rempli (avec les comportements « Rétroaction a posteriori » et « Rétroaction immédiate » seulement ; les autres comportements ne font jamais descendre une question sous zéro).';
$string['blank'] = 'Laisser vide';
$string['choiceno'] = 'Distracteur {$a}';
$string['choiceplaceholder'] = 'Texte du distracteur (texte brut, sans HTML)';
$string['detectgaps'] = 'Détecter les mots';
$string['distractorno'] = 'Distracteur {$a}';
$string['distractors'] = 'Distracteurs';
$string['distractorsintro'] = 'Mots supplémentaires affichés dans la banque, qui ne correspondent à aucun trou. Les lignes laissées vides sont ignorées à l\'enregistrement.';
$string['draghandle'] = 'Glisser pour réordonner';
$string['dragitem'] = 'Choix à glisser : {$a}';
$string['dropzone'] = 'Trou {$a}';
$string['errnogap'] = 'Cochez au moins un mot pour en faire un trou.';
$string['errnosourcetext'] = 'Saisissez d\'abord la phrase.';
$string['gapmark'] = 'En faire un trou';
$string['gapwords'] = 'Mots à transformer en trous';
$string['gapwords_help'] = 'Chaque mot détecté dans la phrase est listé ici. Cochez un mot pour le remplacer par une zone de dépôt. Vous ne tapez pas vous-même les marqueurs [[1]] : ils sont générés à l\'enregistrement.';
$string['gapwordsintro'] = 'Cochez chaque mot qui doit devenir une zone de dépôt. Les marqueurs [[1]], [[2]], … sont générés automatiquement.';
$string['gradingoptions'] = 'Notation';
$string['makegap'] = 'En faire un trou';
$string['movechoicedown'] = 'Descendre ce distracteur';
$string['movechoiceup'] = 'Monter ce distracteur';
$string['negativemarking'] = 'Points négatifs par trou mal rempli';
$string['negativemarking_help'] = 'Part de la note de la question retirée pour chaque trou rempli avec un mauvais choix. « Aucun » signifie que les mauvais placements sont simplement ignorés ; -100 % retire la totalité de la note de la question pour chaque trou mal rempli, si bien que la note de la question peut devenir négative.

Hors mode « tout ou rien », chaque trou correctement rempli rapporte une part égale de la note. Un trou laissé vide ne rapporte rien et n\'est pas pénalisé. Avec les comportements « Rétroaction a posteriori » et « Rétroaction immédiate », la note d\'une question peut descendre jusqu\'à -100 % de sa valeur (le carnet de notes n\'enregistre jamais une note de test inférieure à sa note minimale) ; les comportements « Interactif » et « Adaptatif » ne font jamais descendre une question sous zéro : les points négatifs y réduisent seulement le crédit partiel.';
$string['negativemarkingoutofrange'] = 'Les points négatifs « {$a} » ne sont pas une fraction comprise entre -1 et 0 ; la valeur a été ramenée à la valeur admise la plus proche.';
$string['pleasefillallgaps'] = 'Veuillez remplir tous les trous.';
$string['pluginname'] = 'Texte à trous Chill';
$string['pluginname_help'] = 'Un texte à trous glisser-déposer simplifié : saisissez une phrase, cochez les mots qui deviennent des trous, ajoutez éventuellement des distracteurs, choisissez les points négatifs appliqués à chaque trou mal rempli et, si vous le souhaitez, exigez le mode « tout ou rien ».';
$string['pluginnameadding'] = 'Ajout d\'une question Texte à trous Chill';
$string['pluginnameediting'] = 'Modification d\'une question Texte à trous Chill';
$string['pluginnamesummary'] = 'Un texte à trous glisser-déposer, avec une banque de choix partagée, des points négatifs pour les trous mal remplis et un mode « tout ou rien » facultatif.';
$string['privacy:preference:allornothing'] = 'Indique si l\'option « Tout ou rien » était activée dans la dernière question que vous avez créée.';
$string['privacy:preference:defaultmark'] = 'La note par défaut définie pour une question donnée.';
$string['privacy:preference:negativemarking'] = 'Les points négatifs définis dans la dernière question que vous avez créée.';
$string['privacy:preference:shuffleanswers'] = 'Indique si la banque de choix était mélangée dans la dernière question que vous avez créée.';
$string['removeanswer'] = 'Supprimer ce distracteur';
$string['shuffleanswers'] = 'Mélanger les choix ?';
$string['shuffleanswers_help'] = 'Si cette option est activée, l\'ordre des choix dans la banque (et dans le menu déroulant de repli) est mélangé aléatoirement à chaque tentative, à condition que le réglage « Mélanger les éléments des questions » soit aussi activé dans l\'activité.';
$string['sourcetext'] = 'Phrase';
$string['sourcetext_help'] = 'Saisissez la phrase complète en texte brut. Cochez ensuite les mots ci-dessous pour en faire des trous. Ne tapez pas les marqueurs [[1]] : ils sont générés automatiquement.';
$string['sourcetextplaceholder'] = 'Saisissez la phrase ici, puis cochez les mots qui doivent devenir des trous.';
$string['tooshort'] = 'Ce type de question requiert au moins un trou et un choix.';
