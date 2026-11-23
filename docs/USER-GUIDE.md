# College ERP — user guide (Roman Urdu)

FG Liaquat Ali Degree College ka ERP aik hi jagah se teen kaam karta hai: **admissions**, **attendance** aur **college website**.
Har staff member apne email/password se login karta hai, aur system role ke hisaab se sirf wahi screens dikhata hai jo us ka kaam hai.

## 1. Kaun kya dekhta hai (roles)

| Role | Login ke baad | Kya kar sakta hai |
|---|---|---|
| **Admin** (IT) | Command centre | Sab kuch, staff accounts aur roles bhi |
| **Principal** | Command centre | Poore college ka dashboard, admissions, attendance analytics, staff, website studio, audit log |
| **Admission officer** | Command centre (admissions view) | Applications, AI document checks, merit lists, admission cycles, students |
| **Teacher** | Command centre (apni classes) | Sirf apni classes ki attendance, apne students, apna timetable |
| **Website editor** | Website studio | Sirf website: notices, news, events, gallery, downloads, settings |

Upar **Ctrl + K** dabayein: kisi bhi screen ka naam likh kar seedha wahan ja sakte hain. Chaand/sooraj wala button dark mode on/off karta hai.

## 2. Command centre (home dashboard)

Login ke baad pehli screen. Yeh har subah aik nazar mein batata hai ke **aaj kis cheez par dhyan dena hai**:

- **Upar ka paragraph** — khud likha hua khulasa: aaj kitne students class mein aaye, kitne registers abhi mark hone baqi hain, kitni applications par AI ne aitraaz kiya, kitne students 75% se neeche hain.
- **Present today** gol meter — aaj ki attendance %. Saath mein pichle 7 din aur poore term ki average.
- **4 cards** — students on roll, 75% se neeche students, open admission cycle ki applications, AI ki flag ki hui applications. Har card par click karein to us ki poori list khulti hai.
- **Attendance graph** — pichle 6 hafton ki rozana attendance; peeli line 75% ka qanoon.
- **Registers today** — aaj ke periods: ✓ mark ho gaye, 🟠 due (waqt guzar gaya, abhi mark nahi hua — "Mark now"), baad wale.
- **Admission funnel** — Started → Submitted → Verified → Selected → Admitted, aur har programme ki demand vs seats.
- **AI document verification** — woh applications jin ke documents aapas mein ya form se match nahi karte (naam, walid ka naam, CNIC, date of birth, marks, duplicate CNIC).
- **Lowest attendance** — sab se kam attendance wale students; click karein to student ka poora record.
- **On the website now** — website par is waqt kya live hai (events, notices).
- **Enrolment** aur **Recent activity** (har action ka audit log).

Teacher ko sirf apni classes nazar aati hain; admission officer ko sirf admissions wala hissa.

## 3. Admissions + AI

**Applicant (website par):**
1. *Apply online* → programme chunta hai, naam/mobile deta hai → **tracking number + PIN** milta hai.
2. Documents upload karta hai: CNIC/B-Form, walid ka CNIC, Matric (aur BS ke liye Inter) result card, photo, domicile.
3. **AI har document parhta hai** aur form khud bhar deta hai (sunehri highlight = document se aaya). Applicant check karke submit karta hai.
4. Baad mein tracking number + PIN se status dekh sakta hai.

**Admission officer (ERP mein):**
1. **Applications** — filter (programme, status, AI result, quota), search (naam/CNIC/tracking), CSV export.
2. Application kholen → **Form vs documents** table: har field ke saamne har document ki value, hara = match, laal = mismatch.
3. AI ki ghalti ho to **Accept** (wajah likh kar), ya galat field **correct** karein. Zarurat ho to *Re-read documents*.
4. **Merit lists** → programme chunein → *Generate* (merit khud calculate: Matric/Inter % + Hafiz bonus) → check → *Publish*. Selected applicants ka status khud badal jata hai.
5. **Admit** → student record ban jata hai, roll number aur section khud lag jata hai.

AI ke liye `.env` mein `ANTHROPIC_API_KEY` chahiye. Key na ho to documents "manual" mark hote hain aur staff khud check karta hai — system kabhi andaza nahi lagata.

## 4. Attendance

**Teacher:**
1. **Mark attendance** → aaj ke periods ki list → apna period kholen.
2. Sab students pehle se **Present**. Sirf absent/late wale ko tap karein (P → A → L → E). Keyboard: ↑↓ chalna, P/A/L/E dabana.
3. Topic likhein → **Save register**. 48 ghante baad register lock ho jata hai (Principal phir bhi badal sakte hain).
4. Approved leave wale students khud "E" (leave) par hote hain.

**AI se bol kar attendance (Speak attendance):**
1. Register par **Speak attendance** (mic wala hara button) dabayein → **Tap and speak**.
2. Bolein, maslan: *"Ahmed Raza aur roll 7 absent hain, Hamza ki chutti hai, Ali late hai, baqi sab present"* → **Stop**.
3. Register khud bhar jata hai: jin ke naam liye unka status, baqi sab Present (approved leave wale Leave hi rehte hain). Upar list aati hai ke kis ko kya lagaya.
4. Agar koi naam saaf na ho (do "Usman" hon) to woh peela box mein likha aata hai — us ko haath se theek karein. **Undo** se pehle wali halat wapas.
5. Topic likh kar **Save register** — bina save ke kuch record nahi hota.

Mic ke baghair sentence type bhi kar sakte hain. Bina AI key ke bhi English/Roman Urdu naam aur roll number samajh leta hai; **OpenAI ya Gemini key** lagane se Urdu, ghalat spelling aur dheela jumla bhi samajh aata hai (`.env` mein `OPENAI_API_KEY` ya `GEMINI_API_KEY`).

**Timetable, extra classes aur class alerts:**
1. **Timetable**: har teacher ko apna hafta nazar aata hai, Principal har section ka timetable banata hai.
2. **Extra classes** (Academics → Extra classes): teacher apne subject ki extra / make-up class ya class test rakh sakta hai, Principal kisi bhi teacher ke liye. System check karta hai ke us waqt teacher ya class ki pehle se koi class to nahi. "Tell the students by SMS" par tick karein to students ko SMS outbox mein chala jata hai.
3. **Alerts (ghanti 🔔 upar):**
   - subah 07:30 se teacher ko us din ki saari classes ki list (regular + extra),
   - har class se **10 minute pehle**: "Physics in 10 min · FSC-PM Part-I A · Room B-2",
   - Principal ne extra class rakhi ya cancel ki to us teacher ko foran alert.
   Website khuli ho to neeche kone mein popup aata hai. "Turn on desktop alerts" dabane se doosre tab mein bhi popup aata hai. Chahein to teacher ke phone par SMS bhi (`.env` mein `CLASS_REMINDER_SMS=true`).
4. Extra class us din ke **Mark attendance** mein bhi nazar aati hai, aur us ka register alag lagta hai.

**Khud-ba-khud hone wale kaam:** absent student ke walid ko SMS queue (English + Roman Urdu); 75% se neeche girne ya 3 din lagatar ghair-hazir hone par alert.

**Principal:** Analytics (program/section/din ke hisaab se), Defaulters (75% se kam — CSV aur ek click mein sab guardians ko SMS), Leave requests (approve/reject), Guardian alerts (SMS outbox), monthly register print.

SMS abhi log mein likhe jate hain; Pakistani SMS provider ka account mile to `config/attendance.php` mein jod diya jayega.

## 4b. Student portal aur mobile app

**Student ka account khud banta hai:** admission office jab applicant ko **Admit** karta hai, usi waqt student portal ka account ban jata hai.
- Login: **roll number** + wahi **6-digit PIN** jo admission form ke waqt mila tha. Pehli dafa login par apna password banana zaroori hai.
- Applicant ke "Check status" page par admit hote hi roll number aur "Open student portal" button aa jata hai.
- PIN / password bhool jaye to: Students → student ka profile → **Reset portal password** (naya temporary password ek dafa dikhta hai).

**Student portal mein:** aaj ki classes (har period ka apna P/A), attendance har subject ki aur "75% tak pahunchne ke liye kitni classes lagatar", hafte ka timetable (extra class aur test samait), college ke notices, **leave ki darkhwast** (Principal ki ghanti mein alert aata hai, faisla Attendance → Leave requests se), aur apna admission record (merit list, documents).

**Mobile app:** `FGLA-College-1.0.apk` Android phone par install karein (tafseel `mobile/README.md`). Pehli screen par Student ya Teacher/Staff chunein. iPhone par Safari → Share → *Add to Home Screen*.

## 5. Website studio

Website editor (ya Principal) yahan se poori website chalata hai — koi coding nahi:
- **Announcements / News / Events / Notices** — likhein, PDF lagayein (date sheet, merit list), *Publish now* ya *Schedule* (future date), expiry date, *Pin* (upar rakhein), *Ticker* (website ki pati mein).
- **Hero slides** — website ki slideshow ki photos (building, events…).
- **Gallery, Downloads, Faculty directory, Pages** (About, Principal's message…), **Site settings** (phone, email, address, Principal ka message).
- **Contact inbox** — website ke form se aane wale messages.

Website har cheez `/api/v1/...` se uthati hai, is liye studio mein publish karte hi website par nazar aa jati hai.

## 6. Academics

Students (search, profile, ID card print, CSV), Programs (seats, merit formula), Sections, Subjects, **Timetable** (clash check: aik teacher ya section aik waqt mein do jagah nahi), Staff & roles (naya account, password reset, deactivate), Activity log.

## 7. Rozana ka routine

| Waqt | Kaun | Kya |
|---|---|---|
| Subah | Principal | Command centre dekhein: due registers, flagged applications, defaulters |
| Har period ke baad | Teacher | Register mark (10–20 second) |
| Din mein | Admission officer | AI verification queue saaf karein |
| Jab bhi notice ho | Editor | Studio se publish — website par foran |
| Hafta war | Principal | Defaulters list → guardians ko SMS |
