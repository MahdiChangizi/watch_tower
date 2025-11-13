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
