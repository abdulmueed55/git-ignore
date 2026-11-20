<?php

namespace App\Services\Attendance;

use App\Models\Student;
use App\Services\Ai\Ai;
use App\Services\Ai\AiException;
use App\Services\Ai\JsonCompletion;
use Illuminate\Support\Collection;

/**
 * Turns what a teacher says ("Ahmed Raza aur Bilal absent hain, Hamza ki leave hai,
 * baqi sab present") into register marks for the students of one class.
 *
 * With an AI key (OpenAI, Gemini or Claude) the sentence is understood by the model,
 * which also copes with Urdu script and spelling variants. Without a key, or if the
 * provider fails, a built-in matcher handles names, roll numbers and common
 * English / Roman Urdu words.
 */
class VoiceAttendance
{
    /** Status words, longest first so "ghair hazir" wins over "hazir". */
    private const WORDS = [
        'A' => ['ghair haazir', 'ghair hazir', 'gair hazir', 'ghairhazir', 'ghair-hazir', 'not present', 'not here', 'not came', 'didnt come', 'did not come', 'nahi aaya', 'nahin aaya', 'nahi aya', 'nai aaya', 'nahi aaye', 'nahin aaye', 'nahi aye', 'nahi aayi', 'nahin aayi', 'nahi aayin', 'nahi ayi', 'nahi aai', 'nai aayi', 'ghayab', 'gayab', 'absent', 'missing', 'غیر حاضر', 'غائب'],
        'E' => ['on leave', 'leave par', 'chhutti', 'chutti', 'chuti', 'rukhsat', 'leave', 'چھٹی', 'رخصت'],
        'L' => ['der se', 'dair se', 'late', 'لیٹ', 'دیر'],
        'P' => ['haazir', 'hazir', 'present', 'حاضر'],
    ];

    private const REST = ['baqi sab', 'baki sab', 'baqi', 'baki', 'rest', 'everyone else', 'all others', 'others', 'sab', 'all', 'باقی', 'سب'];

    private const FILLER = ['hain', 'hai', 'he', 'ho', 'ka', 'ki', 'ke', 'ko', 'aur', 'or', 'and', 'roll', 'number', 'no', 'num', 'is', 'are', 'was', 'were', 'today', 'aaj', 'class', 'mein', 'me', 'par', 'pe', 'se', 'bhi', 'also', 'the', 'student', 'students', 'sir', 'madam'];

    private const SAME = ['mohammad' => 'muhammad', 'mohammed' => 'muhammad', 'muhammed' => 'muhammad', 'mohd' => 'muhammad', 'ahmad' => 'ahmed', 'usama' => 'osama', 'hasan' => 'hassan', 'hussein' => 'hussain', 'husain' => 'hussain'];

    public function __construct(private readonly ?JsonCompletion $ai = null) {}

    public static function make(): self
    {
        return new self(Ai::client());
    }

    /**
     * @param  Collection<int, Student>  $roster
     * @return array{engine: string, marks: array<int, array{id:int, status:string, heard:string}>, everyone_else: string, unclear: array<int,string>, warning: ?string}
     */
    public function parse(string $transcript, Collection $roster): array
    {
        $transcript = trim(mb_substr($transcript, 0, 2000));
        $warning = null;

        if ($this->ai) {
            try {
                return $this->withAi($transcript, $roster) + ['warning' => null];
            } catch (AiException $e) {
                $warning = $e->getMessage().' Used the built-in matcher instead.';
            }
        }

        return $this->withRules($transcript, $roster) + ['warning' => $warning];
    }

    // ------------------------------------------------------------------ AI

    private function withAi(string $transcript, Collection $roster): array
    {
        $list = $roster->map(fn (Student $s) => ['student_id' => $s->id, 'name' => $s->name, 'roll_no' => $s->roll_no, 'father' => $s->father_name])->values()->all();

        $system = 'You take attendance for a college class in Pakistan. A teacher speaks in English, Urdu or Roman Urdu '
            .'(for example "Ahmed aur Bilal absent hain, Hamza ki chutti hai, baqi sab present"). Match every student the teacher '
            .'mentions to exactly one student in the class list, by name (allow spelling and speech-recognition variants, '
            .'Urdu script, first name only when it is unique in the list) or by roll number (the number at the end of roll_no). '
            .'Status codes: A = absent (ghair hazir, nahi aaya), E = leave (chutti, rukhsat), L = late (der se), P = present (hazir). '
            .'everyone_else is the status for students not mentioned: "P" unless the teacher says otherwise, "A" if they say the rest are absent. '
            .'Never guess: if a spoken name could be more than one student, or matches nobody, put the words in unclear instead of marking anyone.';

        $prompt = "Class list (JSON):\n".json_encode($list, JSON_UNESCAPED_UNICODE)."\n\nTeacher said:\n\"\"\"{$transcript}\"\"\"";

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['marks', 'everyone_else', 'unclear'],
            'properties' => [
                'marks' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['student_id', 'status', 'heard'],
                    'properties' => [
                        'student_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['P', 'A', 'L', 'E']],
                        'heard' => ['type' => 'string', 'description' => 'The words in the transcript that refer to this student'],
                    ],
                ]],
                'everyone_else' => ['type' => 'string', 'enum' => ['P', 'A']],
                'unclear' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        $data = $this->ai->json($system, $prompt, $schema);
        $ids = $roster->pluck('id')->flip();

        $marks = collect($data['marks'] ?? [])
            ->filter(fn ($m) => isset($m['student_id'], $ids[(int) $m['student_id']]) && in_array($m['status'] ?? null, ['P', 'A', 'L', 'E'], true))
            ->unique('student_id')
            ->map(fn ($m) => ['id' => (int) $m['student_id'], 'status' => $m['status'], 'heard' => mb_substr((string) ($m['heard'] ?? ''), 0, 80)])
            ->values()->all();

        return [
            'engine' => $this->ai->label(),
            'marks' => $marks,
            'everyone_else' => in_array($data['everyone_else'] ?? 'P', ['P', 'A'], true) ? $data['everyone_else'] : 'P',
            'unclear' => array_values(array_map(fn ($u) => mb_substr((string) $u, 0, 120), (array) ($data['unclear'] ?? []))),
        ];
    }

    // ------------------------------------------------------------------ built-in matcher

    private function withRules(string $transcript, Collection $roster): array
    {
        // Keep sentence breaks (. : ; ?) apart from commas — they decide which status a name belongs to.
        $text = ' '.mb_strtolower(str_replace(["'", '’'], '', $transcript)).' ';
        $text = preg_replace('/[.;:!?\x{06D4}\x{061F}]+/u', ' . ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-.]+/u', ' , ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        // 1. Status words with their positions.
        $hits = [];
        foreach (self::WORDS as $status => $words) {
            foreach ($words as $w) {
                $offset = 0;
                while (($pos = mb_strpos($text, ' '.$w.' ', $offset)) !== false) {
                    $taken = collect($hits)->contains(fn ($h) => $pos + 1 < $h['end'] && $pos + 1 + mb_strlen($w) > $h['pos']);
                    if (! $taken) {
                        $hits[] = ['pos' => $pos + 1, 'end' => $pos + 1 + mb_strlen($w), 'status' => $status];
                    }
                    $offset = $pos + 1;
                }
            }
        }
        usort($hits, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        // 2. Give the text between two status words to the one before or after it:
        //    "Ahmed absent, Hamza leave" (name first) and "absent: Ahmed. leave: Hamza" (status first).
        $n = count($hits);
        $pieces = array_fill(0, $n, '');
        for ($c = 0; $c <= $n; $c++) {
            $from = $c === 0 ? 0 : $hits[$c - 1]['end'];
            $to = $c === $n ? mb_strlen($text) : $hits[$c]['pos'];
            $chunk = mb_substr($text, $from, max(0, $to - $from));
            if ($n === 0) {
                break;
            }
            if ($c === 0 || $c === $n) {
                $pieces[$c === 0 ? 0 : $n - 1] .= ' , '.$chunk;

                continue;
            }
            $leftHas = $this->mentionsRest($pieces[$c - 1]) || $this->findStudents($pieces[$c - 1], $roster) !== [];
            [$left, $right] = $this->split($chunk, $leftHas);
            $pieces[$c - 1] .= ' , '.$left;
            $pieces[$c] .= ' , '.$right;
        }

        $everyoneElse = 'P';
        $marks = [];
        $unclear = [];
        foreach ($hits as $i => $h) {
            $piece = $pieces[$i];
            if ($this->mentionsRest($piece)) {
                $everyoneElse = $h['status'] === 'A' ? 'A' : 'P';
                $piece = $this->stripRest($piece);
            }
            foreach ($this->findStudents($piece, $roster) as $found) {
                if ($found['student']) {
                    $marks[$found['student']->id] = ['id' => $found['student']->id, 'status' => $h['status'], 'heard' => $found['heard']];
                } else {
                    $unclear[] = $found['heard'];
                }
            }
        }

        if (! $hits && trim($transcript) !== '') {
            $unclear[] = 'No status word heard — say for example “Ahmed absent, Bilal leave, baqi sab present”.';
        }

        return ['engine' => 'Built-in matcher', 'marks' => array_values($marks), 'everyone_else' => $everyoneElse, 'unclear' => array_values(array_unique($unclear))];
    }

    /**
     * Split the words between two status words. A full stop beats a comma; with no
     * punctuation the words go to the earlier status only if it has no names yet.
     *
     * @return array{0: string, 1: string}
     */
    private function split(string $chunk, bool $leftHas): array
    {
        foreach ([' . ', ' , '] as $sep) {
            $p = $leftHas ? mb_strpos($chunk, $sep) : mb_strrpos($chunk, $sep);
            if ($p !== false) {
                return [mb_substr($chunk, 0, $p), mb_substr($chunk, $p + mb_strlen($sep))];
            }
        }

        return $leftHas ? ['', $chunk] : [$chunk, ''];
    }

    private function mentionsRest(string $piece): bool
    {
        foreach (self::REST as $r) {
            if (preg_match('/(^|\s)'.preg_quote($r, '/').'(\s|$)/u', $piece)) {
                return true;
            }
        }

        return false;
    }

    private function stripRest(string $piece): string
    {
        foreach (self::REST as $r) {
            $piece = preg_replace('/(^|\s)'.preg_quote($r, '/').'(?=\s|$)/u', ' ', $piece);
        }

        return $piece;
    }

    private static function norm(string $token): string
    {
        $t = mb_strtolower(trim($token, " -\t\n"));

        return self::SAME[$t] ?? $t;
    }

    private static function close(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $len = min(mb_strlen($a), mb_strlen($b));
        if ($len < 4) {
            return false;
        }

        return levenshtein($a, $b) <= ($len >= 7 ? 2 : 1);
    }

    /** @return array<int, string> normalised name tokens for a student */
    private static function tokens(Student $s): array
    {
        return array_values(array_filter(array_map([self::class, 'norm'], preg_split('/[\s\-]+/u', $s->name)), fn ($t) => mb_strlen($t) >= 2));
    }

    /**
     * Names and roll numbers mentioned in a piece of the sentence.
     *
     * @return array<int, array{student: ?Student, heard: string}>
     */
    private function findStudents(string $piece, Collection $roster): array
    {
        $out = [];
        $words = array_values(array_filter(preg_split('/\s+/u', trim($piece)), fn ($w) => $w !== '' && $w !== ',' && $w !== '.'));
        // Roll numbers: "5", "roll 12", "FSC-PM-26-005".
        $byNumber = $roster->groupBy(fn (Student $s) => (int) preg_replace('/^.*?(\d+)$/', '$1', $s->roll_no));
        foreach ($words as $w) {
            if (preg_match('/^(?:[a-z\-]*-)?0*(\d{1,3})$/u', $w, $m) && isset($byNumber[(int) $m[1]])) {
                $hit = $byNumber[(int) $m[1]];
                $out[] = ['student' => $hit->count() === 1 ? $hit->first() : null, 'heard' => 'roll '.$m[1]];
            }
        }

        // Names: extend a run of tokens while it narrows the candidates.
        $index = $roster->map(fn (Student $s) => ['s' => $s, 't' => self::tokens($s)]);
        $i = 0;
        while ($i < count($words)) {
            $w = self::norm($words[$i]);
            if (mb_strlen($w) < 3 || in_array($w, self::FILLER, true) || preg_match('/^\d/', $w)) {
                $i++;

                continue;
            }
            $cands = $index->filter(fn ($e) => collect($e['t'])->contains(fn ($t) => self::close($t, $w)));
            if ($cands->isEmpty()) {
                $i++;

                continue;
            }
            $heard = [$words[$i]];
            $j = $i + 1;
            while ($j < count($words) && $cands->count() > 1) {
                $n = self::norm($words[$j]);
                if (mb_strlen($n) < 2 || in_array($n, self::FILLER, true)) {
                    break;
                }
                $narrow = $cands->filter(fn ($e) => collect($e['t'])->contains(fn ($t) => self::close($t, $n)));
                if ($narrow->isEmpty()) {
                    break;
                }
                $cands = $narrow;
                $heard[] = $words[$j];
                $j++;
            }
            // Swallow the rest of a matched full name ("Muhammad Usman Khalid").
            if ($cands->count() === 1) {
                $toks = $cands->first()['t'];
                while ($j < count($words) && collect($toks)->contains(fn ($t) => self::close($t, self::norm($words[$j])))) {
                    $heard[] = $words[$j];
                    $j++;
                }
            }
            // "Muhammad" alone is too common to mean anyone.
            if ($cands->count() > 1 || ($cands->count() === 1 && count($heard) === 1 && $w === 'muhammad')) {
                $names = $cands->take(4)->map(fn ($e) => $e['s']->name)->implode(', ');
                $out[] = ['student' => null, 'heard' => '“'.implode(' ', $heard).'” matches '.$cands->count().' students ('.$names.($cands->count() > 4 ? '…' : '').') — say the full name or roll number'];
            } else {
                $out[] = ['student' => $cands->first()['s'], 'heard' => implode(' ', $heard)];
            }
            $i = $j;
        }

        return $out;
    }
}
