<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\Department;
use App\Models\Download;
use App\Models\FacultyProfile;
use App\Models\GalleryAlbum;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Website content for the public site: posts across all types and states,
 * pages, slides, gallery, downloads, faculty directory, settings and inbox.
 * Images are generated artwork (no photos of real people).
 */
class CmsSeeder extends Seeder
{
    private const PALETTES = [
        ['#0b3f29', '#0f5a3a', '#d4a64a'],
        ['#16213a', '#24406b', '#e1b65e'],
        ['#3a1f12', '#7a3b1d', '#f0c27a'],
        ['#10312f', '#1f6b5f', '#cfe3c8'],
        ['#2b1838', '#55306e', '#e7b8d8'],
        ['#1c2a17', '#3d5a2a', '#e8d38f'],
    ];

    private ?string $font = null;

    private ?string $fontSerif = null;

    public function run(): void
    {
        mt_srand(712);
        $disk = Storage::disk('public');
        foreach (['posts', 'slides', 'gallery', 'downloads', 'faculty'] as $dir) {
            $disk->deleteDirectory($dir);
        }
        $this->font = $this->findFont(['/usr/share/fonts/opentype/inter/Inter-SemiBold.otf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf']);
        $this->fontSerif = $this->findFont(['/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf', '/usr/share/fonts/truetype/freefont/FreeSerifBold.ttf']) ?? $this->font;

        $editor = User::where('role', 'editor')->first() ?? User::first();
        $principal = User::where('role', 'principal')->first();

        $this->settings($principal);
        $this->posts($editor);
        $this->pages($editor);
        $this->slides();
        $this->gallery();
        $this->downloads();
        $this->faculty();
        $this->messages();
    }

    private function settings(?User $principal): void
    {
        $values = [
            'college_name' => 'FG Liaquat Ali Degree College',
            'college_tagline' => 'Federal Government Educational Institutions (Cantts/Garrisons) · Rawalpindi',
            'established_year' => '2007',
            'address' => 'Peshawar Road, Rawalpindi Cantt',
            'phone' => '051-5492888',
            'phone_admissions' => '0336-1000830',
            'email' => 'fgladc@yahoo.com',
            'office_hours' => 'Monday – Friday, 8:00 AM – 3:00 PM',
            'map_url' => 'https://maps.google.com/?q=FG+Liaquat+Ali+Degree+College+Peshawar+Road+Rawalpindi',
            'facebook_url' => 'https://facebook.com/',
            'youtube_url' => 'https://youtube.com/',
            'instagram_url' => '',
            'x_url' => '',
            'principal_name' => $principal?->name ?? 'The Principal',
            'principal_title' => 'Principal, FGLADC Rawalpindi',
            'principal_message' => "Welcome to FG Liaquat Ali Degree College. Education is not merely the acquisition of knowledge; it is the process of developing responsible, confident, creative and compassionate individuals who can contribute meaningfully to society.\n\nAs a public-sector college, FGLADC is committed to serving the youth by providing quality education at nominal fees in a conducive and supportive learning environment. I invite students, parents, alumni and all stakeholders to become active partners in our journey towards excellence.",
            'principal_message_updated_at' => now()->subDays(118)->toDateTimeString(),
            'admission_banner_enabled' => '1',
            'admission_banner_text' => 'Admissions open — Intermediate 1st Year & BS 1st semester. Apply online.',
            'ticker_enabled' => '1',
            'brand_primary' => '#0f5a3a',
            'brand_accent' => '#a8761f',
        ];
        foreach ($values as $k => $v) {
            Setting::put($k, $v, Setting::FIELDS[$k][0] ?? 'general');
        }
    }

    private function posts(User $author): void
    {
        $n = now();
        $p = fn (string $html) => $html;
        // [type, title, category, published offset (days, negative = past), status, extra]
        $rows = [
            ['announcement', 'Admissions open: Intermediate 1st Year & BS', 'Admissions', -6, 'published', ['pinned' => true, 'ticker' => true, 'cover' => true, 'expires' => 56,
                'excerpt' => 'Online applications are open for 1st Year F.Sc., General Science, I.C.S., I.Com. and F.A., and the 1st semester of BS English, BS IT and BS Computer Science.',
                'body' => '<p>The college invites applications for 1st Year (HSSC Part-I) and the 1st semester of its four-year BS programmes.</p><h3>Intermediate — 1st Year</h3><ul><li>F.Sc. Pre-Medical</li><li>F.Sc. Pre-Engineering</li><li>General Science</li><li>I.C.S. (Computer Science)</li><li>I.Com.</li><li>F.A. (Humanities)</li></ul><h3>BS — 1st Semester</h3><ul><li>BS English (Linguistics)</li><li>BS Information Technology</li><li>BS Computer Science</li></ul><h3>How to apply</h3><p>Apply online through the admissions portal. Upload your CNIC/B-Form, your father\'s CNIC, Matric (and for BS, Intermediate) result cards, a photograph and your domicile. Our system reads your documents automatically and fills your form — check the details and submit.</p><p>Merit lists will be displayed on this website and on the college notice board.</p>']],
            ['notice', 'Date sheet — December class tests (Part-I & Part-II)', 'Examinations', -3, 'published', ['pinned' => true, 'ticker' => true, 'pdf' => 'December class tests date sheet', 'expires' => 25,
                'excerpt' => 'Class tests for all intermediate sections begin on Monday 7 December. Download the date sheet and seating plan.',
                'body' => '<p>Monthly class tests for F.Sc., I.C.S., I.Com. and F.A. (Part-I and Part-II) will be held from <strong>7 to 12 December</strong> in the first two periods.</p><ul><li>Students must bring their college ID card.</li><li>Students with less than 75% attendance will not be allowed to sit the tests.</li><li>Results will be shared with parents through the attendance SMS service.</li></ul>']],
            ['notice', 'Winter vacations notification', 'Holidays', -1, 'published', ['ticker' => true, 'pdf' => 'Winter vacations notification',
                'excerpt' => 'The college will remain closed for winter vacations from 22 December 2026 to 1 January 2027. Classes resume on Monday 4 January.',
                'body' => '<p>In line with the directive of the Federal Directorate of Education, the college will observe winter vacations from <strong>22 December 2026 to 1 January 2027</strong> (both days inclusive).</p><p>The admissions office and accounts branch will remain open from 9:00 AM to 1:00 PM on working days during the vacations.</p>']],
            ['event', 'Quaid-e-Azam Day ceremony', 'Co-curricular', -4, 'published', ['cover' => true, 'event' => [35, '09:30', '11:30'], 'venue' => 'College Auditorium', 'ticker' => true,
                'excerpt' => 'A ceremony marking the birth anniversary of Quaid-e-Azam Muhammad Ali Jinnah, with speeches, a tableau and a quiz on the Pakistan Movement.',
                'body' => '<p>The Department of Pakistan Studies will hold a ceremony to commemorate the birth anniversary of the Father of the Nation. The programme includes recitation, a speech competition, a tableau by Part-I students and a quiz on the Pakistan Movement.</p><p>Parents are welcome. Seating opens at 9:00 AM.</p>']],
            ['event', 'Science & IT exhibition 2026', 'Academics', -9, 'published', ['cover' => true, 'event' => [12, '09:00', '14:00'], 'venue' => 'Science Block & CS Labs', 'pinned' => true,
                'excerpt' => 'Students from F.Sc., I.C.S. and BS Computer Science present working models, robotics projects and software built during the term.',
                'body' => '<p>Over 60 student projects will be on display — from water-purification models and solar trackers to mobile apps and a line-following robot built by BSCS Semester 5.</p><p>Schools from Rawalpindi Cantt are invited to visit between 10:00 AM and 1:00 PM. Prizes will be announced at 1:30 PM.</p>']],
            ['event', 'Parent–teacher meeting (Part-I)', 'Academics', -2, 'published', ['event' => [8, '10:00', '13:00'], 'venue' => 'Respective classrooms',
                'excerpt' => 'Parents of Part-I students are requested to meet section in-charges to discuss attendance, class test results and conduct.',
                'body' => '<p>Each section in-charge will share the student\'s attendance record, class test results and conduct report. Parents of students below 75% attendance are especially requested to attend.</p>']],
            ['event', 'Inter-college debate championship', 'Co-curricular', -12, 'published', ['cover' => true, 'event' => [19, '10:00', '15:00'], 'venue' => 'College Auditorium',
                'excerpt' => 'Teams from twelve FG colleges compete in English and Urdu parliamentary debates hosted by our Literary Society.',
                'body' => '<p>The Literary Society hosts the annual inter-college debate championship. Twelve colleges from Rawalpindi and Islamabad have confirmed participation in English and Urdu categories.</p>']],
            ['event', 'Blood donation camp', 'Community', -5, 'published', ['event' => [4, '09:00', '13:00'], 'venue' => 'Sports Complex', 'expires' => 5,
                'excerpt' => 'In collaboration with a local blood bank, students and staff over 18 can donate blood. Every donation can save up to three lives.',
                'body' => '<p>Donors must be over 18, weigh more than 50 kg and should have had breakfast. Refreshments will be provided. Register with the Red Crescent Society desk.</p>']],
            ['event', 'Annual Sports Gala 2026', 'Sports', -40, 'published', ['cover' => true, 'event' => [-20, '08:30', '15:00'], 'venue' => 'College Ground',
                'excerpt' => 'Three days of athletics, cricket, football and tug-of-war. Results and photographs are now available.',
                'body' => '<p>The Annual Sports Gala was held over three days with more than 400 participants. Thanks to all students, coaches and parents.</p>']],
            ['news', 'Annual Sports Gala 2026 — results', 'Sports', -17, 'published', ['cover' => true,
                'excerpt' => 'Jinnah House lifts the overall trophy; F.Sc. Part-II wins the cricket final in a last-over thriller.',
                'body' => '<p>Jinnah House won the overall championship with 142 points, followed by Iqbal House (128) and Liaquat House (117).</p><h3>Highlights</h3><ul><li>100 m sprint — Hamza Khan (F.Sc. Part-II) in 11.4 s</li><li>Cricket final — F.Sc. Part-II beat I.C.S. by 2 wickets</li><li>Best athlete — Usman Tariq (BSCS)</li></ul>']],
            ['news', 'Iqbal Day declamation contest — winners announced', 'Co-curricular', -11, 'published', ['cover' => true,
                'excerpt' => 'Students paid tribute to Allama Iqbal through Urdu and English declamations; BS English students took the top two positions.',
                'body' => '<p>The Urdu Department organised the Iqbal Day declamation contest on 9 November. The judges praised the depth of preparation and delivery.</p><p>First position: Maryam Noor (BS English). Second: Ali Raza (F.A. Part-I). Third: Abdullah Farooq (F.Sc. Part-I).</p>']],
            ['news', 'New computer lab inaugurated for BS programmes', 'Academics', -22, 'published', ['cover' => true,
                'excerpt' => 'A 40-seat computer lab with fibre internet has been opened in the CS block to support BS Computer Science practicals.',
                'body' => '<p>The new lab has 40 workstations, a projector and a dedicated fibre connection. It will be open until 4:00 PM for BS students to complete assignments.</p>']],
            ['news', 'College students shine in BISE Rawalpindi Part-I results', 'Examinations', -30, 'published', [
                'excerpt' => 'Eighteen students scored above 500 marks in the HSSC Part-I examination; the college pass percentage rose to 91%.',
                'body' => '<p>The college congratulates its students on their performance in the HSSC Part-I annual examination. Eighteen students secured more than 500 out of 550 marks.</p>']],
            ['news', 'Green campus drive: 300 saplings planted', 'Community', -48, 'published', [
                'excerpt' => 'Students and faculty planted native trees along the college boundary as part of the monsoon plantation drive.',
                'body' => '<p>The Environment Club led the plantation of 300 saplings, including neem, sukh chain and kachnar. Each section has adopted a row of trees.</p>']],
            ['announcement', 'Fee submission deadline — second instalment', 'Fees', -8, 'published', ['ticker' => true, 'expires' => 18,
                'excerpt' => 'The second instalment of tuition fee must be deposited by 10 December. A fine of Rs. 20 per day applies after the deadline.',
                'body' => '<p>Students should deposit the fee at the designated bank branch using the challan available from the accounts office or the Downloads page.</p><p>Students on fee concession must renew their application before the deadline.</p>']],
            ['announcement', 'Library timings during class tests', 'Library', -2, 'published', ['expires' => 6,
                'excerpt' => 'The library will remain open until 4:00 PM from 1 to 12 December so students can prepare for class tests.',
                'body' => '<p>The reading hall will remain open until 4:00 PM on working days. Reference books may be issued overnight with the librarian\'s permission.</p>']],
            ['announcement', 'Uniform and ID card compliance', 'Administration', -15, 'published', [
                'excerpt' => 'Students must wear the prescribed uniform and carry their college ID card at all times on campus.',
                'body' => '<p>Students found without uniform or ID card will not be allowed into classrooms. Replacement ID cards are issued by the admin office within two working days.</p>']],
            ['announcement', 'Scholarships: PEEF and need-based applications', 'Fees', -10, 'published', [
                'excerpt' => 'Students may apply for PEEF and college need-based scholarships until 20 December. Forms are available in the Downloads section.',
                'body' => '<p>Eligible students should submit the completed form, income certificate of parents and a copy of the latest result card to the scholarship committee.</p>']],
            ['notice', 'Merit list — I.C.S. second list', 'Admissions', -26, 'published', [
                'excerpt' => 'The second merit list for I.C.S. Part-I is displayed. Selected candidates must deposit dues within three working days.',
                'body' => '<p>Candidates should bring original documents for verification at the admissions office.</p>']],
            ['notice', 'Change in college timings for winter', 'Administration', -35, 'published', [
                'excerpt' => 'From 16 November, college timings are 8:30 AM to 1:30 PM (Monday–Thursday) and 8:30 AM to 12:00 PM on Friday.',
                'body' => '<p>The revised timings apply to all classes. Practical periods will be adjusted by the respective departments.</p>']],
            ['notice', 'Submission of examination forms — HSSC annual 2027', 'Examinations', -90, 'published', ['expires' => -30,
                'excerpt' => 'Part-II students must submit BISE examination forms with fee by the board\'s deadline.',
                'body' => '<p>Forms are available from the examination branch.</p>']],
            ['notice', 'Holiday on account of 12 Rabi-ul-Awwal', 'Holidays', -75, 'published', ['expires' => -60,
                'excerpt' => 'The college remained closed on account of Eid Milad-un-Nabi (PBUH).',
                'body' => '<p>Classes resumed on the following working day.</p>']],
            // Scheduled
            ['announcement', 'Merit lists — 1st Year and BS 1st semester', 'Admissions', 58, 'scheduled', [
                'excerpt' => 'First merit lists for every 1st Year group and the BS programmes will be displayed here.',
                'body' => '<p>Selected candidates must deposit the fee within three working days of the list. The second list will follow one week later.</p>']],
            ['notice', 'Classes resume after winter vacations', 'Holidays', 40, 'scheduled', ['ticker' => true,
                'excerpt' => 'Classes for all programmes resume on Monday 4 January 2027 according to the regular timetable.',
                'body' => '<p>Students must bring their attendance registers signed by parents.</p>']],
            ['news', 'Science & IT exhibition — winners', 'Academics', 13, 'scheduled', [
                'excerpt' => 'Results of the Science & IT exhibition and photographs of the winning projects.',
                'body' => '<p>To be updated after the judges\' announcement.</p><p>Results will include all categories: physics, chemistry, biology and computing.</p>']],
            // Drafts
            ['news', 'Alumni meet 2027 — save the date', 'Community', null, 'draft', [
                'excerpt' => 'Former students of all batches are invited to the alumni reunion in February 2027 at the college ground.',
                'body' => '<p>Details of registration and the programme will be shared soon. Alumni can share their contact details with the office.</p>']],
            ['announcement', 'Summer camp registration', 'Co-curricular', null, 'draft', [
                'excerpt' => 'Registration for the summer coding and sports camp for school students will open in May.',
                'body' => '<p>Draft — confirm dates with the sports and CS departments before publishing.</p><p>Fee and timings to be finalised.</p>']],
            ['event', 'Annual prize distribution', 'Academics', null, 'draft', ['event' => [70, '10:00', '13:00'], 'venue' => 'College Auditorium',
                'excerpt' => 'Position holders in board examinations and co-curricular competitions will receive prizes and certificates.',
                'body' => '<p>Chief guest to be confirmed. List of prize winners is being compiled by the examination branch.</p>']],
            // Archived
            ['announcement', 'Admissions Part-I 2026 closed', 'Admissions', -70, 'archived', [
                'excerpt' => 'Admissions to intermediate Part-I for session 2026–2028 are closed. Classes have started.',
                'body' => '<p>Thank you to all applicants.</p><p>Late applications cannot be accepted.</p>']],
        ];

        foreach ($rows as $i => [$type, $title, $cat, $offset, $status, $x]) {
            $published = $offset === null ? null : $n->copy()->addDays($offset)->setTime(9 + $i % 6, [0, 15, 30, 45][$i % 4]);
            $post = new Post([
                'type' => $type, 'title' => $title, 'category' => $cat, 'excerpt' => $x['excerpt'], 'body' => $x['body'],
                'status' => $status, 'published_at' => $published,
                'expires_at' => isset($x['expires']) ? $n->copy()->addDays($x['expires'])->setTime(23, 59) : null,
                'is_pinned' => $x['pinned'] ?? false, 'show_in_ticker' => $x['ticker'] ?? false,
                'views' => $status === 'published' && $published?->isPast() ? (int) (mt_rand(40, 260) * (1 + abs($offset) / 10) * (isset($x['pinned']) ? 3 : 1)) : 0,
                'author_id' => $author->id,
            ]);
            if (isset($x['event'])) {
                [$d, $from, $to] = $x['event'];
                $post->event_starts_at = $n->copy()->addDays($d)->setTimeFromTimeString($from);
                $post->event_ends_at = $n->copy()->addDays($d)->setTimeFromTimeString($to);
                $post->venue = $x['venue'];
            }
            $post->created_at = $published ?? $n->copy()->subDays(mt_rand(1, 5));
            $post->updated_at = $post->created_at;
            $post->save();
            if (! empty($x['cover'])) {
                $post->cover_path = $this->artwork("posts/covers/{$post->slug}.jpg", 1200, 675, '', '', $i);
            }
            if (! empty($x['pdf'])) {
                $post->attachment_path = $this->pdf("posts/attachments/{$post->slug}.pdf", $x['pdf'], [$title, $x['excerpt']]);
                $post->attachment_name = Str::slug($x['pdf']).'.pdf';
            }
            $post->saveQuietly();
        }
    }

    private function pages(User $editor): void
    {
        $pages = [
            ['About the college', 'about', 'A public-sector college on Peshawar Road, Rawalpindi, offering intermediate and four-year BS programmes.',
                '<p>FG Liaquat Ali Degree College is a public-sector college on Peshawar Road, Rawalpindi, run under the Directorate of Federal Government Educational Institutions (Cantonments/Garrisons), Ministry of Defence. It offers intermediate and degree-level education at nominal fees.</p><h3>Campus</h3><ul><li>Spacious classrooms, science and computer laboratories</li><li>A well-stocked library and a digital library</li><li>An auditorium, a sports ground, a cafeteria and a mosque</li></ul>'],
            ["Principal's message", 'principals-message', 'A message from the Principal to students and parents.', null],
            ['History', 'history', 'From an intermediate college for boys in 2007 to a degree college with four-year BS programmes.',
                '<p>The college was established in 2007 as an intermediate college for boys. B.Sc. classes were introduced in 2010.</p><p>In 2019 the Department of English began a four-year BS programme in English Linguistics in affiliation with the University of the Punjab, followed a year later by a BS in Information Technology from the Department of Computer Science. Since 2021 the undergraduate programmes have admitted female students, with equal seats for boys and girls on general merit.</p>'],
            ['Vision & mission', 'vision-mission', 'Why we exist and what we promise to every student who joins us.',
                '<h3>Vision</h3><p>To be a model public college where every student, regardless of background, receives an education of quality, character and purpose.</p><h3>Mission</h3><ul><li>Teach with care, rigour and integrity</li><li>Build discipline, tolerance and civic responsibility</li><li>Use technology to serve students and parents transparently</li></ul>'],
            ['Rules & discipline', 'rules-discipline', 'Attendance, uniform, conduct and examination rules every student must follow.',
                '<h3>Attendance</h3><p>A minimum of 75% attendance in each subject is required to sit class tests and to be sent up for board examinations. Parents are notified by SMS when a student is absent.</p><h3>Uniform & ID</h3><p>The prescribed uniform and the college ID card are compulsory on campus.</p><h3>Conduct</h3><p>Use of mobile phones in classrooms, smoking, and political activity are prohibited.</p>'],
            ['Admission policy', 'admission-policy', 'Eligibility, merit calculation, quotas and the online admission process.',
                '<h3>Merit</h3><p>Admission is offered in 1st Year (HSSC Part-I) and the 1st semester of BS only. Intermediate admissions are based on Matric marks (more than 60% for the science groups). BS admissions use 70% Intermediate and 30% Matric marks. Hafiz-e-Quran candidates receive 20 additional marks on verification.</p><h3>Seat allocation (FGEI Directorate)</h3><ul><li>Open merit — 53%</li><li>Serving Armed Forces personnel — 25%</li><li>Retired Armed Forces personnel — 10%</li><li>Defence paid employees — 10%</li><li>Disability quota — 2%</li><li>Children of serving FGEI (C/G) employees — over and above</li></ul><h3>Documents</h3><p>CNIC/B-Form, father\'s CNIC, result cards, photograph and domicile are uploaded online and verified automatically before merit lists are prepared.</p>'],
            ['Fee structure', 'fee-structure', 'Annual tuition and other charges for each programme, session 2026–2028.',
                '<table><thead><tr><th>Programme</th><th>Annual fee (Rs.)</th></tr></thead><tbody><tr><td>F.Sc. Pre-Medical / Pre-Engineering / General Science</td><td>18,500</td></tr><tr><td>I.C.S.</td><td>19,500</td></tr><tr><td>I.Com.</td><td>15,500</td></tr><tr><td>F.A.</td><td>14,000</td></tr><tr><td>BS Computer Science</td><td>42,000</td></tr><tr><td>BS English</td><td>36,000</td></tr><tr><td>BS Information Technology</td><td>42,000</td></tr></tbody></table><p>Fee concessions are available for deserving students on the recommendation of the scholarship committee.</p>'],
            ['Contact', 'contact', 'Address, phone numbers, office hours and directions to the college.',
                '<p>Visit the admissions office between 8:00 AM and 2:00 PM, Monday to Friday. You can also send a message through the contact form and the office will reply within two working days.</p>'],
        ];
        foreach ($pages as $i => [$title, $slug, $summary, $body]) {
            Page::create([
                'title' => $title, 'slug' => $slug, 'summary' => $summary,
                'body' => $body ?? '<p>'.e(Str::before(Setting::get('principal_message'), "\n")).'</p><p>'.e(Str::after(Setting::get('principal_message'), "\n\n")).'</p>',
                'meta_description' => Str::limit($summary.' F.G. Liaquat Ali Degree College, Rawalpindi.', 290),
                'in_menu' => true, 'menu_order' => $i + 1, 'is_published' => true, 'updated_by' => $editor->id,
            ]);
        }
    }

    /** Copies a bundled website photo into the public disk (as if uploaded in the Studio). */
    private function photo(string $from, string $to): string
    {
        Storage::disk('public')->put($to, file_get_contents(public_path($from)));

        return $to;
    }

    private function slides(): void
    {
        // Real photos of the college; more slides (army visit, principal, labs, sports) can be added in the Studio.
        $slides = [
            ['FG Liaquat Ali Degree College, Peshawar Road.', 'A purpose-built public campus in Rawalpindi Cantt, under the Federal Government Educational Institutions (C/G).', 'Apply for admission', '/apply', 'images/college-building@2x.jpg'],
            ['Disciplined, confident and ready for what comes next.', 'Intermediate and four-year BS programmes at nominal fees.', 'View programmes', '/programmes', 'images/site/students-lawn.jpg'],
            ['Dedicated faculty, one college family.', 'Associate and assistant professors who lead eleven departments — and know their students by name.', 'Meet the faculty', '/faculty', 'images/site/faculty-students.jpg'],
            ['Part of the Armed Forces’ education network.', 'Quality education for the children of Armed Forces personnel and civilians in cantonments and garrisons.', 'Admission policy', '/admission', 'images/site/building-front.jpg'],
        ];
        foreach ($slides as $i => [$h, $sub, $l, $u, $img]) {
            HeroSlide::create([
                'heading' => $h, 'subheading' => $sub, 'cta_label' => $l, 'cta_url' => $u, 'sort_order' => $i + 1, 'is_active' => true,
                'image_path' => $this->photo($img, 'slides/'.basename($img)),
            ]);
        }
    }

    private function gallery(): void
    {
        $campus = GalleryAlbum::create(['title' => 'Our campus', 'slug' => 'our-campus', 'description' => 'The college building, our students and faculty on Peshawar Road, Rawalpindi.', 'taken_on' => now()->subDays(5), 'is_published' => true]);
        foreach ([['images/college-building@2x.jpg', 'College main building'], ['images/site/students-lawn.jpg', 'Students on the college lawn'], ['images/site/faculty-students.jpg', 'Faculty and students'], ['images/site/faculty-students-2.jpg', 'Faculty and students outside the main block']] as $i => [$img, $cap]) {
            $campus->images()->create(['path' => $this->photo($img, "gallery/{$campus->id}/".basename($img)), 'caption' => $cap, 'sort_order' => $i + 1]);
        }

        $albums = [
            ['Annual Sports Gala 2026', 'Athletics, cricket and the closing ceremony of the three-day sports gala.', -20, ['100 m final', 'March past', 'Cricket final', 'Tug of war', 'Prize distribution', 'Jinnah House celebrates']],
            ['Iqbal Day 2026', 'Declamations, poetry recitation and the exhibition on the life of Allama Iqbal.', -11, ['Opening recitation', 'Urdu declamation', 'English declamation', 'Judges panel', 'Winners', 'Exhibition stall']],
            ['New computer lab', 'Inauguration of the 40-seat lab for BS Computer Science.', -22, ['Ribbon cutting', 'Lab overview', 'First practical', 'Network rack', 'Students at work']],
            ['Independence Day 2026', 'Flag hoisting, national songs and the tableau by intermediate students.', -100, ['Flag hoisting', 'National anthem', 'Tableau', 'Speech', 'Group photo', 'Green & white']],
        ];
        foreach ($albums as $ai => [$title, $desc, $days, $captions]) {
            $album = GalleryAlbum::create(['title' => $title, 'slug' => Str::slug($title), 'description' => $desc, 'taken_on' => now()->addDays($days), 'is_published' => true]);
            foreach ($captions as $ci => $cap) {
                $album->images()->create([
                    'path' => $this->artwork("gallery/{$album->id}/".Str::slug($cap).'.jpg', 900, 600, '', '', $ai * 7 + $ci),
                    'caption' => $cap, 'sort_order' => $ci + 1,
                ]);
            }
        }
    }

    private function downloads(): void
    {
        $files = [
            ['Prospectus 2026–2027', 'prospectus', ['Programmes, eligibility, merit policy and fee structure.']],
            ['Admission form (printable)', 'forms', ['For applicants who cannot apply online. Attach attested copies of documents.']],
            ['Fee challan — second instalment', 'forms', ['Deposit at the designated bank branch before the deadline.']],
            ['Scholarship application form', 'forms', ['PEEF and need-based scholarship application.']],
            ['Date sheet — December class tests', 'datesheets', ['Part-I and Part-II, 7–12 December.']],
            ['Date sheet — BS mid-term examinations', 'datesheets', ['Semester 1 and Semester 5, week of 14 December.']],
            ['Syllabus — BS Computer Science', 'syllabus', ['Scheme of studies, semesters 1–8.']],
            ['Syllabus — BS English', 'syllabus', ['Scheme of studies, semesters 1–8.']],
            ['HSSC Part-I results summary 2026', 'results', ['Section-wise pass percentage and position holders.']],
            ['Code of conduct for students', 'policies', ['Attendance, uniform, discipline and examination rules.']],
        ];
        foreach ($files as $i => [$title, $cat, $lines]) {
            $path = $this->pdf('downloads/'.Str::slug($title).'.pdf', $title, $lines);
            $d = Download::create([
                'title' => $title, 'category' => $cat, 'file_path' => $path, 'file_name' => Str::slug($title).'.pdf',
                'size' => Storage::disk('public')->size($path), 'download_count' => mt_rand(20, 900), 'is_published' => true,
            ]);
            $d->created_at = $d->updated_at = now()->subDays(mt_rand(1, 60));
            $d->saveQuietly();
        }
    }

    private function faculty(): void
    {
        // The college's real teaching faculty (config/site.php), with photos where published.
        $departments = Department::pluck('id', 'code');
        $rank = fn (string $d) => str_starts_with($d, 'Associate') ? 0 : (str_starts_with($d, 'Assistant') ? 1 : 2);
        foreach (config('site.faculty') as $i => [$code, $name, $designation, $qualification, $photo]) {
            FacultyProfile::create([
                'department_id' => $departments[$code] ?? null, 'name' => $name, 'designation' => $designation,
                'qualification' => $qualification, 'email' => null,
                'photo_path' => $photo ? $this->photo("images/site/faculty/{$photo}.jpg", "faculty/{$photo}.jpg") : null,
                'bio' => "{$designation}, {$qualification}. Teaches intermediate and degree classes.",
                'sort_order' => $rank($designation) * 100 + $i, 'is_published' => true,
            ]);
        }
    }

    private function messages(): void
    {
        $msgs = [
            ['Rashid Mehmood', 'rashid.m@gmail.com', '0301-5550192', 'BS Computer Science eligibility', 'Assalam-o-Alaikum. My son has done F.Sc. Pre-Medical with 78% marks. Is he eligible for BS Computer Science, or does he need to take additional mathematics?', 0.2, false],
            ['Ayesha Siddiqa', null, '0333-5129087', 'Character certificate', 'I passed F.Sc. from this college in 2024. How can I get my character certificate issued? I am currently in Lahore.', 1, false],
            ['Imtiaz Hussain', 'imtiaz.hussain@outlook.com', null, 'Attendance SMS not received', 'I have not been receiving attendance SMS for my son in ICS Part-I A for the last week. Please update my number to 0345-5551820.', 2, false],
            ['Shahid Nawaz', 'shahidnawaz77@yahoo.com', '0312-5554421', 'Hostel availability', 'Does the college provide hostel facility for students coming from Attock?', 3, true],
            ['Mehwish Akram', 'mehwish.akram@gmail.com', null, 'Fee concession', 'My brother is in F.A. Part-I. Our father passed away last year. Kindly guide about the fee concession procedure.', 5, false],
            ['Col (R) Tariq Javed', 'tjaved@gmail.com', '0300-5550776', 'Alumni association', 'I graduated from this college in 2012 and would like to contribute to the alumni association and the new computer lab.', 8, true],
            ['Hira Batool', 'hira.batool@gmail.com', null, 'Transcript for university admission', 'Please let me know how long it takes to issue a transcript for BS English Semester 1–4.', 12, true],
            ['Zubair Khan', null, '0315-5553311', 'Sports trials', 'When will the cricket trials be held for the inter-college tournament?', 18, true],
        ];
        foreach ($msgs as [$name, $email, $phone, $subject, $body, $daysAgo, $read]) {
            $m = ContactMessage::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subject, 'message' => $body, 'read_at' => $read ? now()->subDays(max(0, $daysAgo - 1)) : null]);
            $m->created_at = $m->updated_at = now()->subHours((int) ($daysAgo * 24) + mt_rand(1, 9));
            $m->saveQuietly();
        }
    }

    // ------------------------------------------------------------------ helpers

    private function findFont(array $candidates): ?string
    {
        foreach ($candidates as $f) {
            if (is_file($f)) {
                return $f;
            }
        }

        return null;
    }

    /** Generated artwork: deep gradient, Mughal-arch and geometric motifs, optional caption. */
    private function artwork(string $path, int $w, int $h, string $title, string $kicker, int $seed, bool $hero = false): string
    {
        [$c1, $c2, $c3] = self::PALETTES[$seed % count(self::PALETTES)];
        $im = imagecreatetruecolor($w, $h);
        $rgb = fn ($hex) => sscanf($hex, '#%02x%02x%02x');
        [$r1, $g1, $b1] = $rgb($c1);
        [$r2, $g2, $b2] = $rgb($c2);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
        [$r3, $g3, $b3] = $rgb($c3);
        $accent = imagecolorallocatealpha($im, $r3, $g3, $b3, 60);
        $faint = imagecolorallocatealpha($im, 255, 255, 255, 112);
        $line = imagecolorallocatealpha($im, $r3, $g3, $b3, 95);
        imagesetthickness($im, 2);

        $star = function (int $cx, int $cy, float $outer, float $inner, $color) use ($im) {
            $pts = [];
            for ($k = 0; $k < 16; $k++) {
                $rad = $k % 2 ? $inner : $outer;
                $a = M_PI / 8 * $k - M_PI / 2;
                $pts[] = (int) ($cx + cos($a) * $rad);
                $pts[] = (int) ($cy + sin($a) * $rad);
            }
            imagefilledpolygon($im, $pts, $color);
        };
        $variant = $seed % 3;

        if ($variant === 0) {
            // Row of Mughal arches + rings and an eight-point star
            $archW = (int) ($w / (4 + $seed % 3));
            $base = (int) ($h * 0.98);
            for ($x = -$archW / 2 + ($seed * 37) % $archW; $x < $w + $archW; $x += $archW) {
                $aw = (int) ($archW * 0.7);
                $ah = (int) ($h * (0.42 + ($seed % 4) * 0.05));
                $top = $base - $ah + (int) ($aw / 2);
                imagefilledrectangle($im, (int) ($x - $aw / 2), $top + 1, (int) ($x + $aw / 2), $base, $faint);
                imagefilledarc($im, (int) $x, $top, $aw, $aw, 180, 360, $faint, IMG_ARC_PIE);
            }
            $cx = (int) ($w * (0.68 + ($seed % 4) * 0.06));
            $cy = (int) ($h * 0.32);
            for ($r = 40; $r < $h * 1.1; $r += 46) {
                imageellipse($im, $cx, $cy, $r * 2, $r * 2, $line);
            }
            $star($cx, $cy, $h * 0.16, $h * 0.07, $accent);
        } elseif ($variant === 1) {
            // Jaali lattice: grid of small stars, fading to one side
            $step = (int) max(48, $h / 6);
            for ($y = $step / 2; $y < $h + $step; $y += $step) {
                for ($x = $step / 2 + (((int) ($y / $step)) % 2) * $step / 2; $x < $w + $step; $x += $step) {
                    $fade = 127 - (int) (min(1, $x / $w * 1.4) * 80);
                    [$rr, $gg, $bb] = $rgb($c3);
                    $star((int) $x, (int) $y, $step * 0.36, $step * 0.16, imagecolorallocatealpha($im, $rr, $gg, $bb, max(40, $fade)));
                }
            }
        } else {
            // Large arch window with a star, offset; diagonal hairlines
            for ($d = -$h; $d < $w; $d += 26) {
                imageline($im, $d, $h, $d + $h, 0, $line);
            }
            $aw = (int) ($h * 0.62);
            $cx = (int) ($w * (0.62 + ($seed % 3) * 0.08));
            $top = (int) ($h * 0.38);
            imagefilledrectangle($im, $cx - $aw / 2, $top, $cx + $aw / 2, $h, $faint);
            imagefilledarc($im, $cx, $top, $aw, $aw, 180, 360, $faint, IMG_ARC_PIE);
            $star($cx, (int) ($top + $aw * 0.15), $aw * 0.26, $aw * 0.11, $accent);
        }
        // Grain
        for ($i = 0; $i < $w * $h / 90; $i++) {
            imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocatealpha($im, 255, 255, 255, 118));
        }
        // Bottom shade for legibility
        for ($y = (int) ($h * 0.55); $y < $h; $y++) {
            $a = (int) (127 - (($y - $h * 0.55) / ($h * 0.45)) * 70);
            imageline($im, 0, $y, $w, $y, imagecolorallocatealpha($im, 0, 0, 0, max(0, min(127, $a))));
        }

        if ($title !== '' && $this->fontSerif) {
            $white = imagecolorallocate($im, 250, 247, 238);
            $gold = imagecolorallocate($im, $r3, $g3, $b3);
            $size = (int) ($h * 0.065);
            if ($kicker !== '' && $this->font) {
                imagettftext($im, (int) ($size * 0.42), 0, (int) ($w * 0.06), (int) ($h * 0.74), $gold, $this->font, strtoupper($kicker));
            }
            $lines = explode("\n", wordwrap($title, (int) (($w * 0.8) / ($size * 0.72)), "\n", true));
            $y = (int) ($h * 0.74) + (int) ($size * 1.4);
            foreach (array_slice($lines, 0, 2) as $ln) {
                imagettftext($im, $size, 0, (int) ($w * 0.06), $y, $white, $this->fontSerif, $ln);
                $y += (int) ($size * 1.25);
            }
        }

        ob_start();
        imagejpeg($im, null, $hero ? 82 : 78);
        $bytes = ob_get_clean();
        imagedestroy($im);
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** Minimal single-page PDF (valid, prints a heading and a few lines). */
    private function pdf(string $path, string $title, array $lines): string
    {
        $esc = fn ($s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: $s);
        $stream = 'BT /F2 18 Tf 56 770 Td ('.$esc('F.G. Liaquat Ali Degree College, Rawalpindi').") Tj ET\n";
        $stream .= "0.06 0.35 0.23 RG 2 w 56 755 m 539 755 l S\n";
        $stream .= 'BT /F2 15 Tf 56 722 Td ('.$esc($title).") Tj ET\n";
        $y = 694;
        foreach ($lines as $line) {
            foreach (explode("\n", wordwrap($line, 88)) as $l) {
                $stream .= "BT /F1 11 Tf 56 {$y} Td (".$esc($l).") Tj ET\n";
                $y -= 16;
            }
        }
        $stream .= 'BT /F1 9 Tf 56 60 Td ('.$esc('Issued by the College Office · '.now()->format('j F Y')).") Tj ET\n";

        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objs) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= 'trailer << /Size '.(count($objs) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        Storage::disk('public')->put($path, $pdf);

        return $path;
    }
}
