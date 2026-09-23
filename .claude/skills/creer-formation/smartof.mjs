// Appel direct de l'API SmartOF, hors Symfony.
//
//   node .claude/skills/creer-formation/smartof.mjs <endpoint> [payload.json]
//   ex. node .claude/skills/creer-formation/smartof.mjs /api/produit/list > liste.json
//
// Identifiants : SMARTOF_EMAIL / SMARTOF_PASSWORD / SMARTOF_API_KEY de .env.local.
// Sortie : le code HTTP sur la première ligne, puis le corps de la réponse.
// Sous Git Bash, préfixer par MSYS_NO_PATHCONV=1 : sinon « /api/... » est
// réécrit en chemin Windows et l'API répond 404.

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const racine = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

const env = Object.fromEntries(
    readFileSync(resolve(racine, '.env.local'), 'utf8')
        .split(/\r?\n/)
        .filter((ligne) => /^SMARTOF_/.test(ligne))
        .map((ligne) => {
            const i = ligne.indexOf('=');
            return [ligne.slice(0, i), ligne.slice(i + 1).replace(/^["']|["']$/g, '')];
        }),
);

const [endpoint, fichier] = process.argv.slice(2);

if (!endpoint?.startsWith('/api/')) {
    console.error('Usage : node smartof.mjs /api/<ressource>/<action> [payload.json]');
    process.exit(1);
}

const auth = await (await fetch(
    'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' + env.SMARTOF_API_KEY,
    {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: env.SMARTOF_EMAIL, password: env.SMARTOF_PASSWORD, returnSecureToken: true }),
    },
)).json();

if (!auth.idToken) {
    console.error('Authentification SmartOF refusée :', auth.error?.message ?? auth);
    process.exit(1);
}

const reponse = await fetch(
    'https://europe-west3-algorythme-formation-mobileo.cloudfunctions.net/external' + endpoint,
    {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + auth.idToken },
        body: fichier ? readFileSync(fichier, 'utf8') : '{}',
    },
);

console.log(reponse.status);
console.log(await reponse.text());
