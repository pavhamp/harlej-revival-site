# Harlej Revival Olomouc — web s napojením na Facebook

Statický web (`public/index.html`) + malý Node.js backend (`server.js`), který
pravidelně stahuje videa z facebookové stránky přes Graph API a servíruje je
frontendu. Když nahrajete nové video na Facebook, backend ho do 10 minut
(nebo hned po ručním obnovení) automaticky vytáhne a zobrazí v sekci "Ukázky".

## 1. Instalace

```bash
npm install
cp .env.example .env
```

## 2. Získání Facebook Page ID a Access Tokenu

Toto je jediná část, kterou musíte udělat ručně přes facebook.com /
developers.facebook.com — Claude to za vás nemůže nastavit, protože jde o
vaše přihlašovací údaje a schvalovací proces na straně Mety.

1. **Vytvořte si aplikaci** na [developers.facebook.com/apps](https://developers.facebook.com/apps) → "Create App" → typ "Business".
2. V aplikaci přidejte produkt **Facebook Login** a/nebo rovnou použijte
   **Graph API Explorer** (developers.facebook.com/tools/explorer).
3. V Graph API Exploreru:
   - Vyberte svou aplikaci nahoře.
   - U "User or Page" zvolte **Get Page Access Token** a vyberte stránku
     Harlej Revival Olomouc (musíte být admin stránky).
   - Odsouhlaste oprávnění **pages_show_list** a **pages_read_engagement**.
4. Tím dostanete **krátkodobý** token. Prodlužte si ho na dlouhodobý (60 dní,
   dá se opakovaně obnovovat) podle [návodu Mety na dlouhodobé tokeny](https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived).
5. Page ID najdete na stránce v **Nastavení stránky → O nás → ID stránky**,
   nebo v Graph API Exploreru zavoláním `GET /me/accounts`.
6. Obě hodnoty vložte do `.env`:
   ```
   FACEBOOK_PAGE_ID=123456789
   FACEBOOK_PAGE_ACCESS_TOKEN=EAAxxxxxxxx...
   ```

**Poznámka k oprávněním:** Pokud token generujete jako admin vlastní
stránky pro vlastní použití (ne pro cizí uživatele), obvykle není potřeba
procházet plné **App Review** od Mety — stačí, že jste v roli
administrátora/testera aplikace. Pokud by web měl číst videa z více
stránek spravovaných jinými lidmi, App Review už potřeba bude.

Token je citlivý údaj — nikdy ho nesdílejte ani necommitujte do gitu.
Soubor `.env` by měl zůstat jen na vašem serveru.

## 3. Spuštění

```bash
npm start
```

Web poběží na `http://localhost:3000`. Backend si videa načte hned při
startu a pak automaticky každých 10 minut.

Pro okamžité obnovení bez čekání (např. hned po nahrání nového videa)
zavolejte:

```bash
curl -X POST http://localhost:3000/api/videos/refresh
```

## 4. Jak to funguje

- `server.js` volá `GET /{page-id}/videos` na Facebook Graph API a ukládá
  výsledek do jednoduché paměťové cache (žádná databáze není potřeba pro
  pár videí).
- Frontend (`public/index.html`) při načtení stránky zavolá `/api/videos`
  na vlastním backendu a vykreslí karty s náhledovým obrázkem, názvem a
  odkazem na video na Facebooku.
- Pokud stažení z Facebooku selže (např. vypršel token), web zobrazí
  poslední známá videa z cache a tichou poznámku pod nimi — nikdy
  nezůstane prázdný kvůli chybě API.

## 5. Hosting (až budete řešit)

Cokoliv, co umí spustit Node.js proces s otevřeným portem — např. Railway,
Render, vlastní VPS s PM2/nginx apod. Až budete hosting vybírat, dejte
vědět a pomůžu s konkrétním nastavením (env proměnné, reverse proxy, atd.).

## Struktura projektu

```
harlej-site/
├── server.js          backend + Graph API sync
├── package.json
├── .env.example        vzor konfigurace (zkopírovat jako .env)
├── public/
│   └── index.html      web (design + JS pro načtení videí)
└── README.md
```
