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
 * Question type class for the QCM Chill question type.
 *
 * @package    qtype_mcq_chill
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/mcq_chill/question.php');

/**
 * The QCM Chill question type.
 *
 * A QCM Chill question is a multiple-answer question with a deliberately
 * lightweight editing form: plain choices, a checkbox for each correct
 * choice, one negative marking setting applied to each wrong choice selected
 * and an optional all-or-nothing mode.
 *
 * The choices are stored in the core question_answers table (fraction 1 for a
 * correct choice, 0 otherwise) and the question settings in the
 * qtype_mcq_chill_options table.
 *
 * @copyright  2025 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_mcq_chill extends question_type {
    /** @var string name of the table storing the question settings. */
    const OPTIONS_TABLE = 'qtype_mcq_chill_options';

    /** @var int above this number of choices the random guess score is not computed. */
    const MAX_CHOICES_FOR_GUESS_SCORE = 12;

    /** @var int minimum number of non-blank choices a question must have. */
    const MIN_CHOICES = 2;

    #[\Override]
    public function extra_question_fields() {
        return [self::OPTIONS_TABLE, 'negativemarking', 'allornothing', 'shuffleanswers'];
    }

    #[\Override]
    public function save_defaults_for_new_questions(stdClass $fromform): void {
        parent::save_defaults_for_new_questions($fromform);
        $this->set_default_value(
            'negativemarking',
            (string) self::clean_negative_marking($fromform->negativemarking ?? 0)
        );
        $this->set_default_value('allornothing', self::clean_flag($fromform->allornothing ?? 0, 0) ? '1' : '0');
        $this->set_default_value('shuffleanswers', self::clean_flag($fromform->shuffleanswers ?? 1, 1) ? '1' : '0');
    }

    #[\Override]
    public function save_question_options($question) {
        $result = new stdClass();

        // Normalise the settings whatever their origin (editing form, XML import, data generator).
        $rawnegativemarking = $question->negativemarking ?? 0;
        if (!is_numeric($rawnegativemarking) || (float) $rawnegativemarking < -1 || (float) $rawnegativemarking > 0) {
            // Bounded to the allowed range; a notice would stop an XML import after this question.
            debugging(
                get_string('negativemarkingoutofrange', 'qtype_mcq_chill', s((string) $rawnegativemarking)),
                DEBUG_DEVELOPER
            );
        }
        $question->negativemarking = self::clean_negative_marking($rawnegativemarking);
        $question->allornothing = self::clean_flag($question->allornothing ?? 0, 0);
        $question->shuffleanswers = self::clean_flag($question->shuffleanswers ?? 1, 1);
        $question->answer = $question->answer ?? [];
        $question->fraction = $question->fraction ?? [];

        // The editing form enforces these rules; imports and generators bypass it.
        $numchoices = 0;
        $numcorrect = 0;
        foreach (array_keys($question->answer) as $key) {
            if ($this->is_answer_empty($question, $key)) {
                continue;
            }
            $numchoices++;
            if (self::is_correct_choice($question->fraction[$key] ?? 0)) {
                $numcorrect++;
            }
        }
        if ($numchoices < self::MIN_CHOICES) {
            $result->notice = get_string('notenoughchoices', 'qtype_mcq_chill', self::MIN_CHOICES);
        } else if ($numcorrect === 0) {
            $result->notice = get_string('errnocorrectanswer', 'qtype_mcq_chill');
        }

        parent::save_question_options($question);
        $this->save_question_answers($question);
        $this->save_hints($question, true);

        return $result;
    }

    #[\Override]
    public function get_question_options($question) {
        global $DB;

        if (!isset($question->options)) {
            $question->options = new stdClass();
        }

        $options = $DB->get_record(self::OPTIONS_TABLE, ['questionid' => $question->id]);
        if (!$options) {
            debugging(
                'Question ID ' . $question->id . ' was missing an options record. Using default.',
                DEBUG_DEVELOPER
            );
            $options = $this->create_default_options($question);
        }
        $question->options->negativemarking = self::clean_negative_marking($options->negativemarking);
        $question->options->allornothing = self::clean_flag($options->allornothing, 0);
        $question->options->shuffleanswers = self::clean_flag($options->shuffleanswers, 1);

        $answers = $DB->get_records('question_answers', ['question' => $question->id], 'id ASC');
        foreach ($answers as $answer) {
            // Some database engines return decimals as strings like '1.0000000'. Cast for consistency.
            $answer->fraction = (float) $answer->fraction;
        }
        $question->options->answers = $answers;

        $question->hints = $DB->get_records('question_hints', ['questionid' => $question->id], 'id ASC');

        return true;
    }

    /**
     * Create the default settings record of a question, without storing it.
     *
     * @param stdClass $question the question we are working with.
     * @return stdClass the default settings.
     */
    public function create_default_options($question): stdClass {
        $options = new stdClass();
        $options->questionid = $question->id;
        $options->negativemarking = 0;
        $options->allornothing = 0;
        $options->shuffleanswers = 1;
        return $options;
    }

    #[\Override]
    protected function is_answer_empty($questiondata, $key) {
        return html_is_blank($this->get_answer_text($questiondata, $key));
    }

    #[\Override]
    protected function fill_answer_fields($answer, $questiondata, $key, $context) {
        // A QCM Chill choice is always plain text, displayed exactly as typed.
        $answer->answer = trim($this->get_answer_text($questiondata, $key));
        $answer->answerformat = FORMAT_PLAIN;
        $answer->fraction = self::is_correct_choice($questiondata->fraction[$key] ?? 0) ? 1.0 : 0.0;
        // Per-choice feedback is not part of a QCM Chill question.
        $answer->feedback = '';
        $answer->feedbackformat = FORMAT_HTML;
        return $answer;
    }

    /**
     * Get the text of a choice from the data being saved.
     *
     * The editing form and the import submit plain strings; a data generator
     * may submit ['text' => ...] arrays, which are accepted too.
     *
     * @param stdClass $questiondata the data being saved.
     * @param int $key the index of the choice.
     * @return string the text of the choice.
     */
    protected function get_answer_text(stdClass $questiondata, $key): string {
        $answer = $questiondata->answer[$key] ?? '';
        if (is_array($answer)) {
            return (string) ($answer['text'] ?? '');
        }
        return (string) $answer;
    }

    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found -- Widens the visibility on purpose.
    /**
     * Create an appropriate question_answer object from a database row.
     *
     * The core multiple choice question definition, which QCM Chill extends,
     * calls this method on the question type when a choice was deleted after
     * an attempt started, so it must be public.
     *
     * @param stdClass $answer the answer row, as loaded from question_answers.
     * @return question_answer the answer object.
     */
    public function make_answer($answer) {
        return parent::make_answer($answer);
    }
    // phpcs:enable Generic.CodeAnalysis.UselessOverridingMethod.Found

    /**
     * Create a hint object from a database row.
     *
     * The inherited multiple choice question expects hints with parts
     * (see qtype_multichoice_multi_question::get_hint()).
     *
     * @param stdClass $hint the hint row, as loaded from question_hints.
     * @return question_hint_with_parts the hint object.
     */
    #[\Override]
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    #[\Override]
    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        return new qtype_mcq_chill_question();
    }

    #[\Override]
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->negativemarking = self::clean_negative_marking($questiondata->options->negativemarking ?? 0);
        $question->allornothing = self::clean_flag($questiondata->options->allornothing ?? 0, 0);
        $question->shuffleanswers = self::clean_flag($questiondata->options->shuffleanswers ?? 1, 1);

        // Settings of the inherited multiple choice question that QCM Chill does not expose:
        // no numbering, the site-wide default for the standard instruction, the standard
        // "Your answer is correct / partially correct / incorrect" feedback.
        $question->answernumbering = 'none';
        $question->showstandardinstruction = (int) get_config('qtype_multichoice', 'showstandardinstruction');
        $question->layout = qtype_multichoice_base::LAYOUT_VERTICAL;
        $question->correctfeedback = get_string('correctfeedbackdefault', 'question');
        $question->correctfeedbackformat = FORMAT_HTML;
        $question->partiallycorrectfeedback = get_string('partiallycorrectfeedbackdefault', 'question');
        $question->partiallycorrectfeedbackformat = FORMAT_HTML;
        $question->incorrectfeedback = get_string('incorrectfeedbackdefault', 'question');
        $question->incorrectfeedbackformat = FORMAT_HTML;

        $this->initialise_question_answers($question, $questiondata, false);
    }

    #[\Override]
    public function get_random_guess_score($questiondata) {
        $answers = array_values($questiondata->options->answers ?? []);
        $numchoices = count($answers);
        if ($numchoices === 0 || $numchoices > self::MAX_CHOICES_FOR_GUESS_SCORE) {
            return null;
        }

        $numcorrect = self::count_correct_choices($answers);
        $negativemarking = self::clean_negative_marking($questiondata->options->negativemarking ?? 0);
        $allornothing = (bool) self::clean_flag($questiondata->options->allornothing ?? 0, 0);

        // Average the fraction over every non-empty combination of choices, each being equally likely.
        $total = 0.0;
        $numresponses = 0;
        $numcombinations = 1 << $numchoices;
        for ($combination = 1; $combination < $numcombinations; $combination++) {
            $numcorrectselected = 0;
            $numwrongselected = 0;
            foreach ($answers as $index => $answer) {
                if (!($combination & (1 << $index))) {
                    continue;
                }
                if (self::is_correct_choice($answer->fraction)) {
                    $numcorrectselected++;
                } else {
                    $numwrongselected++;
                }
            }
            $total += qtype_mcq_chill_question::compute_fraction(
                $numcorrectselected,
                $numwrongselected,
                $numcorrect,
                $negativemarking,
                $allornothing
            );
            $numresponses++;
        }

        return $total / $numresponses;
    }

    /**
     * Describe the possible responses for the response analysis report.
     *
     * Each correct choice is reported with the share of the mark it earns
     * under partial credit (1 / number of correct choices) and each wrong
     * choice with the negative marking, whatever the all-or-nothing setting.
     *
     * @param stdClass $questiondata the question data, as loaded by get_question_options().
     * @return array as required by {@see question_type::get_possible_responses()}.
     */
    #[\Override]
    public function get_possible_responses($questiondata) {
        $answers = $questiondata->options->answers ?? [];
        $numcorrect = self::count_correct_choices($answers);
        $negativemarking = self::clean_negative_marking($questiondata->options->negativemarking ?? 0);

        $parts = [];
        foreach ($answers as $ansid => $answer) {
            if (self::is_correct_choice($answer->fraction)) {
                $fraction = $numcorrect > 0 ? 1 / $numcorrect : 0;
            } else {
                $fraction = $negativemarking;
            }
            $parts[$ansid] = [
                $ansid => new question_possible_response(
                    question_utils::to_plain_text($answer->answer, $answer->answerformat),
                    $fraction
                ),
            ];
        }
        return $parts;
    }

    #[\Override]
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid, true);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    #[\Override]
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid, true);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    #[\Override]
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['@']['type']) || $data['@']['type'] !== $this->name()) {
            return false;
        }

        $qo = $format->import_headers($data);
        $qo->qtype = $this->name();
        foreach (['negativemarking', 'allornothing', 'shuffleanswers'] as $field) {
            // A missing setting keeps its default value.
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], null);
        }

        // A QCM Chill choice is plain text: HTML choices (for instance from a converted multiple
        // choice question) are reduced to their text, and embedded files are ignored.
        $qo->answer = [];
        $qo->fraction = [];
        $answers = $data['#']['answer'] ?? [];
        foreach (is_array($answers) ? $answers : [] as $answer) {
            $ans = $format->import_answer($answer, false, $format->get_format($qo->questiontextformat));
            $declaredformat = $format->trans_format($format->getpath($answer, ['@', 'format'], 'html'));
            $text = $ans->answer['text'];
            if ($declaredformat != FORMAT_PLAIN) {
                $text = html_to_text($text, 0, false);
            }
            $qo->answer[] = trim($text);
            $qo->fraction[] = $ans->fraction;
        }

        // Report an unusable question to the importer, which skips it and honours "stop on error".
        $numchoices = 0;
        $numcorrect = 0;
        foreach ($qo->answer as $key => $text) {
            if (html_is_blank($text)) {
                continue;
            }
            $numchoices++;
            if (self::is_correct_choice($qo->fraction[$key])) {
                $numcorrect++;
            }
        }
        if ($numchoices < self::MIN_CHOICES) {
            $this->report_import_error(
                $format,
                get_string('notenoughchoices', 'qtype_mcq_chill', self::MIN_CHOICES),
                $qo->name
            );
            return false;
        }
        if ($numcorrect === 0) {
            $this->report_import_error($format, get_string('errnocorrectanswer', 'qtype_mcq_chill'), $qo->name);
            return false;
        }

        return $qo;
    }

    /**
     * Report an import error the way the question import formats do, so that the import
     * counts it and, by default, stops before writing anything.
     *
     * @param qformat_xml $format the import format.
     * @param string $message the error message.
     * @param string $questionname the name of the question concerned.
     */
    protected function report_import_error(qformat_xml $format, string $message, string $questionname): void {
        global $OUTPUT;
        echo $OUTPUT->notification(s($questionname) . ': ' . $message, \core\output\notification::NOTIFY_ERROR);
        $format->importerrors++;
    }

    /**
     * Normalise a negative marking value: a number between -1 and 0, with the precision of the database.
     *
     * @param mixed $value the raw value (form select, XML import, database).
     * @return float the cleaned value.
     */
    public static function clean_negative_marking($value): float {
        if (!is_numeric($value)) {
            return 0.0;
        }
        $value = round((float) $value, 7);
        return max(-1.0, min(0.0, $value));
    }

    /**
     * Normalise a yes/no setting to 0 or 1.
     *
     * @param mixed $value the raw value.
     * @param int $default the value to use when the raw value is null.
     * @return int 0 or 1.
     */
    public static function clean_flag($value, int $default): int {
        if ($value === null) {
            return $default;
        }
        // Understands yes/no, true/false and on/off, as written in hand-made XML files.
        return clean_param($value, PARAM_BOOL) ? 1 : 0;
    }

    /**
     * Whether a stored or submitted fraction denotes a correct choice.
     *
     * @param mixed $fraction the fraction.
     * @return bool true for a correct choice.
     */
    public static function is_correct_choice($fraction): bool {
        return is_numeric($fraction) && (float) $fraction > 0;
    }

    /**
     * Count the correct choices in a list of answers.
     *
     * @param array $answers the answers, as loaded from question_answers.
     * @return int the number of correct choices.
     */
    public static function count_correct_choices(array $answers): int {
        $numcorrect = 0;
        foreach ($answers as $answer) {
            if (self::is_correct_choice($answer->fraction)) {
                $numcorrect++;
            }
        }
        return $numcorrect;
    }
}
