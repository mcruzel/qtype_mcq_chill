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
 * Question type class for the Drag-drop into text Chill question type.
 *
 * @package    qtype_ddto_chill
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/ddto_chill/question.php');

/**
 * The Drag-drop into text Chill question type.
 *
 * An autonomous question type (it does not extend qtype_ddwtos): the core
 * drag-and-drop into text type has no negative marking and a storage model
 * that would get in the way. The statement is stored with [[1]], [[2]], …
 * placeholders in question.questiontext. Choices live in
 * qtype_ddto_chill_choices (plain text, one group in v1). Settings live in
 * qtype_ddto_chill_options.
 *
 * @copyright  2026 Maxime Cruzel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddto_chill extends question_type {
    /** @var string name of the table storing the question settings. */
    const OPTIONS_TABLE = 'qtype_ddto_chill_options';

    /** @var string name of the table storing the choices. */
    const CHOICES_TABLE = 'qtype_ddto_chill_choices';

    /** @var int v1 uses a single pool for every gap. */
    const DEFAULT_GROUP = 1;

    /** @var int minimum number of gaps a question must have. */
    const MIN_GAPS = 1;

    /** @var int above this number of gaps the random guess score is not computed. */
    const MAX_GAPS_FOR_GUESS_SCORE = 8;

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
    public function save_question($oldquestion, $fromform) {
        $fromform = $this->apply_source_to_form_data($fromform);
        return parent::save_question($oldquestion, $fromform);
    }

    /**
     * When the editing form submitted a source sentence, generate the stored
     * question text (with [[n]] placeholders) and the choice lists.
     *
     * XML import and the data generator can skip this by already providing
     * questiontext and answer/fraction arrays.
     *
     * @param stdClass $fromform submitted form (or generator) data.
     * @return stdClass the same object, possibly updated.
     */
    public function apply_source_to_form_data(stdClass $fromform): stdClass {
        if (!isset($fromform->sourcetext) || trim((string) $fromform->sourcetext) === '') {
            return $fromform;
        }

        $built = self::build_from_source(
            (string) $fromform->sourcetext,
            self::gap_indices_from_form($fromform),
            self::distractor_texts_from_form($fromform)
        );

        if (is_array($fromform->questiontext ?? null)) {
            $fromform->questiontext['text'] = $built->questiontext;
            $fromform->questiontext['format'] = FORMAT_HTML;
        } else {
            $fromform->questiontext = $built->questiontext;
            $fromform->questiontextformat = FORMAT_HTML;
        }
        $fromform->answer = $built->answers;
        $fromform->fraction = $built->fractions;
        $fromform->choicegroup = $built->choicegroups;
        return $fromform;
    }

    #[\Override]
    public function save_question_options($question) {
        global $DB;

        $result = new stdClass();

        $rawnegativemarking = $question->negativemarking ?? 0;
        if (!is_numeric($rawnegativemarking) || (float) $rawnegativemarking < -1 || (float) $rawnegativemarking > 0) {
            debugging(
                get_string('negativemarkingoutofrange', 'qtype_ddto_chill', s((string) $rawnegativemarking)),
                DEBUG_DEVELOPER
            );
        }
        $question->negativemarking = self::clean_negative_marking($rawnegativemarking);
        $question->allornothing = self::clean_flag($question->allornothing ?? 0, 0);
        $question->shuffleanswers = self::clean_flag($question->shuffleanswers ?? 1, 1);
        $question->answer = $question->answer ?? [];
        $question->fraction = $question->fraction ?? [];
        $question->choicegroup = $question->choicegroup ?? [];

        $choices = $this->normalise_choices_from_question($question);
        $numgaps = self::count_placeholders($question->questiontext ?? '');
        if ($numgaps < self::MIN_GAPS || empty($choices)) {
            $result->notice = get_string('tooshort', 'qtype_ddto_chill');
        }

        parent::save_question_options($question);

        $DB->delete_records(self::CHOICES_TABLE, ['questionid' => $question->id]);
        foreach ($choices as $choice) {
            $record = (object) [
                'questionid' => $question->id,
                'text' => $choice['text'],
                'choicegroup' => $choice['choicegroup'],
                'fraction' => $choice['fraction'],
            ];
            $DB->insert_record(self::CHOICES_TABLE, $record);
        }

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

        $choices = $DB->get_records(self::CHOICES_TABLE, ['questionid' => $question->id], 'id ASC');
        $question->options->choices = [];
        $no = 1;
        foreach ($choices as $choice) {
            $choice->fraction = (float) $choice->fraction;
            $choice->choicegroup = (int) $choice->choicegroup;
            $question->options->choices[$no] = $choice;
            $no++;
        }

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
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records(self::CHOICES_TABLE, ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    #[\Override]
    protected function make_question_instance($questiondata) {
        question_bank::load_question_definition_classes($this->name());
        return new qtype_ddto_chill_question();
    }

    #[\Override]
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->negativemarking = self::clean_negative_marking($questiondata->options->negativemarking ?? 0);
        $question->allornothing = self::clean_flag($questiondata->options->allornothing ?? 0, 0);
        $question->shuffleanswers = self::clean_flag($questiondata->options->shuffleanswers ?? 1, 1);

        $question->choices = [];
        $question->places = [];
        $no = 1;
        foreach ($questiondata->options->choices ?? [] as $choice) {
            $obj = (object) [
                'id' => $choice->id ?? $no,
                'text' => (string) $choice->text,
                'choicegroup' => (int) ($choice->choicegroup ?? self::DEFAULT_GROUP),
                'fraction' => (float) $choice->fraction,
            ];
            $question->choices[$no] = $obj;
            $no++;
        }

        $placeholders = self::placeholder_numbers($questiondata->questiontext ?? '');
        foreach ($placeholders as $place) {
            // Gap n expects choice n when that choice exists; otherwise the first choice.
            $question->places[$place] = isset($question->choices[$place]) ? $place : 0;
        }
    }

    #[\Override]
    public function get_random_guess_score($questiondata) {
        $choices = array_values($questiondata->options->choices ?? []);
        $numgaps = self::count_placeholders($questiondata->questiontext ?? '');
        $numchoices = count($choices);
        if ($numgaps === 0 || $numchoices === 0 || $numgaps > self::MAX_GAPS_FOR_GUESS_SCORE) {
            return null;
        }

        $negativemarking = self::clean_negative_marking($questiondata->options->negativemarking ?? 0);
        $allornothing = (bool) self::clean_flag($questiondata->options->allornothing ?? 0, 0);

        // Each gap is filled independently, including the "leave blank" option.
        // Average the fraction over every combination of placements.
        $optionspergap = $numchoices + 1; // choices plus empty.
        $numcombinations = $optionspergap ** $numgaps;
        if ($numcombinations > 20000) {
            return null;
        }

        $choicetexts = [];
        $expected = [];
        $no = 1;
        foreach ($choices as $choice) {
            $choicetexts[$no] = (string) $choice->text;
            $no++;
        }
        foreach (self::placeholder_numbers($questiondata->questiontext ?? '') as $place) {
            $expected[$place] = $choicetexts[$place] ?? '';
        }
        $places = array_keys($expected);

        $total = 0.0;
        for ($combination = 0; $combination < $numcombinations; $combination++) {
            $numcorrect = 0;
            $numwrong = 0;
            $rest = $combination;
            foreach ($places as $place) {
                $pick = $rest % $optionspergap;
                $rest = intdiv($rest, $optionspergap);
                if ($pick === 0) {
                    continue;
                }
                $text = $choicetexts[$pick] ?? '';
                if ($text === $expected[$place]) {
                    $numcorrect++;
                } else {
                    $numwrong++;
                }
            }
            $total += qtype_ddto_chill_question::compute_fraction(
                $numcorrect,
                $numwrong,
                $numgaps,
                $negativemarking,
                $allornothing
            );
        }

        return $total / $numcombinations;
    }

    #[\Override]
    public function get_possible_responses($questiondata) {
        $choices = $questiondata->options->choices ?? [];
        $placeholders = self::placeholder_numbers($questiondata->questiontext ?? '');
        $numgaps = count($placeholders);
        $negativemarking = self::clean_negative_marking($questiondata->options->negativemarking ?? 0);

        $parts = [];
        foreach ($placeholders as $place) {
            $expectedtext = isset($choices[$place]) ? (string) $choices[$place]->text : '';
            $part = [];
            foreach ($choices as $no => $choice) {
                $correct = ((string) $choice->text) === $expectedtext;
                $fraction = $correct ? ($numgaps > 0 ? 1 / $numgaps : 0) : $negativemarking;
                $part[$no] = new question_possible_response((string) $choice->text, $fraction);
            }
            $part[null] = question_possible_response::no_response();
            $parts[$place] = $part;
        }
        return $parts;
    }

    #[\Override]
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['@']['type']) || $data['@']['type'] !== $this->name()) {
            return false;
        }

        $qo = $format->import_headers($data);
        $qo->qtype = $this->name();
        foreach (['negativemarking', 'allornothing', 'shuffleanswers'] as $field) {
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], null);
        }

        $qo->answer = [];
        $qo->fraction = [];
        $qo->choicegroup = [];
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
            $group = $format->getpath($answer, ['#', 'choicegroup', 0, '#'], self::DEFAULT_GROUP);
            $qo->choicegroup[] = (int) $group;
        }

        $numgaps = self::count_placeholders($qo->questiontext ?? '');
        $numchoices = 0;
        foreach ($qo->answer as $text) {
            if (trim((string) $text) !== '') {
                $numchoices++;
            }
        }
        if ($numgaps < self::MIN_GAPS || $numchoices === 0) {
            $this->report_import_error($format, get_string('tooshort', 'qtype_ddto_chill'), $qo->name);
            return false;
        }

        return $qo;
    }

    #[\Override]
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $output = '';
        $fields = $this->extra_question_fields();
        array_shift($fields);
        foreach ($fields as $field) {
            $output .= "    <{$field}>" . $question->options->$field . "</{$field}>\n";
        }

        foreach ($question->options->choices ?? [] as $choice) {
            $fraction = ((float) $choice->fraction > 0) ? 100 : 0;
            $output .= "    <answer fraction=\"{$fraction}\" format=\"plain_text\">\n";
            $output .= $format->writetext((string) $choice->text, 3);
            $output .= "      <choicegroup>" . (int) $choice->choicegroup . "</choicegroup>\n";
            $output .= "    </answer>\n";
        }
        return $output;
    }

    /**
     * Report an import error the way the question import formats do.
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
        return clean_param($value, PARAM_BOOL) ? 1 : 0;
    }

    /**
     * Whether a stored or submitted fraction denotes a correct (gap) choice.
     *
     * @param mixed $fraction the fraction.
     * @return bool true for a gap answer.
     */
    public static function is_correct_choice($fraction): bool {
        return is_numeric($fraction) && (float) $fraction > 0;
    }

    /**
     * Split a source sentence into glue and word tokens.
     *
     * @param string $source the plain-text sentence.
     * @return array[] list of ['type' => 'word'|'glue', 'text' => string].
     */
    public static function tokenize_source(string $source): array {
        $tokens = [];
        $offset = 0;
        if (preg_match_all('/[\p{L}\p{N}]+/u', $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $pos = $match[1];
                if ($pos > $offset) {
                    $tokens[] = ['type' => 'glue', 'text' => substr($source, $offset, $pos - $offset)];
                }
                $tokens[] = ['type' => 'word', 'text' => $match[0]];
                $offset = $pos + strlen($match[0]);
            }
        }
        if ($offset < strlen($source)) {
            $tokens[] = ['type' => 'glue', 'text' => substr($source, $offset)];
        }
        return $tokens;
    }

    /**
     * The word tokens of a source sentence, in order.
     *
     * @param string $source the plain-text sentence.
     * @return string[]
     */
    public static function word_list(string $source): array {
        $words = [];
        foreach (self::tokenize_source($source) as $token) {
            if ($token['type'] === 'word') {
                $words[] = $token['text'];
            }
        }
        return $words;
    }

    /**
     * Build the stored question text and the choice lists from the editing form.
     *
     * Each ticked word becomes a gap [[n]] and a choice with fraction 1.0.
     * Distractors are appended with fraction 0.0.
     *
     * @param string $source the plain-text sentence.
     * @param int[] $gapindices 0-based word indices that should become gaps.
     * @param string[] $distractors extra choice texts.
     * @return stdClass with questiontext, answers, fractions, choicegroups.
     */
    public static function build_from_source(string $source, array $gapindices, array $distractors): stdClass {
        $gapset = [];
        foreach ($gapindices as $index) {
            $gapset[(int) $index] = true;
        }

        $html = '';
        $answers = [];
        $fractions = [];
        $choicegroups = [];
        $wordindex = 0;
        $gapno = 0;
        foreach (self::tokenize_source($source) as $token) {
            if ($token['type'] === 'glue') {
                $html .= nl2br(s($token['text']));
                continue;
            }
            if (!empty($gapset[$wordindex])) {
                $gapno++;
                $html .= '[[' . $gapno . ']]';
                $answers[] = $token['text'];
                $fractions[] = 1.0;
                $choicegroups[] = self::DEFAULT_GROUP;
            } else {
                $html .= s($token['text']);
            }
            $wordindex++;
        }

        foreach ($distractors as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $answers[] = $text;
            $fractions[] = 0.0;
            $choicegroups[] = self::DEFAULT_GROUP;
        }

        $built = new stdClass();
        $built->questiontext = $html;
        $built->answers = $answers;
        $built->fractions = $fractions;
        $built->choicegroups = $choicegroups;
        $built->numgaps = $gapno;
        return $built;
    }

    /**
     * Reconstruct the source sentence and the ticked word indices from stored data.
     *
     * @param string $questiontext the stored question text, with [[n]] placeholders.
     * @param array $choices 1-based choice number => object with a text property.
     * @return stdClass with sourcetext, words, gapindices, atomic (list of checkbox labels).
     */
    public static function reconstruct_source(string $questiontext, array $choices): stdClass {
        $parts = preg_split('/\[\[(\d+)\]\]/', $questiontext, -1, PREG_SPLIT_DELIM_CAPTURE);
        $source = '';
        $words = [];
        $gapindices = [];
        $labels = [];
        $wordindex = 0;

        $partcount = count($parts);
        for ($i = 0; $i < $partcount; $i++) {
            if ($i % 2 === 0) {
                $plain = html_to_text($parts[$i], 0, false);
                $source .= $plain;
                foreach (self::word_list($plain) as $word) {
                    $words[] = $word;
                    $labels[] = ['text' => $word, 'gap' => false];
                    $wordindex++;
                }
            } else {
                $choiceno = (int) $parts[$i];
                $text = isset($choices[$choiceno]) ? (string) $choices[$choiceno]->text : '';
                if ($text === '') {
                    $text = '[[' . $choiceno . ']]';
                }
                $source .= $text;
                $gapindices[] = $wordindex;
                $words[] = $text;
                $labels[] = ['text' => $text, 'gap' => true];
                $wordindex++;
            }
        }

        $result = new stdClass();
        $result->sourcetext = $source;
        $result->words = $words;
        $result->gapindices = $gapindices;
        $result->labels = $labels;
        return $result;
    }

    /**
     * The 1-based placeholder numbers present in a question text, in order of appearance.
     *
     * @param string $questiontext the stored question text.
     * @return int[]
     */
    public static function placeholder_numbers(string $questiontext): array {
        $numbers = [];
        if (preg_match_all('/\[\[(\d+)\]\]/', $questiontext, $matches)) {
            foreach ($matches[1] as $n) {
                $numbers[] = (int) $n;
            }
        }
        return $numbers;
    }

    /**
     * How many gaps the stored question text declares.
     *
     * @param string $questiontext the stored question text.
     * @return int
     */
    public static function count_placeholders(string $questiontext): int {
        return count(self::placeholder_numbers($questiontext));
    }

    /**
     * Read the ticked word indices from submitted form data.
     *
     * @param stdClass $fromform the form data.
     * @return int[]
     */
    public static function gap_indices_from_form(stdClass $fromform): array {
        $indices = [];
        $selection = trim((string) ($fromform->gapselection ?? ''));
        if ($selection !== '') {
            foreach (explode(',', $selection) as $bit) {
                $bit = trim($bit);
                if ($bit !== '' && ctype_digit($bit)) {
                    $indices[] = (int) $bit;
                }
            }
            return array_values(array_unique($indices));
        }
        foreach ($fromform->isgap ?? [] as $index => $value) {
            if (!empty($value)) {
                $indices[] = (int) $index;
            }
        }
        return $indices;
    }

    /**
     * Read the distractor texts from submitted form data.
     *
     * @param stdClass $fromform the form data.
     * @return string[]
     */
    public static function distractor_texts_from_form(stdClass $fromform): array {
        $texts = [];
        foreach ($fromform->answer ?? [] as $answer) {
            if (is_array($answer)) {
                $answer = $answer['text'] ?? '';
            }
            $texts[] = (string) $answer;
        }
        return $texts;
    }

    /**
     * Normalise the choice list being saved (drop blanks, force group and fraction).
     *
     * @param stdClass $question the data being saved.
     * @return array[] list of ['text', 'choicegroup', 'fraction'].
     */
    protected function normalise_choices_from_question(stdClass $question): array {
        $choices = [];
        foreach (array_keys($question->answer) as $key) {
            $text = $question->answer[$key];
            if (is_array($text)) {
                $text = $text['text'] ?? '';
            }
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $group = (int) ($question->choicegroup[$key] ?? self::DEFAULT_GROUP);
            if ($group < 1) {
                $group = self::DEFAULT_GROUP;
            }
            $choices[] = [
                'text' => $text,
                'choicegroup' => $group,
                'fraction' => self::is_correct_choice($question->fraction[$key] ?? 0) ? 1.0 : 0.0,
            ];
        }
        return $choices;
    }
}
