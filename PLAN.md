# Plán vývoje: Evidence majetku EKOSPOL (náhrada AssetTiger)

**Cíl:** Vlastní webová aplikace pro evidenci majetku běžící na `assets.ekospol.cz` (Wedos webhosting), která nahradí AssetTiger v rozsahu, v jakém jej reálně používáme. **Multi-organizační** (EKOSPOL, ZOO Tábor, možnost přidat další) s centrálním přehledem napříč organizacemi, s přihlašováním, česky, s přepínačem světlého/tmavého vzhledu a brandingem dle organizace — vizuálně laděno stejně jako náš „Skladový systém" (officeo.ekospol.cz).

**GitHub:** https://github.com/zoo-tabor/assets
**Datum sepsání:** 26. 8. 2026 (aktualizováno po upřesnění zadání)

---

## 0. Potvrzená rozhodnutí (ze zadání)

| Téma | Rozhodnutí |
|---|---|
| Role | Jediná role **Administrátor** — přístup mají jen správci majetku (admin = správce v jedné osobě). Sloupec `role` v DB necháme pro budoucnost, v UI se neřeší. |
| Organizace | **Přepínač organizací** v horní liště (EKOSPOL / ZOO Tábor / …), správa organizací v nastavení (přidání další organizace bez zásahu do kódu). Každá organizace má vlastní logo, barevné ladění, číselníky, osoby i majetek. |
| Centrální přehled | V přepínači navíc volba **„Všechny organizace"** — agregovaný dashboard a přehled majetku napříč organizacemi (viz kap. 2). |
| Vzhled | Podle Skladového systému: bílé karty, levý sidebar se sekcemi, akcentní barva dle organizace (EKOSPOL zelená, ZOO Tábor oranžová), login se stejným layoutem vč. výběru společnosti. **Přepínač light/dark.** |
| Custom pole | Ano — pevná IT pole (OS/Office) + modul „Vlastní pole" pro přidávání dalších polí z administrace bez zásahu do kódu (kap. 2). |
| Konfigurace | Všechna tajemství v **`.env`** (v `.gitignore`, na server se nahraje ručně 1×). V gitu je šablona **`.env_example`**. |
| Routing | **Pretty URL** (např. `/majetek/EKOSPOL-0000012`, `/vydej`, `/nastaveni/organizace`) — `.htaccess` přepisuje vše na `index.php` bez query parametru, router parsuje `REQUEST_URI`. Žádné `?route=…`. |
| `.htaccess` | Struktura podle fungujícího `.htaccess` aplikace VetApp Mobile (ověřená pravidla Wedos KB): HTTPS redirect, security hlavičky vč. CSP `upgrade-insecure-requests`, deny citlivých souborů, ochrana systémových adresářů s podmínkami `-f/-d` (aby URL routy procházely), cache statiky, fallback rewrite na `index.php [QSA,L]`. **Pozor: Wedos blokuje `php_flag`/`php_value` v `.htaccess`** — nic takového v něm nebude. |
| php.ini hostingu | **Neměníme** (multihosting sdílený s dalšími projekty) a `php_flag` v `.htaccess` Wedos blokuje → vše řeší kód za běhu: `ini_set('display_errors', '0')`, `ini_set('log_errors', '1')` + vlastní error handler logující do `/data/logs`, session cookie flagy přes `session_set_cookie_params()`. Direktivy typu `upload_max_filesize` za běhu změnit nelze — platí hodnoty hostingu (zjistí sonda ve Fázi 0). |
| Deploy secrets | GitHub Actions: **`FTP_SERVER`**, **`FTP_USER`**, **`FTP_PASS`**. |
| E-maily | `mail()` z Wedosu, odesílatel `ekospol@ekospol.cz` (SPF je v pořádku). |
| Migrace dat | Jen **aktuální stav** (majetek + kdo co má přiděleno), bez historie událostí z AssetTigeru. |
| Tag ID | Vlastní formát **`EKOSPOL-XXXXXXX`** — prefix nastavitelný per organizace (ZOO Tábor např. `ZOOTABOR-XXXXXXX`), automatická číselná řada, ruční přepis možný. |
| Jazyk | Čistě česky. |
| 2FA | Ne, stačí heslo (s rate-limitem a kvalitním hashem). |

---

## 1. Prostředí a omezení (Wedos)

| Parametr | Hodnota | Poznámka |
|---|---|---|
| PHP | 8.3 (Apache) | výchozí verze na hostingu |
| DB | MariaDB 10.11.18, server `md391.wedos.net` (TCP/IP), uživatel `a48423_vetapp` | znaková sada účtu `utf8` → tabulky zakládat explicitně jako `utf8mb4_czech_ci` |
| PHP extension | `mysqli` (potvrzeno v infu hostingu) | PDO ověřit sondou (Fáze 0); pokud není, jednotný DB wrapper nad mysqli |
| Limity PHP | max_execution_time 90 s, max_file_uploads 20, max_input_vars 10000 | limity uploadů (`upload_max_filesize`, `post_max_size`) dané hostingem — skutečné hodnoty zjistí sonda; fotky zmenšujeme přes GD, takže výchozí limity postačí |
| Shell na serveru | **NENÍ** | žádný composer/git/cron příkaz na serveru — vše se nahrává hotové přes FTP(S) |
| php.ini hostingu | **needitovatelné pro nás** (multihosting), `php_flag`/`php_value` v `.htaccess` Wedos **blokuje** | vše potřebné řeší kód přes `ini_set()` v bootstrapu — viz kap. 0 |
| phpMyAdmin | 3.5.8.2 | jen nouzová správa; běžnou správu DB řeší webový migrátor v aplikaci |

**Důsledky:**
1. **Bez frameworku a bez runtime závislostí.** Čisté PHP 8.3 (vlastní mini-router, DB wrapper, PHP šablony, vlastní ~30řádkový `.env` parser). Žádný `vendor/` v produkci. Vývojové nástroje (PHPUnit, PHPStan) jen lokálně, nedeployují se.
2. **Databázové migrace bez CLI:** migrační SQL soubory verzované v repu + webový spouštěč `/admin/migrate` (jen pro přihlášeného admina, evidence v tabulce `migrations`).
3. **Deploy přes GitHub Actions → FTPS** (`SamKirkland/FTP-Deploy-Action`), secrets `FTP_SERVER` / `FTP_USER` / `FTP_PASS`. `/data` a `.env` se při deployi nikdy nepřepisují.
4. **Plánované úlohy (e-maily):** Wedos cron, nebo externí trigger (GitHub Actions `schedule` → curl na tajnou URL `https://assets.ekospol.cz/cron/run?key=CRON_KEY`).
5. **HTTPS:** Let's Encrypt pro `assets.ekospol.cz` v administraci Wedosu; `.htaccess` vynucuje HTTPS redirect + CSP `upgrade-insecure-requests` od začátku (dle vzoru VetApp).
6. **FTP deploy míří přímo do zdrojové/webové složky projektu** — kořen repa = kořen webu na Wedosu. Žádný `/public` DocumentRoot (na Wedosu nefunguje spolehlivě); citlivé adresáře chrání `.htaccess`.

---

## 2. Rozsah funkcí

### Multi-organizace (jádro systému)
- Tabulka `organizations`: název, logo, akcentní barva, prefix tag ID, aktivní.
- **Každý datový záznam patří organizaci** (`organization_id` na majetku, osobách, číselnících, událostech…). Data organizací jsou striktně oddělená.
- Přepínač v horní liště (uloženo v session), výběr společnosti už na login stránce (jako u Skladového systému). Uživatelé mají přístup ke všem organizacím.
- Správa organizací v Nastavení: přidání/úprava, upload loga, volba barvy, prefix číselné řady.

### Centrální přehled („Všechny organizace")
- Speciální položka v přepínači organizací — **agregovaný pohled napříč všemi organizacemi**:
  - dashboard: součty aktiv a hodnot celkem i po organizacích, blížící se termíny (vrácení, záruky, údržba) ze všech organizací, poslední pohyby,
  - seznam majetku se sloupcem **Organizace** (fulltext, filtry vč. filtru na organizaci, export CSV/XLSX),
  - reporty přes všechny organizace (dle organizace / kategorie / stavu).
- Režim je **jen pro čtení** — úprava záznamu proklikem přepne do kontextu příslušné organizace (jistota, že nic nevznikne „bez organizace" a data se nepomíchají). Neutrální EKOSPOL branding.

### Majetek (dnes 40 aktiv u EKOSPOL, ~710 000 Kč)
- Pole: **Tag ID** `EKOSPOL-XXXXXXX` (automatická řada dle organizace, ruční přepis možný), Popis, Značka, Model, Sériové číslo, Datum nákupu, Cena (Kč), Dodavatel, Fotky.
- Zařazení: Lokace (0.–4. patro, Lipence, Stavba…), Kategorie, Oddělení — číselníky per organizace. (Úroveň „Sites" z AssetTigeru vypouštíme — roli společnosti přebírá organizace; agregaci řeší centrální přehled.)
- **Pevná IT pole:** OS Type (Linux / Win 10 / Win 11 Home / Win 11 Pro), OS SN, Office (2021/2024), Office SN, Poznámka.
- **Modul Vlastní pole:** v Nastavení lze přidat další pole (text / číslo / datum / výběr ze seznamu / ano-ne) bez zásahu do kódu. Definice v `custom_fields` (per organizace), hodnoty v `asset_custom_values`. Pole se automaticky objeví ve formuláři, detailu, filtrech i exportu.
- Stavy: K dispozici / Přiděleno / Rezervováno / Vyřazeno (+ interní dle pohybů).

### Pohyby majetku
- **Výdej / Vrácení (check-out/check-in)** na zaměstnance — hlavní workflow; datum, poznámka, volitelný termín vrácení.
- **Přesun** mezi lokacemi/odděleními, **Vyřazení**, **Rezervace**.
- Hromadné akce nad výběrem v seznamu. Každý pohyb → zápis do historie událostí (kdo, kdy, co).

### Moduly (v AssetTigeru zapnuté → přebíráme)
Údržba/opravy, záruky (expirace + upozornění), více fotek, dokumenty (PDF/Word/Excel k majetku), vazby rodič–potomek, inventura (audit). 
**Vynecháváme:** odpisy, smlouvy/licence, pojištění, funding, lease, mobilní aplikace. (Tisk štítku s QR/čárovým kódem tag ID — volitelné v2.)

### Osoby a uživatelé
- **Zaměstnanci** (40 u EKOSPOL): jméno, employee ID, pozice, e-mail, telefon, lokace, oddělení, poznámka; per organizace; cíl výdeje majetku, bez přístupu do aplikace.
- **Uživatelé:** admin účty (jméno, e-mail, heslo), správa v Nastavení. Jediná role Administrátor.

### Ostatní
- **Dashboard** (per organizace): počet aktiv, hodnota celkem a dle kategorií, přehled blížících se termínů, poslední pohyby. Karty + rychlé akce jako ve Skladovém systému.
- **Seznam majetku:** fulltext, filtry (kategorie/lokace/oddělení/stav/osoba), řazení, stránkování, volba sloupců (uloženo per uživatel), **export CSV + XLSX** (vlastní writer bez závislostí).
- **Import** majetku a osob z CSV (i pro migraci z AssetTigeru).
- **Reporty:** dle stavu/kategorie/oddělení/zaměstnance, výdejový report, historie pohybů, záruky, údržba — vše s exportem.
- **E-mailová upozornění:** termín vrácení, expirace záruky, plánovaná údržba — denní cron, odesílatel `ekospol@ekospol.cz`.

---

## 3. Architektura

Plochá struktura (kořen repa = kořen webu na Wedosu, FTP deploy míří přímo sem; žádný `/public` DocumentRoot — na Wedosu nefunguje spolehlivě):

```
/ (kořen repa = kořen webu na Wedosu)
  index.php          ← front controller (jediný vstupní bod, router parsuje REQUEST_URI)
  .htaccess          ← podle vzoru VetApp Mobile (pravidla Wedos KB, viz níže)
  .env               ← tajemství (v .gitignore, NEdeployuje se; na serveru založit ručně 1×)
  .env_example       ← šablona v gitu
  /assets            ← CSS/JS/ikony (lokální, žádné CDN)
  /app
    /Core            ← Router, Env, Db, Auth, Csrf, View, Mailer, Validator, Org (kontext organizace)
    /Controllers
    /Models          ← třídy nad tabulkami (čisté SQL, žádné ORM)
    /Views           ← PHP šablony (layout + stránky)
  /migrations        ← 001_init.sql, 002_…  (spouští webový migrátor)
  /data              ← uploady (fotky, dokumenty, loga), logy – MIMO git i deploy
  /tools             ← lokální skripty (převod AssetTiger exportu…) – NEdeployuje se
  /.github/workflows/deploy.yml
```

### Routing — pretty URL (bez `?route=`)

- `.htaccess` posílá všechny neexistující cesty na `index.php` **bez query parametru**: `RewriteCond %{REQUEST_FILENAME} !-f` + `!-d` → `RewriteRule ^(.*)$ index.php [QSA,L]`.
- Router v `index.php` čte cestu z `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` a mapuje ji na controllery deklarativní tabulkou rout s parametry, např.:
  - `GET  /` → dashboard, `GET /prehled` → centrální přehled,
  - `GET  /majetek`, `GET/POST /majetek/novy`, `GET /majetek/{id}`, `GET/POST /majetek/{id}/upravit`,
  - `GET/POST /vydej`, `/vraceni`, `/presun`, `/vyrazeni`, `/rezervace`,
  - `GET /inventury`, `POST /inventury/nova`, `GET /inventury/{id}`,
  - `GET /reporty/...`, `GET /nastaveni/organizace|uzivatele|ciselniky|vlastni-pole`,
  - `GET /soubor/{typ}/{id}` (chráněný výdej uploadů), `GET /cron/run` (s klíčem), `GET/POST /admin/migrate`.
- Původní query stringy zůstávají funkční díky `[QSA]` (stránkování, filtry: `/majetek?kategorie=3&stav=vydano`).
- Odkazy v aplikaci generuje helper `url('/majetek/5')` — jediné místo, kde se skládá base URL z `.env` (`APP_URL`).

### `.htaccess` (převzatá struktura z VetApp Mobile — na Wedosu ověřeně funkční)

1. `RewriteEngine On` + **HTTPS redirect** (aktivní od začátku),
2. security hlavičky: CSP `upgrade-insecure-requests`, `X-Frame-Options SAMEORIGIN`, `X-Content-Type-Options nosniff`, `Referrer-Policy strict-origin-when-cross-origin`,
3. `FilesMatch` deny: `.env`, `.json`, `.htaccess`, `.ini`, `.log`, `.sh`, `.inc`, `.bak`, `.sql`, `.md`, `.txt` + `composer.*`, `.git*`,
4. `Options -Indexes`,
5. ochrana systémových adresářů `app/`, `migrations/`, `data/`, `tools/` — s podmínkami `RewriteCond %{REQUEST_FILENAME} -f [OR] -d`, aby **URL routy začínající stejně (např. `/app/...`) dál procházely na `index.php`** a blokoval se jen přímý přístup k existujícím souborům,
6. `FilesMatch` allow pro statiku (css/js/obrázky/fonty/pdf) + `Cache-Control public, max-age=31536000, immutable`,
7. fallback routing: `RewriteCond !-f`, `!-d` → `RewriteRule ^(.*)$ index.php [QSA,L]`.

**ŽÁDNÉ `php_flag`/`php_value`** — Wedos je blokuje. Ekvivalenty řeší bootstrap v PHP:
`ini_set('display_errors', '0')` (v development režimu dle `.env` naopak '1'), `ini_set('log_errors', '1')` + `set_error_handler`/`set_exception_handler` logující do `/data/logs/`, `session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Lax'])`, `mb_internal_encoding('UTF-8')`, hlavička `Content-Type: text/html; charset=utf-8` z aplikace.

**Konfigurace (`.env`):** vlastní jednoduchý parser v `/app/Core/Env.php` (bez závislostí). Šablona `.env_example` je v repu — obsahuje APP (prostředí, URL, APP_KEY), DB (host/name/user/pass), MAIL (odesílatel) a CRON_KEY. Skutečný `.env` je v `.gitignore` a v deploy `exclude` (a pro jistotu ho kryje i `FilesMatch` deny).

- **Frontend:** serverem renderované PHP šablony, vlastní lehké CSS na **CSS proměnných**:
  - `--accent` a spol. plněné z nastavení aktivní organizace (zelená/oranžová…), neutrální varianta pro centrální přehled,
  - **light/dark:** `data-theme` na `<html>`, přepínač v horní liště, volba per uživatel (+ výchozí dle `prefers-color-scheme`),
  - layout dle Skladového systému: levý sidebar se sekcemi (MAJETEK, POHYBY, INVENTURA, REPORTY, NASTAVENÍ), karty, horní lišta s přepínačem organizace, zvonečkem a menu uživatele; login s výběrem společnosti.
  - Žádný build krok, žádný npm.
- **DB vrstva:** preferovaně PDO (ověří sonda), wrapper s prepared statements jako jediné místo dotazů; dotazy na data organizace povinně filtrují `organization_id` (helper v Db vrstvě; centrální přehled používá explicitní agregační metody).
- **Uploads:** `/data/photos/{org}/{asset_id}/`, `/data/docs/…`, `/data/logos/{org}/`; výdej přes PHP endpoint s kontrolou přihlášení; kontrola MIME, zmenšování fotek přes GD.
- **Bezpečnost:** `password_hash()`/`password_verify()`, CSRF tokeny všude, escapování výstupu, rate-limit přihlášení, `session_regenerate_id` po loginu.

### Návrh databázového schématu (v1)

- `organizations` (id, name, logo_file, accent_color, tag_prefix, tag_next_number, active)
- `users` (id, name, email, password_hash, role, theme_pref, active, last_login_at)
- `persons` (id, organization_id, name, employee_id, title, email, phone, location_id, department_id, notes, active)
- `locations`, `categories`, `departments` (číselníky, vše s `organization_id`)
- `assets` (id, organization_id, tag_id UNIQUE, description, brand, model, serial_no, purchase_date, cost, purchased_from, location_id, category_id, department_id, status, os_type, os_sn, office, office_sn, note, created_by, created_at, updated_at)
- `custom_fields` (id, organization_id, name, type ENUM(text,number,date,select,bool), options JSON, sort, active)
- `asset_custom_values` (asset_id, custom_field_id, value)
- `asset_events` (id, asset_id, type ENUM(create, edit, checkout, checkin, move, dispose, reserve, maintenance, audit…), person_id NULL, user_id, event_date, due_date NULL, note, data JSON)
- `asset_photos` (id, asset_id, filename, is_primary)
- `asset_documents` (id, asset_id, filename, original_name, uploaded_by)
- `asset_links` (parent_asset_id, child_asset_id)
- `warranties` (id, asset_id, expires_at, notes)
- `maintenances` (id, asset_id, title, status, due_date, completed_at, cost, notes)
- `audits` (id, organization_id, name, location_id NULL, created_at, closed_at) + `audit_items` (audit_id, asset_id, status found/missing, checked_at, checked_by)
- `migrations`, `login_attempts`

Vše `InnoDB`, `utf8mb4_czech_ci`, FK s `ON DELETE RESTRICT` (číselníky) / `CASCADE` (podřízené záznamy majetku).

---

## 4. Deploy pipeline (GitHub Actions → Wedos FTPS)

1. Repo `zoo-tabor/assets`, větev `main` = produkce. Vývoj ve feature větvích + PR.
2. Workflow `deploy.yml`: push do `main` → checkout → PHP lint (`php -l`) → `SamKirkland/FTP-Deploy-Action` přes **FTPS**.
   - Secrets: **`FTP_SERVER`**, **`FTP_USER`**, **`FTP_PASS`**.
   - `exclude`: `.git*`, `/tools`, `/tests`, `.env`, `.env_example` (na server nepatří ani šablona); **nikdy nemazat** `/data` a `.env` na serveru (`dangerous-clean-slate: false`, state soubor pro inkrementální sync).
3. Po deployi s novou migrací: admin otevře `/admin/migrate` a potvrdí (aplikace sama zobrazí banner „čeká migrace").
4. Rollback = revert commit + push.

**DNS/hosting příprava:** subdoména `assets.ekospol.cz` na Wedos hostingu, Let's Encrypt; FTP účet pipeline míří přímo do webové složky projektu (kořen repa = kořen webu). HTTPS redirect v `.htaccess` je aktivní od začátku.

---

## 5. Fáze vývoje

### Fáze 0 – Příprava a ověření prostředí (0,5–1 den)
- [ ] Struktura repa, `README`, `.gitignore` (`.env`, `/data`, `/tools/migration-data`), `.env_example`, `.htaccess` dle vzoru.
- [ ] Sonda `probe.php` na hostingu: `pdo_mysql`, `mail()`, reálné hodnoty `upload_max_filesize`/`post_max_size`/`display_errors`, funkčnost `ini_set()` pro display/log errors, průchod pretty URL rewrite (test `.htaccess`), zápis na disk, connect na MariaDB. Po ověření smazat.
- [ ] Ověřit: cron ve Wedos administraci, FTPS z GitHub Actions (testovací deploy „hello world" přímo do webové složky projektu).
- [ ] HTTPS (Let's Encrypt) pro `assets.ekospol.cz` — redirect v `.htaccess` je aktivní od začátku.
- [ ] Lokální prostředí: Docker (`php:8.3-apache` + `mariadb:10.11`), `docker-compose.yml` v repu.
- [ ] Plný export z AssetTigeru (Tools → Export: assets vč. custom polí, persons, číselníky) + stažení fotek. Do `/tools/migration-data` (mimo git).

### Fáze 1 – Kostra, auth, organizace, deploy (2–3 dny)
- [ ] **Pretty URL router** (parsování `REQUEST_URI`, deklarativní tabulka rout s `{id}` parametry, helper `url()`), `.env` loader, layout dle Skladového systému (sidebar, horní lišta), CSS proměnné, **light/dark přepínač**, error handling + logování do `/data/logs`.
- [ ] Bootstrap hardening (`ini_set` display/log errors dle `APP_ENV`, `session_set_cookie_params` — httponly, secure, samesite).
- [ ] DB wrapper, webový migrátor `/admin/migrate` + `001_init.sql`.
- [ ] Login (s výběrem společnosti), CSRF, rate-limit; správa uživatelů (CRUD, změna hesla, deaktivace).
- [ ] **Organizace:** tabulka, správa v Nastavení (logo, barva, tag prefix), přepínač v liště, org-kontext v celé aplikaci.
- [ ] Funkční pipeline: push do `main` → běží na `assets.ekospol.cz`.
- **Milník: na produkci se lze přihlásit a přepínat mezi EKOSPOL a ZOO Tábor.**

### Fáze 2 – Číselníky a zaměstnanci (1–2 dny)
- [ ] CRUD: lokace, kategorie, oddělení (per organizace, ochrana proti smazání používané položky).
- [ ] CRUD zaměstnanců + vyhledávání; import z CSV.
- **Milník: číselníky a zaměstnanci obou organizací v systému.**

### Fáze 3 – Majetek (3–5 dní)
- [ ] CRUD majetku se všemi poli, automatické Tag ID `EKOSPOL-XXXXXXX` dle organizace.
- [ ] **Modul Vlastní pole** (definice v Nastavení, dynamické vykreslení ve formuláři/detailu/filtrech/exportu).
- [ ] Seznam: vyhledávání, filtry, řazení, stránkování, volba sloupců per uživatel, export CSV + XLSX.
- [ ] Fotky (více, hlavní, náhledy přes GD), dokumenty.
- [ ] Detail se záložkami: Detaily, Historie, Fotky, Dokumenty, Záruka, Vazby, Údržba.
- [ ] Import z CSV + převodní skript v `/tools` (AssetTiger export → náš formát; stará tag ID do vlastního pole pro dohledatelnost).
- **Milník: kompletní evidence, zkušební migrace dat EKOSPOL.**

### Fáze 4 – Pohyby, dashboardy (2–3 dny)
- [ ] Výdej (na zaměstnance, s termínem), vrácení, přesun, vyřazení, rezervace — zápis do `asset_events`, změny stavů, hromadné akce.
- [ ] Historie u majetku + globální historie pohybů.
- [ ] Dashboard organizace s kartami a rychlými akcemi (jako Skladový systém), hodnota dle kategorií (ruční SVG graf), blížící se termíny.
- [ ] **Centrální přehled „Všechny organizace":** agregovaný dashboard, seznam majetku se sloupcem organizace, exporty; jen pro čtení, proklik přepíná do kontextu organizace.
- **Milník: plnohodnotná náhrada denního workflow AssetTigeru + centrální pohled.**

### Fáze 5 – Rozšiřující moduly (3–4 dny)
- [ ] Záruky, údržba, vazby rodič–potomek, inventura (checklist dle lokace, found/missing, uzavření inventury).
- [ ] E-maily: denní cron (termíny vrácení, záruky, údržba), odesílatel `ekospol@ekospol.cz`, tajný `CRON_KEY` z `.env`.
- [ ] Reporty (dle oddělení / zaměstnance / položky / stavu + pohyby; per organizace i centrálně) s exportem.
- **Milník: paritní rozsah s naším využitím AssetTigeru.**

### Fáze 6 – Migrace ostrých dat a přepnutí (1–2 dny)
- [ ] Finální export z AssetTigeru → import **aktuálního stavu** (majetek, fotky, dokumenty, aktuální přidělení osobám jako výchozí výdejové události).
- [ ] Kontrola proti AssetTigeru (počty, hodnoty, přiřazení), týden souběhu.
- [ ] Zálohy: Wedos denní zálohy + `/admin/backup` (SQL dump ke stažení / na e-mail).
- [ ] Krátká nápověda v aplikaci. AssetTiger zrušit až po ověření.

**Celkem odhad: ~13–21 pracovních dní čistého času, nasaditelné po fázích.**

---

## 6. Rizika a jejich řešení

| Riziko | Řešení |
|---|---|
| Na hostingu chybí `pdo_mysql` | DB wrapper s fallbackem na mysqli (ověří Fáze 0) |
| `display_errors` hostingu je zapnuté a `ini_set` nechytí parse error (chyba se syntaxí by se vypsala návštěvníkovi) | CI krok `php -l` nad všemi soubory před každým deployem; `index.php` drží jen minimální bootstrap v try/catch |
| Wedos nemá cron | GitHub Actions `schedule` → curl na tajnou URL s `CRON_KEY` |
| Limit uploadů na hostingu (nelze měnit za běhu ani přes `.htaccess`) | sonda zjistí reálné hodnoty; fotky zmenšovat (GD), velikost dokumentů hlídat v aplikaci pod limitem hostingu |
| `mail()` ve spamu | odesílatel `ekospol@ekospol.cz` (SPF ověřeno), Reply-To, rozumná frekvence |
| Únik dat mezi organizacemi | povinný org-filtr v DB vrstvě + testy na izolaci; centrální přehled jen pro čtení přes explicitní agregační metody |
| Ztráta dat | migrátor nikdy nemaže, deploy nesahá na `/data` a `.env`, pravidelné dumpy |
