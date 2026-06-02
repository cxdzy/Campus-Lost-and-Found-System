# Dokploy / Deploy integration instructions

This project includes a recommended post-deploy script and a Dokploy hook to automate server-side deployment tasks.

Files added
- `scripts/deploy.sh` — main deploy script (pull, composer install, npm build, storage:link, migrate, cache clear, optional service reload).
- `.dokploy/hooks/post-deploy.sh` — Dokploy hook that invokes `scripts/deploy.sh` after Dokploy places the code on the server.

Important: DO NOT PUT SECRETS IN THE DOCKERFILE
- Use Dokploy (or your hosting) environment variables / secret store to set sensitive values (APP_KEY, DB_PASSWORD, MAIL_PASSWORD, REDIS_PASSWORD, VITE_APP_NAME) at runtime.
- Ensure that VITE_APP_NAME (if you want it embedded in built frontend) is available during the `npm run build` step — Dokploy must pass it into the build environment.

How to enable the Dokploy hook
1. Ensure the `.dokploy/hooks/post-deploy.sh` file is present in the repository (it is added in this commit).
2. On your Dokploy project settings, enable repository hooks (Dokploy runs scripts in `.dokploy/hooks/*` by default — consult Dokploy docs if you use a custom hook path).
3. Make sure the server user that runs Dokploy has permission to execute the script and run the commands (composer, npm, php artisan). If `sudo` is required to reload services, configure passwordless sudo for the deploy user for those commands.

Environment variables
- Add your secrets through Dokploy's environment / config interface (do not commit .env to the repo):
  - APP_ENV=production
  - APP_KEY (secret)
  - APP_URL=https://your.domain
  - DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD (secret)
  - MAIL_*, REDIS_PASSWORD (secret)
  - VITE_APP_NAME (optional: set to "Campus Lost Found" or empty)

After deploy
- If you have existing items that store remote image URLs, run on the server:
  php artisan items:fetch-remote-images --limit=200

Notes and caveats
- If your server does not have Node/npm, consider doing the frontend build in CI and deploying built `public/build` artifacts instead.
- If Dokploy builds the image in Docker, ensure build-time envs (VITE_APP_NAME) are passed into the build step; don't embed secrets — use BuildKit secrets if needed.

If you'd like, I can adjust this script for your exact server paths, PHP-FPM version, or Dokploy settings — tell me which PHP version and whether you prefer the build to run on CI or on the server.
