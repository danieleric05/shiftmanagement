#!/usr/bin/env node
/**
 * Captures d'écran du mode d'emploi (données 100 % fictives).
 *
 * Usage (depuis la racine du projet) :
 *   npm i --no-save playwright-core
 *   DB_DATABASE=sm_manuel_demo DB_USERNAME=... DB_PASSWORD=... node tools/manuel/capturer.mjs [--only=a,b]
 *
 * Prérequis : base JETABLE migrée et peuplée par ManuelDemoSeeder (voir README.md).
 * Le script lance son propre serveur PHP sur le port 8811 (variables DB_*
 * surchargées pour ce serveur seulement, fichier public/hot ignoré), se
 * connecte par le formulaire avec chaque rôle de démonstration et enregistre
 * des JPEG 1280 px en mode clair dans resources/manuel/images/.
 * Toute page qui afficherait une adresse e-mail hors @exemple.test, ou un
 * mot de passe/token, est rejetée (capture supprimée, code de sortie 1).
 */
import { chromium } from 'playwright-core';
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readdirSync, rmSync, statSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SORTIE = join(RACINE, 'resources', 'manuel', 'images');
const PORT = process.env.MANUEL_PORT ?? '8811';
const BASE = `http://127.0.0.1:${PORT}`;
const MOT_DE_PASSE = 'demo-password';
const CHROME = process.env.CHROME_PATH ?? '/usr/bin/google-chrome';
const QUALITE = 75;
const seulement = (process.argv.find((a) => a.startsWith('--only=')) ?? '').slice(7).split(',').filter(Boolean);

if (!process.env.DB_DATABASE || /^(shiftmanagement|c1appstat)/.test(process.env.DB_DATABASE)) {
    console.error('Refus : définissez DB_DATABASE sur une base jetable de démonstration (ex. sm_manuel_demo).');
    process.exit(2);
}

const COMPTES = {
    superAdmin: 'super.admin@exemple.test',
    conseil: 'conseil@exemple.test',
    secretaire: 'secretaire@exemple.test',
    coordonnateur: 'coordonnateur@exemple.test',
    autres: 'consultation@exemple.test',
};

// --- Serveur PHP local (docroot temporaire : jamais de public/hot) -----------
function demarrerServeur() {
    const doc = mkdtempSync(join(tmpdir(), 'sm-manuel-'));
    const pub = join(doc, 'public');
    mkdirSync(pub);
    for (const f of readdirSync(join(RACINE, 'public'))) {
        if (f !== 'hot' && f !== 'index.php') symlinkSync(join(RACINE, 'public', f), join(pub, f));
    }
    writeFileSync(join(pub, 'index.php'), `<?php
use Illuminate\\Http\\Request;
define('LARAVEL_START', microtime(true));
require '${RACINE}/vendor/autoload.php';
$app = require_once '${RACINE}/bootstrap/app.php';
$app->booted(fn () => \\Illuminate\\Support\\Facades\\Vite::useHotFile('${doc}/aucun-hot'));
$app->handleRequest(Request::capture());
`);
    const proc = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', pub], {
        cwd: RACINE,
        env: { ...process.env, APP_ENV: 'local', APP_URL: BASE, APP_DEBUG: 'false', SESSION_COOKIE: 'sm_manuel_session', PHP_CLI_SERVER_WORKERS: '4' },
        stdio: 'ignore',
    });
    return { proc, doc };
}

async function attendreServeur() {
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(`${BASE}/login`)).ok) return; } catch { /* pas encore prêt */ }
        await new Promise((r) => setTimeout(r, 500));
    }
    throw new Error('Serveur PHP injoignable');
}

// --- Contrôle de confidentialité ----------------------------------------------
const MOTIFS_INTERDITS = [
    [/[\w.+-]+@(?!exemple\.test\b)[\w-]+\.[\w.-]+/i, 'adresse e-mail hors @exemple.test'],
    [/daertech|gmail\.com|temple\.ci/i, 'domaine réel'],
    [/token|_token|Bearer /i, 'jeton'],
];
async function verifier(page) {
    const texte = await page.evaluate(() => document.body.innerText + '\n' + [...document.querySelectorAll('input,textarea')].map((e) => (e.type === 'password' ? '' : e.value)).join('\n'));
    for (const [motif, nom] of MOTIFS_INTERDITS) {
        if (motif.test(texte)) throw new Error(`contenu interdit (${nom}) : ${texte.match(motif)[0]}`);
    }
}

// --- Aides --------------------------------------------------------------------
const echecs = [];
const produits = [];
async function connexion(browser, email, { largeur = 1280, hauteur = 800 } = {}) {
    const ctx = await browser.newContext({ viewport: { width: largeur, height: hauteur }, colorScheme: 'light', locale: 'fr-FR', deviceScaleFactor: 1 });
    await ctx.addInitScript(() => { try { localStorage.setItem('theme', 'light'); } catch { /* ignoré */ } });
    const page = await ctx.newPage();
    if (process.env.MANUEL_DEBUG) {
        page.on('console', (m) => console.log('[console]', m.text().slice(0, 200)));
        page.on('response', (r) => { if (r.request().method() !== 'GET' || r.status() >= 300) console.log('[http]', r.status(), r.url()); });
    }
    await page.goto(`${BASE}/login`);
    await calmer(page);
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', MOT_DE_PASSE);
    try {
        await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }), page.getByRole('button', { name: 'Se connecter' }).click()]);
    } catch (e) {
        if (process.env.MANUEL_DEBUG) await page.screenshot({ path: '/tmp/sm_login_fail.png' });
        const texte = (await page.innerText('body').catch(() => '')).slice(0, 300).replace(/\s+/g, ' ');
        throw new Error(`connexion impossible pour ${email} : ${texte}`);
    }
    await calmer(page);
    return { ctx, page };
}
async function calmer(page, ms = 500) {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(ms);
}
async function capture(page, nom, options = {}) {
    if (seulement.length && !seulement.includes(nom)) return;
    const fichier = join(SORTIE, `${nom}.jpg`);
    const { hauteur = null, viewport = null, clip = null } = typeof options === 'number' ? { hauteur: options } : options;
    const initial = page.viewportSize();
    try {
        if (viewport) await page.setViewportSize({ width: initial.width, height: viewport });
        await calmer(page, 300);
        await verifier(page);
        // Les emojis du texte s'affichent en carrés vides sans police emoji : on les retire de la page (cosmétique, capture seulement).
        await page.evaluate(() => {
            const marcheur = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            for (let n = marcheur.nextNode(); n; n = marcheur.nextNode()) {
                n.nodeValue = n.nodeValue.replace(/[\p{Extended_Pictographic}\uFE0F]/gu, '').replace(/\s+,/g, ',');
            }
        });
        await page.mouse.move(0, 0);
        const zone = clip ?? (hauteur ? { x: 0, y: 0, width: page.viewportSize().width, height: hauteur } : undefined);
        await page.screenshot({ path: fichier, type: 'jpeg', quality: QUALITE, clip: zone });
        produits.push(fichier);
        console.log('ok  ', nom);
    } catch (e) {
        rmSync(fichier, { force: true });
        echecs.push(`${nom} : ${e.message}`);
        console.error('ECHEC', nom, e.message);
    } finally {
        if (viewport) await page.setViewportSize(initial);
    }
}
const aller = async (page, chemin) => { await page.goto(`${BASE}${chemin}`); await calmer(page); };

async function etape(nom, fn) {
    if (seulement.length && !seulement.some((s) => s === nom || s === '*')) return;
    try { await fn(); } catch (e) { echecs.push(`${nom} : ${e.message}`); console.error('ECHEC', nom, e.message); }
}

// --- Scénario -------------------------------------------------------------------
async function scenario(browser) {
    mkdirSync(SORTIE, { recursive: true });

    // 1. Connexion (page vierge)
    {
        const ctx = await browser.newContext({ viewport: { width: 1280, height: 720 }, colorScheme: 'light', locale: 'fr-FR' });
        await ctx.addInitScript(() => { try { localStorage.setItem('theme', 'light'); } catch { /* ignoré */ } });
        const page = await ctx.newPage();
        await aller(page, '/login');
        await capture(page, '01-connexion');
        await ctx.close();
    }

    // 2. Conseil du Temple
    {
        const { ctx, page } = await connexion(browser, COMPTES.conseil, { hauteur: 900 });
        await etape('02-tableau-bord-conseil', async () => { await aller(page, '/dashboard'); await capture(page, '02-tableau-bord-conseil'); });
        await etape('03-servants-liste', async () => {
            await aller(page, '/servants');
            await page.getByRole('searchbox').or(page.locator('input[type=search], input[placeholder*="Rechercher"]')).first().fill('o');
            await calmer(page, 900);
            await page.locator('th button').filter({ hasText: /^\s*Statut/ }).first().click();
            await calmer(page, 900);
            await capture(page, '03-servants-liste');
        });
        await etape('04-servant-situation', async () => {
            await aller(page, '/servants?recherche=Mensah');
            await page.getByRole('link', { name: /Esther/ }).first().click();
            await calmer(page);
            await page.getByRole('button', { name: 'Situation' }).click();
            await calmer(page, 300);
            await capture(page, '04-servant-situation', 600);
        });
        await etape('05-recommandes', async () => { await aller(page, '/servants/nouveaux'); await capture(page, '05-recommandes'); });
        await etape('06-shifts-liste', async () => { await aller(page, '/shifts'); await capture(page, '06-shifts-liste'); });
        await etape('07-shift-fiche', async () => {
            await aller(page, '/shifts');
            await page.getByRole('link', { name: /Mardi Matin Frères/ }).first().click();
            await calmer(page);
            await capture(page, '07-shift-fiche', 900);
        });
        await etape('08-modele-shift', async () => {
            await aller(page, '/shift-templates');
            await page.getByRole('link', { name: /Temple Standard/ }).first().click();
            await calmer(page);
            await capture(page, '08-modele-shift', { viewport: 1280, hauteur: 1230 });
        });
        await etape('09-changement-liste', async () => { await aller(page, '/transferts'); await capture(page, '09-changement-liste'); });
        await etape('10-changement-detail', async () => {
            await aller(page, '/transferts');
            await page.getByRole('button', { name: /^(Traiter|Détails) : demande de David Mensah/ }).first().click();
            await page.waitForSelector('dialog[open]');
            await calmer(page, 600);
            await capture(page, '10-changement-detail', { clip: { x: 304, y: 24, width: 672, height: 396 } });
        });
        await etape('11-releves', async () => { await aller(page, '/transferts/releves'); await capture(page, '11-releves'); });
        await etape('12-recrutement', async () => { await aller(page, '/recrutement'); await capture(page, '12-recrutement'); });
        await etape('13-rapports', async () => { await aller(page, '/rapports'); await capture(page, '13-rapports'); });
        await etape('14-parametres', async () => { await aller(page, '/parametres'); await capture(page, '14-parametres'); });
        await etape('15-utilisateurs', async () => { await aller(page, '/parametres/utilisateurs'); await capture(page, '15-utilisateurs'); });
        await etape('16-utilisateurs-modification', async () => {
            await aller(page, '/parametres/utilisateurs');
            await page.getByRole('button', { name: /Modifier le compte de Hugo Démo-Suspendu/ }).first().click();
            await page.waitForSelector('dialog[open]');
            await calmer(page, 600);
            await capture(page, '16-utilisateurs-modification', 570);
        });
        await etape('18-pieux', async () => { await aller(page, '/parametres/pieux'); await capture(page, '18-pieux'); });
        await etape('19-horaires', async () => { await aller(page, '/parametres/horaires'); await capture(page, '19-horaires'); });
        await etape('20-parcours', async () => { await aller(page, '/parametres/parcours'); await capture(page, '20-parcours'); });
        await etape('21-journal', async () => { await aller(page, '/parametres/journal'); await capture(page, '21-journal'); });
        await etape('22-licence', async () => { await aller(page, '/parametres/licence'); await capture(page, '22-licence'); });
        await ctx.close();
    }

    // 3. Utilisateurs en cartes (téléphone)
    {
        const { ctx, page } = await connexion(browser, COMPTES.conseil, { largeur: 390, hauteur: 844 });
        await etape('26-utilisateurs-mobile', async () => { await aller(page, '/parametres/utilisateurs'); await page.evaluate(() => window.scrollTo(0, 560)); await capture(page, '26-utilisateurs-mobile'); });
        await ctx.close();
    }

    // 4. Super Administrateur : Rôles
    {
        const { ctx, page } = await connexion(browser, COMPTES.superAdmin);
        await etape('17-roles', async () => { await aller(page, '/parametres/roles'); await capture(page, '17-roles'); });
        await ctx.close();
    }

    // 5. Coordonnateur
    {
        const { ctx, page } = await connexion(browser, COMPTES.coordonnateur, { hauteur: 900 });
        await etape('23-tableau-bord-coordonnateur', async () => { await aller(page, '/dashboard'); await capture(page, '23-tableau-bord-coordonnateur'); });
        await ctx.close();
    }

    // 6. Secrétaire
    {
        const { ctx, page } = await connexion(browser, COMPTES.secretaire);
        await etape('24-servants-secretaire', async () => { await aller(page, '/servants'); await capture(page, '24-servants-secretaire'); });
        await ctx.close();
    }

    // 7. Autres (lecture seule)
    {
        const { ctx, page } = await connexion(browser, COMPTES.autres, { hauteur: 900 });
        await etape('25-autres-tableau-bord', async () => { await aller(page, '/dashboard'); await capture(page, '25-autres-tableau-bord'); });
        await ctx.close();
    }
}

const { proc, doc } = demarrerServeur();
let code = 0;
try {
    await attendreServeur();
    const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
    try { await scenario(browser); } finally { await browser.close(); }
} catch (e) {
    console.error(e);
    code = 1;
} finally {
    proc.kill();
    rmSync(doc, { recursive: true, force: true });
}
const poids = produits.reduce((s, f) => s + (existsSync(f) ? statSync(f).size : 0), 0);
console.log(`${produits.length} capture(s), ${(poids / 1024 / 1024).toFixed(2)} Mo`);
if (echecs.length) { console.error('Échecs :\n- ' + echecs.join('\n- ')); code = 1; }
process.exit(code);
