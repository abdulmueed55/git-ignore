# System test karne ka tareeqa (Roman Urdu)

Yeh guide batati hai ke ERP ko **apne computer par** kaise chalayein aur kya kya test karein.
Demo mein aik poora "farzi" college pehle se bhara hota hai (staff, ~430 students, 10 hafte ki attendance,
2 admission cycles, website ka content), taake har screen par data ho.

---

## Hissa 1 — Computer par chalana (Windows, sab se aasaan)

**Aik dafa install karein:**

1. **Laragon** download karein (laragon.org → *Laragon Full*) aur install karein. Is mein PHP, Composer aur Node.js sab hote hain.
   Laragon kholein → menu → *PHP* → version **8.3** ya naya chunein.
2. **Code download karein:** GitHub par repo `abdulmueed55/git-ignore` → branch **`claude/eloquent-curie-taascf`** chunein →
   *Code → Download ZIP*. ZIP ko `C:\laragon\www\fgla-erp` mein extract karein.

**Har dafa chalane ke liye:**

3. Folder `C:\laragon\www\fgla-erp` mein **`demo.bat`** par double-click karein.
   Pehli dafa 3–5 minute lagte hain (packages download hote hain). Aakhir mein likha aayega: *Demo ready*.
4. Browser mein kholein: **http://localhost:8000**
5. Kaam khatam ho to black window band kar dein. Dobara chalana ho to phir `demo.bat` — har dafa demo data naye siray se ban jata hai.

> Mac/Linux par: Terminal mein folder kholein aur `./demo.sh` chalayein.
> Kuch ghalat ho to window mein jo **laal error** likha ho, us ka screenshot mujhe bhej dein.

---

## Hissa 2 — Demo logins

Sab ka password: **`password`**

| Kis tarah test karna hai | Email |
|---|---|
| Principal | `principal@fglacollege.edu.pk` |
| Admission officer | `admissions@fglacollege.edu.pk` |
| Teacher | `teacher@fglacollege.edu.pk` |
| Website editor | `editor@fglacollege.edu.pk` |
| Admin (IT) | `admin@fglacollege.edu.pk` |

Aik role se nikal kar doosre mein jaane ke liye neeche-baayen apne naam ke saath wala **logout** button dabayein.

---

## Hissa 3 — Test checklist

Har line ke saamne ✓ ya ✗ lagayein. ✗ ho to screen ka screenshot aur 1 line mein kya hua, bhej dein.

### A. Login aur dashboard
- [ ] Login page par college building aur crest nazar aata hai
- [ ] Ghalat password dalne par laal error aata hai
- [ ] Principal login → **Command centre**: upar khulasa, attendance meter, 4 cards, graph, "Registers today", admission funnel, AI flagged list, website, activity
- [ ] Kisi card par click → us ki list khulti hai
- [ ] **Ctrl + K** dabayein → "defaulters" likhein → Enter → Defaulters page
- [ ] Upar chaand/sooraj button → dark mode
- [ ] Mobile par (ya browser chhota kar ke) menu ☰ se sidebar khulta hai

### B. Teacher — attendance
- [ ] Teacher login → **Mark attendance** → aaj ke periods nazar aate hain
- [ ] Koi period kholein → sab students Present → 2 students ko **A** karein → counter badalta hai
- [ ] Topic khaali chhor kar save → rokta hai (topic zaroori)
- [ ] Topic likh kar **Save register** → hara message "Register saved … guardian alert queued"
- [ ] Keyboard: ↓ se agla student, **A** dabane se absent
- [ ] **Analytics**, **Defaulters** (CSV download), kisi student ka naam → attendance profile
- [ ] Teacher kisi doosre teacher ki class nahi khol sakta (403)

### C. Principal — attendance control
- [ ] **Leave requests** → aik approve karein
- [ ] **Guardian alerts** → *Send queued* (abhi SMS log mein jate hain)
- [ ] **Monthly register** → print preview

### D. Admissions + AI
- [ ] Logout → login page par **Apply for admission** (ya http://localhost:8000/apply)
- [ ] Form khaali submit → har zaroori field par laal error
- [ ] Form bharein → **tracking number + PIN** milta hai (note kar lein)
- [ ] Documents upload karein (koi bhi JPG/PDF) → status dikhta hai
  *(AI key ke baghair "manual" aata hai — yeh sahi hai)*
- [ ] Review → Submit → done page
- [ ] **Check status** → tracking no + PIN → status nazar aata hai
- [ ] Admission officer login → **Applications** → apni nayi application nazar aati hai
- [ ] Koi **flagged** application kholein → *Form vs documents* table mein laal mismatch
- [ ] Mismatch par **Accept** (wajah likh kar) → status badalta hai
- [ ] **Merit lists** → programme → *Generate* → *Publish*
- [ ] Selected application → **Admit** → **Students** mein roll number ke saath nazar aata hai

### E. Website studio
- [ ] Editor login → seedha **Website studio** khulta hai (ERP nahi)
- [ ] **New announcement** → title, summary, text → *Publish now*
- [ ] Naye tab mein http://localhost:8000 → announcement website par nazar aata hai
- [ ] Post ko **Schedule** (kal ki date) → website par abhi nahi aata
- [ ] PDF attach kar ke notice → website par download hota hai
- [ ] Gallery album banayein, photos upload karein
- [ ] **Site settings** → phone number badlein → website footer mein badal jata hai
- [ ] Website ka contact form bharein → **Contact inbox** mein aata hai

### F. Academics aur staff
- [ ] **Students** → search (naam / roll no / CNIC) → profile → **ID card** print
- [ ] Naya student → ghalat CNIC (12345) → error; sahi format `37405-1234567-1`
- [ ] **Timetable** → aik teacher ko aik hi waqt do jagah lagayein → clash error
- [ ] **Staff & roles** → naya teacher banayein → us se login ho jata hai
- [ ] **Activity log** → upar ke saare kaam yahan record hain

---

## Hissa 4 — Demo aur asli system mein farq

| Demo mein | Asli system ke liye chahiye |
|---|---|
| Farzi students/staff (asli naam college website se faculty ke) | College ka asli staff aur students ka data (Excel bhi chalega) |
| AI documents "manual" | `ANTHROPIC_API_KEY` |
| SMS sirf log mein | Pakistani SMS provider ka account |
| SQLite database (aik file) | Server par MySQL |
| `localhost` | Domain + hosting |
