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
 * English strings for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allornothing'] = 'All or nothing';
$string['allornothing_help'] = 'If enabled, the full mark is awarded only when every correct choice is selected and no wrong choice is selected. Any other response scores zero, minus the negative marking for each wrong choice selected (with the "Deferred feedback" and "Immediate feedback" behaviours only; the other behaviours never take a question below zero).';
$string['choiceno'] = 'Choice {$a}';
$string['correctanswer'] = 'Correct answer';
$string['errcorrectblank'] = 'A blank choice cannot be marked as correct.';
$string['errnocorrectanswer'] = 'At least one choice must be marked as correct.';
$string['gradingoptions'] = 'Grading';
$string['negativemarking'] = 'Negative marking for each wrong choice selected';
$string['negativemarking_help'] = 'Share of the question mark deducted for each wrong choice the student selects. "None" means wrong choices are simply ignored; -100% deducts the whole mark of the question for each wrong choice selected, so the score of the question can become negative.

Outside all-or-nothing mode, each correct choice selected earns an equal share of the mark. Beware that with "None" and partial credit, selecting every choice earns the full mark: choose a negative marking or the all-or-nothing mode to avoid it. With the "Deferred feedback" and "Immediate feedback" behaviours the score of a question can go down to -100% of its mark (the gradebook never records a quiz grade below its minimum grade); the "Interactive" and "Adaptive" behaviours never take a question below zero, so negative marking then only reduces the partial credit.';
$string['negativemarkingoutofrange'] = 'The negative marking "{$a}" is not a fraction between -1 and 0; it has been adjusted to the nearest allowed value.';
$string['notenoughchoices'] = 'This type of question requires at least {$a} choices.';
$string['pluginname'] = 'QCM Chill';
$string['pluginname_help'] = 'A streamlined multiple-answer question: type the choices, tick the correct ones, pick the negative marking applied to each wrong choice selected and, if you wish, require all-or-nothing grading.';
$string['pluginnameadding'] = 'Adding a QCM Chill question';
$string['pluginnameediting'] = 'Editing a QCM Chill question';
$string['pluginnamesummary'] = 'A simple multiple-answer question with one checkbox per choice, negative marking for wrong choices and an optional all-or-nothing mode.';
$string['privacy:preference:allornothing'] = 'Whether the "All or nothing" option was enabled in the last question you created.';
$string['privacy:preference:defaultmark'] = 'The default mark set for a given question.';
$string['privacy:preference:negativemarking'] = 'The negative marking set in the last question you created.';
$string['privacy:preference:shuffleanswers'] = 'Whether the choices were shuffled in the last question you created.';
$string['shuffleanswers'] = 'Shuffle the choices?';
$string['shuffleanswers_help'] = 'If enabled, the order of the choices is randomly shuffled for each attempt, provided that "Shuffle within questions" in the activity settings is also enabled.';
