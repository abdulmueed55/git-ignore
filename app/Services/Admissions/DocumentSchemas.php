<?php

namespace App\Services\Admissions;

/**
 * JSON schemas (structured outputs) and reading instructions per document type.
 * Every schema carries document_type_matches, confidence and issues so the
 * verifier can treat unreadable or wrong uploads separately from mismatches.
 */
class DocumentSchemas
{
    /** Human description of each document as found in Pakistan. */
    public const DESCRIPTIONS = [
        'cnic' => 'a Pakistani NADRA Computerized National Identity Card (CNIC), Smart NICOP, or a NADRA B-Form / Child Registration Certificate of the applicant',
        'father_cnic' => "the NADRA CNIC of the applicant's father (or guardian)",
        'matric_dmc' => 'a Secondary School Certificate (SSC / Matric) Detailed Marks Certificate or result card issued by a Pakistani Board of Intermediate and Secondary Education (BISE) or the Federal Board',
        'inter_dmc' => 'a Higher Secondary School Certificate (HSSC / Intermediate) Detailed Marks Certificate or result card issued by a Pakistani BISE or the Federal Board',
        'domicile' => 'a Pakistani domicile certificate issued by a district authority',
        'character_certificate' => 'a character certificate issued by the head of the previous school or college',
    ];

    public static function for(string $type): array
    {
        $common = [
            'document_type_matches' => ['type' => 'boolean', 'description' => 'True only if the upload really is the expected document type.'],
            'confidence' => ['type' => 'integer', 'description' => 'Overall confidence 0-100 that the values below were read correctly.'],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Short notes such as "blurry", "cropped", "possible editing near marks", "back side only". Empty if none.'],
        ];
        $str = fn (string $d) => ['type' => ['string', 'null'], 'description' => $d];
        $int = fn (string $d) => ['type' => ['integer', 'null'], 'description' => $d];

        $fields = match ($type) {
            'cnic', 'father_cnic' => [
                'name' => $str('Full name exactly as printed in English.'),
                'father_name' => $str('Father / husband name as printed.'),
                'cnic_number' => $str('13-digit identity number formatted 12345-1234567-1.'),
                'date_of_birth' => $str('Date of birth as YYYY-MM-DD.'),
                'gender' => $str('"male" or "female".'),
            ],
            'matric_dmc', 'inter_dmc' => [
                'name' => $str("Candidate's name."),
                'father_name' => $str("Father's name."),
                'board' => $str('Issuing board, e.g. "BISE Rawalpindi" or "FBISE Islamabad".'),
                'roll_no' => $str('Roll number as printed.'),
                'year' => $int('Examination year (annual or supplementary), 4 digits.'),
                'group' => $str('Group, e.g. Science, Pre-Medical, Pre-Engineering, ICS, Humanities, Commerce.'),
                'obtained' => $int('Total marks obtained (grand total).'),
                'total' => $int('Maximum total marks.'),
                'date_of_birth' => $str('Date of birth as YYYY-MM-DD if printed, else null.'),
            ],
            'domicile' => [
                'name' => $str('Name of the person.'),
                'father_name' => $str("Father's name."),
                'district' => $str('District of domicile, e.g. Rawalpindi.'),
            ],
            'character_certificate' => [
                'name' => $str('Name of the student.'),
                'father_name' => $str("Father's name if printed."),
                'institution' => $str('Issuing school or college.'),
            ],
            default => [],
        };

        $properties = $fields + $common;

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    public static function prompt(string $type): string
    {
        $what = self::DESCRIPTIONS[$type] ?? 'an admission document';

        return <<<TXT
        You are reading an admission document uploaded to FG Liaquat Ali Degree College, Rawalpindi (Pakistan).
        It should be {$what}.

        Transcribe the requested fields exactly as printed — do not correct spellings, guess, or fill gaps. Use null for any field that is not visible or not legible.
        Write Urdu-only text in its common English transliteration. Normalise dates to YYYY-MM-DD and identity numbers to 12345-1234567-1.
        Set document_type_matches to false if this is a different document. Lower confidence and list issues if the image is blurry, cropped, partially covered, or shows signs of editing.
        TXT;
    }
}
