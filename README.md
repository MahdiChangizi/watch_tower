## About Watch Tower
Watch Tower is a tool that scans the web for programs, subdomains, live hosts and HTTP fingerprints in a Postgres database.
If a program add a new subdomain it will send message to discord.
It is a small PHP service that uses the following tools:
- dnsx
- httpx
- subfinder
- nuclei
- chaos
- samoscout
- naabu
- crt.sh
- whois

## Automated setup

Clone the repository, make the helper executable, and run it:

```bash
chmod +x ./setup_watch_tower.sh
./setup_watch_tower.sh
```

The script installs all required Ubuntu packages (PHP, Apache, PostgreSQL, Composer), fetches Go + the reconnaissance binaries (dnsx, httpx, subfinder, nuclei, chaos, samoscout, waybackurls, naabu), installs Composer dependencies, creates the `watch` database, applies the SQL schemas, seeds a `.env`, and deploys the app to `/var/www/watch_tower` with an Apache virtual host pointing at `public/`.

Tweak the behavior with environment variables before running it:

- `APP_ROOT` (default `/var/www/watch_tower`)
- `SERVER_NAME` (default `watchtower.local`)
- `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, `DB_PORT`
- `WEBHOOK_URL`, `DISCORD_NOTIFICATIONS_ENABLED`, `PDCP_API_KEY`
- `GO_VERSION` (default `1.23.5`)
- `CONFIGURE_APACHE` (`true`/`false`)

Example for a remote host name and custom webhook:

```bash
SERVER_NAME=watch.example.com \
WEBHOOK_URL=https://discord.com/api/webhooks/... \
./setup_watch_tower.sh
```

## Nuclei
The Nuclei scan and find you, subdomain takover
```bash
watch_nuclei <program_name>
```

## URL Scanner
The URL scanner pivots from stored program scopes, pulls archived paths with `waybackurls`, and highlights endpoints that expose injectable parameters (default list: `common_param.txt`). Run it from the project root:

```bash
watch_url_scan <program_name> [--save] [--params=/path/list.txt] [--limit=1000] [--flag=-dates]
```

- `--save` writes matches to the new `urls` table so you can diff future runs.
- `--params` lets you swap in a custom wordlist (defaults to `common_param.txt`).
- `--limit` trims per-scope results to speed up large programs.
- `--flag` forwards extra switches to `waybackurls` (repeat the flag to send more than one).
- `--source` overrides the stored source label (default `waybackurls`).

Example output includes the scope hit and parameter names, making it easy to queue requests for fuzzing tools like `qsreplace`, Burp, or custom scripts.
