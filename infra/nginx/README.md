# Nginx — kalendarium.it

Copia di riferimento della configurazione nginx in produzione.
**Source of truth**: `/etc/nginx/sites-available/kalendarium` sulla VM.

## Sync server → repo

```bash
sudo cp /etc/nginx/sites-available/kalendarium infra/nginx/kalendarium.conf
sudo chown $(id -u):$(id -g) infra/nginx/kalendarium.conf
git add infra/nginx/kalendarium.conf
git commit -m "infra(nginx): sync kalendarium.conf"
```

## Apply repo → server

```bash
sudo cp infra/nginx/kalendarium.conf /etc/nginx/sites-available/kalendarium
sudo nginx -t && sudo systemctl reload nginx
```

## Note di design

- `location /api/` (con trailing slash) è **intenzionale**: previene il
  match accidentale di `/api-keys`, `/api-status` o altre rotte SPA che
  iniziano con `api-`. Senza lo slash, `/api-keys` veniva forward a
  Laravel e ritornava JSON 404 invece di servire `index.html` della SPA.
- `location /` con `try_files $uri $uri/ /index.html` è la SPA fallback:
  React Router gestisce qualunque rotta non statica.
- `index.html` è marcato `no-cache` per evitare che il browser serva
  bundle JS vecchi dopo un deploy.
