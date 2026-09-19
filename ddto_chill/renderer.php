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
 * Renderer for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for Drag-drop into text Chill questions.
 *
 * Each gap is a drop zone that always contains a native <select> (keyboard
 * and mobile fallback). When JavaScript runs, a bank of draggable chips is
 * shown above 768px; dropping a chip onto a gap sets the corresponding select.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddto_chill_renderer extends qtype_renderer {
    #[\Override]
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $questiontext = $question->format_questiontext($qa);
        $questiontext = $this->embed_dropzones($qa, $question, $questiontext, $options);

        $wrapperid = 'qtype-ddto-chill-' . $qa->get_slot();
        $output = html_writer::start_div('qtype_ddto_chill', ['id' => $wrapperid]);
        $output .= html_writer::div($questiontext, 'qtext');
        if (!$options->readonly) {
            $output .= $this->drag_bank($qa, $question, $options);
        }
        $output .= html_writer::end_div();

        if (!$options->readonly) {
            $this->page->requires->js_call_amd('qtype_ddto_chill/dragdrop', 'init', [[
                'containerId' => $wrapperid,
            ]]);
        }

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag(
                'div',
                $question->get_validation_error($qa->get_last_qt_data()),
                ['class' => 'validationerror']
            );
        }

        return $output;
    }

    /**
     * Replace [[n]] placeholders with drop zones (select + drop target).
     *
     * @param question_attempt $qa the attempt.
     * @param qtype_ddto_chill_question $question the question.
     * @param string $questiontext already formatted question text.
     * @param question_display_options $options display options.
     * @return string
     */
    protected function embed_dropzones(
        question_attempt $qa,
        qtype_ddto_chill_question $question,
        string $questiontext,
        question_display_options $options
    ): string {
        return preg_replace_callback(
            '/\[\[(\d+)\]\]/',
            function ($matches) use ($qa, $question, $options) {
                return $this->dropzone($qa, $question, (int) $matches[1], $options);
            },
            $questiontext
        );
    }

    /**
     * Markup of one drop zone.
     *
     * @param question_attempt $qa the attempt.
     * @param qtype_ddto_chill_question $question the question.
     * @param int $place the gap number.
     * @param question_display_options $options display options.
     * @return string
     */
    protected function dropzone(
        question_attempt $qa,
        qtype_ddto_chill_question $question,
        int $place,
        question_display_options $options
    ): string {
        $field = $question->field($place);
        $inputname = $qa->get_qt_field_name($field);
        $current = (int) $qa->get_last_qt_var($field, 0);
        $selectid = $inputname;

        $attributes = [
            'id' => $selectid,
            'class' => 'qtype-ddto-chill-select',
            'data-place' => $place,
        ];
        if ($options->readonly) {
            $attributes['disabled'] = 'disabled';
        }

        $choices = [0 => get_string('blank', 'qtype_ddto_chill')];
        foreach ($question->choiceorder as $choiceno) {
            if (isset($question->choices[$choiceno])) {
                $choices[$choiceno] = $question->choices[$choiceno]->text;
            }
        }

        $select = html_writer::label(
            get_string('dropzone', 'qtype_ddto_chill', $place),
            $selectid,
            false,
            ['class' => 'sr-only accesshide']
        );
        $select .= html_writer::select($choices, $inputname, $current, false, $attributes);

        $classes = 'qtype-ddto-chill-drop';
        if ($options->correctness && $question->is_filled($qa->get_last_qt_data(), $place)) {
            $classes .= $question->is_correct_placement($place, $current) ? ' correct' : ' incorrect';
        } else if ($options->correctness && !$question->is_filled($qa->get_last_qt_data(), $place)) {
            $classes .= ' incorrect';
        }

        $feedbackimage = '';
        if ($options->correctness) {
            $filled = $question->is_filled($qa->get_last_qt_data(), $place);
            $fraction = ($filled && $question->is_correct_placement($place, $current)) ? 1 : 0;
            $feedbackimage = $this->feedback_image($fraction);
        }

        return html_writer::span($select . $feedbackimage, $classes, ['data-place' => $place]);
    }

    /**
     * Bank of draggable chips. Hidden below 768px in favour of the selects.
     *
     * @param question_attempt $qa the attempt.
     * @param qtype_ddto_chill_question $question the question.
     * @param question_display_options $options display options.
     * @return string
     */
    protected function drag_bank(
        question_attempt $qa,
        qtype_ddto_chill_question $question,
        question_display_options $options
    ): string {
        $items = '';
        foreach ($question->choiceorder as $choiceno) {
            if (!isset($question->choices[$choiceno])) {
                continue;
            }
            $text = $question->choices[$choiceno]->text;
            $items .= html_writer::span(
                s($text),
                'qtype-ddto-chill-drag',
                [
                    'draggable' => 'true',
                    'tabindex' => '0',
                    'role' => 'button',
                    'data-choice' => $choiceno,
                    'aria-label' => get_string('dragitem', 'qtype_ddto_chill', $text),
                ]
            );
        }
        return html_writer::div($items, 'qtype-ddto-chill-bank');
    }

    #[\Override]
    public function specific_feedback(question_attempt $qa) {
        return '';
    }

    #[\Override]
    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $bits = [];
        foreach ($question->places as $place => $choiceno) {
            if (isset($question->choices[$choiceno])) {
                $bits[] = get_string('dropzone', 'qtype_ddto_chill', $place)
                    . ': ' . $question->choices[$choiceno]->text;
            }
        }
        if (empty($bits)) {
            return '';
        }
        return get_string('correctansweris', 'question', implode(', ', $bits));
    }
}
