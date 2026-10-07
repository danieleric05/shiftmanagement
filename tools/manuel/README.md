# Captures du mode d'emploi

Les images de `resources/manuel/images/` (données 100 % fictives) sont intégrées au PDF
« Mode d'emploi » (`App\Support\ManuelFigures`). Pour les régénérer, sur une base JETABLE
(jamais staging ni production) :

```
npm i --no-save playwright-core
createdb/CREATE DATABASE sm_manuel_demo
DB_DATABASE=sm_manuel_demo php artisan migrate --force
DB_DATABASE=sm_manuel_demo php artisan db:seed --class=ManuelDemoSeeder
DB_DATABASE=sm_manuel_demo DB_USERNAME=... DB_PASSWORD=... node tools/manuel/capturer.mjs
```

`ManuelDemoSeeder` refuse de s'exécuter en production ou avec une `APP_URL` de production.
Un fichier d'image manquant est simplement omis du PDF.
