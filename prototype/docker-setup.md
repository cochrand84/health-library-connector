# Mayo Library PHP Prototype — Docker Setup

## 1. Create your project folder

```bash
mkdir ~/mayo-library
cd ~/mayo-library
```

## 2. Drop the PHP file in

Copy `mayo-library.php` into `~/mayo-library/`. Before continuing, open it and fill in your credentials:

```bash
nano mayo-library.php
```

Find these two lines near the top and replace the placeholder values:

```php
define('MAYO_API_KEY', 'YOUR_API_KEY_HERE');
define('MAYO_TRCKR',   'YOUR_TRCKR_VALUE_HERE');
```

Save and close (`Ctrl+X`, `Y`, `Enter`).

---

## 3. Create the Dockerfile

```bash
nano Dockerfile
```

Paste this content:

```dockerfile
FROM php:8.3-apache

# Enable mod_rewrite (not strictly needed here but good habit)
RUN a2enmod rewrite

# Copy the PHP file into Apache's web root
COPY mayo-library.php /var/www/html/index.php

# Apache listens on 80 inside the container
EXPOSE 80
```

Save and close.

---

## 4. Build the image

```bash
docker build -t mayo-library .
```

---

## 5. Run the container

```bash
docker run -d \
  --name mayo-library \
  -p 8080:80 \
  mayo-library
```

The site will be available at:
- **On the server itself:** http://localhost:8080
- **From another machine on your network:** http://YOUR-SERVER-IP:8080

To find your server's local IP:
```bash
hostname -I | awk '{print $1}'
```

---

## 6. Test the three modes

| Mode | URL |
|------|-----|
| Home / search form | `http://YOUR-SERVER-IP:8080/` |
| Search results | `http://YOUR-SERVER-IP:8080/?q=diabetes` |
| Article display | `http://YOUR-SERVER-IP:8080/?mayo-id=CON-20372695` |

---

## 7. Iterating on the PHP file

Since the file is baked into the image, any edit requires a rebuild. This is a two-second operation:

```bash
# Edit the file
nano mayo-library.php

# Stop and remove the old container
docker stop mayo-library && docker rm mayo-library

# Rebuild and rerun (one line)
docker build -t mayo-library . && docker run -d --name mayo-library -p 8080:80 mayo-library
```

**Tip:** If you'd rather skip the rebuild step during heavy editing, use a bind mount instead:

```bash
docker run -d \
  --name mayo-library \
  -p 8080:80 \
  -v $(pwd)/mayo-library.php:/var/www/html/index.php \
  php:8.3-apache
```

With a bind mount, edits to `mayo-library.php` on disk are reflected immediately — just reload the browser. No rebuild needed.

---

## 8. Useful commands

```bash
# View live logs (good for debugging API call errors)
docker logs -f mayo-library

# Stop the container
docker stop mayo-library

# Start it again without rebuilding
docker start mayo-library

# Remove container and image when done
docker stop mayo-library && docker rm mayo-library && docker rmi mayo-library
```

---

## 9. Check the cache folder

The PHP file caches API responses in a `cache/` subfolder relative to the script. Inside the container that's `/var/www/html/cache/`. Apache runs as `www-data` so the cache writes should work automatically. If you see cache permission errors in the logs:

```bash
docker exec mayo-library chmod 777 /var/www/html/cache
```

---

## When you're done

```bash
docker stop mayo-library && docker rm mayo-library
```

The image stays on disk (`docker images`). Remove it too if you want:

```bash
docker rmi mayo-library
```
