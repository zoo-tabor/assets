# Evidence majetku EKOSPOL

Vlastní webová aplikace pro evidenci majetku (náhrada AssetTiger) běžící na
`https://assets.ekospol.cz` (Wedos webhosting). Multi-organizační (EKOSPOL,
ZOO Tábor, …), česky, light/dark, branding dle organizace.

Podrobný plán vývoje: [PLAN.md](PLAN.md)

## Technologie

- Čisté PHP 8.3, žádný framework, žádné runtime závislosti (na Wedosu není shell ani composer)
- MariaDB 10.11 (Wedos), PDO s fallbackem na mysqli
- Serverem renderované PHP šablony, vlastní CSS (CSS proměnné, light/dark), žádný build krok
- Pretty URL routing přes `.htaccess` → `index.php`

## Struktura

```
index.php            front controller (router)
.htaccess            HTTPS redirect, security hlavičky, rewrite na index.php
.env                 tajemství (NENÍ v gitu, na server se nahrává ručně 1×)
.env_example         šablona konfigurace
/assets              CSS/JS (lokální, žádné CDN)
/app/Core            Router, Env, Db, Auth, Csrf, View, Org, Migrator…
/app/Controllers
/app/Models
/app/Views           PHP šablony
/migrations          verzované SQL migrace (spouští webový migrátor /admin/migrate)
/data                uploady a logy — MIMO git i deploy
/tools               lokální skripty — NEdeployuje se
```

## Deploy

Push do `main` → GitHub Actions (`.github/workflows/deploy.yml`):
PHP lint → FTPS deploy (`SamKirkland/FTP-Deploy-Action`) přímo do webové složky.
Secrets: `FTP_SERVER`, `FTP_USER`, `FTP_PASS`.
Deploy nikdy nesahá na `/data` a `.env` na serveru.

Po deployi s novou migrací: přihlášený admin otevře `/admin/migrate` a potvrdí
(aplikace sama zobrazí banner „čeká migrace“).

## První spuštění

1. Na server nahrát ručně `.env` (dle `.env_example`).
2. Otevřít `/admin/migrate` — v setup režimu (prázdná DB) proběhne bez přihlášení,
   založí schéma, organizace EKOSPOL a ZOO Tábor a prvního uživatele
   `admin` / `admin123` (heslo ihned změňte v Nastavení).
