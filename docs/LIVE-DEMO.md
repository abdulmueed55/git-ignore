# System ko live (online) chalana

Do tareeqe hain. Dono mein **poora system chalta hai** — login, attendance save, admission apply, notice publish, sab.

---

## Tareeqa 1 — Online link (Render.com, free) · ~10 minute

Is se aap ko `https://fgla-erp-demo.onrender.com` jaisa link milega jo kisi bhi phone/computer par khulta hai.

1. **render.com** par jayein → **Get Started** → **GitHub se sign up** karein (wohi GitHub account jis mein yeh repo hai).
2. Render ko repo `abdulmueed55/git-ignore` ka access dein.
3. Dashboard par **New +** → **Blueprint** chunein.
4. Repo `abdulmueed55/git-ignore` chunein, branch **`claude/eloquent-curie-taascf`**.
5. Render khud `render.yaml` parh lega (service: `fgla-erp-demo`, Docker, Free plan). **ANTHROPIC_API_KEY** khaali chhor dein (ya AI key ho to daal dein) → **Apply / Deploy**.
6. Pehli dafa build mein 5–10 minute lagte hain. "Live" likha aaye to upar diya hua **URL** kholein.
7. Login: `principal@fglacollege.edu.pk` / `password` (baqi accounts login page par likhe hain).

**Free plan ki baatein:**
- 15 minute koi na aaye to server so jata hai; agli dafa kholne par **~1 minute** lagta hai (demo college dobara banta hai).
- Har restart par demo data **naye siray se** banta hai — test ke liye theek hai, asal data ke liye nahi.
- Asal istemal ke liye paid plan / VPS + MySQL lein aur `DEMO_RESET=false` rakhein.

---

## Tareeqa 2 — Apne computer par (Windows)

1. **Laragon Full** install karein (laragon.org), PHP 8.3 chunein.
2. GitHub se branch `claude/eloquent-curie-taascf` ka ZIP download kar ke `C:\laragon\www\fgla-erp` mein nikalein.
3. **`demo.bat`** par double-click → "Demo ready" → browser mein **http://localhost:8000**

Testing checklist: `docs/TESTING-GUIDE.md`.

---

## Docker (kisi bhi server par)

```bash
docker build -t fgla-erp-demo .
docker run -p 8080:8080 -e APP_URL=http://SERVER-IP:8080 fgla-erp-demo
```

| Setting | Matlab |
|---|---|
| `DEMO_RESET=true` | har start par demo college naya banta hai (default) |
| `DEMO_RESET=false` + `DB_CONNECTION=mysql`, `DB_HOST`… | asal data, sirf migrations chalti hain |
| `ANTHROPIC_API_KEY` | AI document reading on |
| `OPENAI_API_KEY` / `GEMINI_API_KEY` | Speak attendance AI se (Urdu bhi) — bina key ke built-in matcher |
| `APP_DEMO=false` | login page par demo accounts chhupa dein |
