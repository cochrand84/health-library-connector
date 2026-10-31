# Prototype (standalone PHP)

> **Reference only — do not deploy this publicly.** It was a local test
> harness: its file cache lives inside the web root and it has none of the
> plugin's hardening (escaping, rate limiting, settings). Use the plugin.

This is the original single-file proof of concept that the WordPress plugin
grew out of. It is kept here for reference; new work happens in the plugin.

```bash
cd prototype
cp html/config.sample.php html/config.php   # add your API key and trckr
mkdir -p html/cache
docker compose up -d
```

| URL | What it shows |
|-----|---------------|
| `http://localhost:8090/` | Landing page with featured articles |
| `http://localhost:8090/?q=diabetes` | Search results |
| `http://localhost:8090/?mayo-id=CON-20372695` | Article |
| `http://localhost:8090/index-cta.php` | Same, with the specialist sidebar |

`doctors.php` maps Mayo `mcvId` category IDs to specialties and contains
**placeholder** providers only.
