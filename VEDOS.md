# Nasazení na VEDOS

Na VEDOS běží web jako obyčejné soubory + dva malé PHP skripty pro admin panel.
Node.js (`server.js`) tam není potřeba — slouží jen pro spuštění webu u vás na počítači.

## Co se nahrává

Na hosting se nahrává **obsah složky `public/`** (ne složka samotná) do kořenové
složky webu na VEDOS (obvykle `www` — přesný název uvidíte po připojení přes FTP):

```
www/
├── .htaccess            nastavení serveru (skrytý soubor — musí se nahrát taky!)
├── index.html, admin.html, 404.html, robots.txt, sitemap.xml
├── api/
│   ├── content.php      načítání a ukládání obsahu
│   ├── verify.php       ověření hesla do adminu
│   ├── _lib.php
│   └── config.php       heslo do adminu (vytvoříte podle návodu níže)
├── data/content.json    výchozí obsah webu
├── storage/.htaccess    sem se ukládají změny z adminu (zvenku nepřístupné)
├── img/  audio/  video/
```

## Automatické nasazení přes GitHub (doporučeno)

Po každém pushi na `master` GitHub sám nahraje obsah `public/` na VEDOS přes FTP
(soubor `.github/workflows/deploy-vedos.yml`). Nahrávají se jen změněné soubory;
obsah uložený z adminu (`storage/`) se nikdy nepřepíše ani nesmaže.

Jednorázové nastavení na GitHubu — repozitář → **Settings → Secrets and variables → Actions**:

| Secret | Hodnota |
|---|---|
| `FTP_SERVER` | adresa FTP serveru z administrace VEDOS |
| `FTP_USERNAME` | FTP uživatel |
| `FTP_PASSWORD` | FTP heslo |
| `ADMIN_PASSWORD` | heslo do admin panelu (z něj se na serveru vytvoří `api/config.php`) |

Nepovinné **Variables** (záložka Variables vedle Secrets):

| Variable | Výchozí | Kdy změnit |
|---|---|---|
| `FTP_SERVER_DIR` | `www/` | když se složka webu na FTP jmenuje jinak (musí končit `/`) |
| `FTP_PROTOCOL` | `ftps` | když šifrované FTPS nefunguje, nastavte `ftp` |

Pak v záložce **Actions** spusťte „Nasazení na VEDOS“ → **Run workflow**
(nebo stačí další push). Průběh a případné chyby jsou vidět tamtéž.

Dokud není `FTP_SERVER` vyplněný, workflow se jen přeskočí — nic se nerozbije.

Ruční nahrání přes FTP níže pak není potřeba (kromě kroků 1, 4, 5 a 6).

## První nasazení (ruční přes FTP)

1. **Tarif:** stačí LowCost (web má ~100 MB). V administraci VEDOS u webhostingu
   nastavte **PHP 8.x**.
2. **Heslo do adminu:** zkopírujte `public/api/config.example.php` jako
   `public/api/config.php` a napište do něj silné heslo. Tento soubor se do gitu
   nedostane (je v `.gitignore`), nahrajte ho jen na hosting.
3. **Nahrání:** připojte se přes FTP/SFTP (údaje najdete v zákaznické administraci
   VEDOS, návod: https://kb.vedos.cz/wms-sftp/) — např. aplikací Cyberduck nebo
   FileZilla. Zapněte zobrazení skrytých souborů a nahrajte obsah `public/` do `www/`.
4. **Práva k zápisu:** složka `www/storage/` musí být zapisovatelná pro PHP
   (obvykle je, jinak nastavte práva 755 nebo 775).
5. **Doména:** v administraci VEDOS napojte doménu na webhosting a zapněte
   HTTPS certifikát (Let's Encrypt). Až HTTPS funguje, odkomentujte v
   `www/.htaccess` blok „Přesměrování na HTTPS“.
6. **Adresy webu:** v `public/index.html` (OG tagy, JSON-LD), `public/sitemap.xml`
   a `public/robots.txt` je zatím adresa `harlej-revival-site.onrender.com` —
   nahraďte ji novou doménou.

## Kontrola po nahrání

- `https://vase-domena.cz/` — web se zobrazí, v kontaktu je e-mail a telefon
- `https://vase-domena.cz/api/content.php` — vrátí JSON s obsahem
- `https://vase-domena.cz/api/config.php` — **musí** vrátit chybu 403/404
- `https://vase-domena.cz/storage/` — **musí** vrátit chybu 403
- `https://vase-domena.cz/admin.html` — přihlášení heslem z `config.php`, uložení změny

## Aktualizace webu později

Změny z admin panelu se ukládají na hosting do `www/storage/content.json`
(+ zálohy posledních 30 verzí v `www/storage/backups/`). Do GitHubu se nedostanou.

- Při nahrávání nové verze webu **nemažte a nepřepisujte složku `storage/`** —
  v gitu žádný `content.json` ve `storage/` není, takže běžné nahrání ji nepřepíše.
- Soubor `data/content.json` je jen výchozí obsah, který se použije, dokud se
  v adminu nic neuloží.

## Lokální spuštění

```bash
npm install
npm run dev
```

`server.js` obsluhuje stejné adresy (`/api/content.php`, `/api/verify.php`) jako
PHP na VEDOS, heslo bere z `.env` (`ADMIN_PASSWORD`). Změny z adminu ukládá do
složky `storage/` v kořeni projektu (mimo `public/`, takže se omylem nenahraje).
