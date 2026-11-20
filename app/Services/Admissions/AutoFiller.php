<?php

namespace App\Services\Admissions;

use App\Models\AdmissionApplication;

/** Copies values read from documents into empty application fields. */
class AutoFiller
{
    public function fill(AdmissionApplication $app): array
    {
        $app->loadMissing('documents');
        $filled = $app->autofilled_fields ?? [];

        foreach (FieldMap::FIELDS as $field => [$label, $kind, $sources]) {
            if (filled($app->{$field}) && ! in_array($field, $filled, true)) {
                continue; // applicant typed it — never overwrite
            }
            foreach ($sources as $docType => $key) {
                $doc = $app->documents->firstWhere('type', $docType);
                $value = $doc?->extraction_status === 'done' ? ($doc->extracted[$key] ?? null) : null;
                if (blank($value)) {
                    continue;
                }
                $value = match ($kind) {
                    'date' => Normalizer::date($value),
                    'number' => is_numeric($value) ? (int) $value : null,
                    default => is_string($value) ? trim($value) : $value,
                };
                if ($field === 'gender') {
                    $value = in_array(strtolower((string) $value), ['male', 'female'], true) ? strtolower($value) : null;
                }
                if ($field === 'domicile' && $value) {
                    $value = ucwords(strtolower($value));
                }
                if ($value === null) {
                    continue;
                }
                if (! filled($app->{$field})) {
                    $app->{$field} = $value;
                    $filled[] = $field;
                }
                break;
            }
        }

        $app->autofilled_fields = array_values(array_unique($filled));
        $app->save();

        return $app->autofilled_fields;
    }
}
