<?php

namespace App\Services\Admissions;

/**
 * Which application field is backed by which key on which document.
 * Order of documents = priority when auto-filling.
 */
class FieldMap
{
    /** field => [label, kind, [docType => extractedKey, ...], weight] */
    public const FIELDS = [
        'name' => ['Applicant name', 'name', ['cnic' => 'name', 'matric_dmc' => 'name', 'inter_dmc' => 'name', 'domicile' => 'name', 'character_certificate' => 'name'], 3],
        'father_name' => ["Father's name", 'name', ['cnic' => 'father_name', 'matric_dmc' => 'father_name', 'inter_dmc' => 'father_name', 'domicile' => 'father_name', 'father_cnic' => 'name'], 3],
        'cnic' => ['CNIC / B-Form no.', 'digits', ['cnic' => 'cnic_number'], 3],
        'father_cnic' => ["Father's CNIC", 'digits', ['father_cnic' => 'cnic_number'], 2],
        'date_of_birth' => ['Date of birth', 'date', ['cnic' => 'date_of_birth', 'matric_dmc' => 'date_of_birth'], 2],
        'gender' => ['Gender', 'text', ['cnic' => 'gender'], 1],
        'matric_board' => ['Matric board', 'board', ['matric_dmc' => 'board'], 1],
        'matric_roll_no' => ['Matric roll no.', 'digits', ['matric_dmc' => 'roll_no'], 2],
        'matric_year' => ['Matric year', 'number', ['matric_dmc' => 'year'], 1],
        'matric_group' => ['Matric group', 'text', ['matric_dmc' => 'group'], 0],
        'matric_obtained' => ['Matric marks obtained', 'number', ['matric_dmc' => 'obtained'], 3],
        'matric_total' => ['Matric total marks', 'number', ['matric_dmc' => 'total'], 2],
        'inter_board' => ['Inter board', 'board', ['inter_dmc' => 'board'], 1],
        'inter_roll_no' => ['Inter roll no.', 'digits', ['inter_dmc' => 'roll_no'], 2],
        'inter_year' => ['Inter year', 'number', ['inter_dmc' => 'year'], 1],
        'inter_group' => ['Inter group', 'text', ['inter_dmc' => 'group'], 0],
        'inter_obtained' => ['Inter marks obtained', 'number', ['inter_dmc' => 'obtained'], 3],
        'inter_total' => ['Inter total marks', 'number', ['inter_dmc' => 'total'], 2],
        'domicile' => ['Domicile district', 'text', ['domicile' => 'district'], 1],
    ];

    /** Fields that only matter when the program admits on intermediate marks. */
    public const INTER_FIELDS = ['inter_board', 'inter_roll_no', 'inter_year', 'inter_group', 'inter_obtained', 'inter_total'];

    /** Columns shown in the side-by-side comparison. */
    public const COMPARE_DOCS = ['cnic', 'father_cnic', 'matric_dmc', 'inter_dmc', 'domicile'];
}
