<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic base data for an FG degree college: departments, staff, programs,
 * sessions, sections, subjects, teaching assignments, a clash-free weekly
 * timetable and enrolled students.
 */
class AcademicSeeder extends Seeder
{
    public const MALE = ['Muhammad Ahmed', 'Ali Raza', 'Hamza Khan', 'Usman Tariq', 'Bilal Hussain', 'Zain ul Abideen', 'Abdullah Farooq', 'Hassan Javed', 'Umar Saeed', 'Saad Mehmood', 'Faizan Akhtar', 'Talha Iqbal', 'Hammad Aslam', 'Shahzaib Anwar', 'Arslan Nadeem', 'Danish Qureshi', 'Moiz Abbasi', 'Rehan Siddiqui', 'Waleed Arshad', 'Huzaifa Malik', 'Ahsan Butt', 'Taimoor Kiani', 'Fahad Rashid', 'Owais Satti', 'Sufyan Gondal', 'Areeb Shah', 'Haris Janjua', 'Junaid Bhatti', 'Noman Raja', 'Adeel Mirza', 'Kashif Awan', 'Shoaib Akhtar', 'Musa Khattak', 'Ibrahim Yousaf', 'Haider Naqvi', 'Ammar Zaidi', 'Mustafa Chaudhry', 'Rayyan Sheikh', 'Shayan Hashmi', 'Azan Rafique', 'Mohsin Kayani', 'Sami Ullah', 'Wahab Riaz', 'Ehtisham ul Haq', 'Abdul Rehman', 'Daniyal Afzal', 'Shahroz Khan', 'Nabeel Asghar', 'Muneeb Ashraf', 'Yasir Dar'];

    public const FEMALE = ['Ayesha Siddiqa', 'Fatima Zahra', 'Maryam Noor', 'Hira Batool', 'Iqra Shahid', 'Zainab Ali', 'Amna Riaz', 'Mahnoor Khan', 'Laiba Aslam', 'Eman Fatima', 'Rimsha Javed', 'Sana Tariq', 'Areeba Hassan', 'Kinza Malik', 'Noor ul Ain', 'Anum Kiani', 'Hafsa Qureshi', 'Javeria Abbasi', 'Momina Butt', 'Aleena Shah'];

    public const FATHERS = ['Muhammad Akram', 'Tariq Mehmood', 'Ghulam Rasool', 'Abdul Majeed', 'Javed Iqbal', 'Muhammad Aslam', 'Khalid Hussain', 'Shahid Mehmood', 'Nadeem Akhtar', 'Zafar Iqbal', 'Rashid Ali', 'Muhammad Riaz', 'Pervaiz Akhtar', 'Asghar Ali', 'Sajid Mehmood', 'Imran Khan', 'Abdul Ghafoor', 'Liaquat Ali', 'Muhammad Saleem', 'Arshad Mehmood', 'Mushtaq Ahmed', 'Naveed Anjum', 'Farooq Azam', 'Irfan Ullah', 'Waseem Abbas'];

    public const CITIES = ['Rawalpindi', 'Rawalpindi', 'Rawalpindi', 'Islamabad', 'Rawalpindi Cantt', 'Taxila', 'Wah Cantt', 'Gujar Khan', 'Murree', 'Kahuta', 'Chakwal', 'Attock'];

    public const AREAS = ['Saddar', 'Westridge', 'Chaklala Scheme III', 'Satellite Town', 'Lalkurti', 'Adiala Road', 'Bahria Town Phase 4', 'Dhok Syedan', 'Peshawar Road', 'Askari 14', 'Tench Bhatta', 'Range Road', 'Misrial Road', 'Gulraiz', 'Morgah'];

    public function run(): void
    {
        mt_srand(1947);

        // ---------- Departments ----------
        $deptData = [
            'ENG' => 'English', 'URD' => 'Urdu', 'ISL' => 'Islamic Studies', 'PST' => 'Pakistan Studies',
            'MTH' => 'Mathematics', 'PHY' => 'Physics', 'CHE' => 'Chemistry', 'BIO' => 'Biology',
            'CS' => 'Computer Science', 'ECO' => 'Economics', 'COM' => 'Commerce', 'STA' => 'Statistics', 'POL' => 'Political Science',
        ];
        $depts = [];
        foreach ($deptData as $code => $name) {
            $depts[$code] = Department::create(['name' => $name, 'code' => $code, 'description' => "Department of {$name}"]);
        }

        // ---------- Staff ----------
        $pw = Hash::make('password');
        $mk = fn (array $a) => User::create($a + ['password' => $pw, 'email_verified_at' => now(), 'is_active' => true]);

        $mk(['name' => 'System Administrator', 'email' => 'admin@fglacollege.edu.pk', 'role' => 'admin', 'designation' => 'IT Administrator', 'employee_no' => 'FGLA-0001', 'phone' => '0300-5551001']);
        $principal = $mk(['name' => 'Prof. Dr. Bilal Sohail', 'email' => 'principal@fglacollege.edu.pk', 'role' => 'principal', 'designation' => 'Principal', 'employee_no' => 'FGLA-0002', 'phone' => '051-5492888', 'department_id' => $depts['PHY']->id]);
        $mk(['name' => 'Mr. Tahir Nawaz Abbasi', 'email' => 'admissions@fglacollege.edu.pk', 'role' => 'admission_officer', 'designation' => 'Admission Officer', 'employee_no' => 'FGLA-0003', 'phone' => '0333-5124410']);
        $mk(['name' => 'Ms. Sadia Rehman', 'email' => 'editor@fglacollege.edu.pk', 'role' => 'editor', 'designation' => 'Media & Website Coordinator', 'employee_no' => 'FGLA-0004', 'phone' => '0321-5009812']);

        $faculty = [
            // [name, dept, designation, email?]
            ['Mr. Imran Ahmed Qureshi', 'PHY', 'Associate Professor', 'teacher@fglacollege.edu.pk'],
            ['Dr. Shazia Parveen', 'CHE', 'Associate Professor'],
            ['Mr. Naveed Iqbal Satti', 'MTH', 'Assistant Professor'],
            ['Ms. Rabia Anwar', 'BIO', 'Assistant Professor'],
            ['Mr. Asif Mehmood Kiani', 'ENG', 'Assistant Professor'],
            ['Mr. Zahid Hussain Shah', 'URD', 'Associate Professor'],
            ['Hafiz Abdul Basit', 'ISL', 'Assistant Professor'],
            ['Mr. Fawad Hussain Raja', 'PST', 'Lecturer'],
            ['Mr. Usman Ghani Malik', 'CS', 'Assistant Professor'],
            ['Ms. Nida Sarwar', 'CS', 'Lecturer'],
            ['Dr. Amjad Ali Chaudhry', 'ECO', 'Professor'],
            ['Mr. Waqas Ahmed Butt', 'COM', 'Lecturer'],
            ['Mr. Shahid Nawaz Janjua', 'STA', 'Assistant Professor'],
            ['Mr. Kamran Akhtar Mirza', 'MTH', 'Lecturer'],
            ['Dr. Saima Batool', 'ENG', 'Associate Professor'],
            ['Mr. Tanveer Hussain Bhatti', 'CHE', 'Lecturer'],
            ['Mr. Adnan Riaz Gondal', 'PHY', 'Lecturer'],
            ['Ms. Huma Yasmeen', 'BIO', 'Lecturer'],
            ['Mr. Sajjad Haider Naqvi', 'POL', 'Assistant Professor'],
            ['Mr. Muhammad Ali Khattak', 'CS', 'Lecturer'],
            ['Mr. Ehsan Ullah Khan', 'ENG', 'Lecturer'],
            ['Mr. Rizwan Akram', 'URD', 'Lecturer'],
        ];
        $teachers = [];
        foreach ($faculty as $i => $f) {
            $email = $f[3] ?? Str::slug(preg_replace('/^(Dr\.|Mr\.|Ms\.|Hafiz)\s*/', '', $f[0]), '.').'@fglacollege.edu.pk';
            $teachers[$f[1]][] = $mk([
                'name' => $f[0], 'email' => $email, 'role' => 'teacher', 'designation' => $f[2],
                'department_id' => $depts[$f[1]]->id, 'employee_no' => sprintf('FGLA-%04d', 10 + $i),
                'phone' => sprintf('03%02d-%07d', mt_rand(0, 49), mt_rand(1000000, 9999999)),
            ]);
        }
        foreach ($depts as $code => $d) {
            if (isset($teachers[$code])) {
                $d->update(['head_id' => $teachers[$code][0]->id]);
            }
        }

        // ---------- Sessions ----------
        $sessions = [
            '2025–2027' => AcademicSession::create(['name' => '2025–2027', 'starts_on' => '2025-09-01', 'ends_on' => '2027-06-30']),
            '2026–2028' => AcademicSession::create(['name' => '2026–2028', 'starts_on' => '2026-09-01', 'ends_on' => '2028-06-30', 'is_current' => true]),
            '2024–2028' => AcademicSession::create(['name' => '2024–2028', 'starts_on' => '2024-10-01', 'ends_on' => '2028-07-31']),
            '2026–2030' => AcademicSession::create(['name' => '2026–2030', 'starts_on' => '2026-10-01', 'ends_on' => '2030-07-31']),
        ];

        // ---------- Subjects ----------
        $subjectData = [
            ['ENG-I', 'English (Compulsory)', 'ENG'], ['URD-I', 'Urdu (Compulsory)', 'URD'], ['ISL-I', 'Islamiyat', 'ISL'],
            ['PST-I', 'Pakistan Studies', 'PST'], ['PHY-I', 'Physics', 'PHY', true], ['CHE-I', 'Chemistry', 'CHE', true],
            ['BIO-I', 'Biology', 'BIO', true], ['MTH-I', 'Mathematics', 'MTH'], ['CS-I', 'Computer Science', 'CS', true],
            ['ECO-I', 'Economics', 'ECO'], ['STA-I', 'Statistics', 'STA'], ['ACC-I', 'Principles of Accounting', 'COM'],
            ['COM-I', 'Principles of Commerce', 'COM'], ['BMT-I', 'Business Mathematics', 'MTH'], ['POL-I', 'Civics', 'POL'],
            ['CS-101', 'Programming Fundamentals', 'CS', true], ['CS-102', 'Introduction to ICT', 'CS', true],
            ['MTH-101', 'Calculus & Analytical Geometry', 'MTH'], ['ENG-101', 'Functional English', 'ENG'],
            ['CS-201', 'Data Structures & Algorithms', 'CS', true], ['CS-202', 'Database Systems', 'CS', true],
            ['MTH-201', 'Linear Algebra', 'MTH'], ['CS-301', 'Software Engineering', 'CS'], ['CS-302', 'Computer Networks', 'CS', true],
            ['CS-303', 'Artificial Intelligence', 'CS', true], ['ENG-201', 'Introduction to Linguistics', 'ENG'],
            ['ENG-202', 'Classical Poetry', 'ENG'], ['ENG-203', 'History of English Literature', 'ENG'],
        ];
        $subjects = [];
        foreach ($subjectData as $s) {
            $subjects[$s[0]] = Subject::create(['code' => $s[0], 'name' => $s[1], 'department_id' => $depts[$s[2]]->id, 'has_practical' => $s[3] ?? false, 'credit_hours' => str_contains($s[0], '-I') ? 4 : 3]);
        }

        // ---------- Programs ----------
        $inter = ['academic' => 1.0, 'hafiz_bonus' => 20];
        $programs = [
            'FSC-PM' => Program::create(['code' => 'FSC-PM', 'name' => 'F.Sc. Pre-Medical', 'level' => 'intermediate', 'department_id' => $depts['BIO']->id, 'seats' => 120, 'min_percentage' => 60, 'annual_fee' => 18500, 'eligibility' => 'Matric (Science) with Biology, minimum 60% marks', 'merit_weights' => $inter]),
            'FSC-PE' => Program::create(['code' => 'FSC-PE', 'name' => 'F.Sc. Pre-Engineering', 'level' => 'intermediate', 'department_id' => $depts['PHY']->id, 'seats' => 80, 'min_percentage' => 60, 'annual_fee' => 18500, 'eligibility' => 'Matric (Science), minimum 60% marks', 'merit_weights' => $inter]),
            'ICS' => Program::create(['code' => 'ICS', 'name' => 'I.C.S. (Physics / Computer Science)', 'level' => 'intermediate', 'department_id' => $depts['CS']->id, 'seats' => 80, 'min_percentage' => 55, 'annual_fee' => 19500, 'eligibility' => 'Matric (Science or Computer), minimum 55% marks', 'merit_weights' => $inter]),
            'ICOM' => Program::create(['code' => 'ICOM', 'name' => 'I.Com.', 'level' => 'intermediate', 'department_id' => $depts['COM']->id, 'seats' => 60, 'min_percentage' => 45, 'annual_fee' => 15500, 'eligibility' => 'Matric (any group), minimum 45% marks', 'merit_weights' => $inter]),
            'FA' => Program::create(['code' => 'FA', 'name' => 'F.A. (Humanities)', 'level' => 'intermediate', 'department_id' => $depts['ENG']->id, 'seats' => 60, 'min_percentage' => 45, 'annual_fee' => 14000, 'eligibility' => 'Matric (any group), minimum 45% marks', 'merit_weights' => $inter]),
            'BSCS' => Program::create(['code' => 'BSCS', 'name' => 'BS Computer Science', 'level' => 'bs', 'duration_years' => 4, 'department_id' => $depts['CS']->id, 'seats' => 50, 'min_percentage' => 50, 'annual_fee' => 42000, 'eligibility' => 'Intermediate (Pre-Engineering / ICS / Pre-Medical with Maths), minimum 50%', 'merit_weights' => ['academic' => 0.7, 'matric' => 0.3, 'hafiz_bonus' => 20]]),
            'BSENG' => Program::create(['code' => 'BSENG', 'name' => 'BS English', 'level' => 'bs', 'duration_years' => 4, 'department_id' => $depts['ENG']->id, 'seats' => 50, 'min_percentage' => 45, 'annual_fee' => 36000, 'eligibility' => 'Intermediate (any group), minimum 45%', 'merit_weights' => ['academic' => 0.7, 'matric' => 0.3, 'hafiz_bonus' => 20]]),
            'ADP-SC' => Program::create(['code' => 'ADP-SC', 'name' => 'Associate Degree in Science', 'level' => 'adp', 'duration_years' => 2, 'department_id' => $depts['PHY']->id, 'seats' => 40, 'min_percentage' => 45, 'annual_fee' => 28000, 'eligibility' => 'Intermediate (Science), minimum 45%', 'merit_weights' => ['academic' => 0.7, 'matric' => 0.3, 'hafiz_bonus' => 20], 'is_active' => true]),
        ];

        $curriculum = [
            'FSC-PM' => ['ENG-I', 'URD-I', 'ISL-I', 'PHY-I', 'CHE-I', 'BIO-I'],
            'FSC-PE' => ['ENG-I', 'URD-I', 'ISL-I', 'PHY-I', 'CHE-I', 'MTH-I'],
            'ICS' => ['ENG-I', 'URD-I', 'PST-I', 'PHY-I', 'MTH-I', 'CS-I'],
            'ICOM' => ['ENG-I', 'URD-I', 'ISL-I', 'ACC-I', 'COM-I', 'ECO-I'],
            'FA' => ['ENG-I', 'URD-I', 'ISL-I', 'ECO-I', 'POL-I', 'STA-I'],
            'BSCS-1' => ['CS-101', 'CS-102', 'MTH-101', 'ENG-101', 'ISL-I', 'PST-I'],
            'BSCS-3' => ['CS-201', 'CS-202', 'MTH-201', 'STA-I', 'ENG-101'],
            'BSCS-5' => ['CS-301', 'CS-302', 'CS-303', 'MTH-201', 'ECO-I'],
            'BSENG-1' => ['ENG-101', 'ENG-201', 'ISL-I', 'PST-I', 'CS-102'],
            'BSENG-3' => ['ENG-202', 'ENG-203', 'ENG-201', 'ECO-I', 'URD-I'],
        ];

        // [program, session, name, year, curriculumKey, sizeRange, room]
        $sectionPlan = [
            ['FSC-PM', '2026–2028', 'Part-I · A', 1, 'FSC-PM', [38, 44], 'B-101'],
            ['FSC-PM', '2026–2028', 'Part-I · B', 1, 'FSC-PM', [36, 42], 'B-102'],
            ['FSC-PM', '2025–2027', 'Part-II · A', 2, 'FSC-PM', [34, 40], 'B-201'],
            ['FSC-PE', '2026–2028', 'Part-I · A', 1, 'FSC-PE', [36, 42], 'A-103'],
            ['FSC-PE', '2025–2027', 'Part-II · A', 2, 'FSC-PE', [32, 38], 'A-203'],
            ['ICS', '2026–2028', 'Part-I · A', 1, 'ICS', [34, 40], 'C-Lab 1'],
            ['ICS', '2025–2027', 'Part-II · A', 2, 'ICS', [30, 36], 'C-Lab 2'],
            ['ICOM', '2026–2028', 'Part-I · A', 1, 'ICOM', [30, 36], 'D-104'],
            ['FA', '2026–2028', 'Part-I · A', 1, 'FA', [26, 32], 'D-105'],
            ['BSCS', '2026–2030', 'Semester 1', 1, 'BSCS-1', [34, 40], 'CS-Hall 1'],
            ['BSCS', '2024–2028', 'Semester 5', 3, 'BSCS-5', [26, 32], 'CS-Hall 2'],
            ['BSENG', '2026–2030', 'Semester 1', 1, 'BSENG-1', [30, 36], 'E-201'],
            ['BSENG', '2024–2028', 'Semester 5', 3, 'BSENG-3', [22, 28], 'E-202'],
        ];

        // Give each course to the least-loaded teacher of the subject's department
        // (falls back to the whole faculty when a department is stretched).
        $load = [];
        $subjectTeacher = function (Subject $s) use ($teachers, $depts, &$load) {
            $code = array_search($s->department_id, array_map(fn ($d) => $d->id, $depts));
            $pool = $teachers[$code] ?? $teachers['ENG'];
            usort($pool, fn ($a, $b) => ($load[$a->id] ?? 0) <=> ($load[$b->id] ?? 0));
            if (($load[$pool[0]->id] ?? 0) >= 25) {
                $all = array_merge(...array_values($teachers));
                usort($all, fn ($a, $b) => ($load[$a->id] ?? 0) <=> ($load[$b->id] ?? 0));
                $pool = $all;
            }
            $load[$pool[0]->id] = ($load[$pool[0]->id] ?? 0) + 5;

            return $pool[0];
        };

        $periods = [['08:00', '08:45'], ['08:45', '09:30'], ['09:30', '10:15'], ['10:30', '11:15'], ['11:15', '12:00'], ['12:00', '12:45'], ['12:45', '13:30']];
        $teacherBusy = [];   // [teacherId][day][period]
        $roll = [];
        $studentRows = [];
        $nameIdx = 0;

        foreach ($sectionPlan as $si => [$pcode, $sess, $sname, $year, $ckey, $range, $room]) {
            $program = $programs[$pcode];
            $section = Section::create([
                'program_id' => $program->id, 'academic_session_id' => $sessions[$sess]->id, 'name' => $sname,
                'year' => $year, 'room' => $room, 'capacity' => 45,
            ]);

            // Courses + clash-free timetable (Mon–Fri, one period per course per day, 5 days)
            $sectionBusy = [];
            $courseCount = 0;
            foreach ($curriculum[$ckey] as $ci => $scode) {
                $subject = $subjects[$scode];
                $teacher = $subjectTeacher($subject);
                $course = Course::create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
                $courseCount++;
                foreach ([1, 2, 3, 4, 5] as $day) {
                    for ($k = 0; $k < count($periods); $k++) {
                        $p = ($ci + $k + $si) % count($periods);
                        if (! isset($sectionBusy[$day][$p]) && ! isset($teacherBusy[$teacher->id][$day][$p])) {
                            $sectionBusy[$day][$p] = true;
                            $teacherBusy[$teacher->id][$day][$p] = true;
                            TimetableSlot::create(['course_id' => $course->id, 'weekday' => $day, 'starts_at' => $periods[$p][0], 'ends_at' => $periods[$p][1], 'room' => $subject->has_practical && $day === 5 ? $subject->code.' Lab' : $room]);
                            break;
                        }
                    }
                }
            }
            if ($si === 0) {
                $section->update(['incharge_id' => $teachers['PHY'][0]->id]);
            } else {
                $section->update(['incharge_id' => $section->courses()->inRandomOrder()->value('teacher_id')]);
            }

            // Students
            $n = mt_rand($range[0], $range[1]);
            $yearCode = substr($sessions[$sess]->name, 2, 2);
            for ($i = 1; $i <= $n; $i++) {
                $female = in_array($pcode, ['BSENG', 'BSCS'], true) && mt_rand(1, 100) <= 40;
                $first = $female ? self::FEMALE[mt_rand(0, count(self::FEMALE) - 1)] : self::MALE[($nameIdx++ * 7 + $si) % count(self::MALE)];
                $father = self::FATHERS[mt_rand(0, count(self::FATHERS) - 1)];
                $key = $pcode.$yearCode;
                $roll[$key] = ($roll[$key] ?? 0) + 1;
                $dobYear = $program->level === 'intermediate' ? (2026 - 15 - $year) : (2026 - 17 - $year);
                $studentRows[] = [
                    'program_id' => $program->id,
                    'section_id' => $section->id,
                    'academic_session_id' => $sessions[$sess]->id,
                    'roll_no' => sprintf('%s-%s-%03d', $pcode, $yearCode, $roll[$key]),
                    'registration_no' => sprintf('BISE-RWP-%s%06d', $yearCode, mt_rand(100000, 999999)),
                    'name' => $first,
                    'father_name' => $father,
                    'cnic' => sprintf('37405-%07d-%d', mt_rand(1000000, 9999999), $female ? 2 * mt_rand(1, 4) : 2 * mt_rand(0, 4) + 1),
                    'father_cnic' => sprintf('37405-%07d-%d', mt_rand(1000000, 9999999), 2 * mt_rand(0, 4) + 1),
                    'date_of_birth' => sprintf('%d-%02d-%02d', $dobYear, mt_rand(1, 12), mt_rand(1, 28)),
                    'gender' => $female ? 'female' : 'male',
                    'phone' => sprintf('03%02d-%07d', mt_rand(0, 49), mt_rand(1000000, 9999999)),
                    'guardian_phone' => sprintf('03%02d-%07d', mt_rand(0, 49), mt_rand(1000000, 9999999)),
                    'email' => Str::slug($first, '.').mt_rand(10, 99).'@gmail.com',
                    'address' => 'House '.mt_rand(1, 900).', Street '.mt_rand(1, 40).', '.self::AREAS[mt_rand(0, count(self::AREAS) - 1)],
                    'city' => self::CITIES[mt_rand(0, count(self::CITIES) - 1)],
                    'domicile' => mt_rand(1, 10) > 2 ? 'Rawalpindi' : ['Attock', 'Chakwal', 'Jhelum', 'Islamabad'][mt_rand(0, 3)],
                    'blood_group' => ['A+', 'B+', 'O+', 'AB+', 'A-', 'B-', 'O-'][mt_rand(0, 6)],
                    'status' => mt_rand(1, 100) <= 2 ? 'struck_off' : 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($studentRows, 200) as $chunk) {
            Student::insert($chunk);
        }
    }
}
