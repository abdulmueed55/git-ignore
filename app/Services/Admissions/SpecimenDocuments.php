<?php

namespace App\Services\Admissions;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Draws clearly-watermarked SPECIMEN document images for demo / seed data,
 * so the verification screens can be explored without real applicant files.
 * Generic layouts only — no official emblems or logos.
 */
class SpecimenDocuments
{
    private ?string $sans;

    private ?string $bold;

    private ?string $mono;

    public function __construct()
    {
        $dir = '/usr/share/fonts/truetype/dejavu/';
        $this->sans = is_file($dir.'DejaVuSans.ttf') && function_exists('imagettftext') ? $dir.'DejaVuSans.ttf' : null;
        $this->bold = is_file($dir.'DejaVuSans-Bold.ttf') ? $dir.'DejaVuSans-Bold.ttf' : $this->sans;
        $this->mono = is_file($dir.'DejaVuSansMono.ttf') ? $dir.'DejaVuSansMono.ttf' : $this->sans;
    }

    /** Draw the document and store it; returns [path, mime, size]. */
    public function make(string $type, array $d, string $dir): array
    {
        $img = match ($type) {
            'cnic', 'father_cnic' => $this->idCard($d),
            'matric_dmc', 'inter_dmc' => $this->resultCard($type, $d),
            'domicile' => $this->certificate('DOMICILE CERTIFICATE', [
                'Certified that' => $d['name'] ?? '', 'Son / Daughter of' => $d['father_name'] ?? '',
                'is a permanent resident of District' => $d['district'] ?? '', 'Issued' => $d['issued'] ?? '12-03-2021',
            ], [233, 240, 250]),
            'character_certificate' => $this->certificate('CHARACTER CERTIFICATE', [
                'This is to certify that' => $d['name'] ?? '', 'Son / Daughter of' => $d['father_name'] ?? '',
                'was a bona fide student of' => $d['institution'] ?? '', 'Character' => 'Good',
            ], [250, 246, 236]),
            default => $this->photo($d['gender'] ?? 'male'),
        };

        ob_start();
        imagejpeg($img, null, 72);
        $bin = ob_get_clean();
        imagedestroy($img);

        $path = trim($dir, '/').'/'.$type.'-specimen.jpg';
        Storage::disk('local')->put($path, $bin);

        return [$path, 'image/jpeg', strlen($bin)];
    }

    private function canvas(int $w, int $h, array $bg): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$bg));
        // light guilloche-ish texture
        $line = imagecolorallocatealpha($img, 0, 0, 0, 120);
        for ($x = -$h; $x < $w; $x += 9) {
            imageline($img, $x, 0, $x + $h, $h, $line);
        }

        return $img;
    }

    private function text(GdImage $img, string $s, int $x, int $y, float $size, array $rgb = [30, 34, 32], string $font = 'sans', float $angle = 0, int $alpha = 0): void
    {
        $c = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
        $f = $this->{$font} ?? null;
        if ($f) {
            imagettftext($img, $size, $angle, $x, $y, $c, $f, $s);
        } else {
            imagestring($img, $size > 12 ? 5 : 3, $x, $y - 12, $s, $c);
        }
    }

    private function watermark(GdImage $img): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        for ($y = (int) ($h * 0.35); $y < $h + 200; $y += 170) {
            $this->text($img, 'SPECIMEN · DEMO DATA', 20, $y, 22, [200, 40, 40], 'bold', 18, 88);
        }
        $this->text($img, 'Generated sample — not a real document', 12, $h - 8, 8, [150, 40, 40]);
    }

    private function idCard(array $d): GdImage
    {
        $img = $this->canvas(520, 330, [226, 240, 228]);
        $green = imagecolorallocate($img, 18, 92, 58);
        imagefilledrectangle($img, 0, 0, 520, 52, $green);
        $this->text($img, 'IDENTITY CARD', 18, 24, 13, [255, 255, 255], 'bold');
        $this->text($img, 'Specimen layout · Pakistan', 18, 43, 9, [210, 235, 220]);
        // photo box
        imagefilledrectangle($img, 22, 72, 132, 202, imagecolorallocate($img, 196, 210, 200));
        imagefilledellipse($img, 77, 118, 52, 60, imagecolorallocate($img, 150, 168, 156));
        imagefilledellipse($img, 77, 195, 96, 70, imagecolorallocate($img, 150, 168, 156));

        $rows = [
            ['Name', $d['name'] ?? ''],
            [($d['gender'] ?? '') === 'female' && ! empty($d['husband']) ? 'Husband Name' : 'Father Name', $d['father_name'] ?? ''],
            ['Gender', strtoupper(substr($d['gender'] ?? 'M', 0, 1))],
            ['Country of Stay', 'Pakistan'],
        ];
        $y = 82;
        foreach ($rows as [$k, $v]) {
            $this->text($img, $k, 150, $y, 8, [80, 100, 90]);
            $this->text($img, $v, 150, $y + 17, 12, [20, 24, 22], 'bold');
            $y += 40;
        }
        $this->text($img, 'Identity Number', 22, 236, 8, [80, 100, 90]);
        $this->text($img, $d['cnic_number'] ?? '', 22, 256, 13, [20, 24, 22], 'mono');
        $dob = isset($d['date_of_birth']) ? date('d.m.Y', strtotime($d['date_of_birth'])) : '';
        $this->text($img, 'Date of Birth', 250, 236, 8, [80, 100, 90]);
        $this->text($img, $dob, 250, 256, 13, [20, 24, 22], 'mono');
        $this->text($img, 'Date of Expiry', 390, 236, 8, [80, 100, 90]);
        $this->text($img, '14.08.2032', 390, 256, 13, [20, 24, 22], 'mono');
        $this->watermark($img);

        return $img;
    }

    private function resultCard(string $type, array $d): GdImage
    {
        $img = $this->canvas(520, 640, [251, 249, 242]);
        $ink = [24, 28, 40];
        $band = imagecolorallocate($img, 34, 52, 102);
        imagefilledrectangle($img, 0, 0, 520, 70, $band);
        $this->text($img, strtoupper($d['board'] ?? 'BISE'), 18, 28, 13, [255, 255, 255], 'bold');
        $exam = $type === 'matric_dmc' ? 'SECONDARY SCHOOL CERTIFICATE (SSC)' : 'HIGHER SECONDARY SCHOOL CERTIFICATE (HSSC)';
        $this->text($img, $exam.' · ANNUAL '.($d['year'] ?? ''), 18, 52, 9, [205, 214, 240]);
        $this->text($img, 'RESULT CARD / DETAILED MARKS CERTIFICATE', 18, 98, 10, $ink, 'bold');

        $info = [['Roll No.', $d['roll_no'] ?? ''], ['Name', $d['name'] ?? ''], ["Father's Name", $d['father_name'] ?? ''], ['Group', $d['group'] ?? '']];
        if (! empty($d['date_of_birth'])) {
            $info[] = ['Date of Birth', date('d-m-Y', strtotime($d['date_of_birth']))];
        }
        $y = 128;
        foreach ($info as [$k, $v]) {
            $this->text($img, $k, 18, $y, 9, [90, 96, 110]);
            $this->text($img, (string) $v, 150, $y, 11, $ink, $k === 'Roll No.' ? 'mono' : 'bold');
            $y += 24;
        }

        $line = imagecolorallocate($img, 190, 194, 205);
        $y += 6;
        imageline($img, 18, $y, 502, $y, $line);
        $this->text($img, 'SUBJECT', 18, $y + 18, 8, [90, 96, 110], 'bold');
        $this->text($img, 'MAX', 360, $y + 18, 8, [90, 96, 110], 'bold');
        $this->text($img, 'OBTAINED', 420, $y + 18, 8, [90, 96, 110], 'bold');
        $y += 26;
        imageline($img, 18, $y, 502, $y, $line);

        $subjects = $d['subjects'] ?? [];
        foreach ($subjects as [$s, $max, $obt]) {
            $y += 22;
            $this->text($img, $s, 18, $y, 9.5, $ink);
            $this->text($img, (string) $max, 360, $y, 9.5, $ink, 'mono');
            $this->text($img, (string) $obt, 430, $y, 9.5, $ink, 'mono');
        }
        $y += 14;
        imageline($img, 18, $y, 502, $y, $line);
        $y += 26;
        $this->text($img, 'GRAND TOTAL', 18, $y, 11, $ink, 'bold');
        $this->text($img, (string) ($d['total'] ?? ''), 352, $y, 12, $ink, 'mono');
        $this->text($img, (string) ($d['obtained'] ?? ''), 425, $y, 13, [20, 90, 50], 'bold');
        $this->watermark($img);

        return $img;
    }

    private function certificate(string $title, array $rows, array $bg): GdImage
    {
        $img = $this->canvas(520, 380, $bg);
        $ink = [30, 30, 40];
        $border = imagecolorallocate($img, 120, 110, 80);
        imagerectangle($img, 10, 10, 509, 369, $border);
        imagerectangle($img, 14, 14, 505, 365, $border);
        $this->text($img, $title, 130, 56, 15, $ink, 'bold');
        $y = 105;
        foreach ($rows as $k => $v) {
            $this->text($img, $k, 34, $y, 9, [90, 90, 100]);
            $this->text($img, (string) $v, 34, $y + 20, 12, $ink, 'bold');
            $y += 52;
        }
        $this->watermark($img);

        return $img;
    }

    private function photo(string $gender): GdImage
    {
        $img = imagecreatetruecolor(300, 360);
        imagefill($img, 0, 0, imagecolorallocate($img, 70, 120, 170));
        $skin = imagecolorallocate($img, 214, 178, 150);
        $cloth = imagecolorallocate($img, $gender === 'female' ? 120 : 40, $gender === 'female' ? 50 : 52, $gender === 'female' ? 90 : 70);
        imagefilledellipse($img, 150, 330, 250, 190, $cloth);
        imagefilledellipse($img, 150, 150, 120, 150, $skin);
        if ($gender === 'female') {
            imagefilledarc($img, 150, 150, 150, 180, 180, 360, $cloth, IMG_ARC_PIE);
        } else {
            imagefilledarc($img, 150, 110, 124, 80, 180, 360, imagecolorallocate($img, 30, 26, 24), IMG_ARC_PIE);
        }
        $this->text($img, 'SPECIMEN', 82, 30, 18, [255, 255, 255], 'bold', 0, 40);

        return $img;
    }
}
