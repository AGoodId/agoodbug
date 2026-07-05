# AGoodBug

WordPress-plugin för visuell feedback/buggrapportering med skärmbildsfångst.

## Release

- Versionsbump i `agoodbug.php` (både headern och `AGOODBUG_VERSION`) och push till `main` triggar `.github/workflows/release.yml`, som bygger zip, taggar `vX.Y.Z` och skapar GitHub-releasen.
- Sajter uppdaterar via plugin-update-checker (PUC v5) mot GitHub-releases; release-zippen är update-källan (`enableReleaseAssets`).
- Push till main = release-gate: kräver mänskligt OK.

## AGoodApp SSOT

- Projekt: AGoodBug (AGI0101) — `85e8c69d-d3c8-4bcd-9310-f886b8a63ced`
- https://www.agoodsport.se/projects/85e8c69d-d3c8-4bcd-9310-f886b8a63ced

## Verifiering

- `php -l` på ändrade PHP-filer.
- Repot saknar testramverk — beteendeändringar smoke-testas med fristående PHP-script med WP-stubbar (se mönstret i tidigare sessioner).
