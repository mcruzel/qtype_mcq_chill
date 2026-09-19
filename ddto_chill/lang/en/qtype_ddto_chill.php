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
 * English strings for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addanswer'] = '+ Add a distractor';
$string['allornothing'] = 'All or nothing';
$string['allornothing_help'] = 'If enabled, the full mark is awarded only when every gap is filled with the expected choice. Any other response scores zero, minus the negative marking for each incorrectly filled gap (with the "Deferred feedback" and "Immediate feedback" behaviours only; the other behaviours never take a question below zero).';
$string['blank'] = 'Leave blank';
$string['choiceno'] = 'Distractor {$a}';
$string['choiceplaceholder'] = 'Distractor text (plain text, no HTML)';
$string['detectgaps'] = 'Detect words';
$string['distractorno'] = 'Distractor {$a}';
$string['distractors'] = 'Distractors';
$string['distractorsintro'] = 'Optional extra words shown in the bank that do not fill any gap. Blank rows are ignored when the question is saved.';
$string['draghandle'] = 'Drag to reorder';
$string['dragitem'] = 'Draggable choice: {$a}';
$string['dropzone'] = 'Gap {$a}';
$string['errnogap'] = 'Tick at least one word to turn it into a gap.';
$string['errnosourcetext'] = 'Type the sentence first.';
$string['gapmark'] = 'Make this a gap';
$string['gapwords'] = 'Words to turn into gaps';
$string['gapwords_help'] = 'Each word detected in the sentence is listed here. Tick a word to replace it with a drop zone. You do not type [[1]] markers yourself: they are generated when the question is saved.';
$string['gapwordsintro'] = 'Tick each word that should become a drop zone. The markers [[1]], [[2]], … are generated automatically.';
$string['gradingoptions'] = 'Grading';
$string['makegap'] = 'Make this a gap';
$string['movechoicedown'] = 'Move this distractor down';
$string['movechoiceup'] = 'Move this distractor up';
$string['negativemarking'] = 'Negative marking for each incorrectly filled gap';
$string['negativemarking_help'] = 'Share of the question mark deducted for each gap filled with a wrong choice. "None" means wrong placements are simply ignored; -100% deducts the whole mark of the question for each incorrectly filled gap, so the score of the question can become negative.

Outside all-or-nothing mode, each correctly filled gap earns an equal share of the mark. Empty gaps earn nothing and are not penalised. With the "Deferred feedback" and "Immediate feedback" behaviours the score of a question can go down to -100% of its mark (the gradebook never records a quiz grade below its minimum grade); the "Interactive" and "Adaptive" behaviours never take a question below zero, so negative marking then only reduces the partial credit.';
$string['negativemarkingoutofrange'] = 'The negative marking "{$a}" is not a fraction between -1 and 0; it has been adjusted to the nearest allowed value.';
$string['pleasefillallgaps'] = 'Please fill every gap.';
$string['pluginname'] = 'Drag-drop into text Chill';
$string['pluginname_help'] = 'A streamlined drag-and-drop into text question: type a sentence, tick the words that become gaps, add optional distractors, pick the negative marking applied to each incorrectly filled gap and, if you wish, require all-or-nothing grading.';
$string['pluginnameadding'] = 'Adding a Drag-drop into text Chill question';
$string['pluginnameediting'] = 'Editing a Drag-drop into text Chill question';
$string['pluginnamesummary'] = 'A drag-and-drop into text question with a shared choice bank, negative marking for wrongly filled gaps and an optional all-or-nothing mode.';
$string['privacy:preference:allornothing'] = 'Whether the "All or nothing" option was enabled in the last question you created.';
$string['privacy:preference:defaultmark'] = 'The default mark set for a given question.';
$string['privacy:preference:negativemarking'] = 'The negative marking set in the last question you created.';
$string['privacy:preference:shuffleanswers'] = 'Whether the choice bank was shuffled in the last question you created.';
$string['removeanswer'] = 'Remove this distractor';
$string['shuffleanswers'] = 'Shuffle the choices?';
$string['shuffleanswers_help'] = 'If enabled, the order of the choices in the bank (and in the drop-down fallback) is randomly shuffled for each attempt, provided that "Shuffle within questions" in the activity settings is also enabled.';
$string['sourcetext'] = 'Sentence';
$string['sourcetext_help'] = 'Type the full sentence as plain text. Tick the words below to turn them into gaps. Do not type [[1]] markers: they are generated automatically.';
$string['sourcetextplaceholder'] = 'Type the sentence here, then tick the words that should become gaps.';
$string['tooshort'] = 'This type of question requires at least one gap and one choice.';
