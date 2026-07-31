# Harlej Revival Olomouc — web

Node.js/Express web s admin panelem a napojením na Facebook Graph API.

## Požadavky

- Node.js 18+
- npm

## Lokální spuštění

```bash
npm install
```

Zkopíruj `.env.example` jako `.env` a vyplň hodnoty:

```env
ADMIN_PASSWORD=zvol-si-silne-heslo
FACEBOOK_PAGE_ID=123456789
FACEBOOK_PAGE_ACCESS_TOKEN=EAAxxxxxxxx...
FACEBOOK_GRAPH_VERSION=v20.0
PORT=3000
```

Spuštění:

```bash
# produkční režim
npm start

# vývojový režim (automatický restart při změně souboru)
npm run dev
```

Web běží na `http://localhost:3000`, admin panel na `http://localhost:3000/admin.html`.

## Admin panel

Správa obsahu webu probíhá přes `/admin.html` — statistiky, členové kapely, setlist, termíny koncertů a kontaktní údaje. Přihlášení heslem z `ADMIN_PASSWORD`.

## Facebook Graph API

Pro zobrazení videí v sekci "Ukázky" je potřeba:

1. Vytvořit aplikaci na [developers.facebook.com/apps](https://developers.facebook.com/apps) → typ "Business"
2. V **Graph API Exploreru** získat Page Access Token s oprávněními `pages_show_list` a `pages_read_engagement`
3. Krátkodobý token prodloužit na dlouhodobý (60 dní) podle [návodu Mety](https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived)
4. Page ID najdeš v Nastavení stránky → O nás → ID stránky

Videa se synchronizují automaticky každých 10 minut. Pro ruční obnovení:

```bash
curl -X POST http://localhost:3000/api/videos/refresh
```

## Deployment na Render

1. Pushni kód na GitHub
2. Na [render.com](https://render.com) vytvoř **New → Web Service** napojený na GitHub repo
3. Nastav:
   - **Build Command:** `npm install`
   - **Start Command:** `node server.js`
   - **Instance Type:** Free
4. V sekci **Environment** přidej proměnné ze svého `.env`
5. Klikni **Deploy Web Service**

Render automaticky nasadí každý push na `master`.

### Vlastní doména

V Render dashboardu: **Settings → Custom Domains → Add Custom Domain**.
Render vygeneruje DNS záznamy, které nastavíš u svého registrátora. HTTPS certifikát se vytvoří automaticky.

Po přidání domény aktualizuj URL v `public/index.html` (OG tagy a JSON-LD) a commitni.

## Struktura projektu

```
harlej-revival-site/
├── server.js              Express server, Graph API sync, admin API
├── package.json
├── .env                   Lokální konfigurace (nesdílet, není v gitu)
├── data/
│   └── content.json       Obsah webu (setlist, termíny, kontakt…)
├── pictures/              Originální fotky
└── public/
    ├── index.html         Hlavní stránka
    ├── admin.html         Admin panel
    ├── 404.html           Stránka pro nenalezené URL
    └── img/
        ├── hero.jpg       Fotka kapely v hero sekci
        └── favicon.webp   Favicon (logo kapely)
```

## Bezpečnost

- HTTP security headers zajišťuje **Helmet**
- Admin endpointy mají **rate limiting** (20 req / 15 min na IP)
- POST požadavky jsou omezeny na **50 KB**
- `.env` je v `.gitignore` — nikdy ho necommituj
