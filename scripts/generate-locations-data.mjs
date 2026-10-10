/**
 * One-time (re-runnable) generator that vendors the country/state/city data
 * used by the public registration location fields.
 *
 * Sources data from the `country-state-city` package's assets and produces:
 *   - resources/js/data/countries.json          -> [{ code, name }] sorted by name
 *   - resources/js/data/states.json             -> { [countryCode]: [{ code, name }] }
 *   - resources/js/data/cities.json             -> { ["cc-sc"]: [name, ...] }
 *   - config/locations.php                      -> PHP arrays for server-side validation
 *
 * Run: node scripts/generate-locations-data.mjs
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));

function readAsset(name) {
    return JSON.parse(
        readFileSync(
            join(root, 'node_modules', 'country-state-city', 'lib', 'assets', `${name}.json`),
            'utf8'
        )
    );
}

function ensureDir(path) {
    mkdirSync(dirname(path), { recursive: true });
}

const countries = readAsset('country');
const states = readAsset('state');
const rawCities = readAsset('city'); // [name, countryCode, stateCode, lat, lng]

const countriesList = countries
    .map((c) => ({ code: c.isoCode, name: c.name }))
    .sort((a, b) => a.name.localeCompare(b.name));

const statesByCountry = {};
for (const s of states) {
    statesByCountry[s.countryCode] ??= [];
    statesByCountry[s.countryCode].push({ code: s.isoCode, name: s.name });
}
for (const code of Object.keys(statesByCountry)) {
    statesByCountry[code].sort((a, b) => a.name.localeCompare(b.name));
}

const citiesByCountryState = {};
for (const [name, cc, sc] of rawCities) {
    const key = `${cc}-${sc || ''}`;
    citiesByCountryState[key] ??= [];
    citiesByCountryState[key].push(name);
}
for (const key of Object.keys(citiesByCountryState)) {
    citiesByCountryState[key].sort((a, b) => a.localeCompare(b));
}

const jsDir = join(root, 'resources', 'js', 'data');
ensureDir(join(jsDir, 'countries.json'));
writeFileSync(join(jsDir, 'countries.json'), JSON.stringify(countriesList));
writeFileSync(join(jsDir, 'states.json'), JSON.stringify(statesByCountry));
writeFileSync(join(jsDir, 'cities.json'), JSON.stringify(citiesByCountryState));

const nameOf = new Map(countries.map((c) => [c.isoCode, c.name]));
const countriesNames = countriesList.map(({ name }) => name);

const statesByName = {};
for (const [cc, list] of Object.entries(statesByCountry)) {
    statesByName[nameOf.get(cc)] = list.map((s) => s.name);
}

const php = `<?php

return [
    'countries' => [
${countriesNames.map((n) => `        '${n.replace(/'/g, "\\'")}',`).join('\n')}
    ],
    'states' => [
${Object.entries(statesByName)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([name, list]) => {
        const inner = list.map((s) => `'${s.replace(/'/g, "\\'")}'`).join(', ');
        return `        '${name.replace(/'/g, "\\'")}' => [${inner}],`;
    })
    .join('\n')}
    ],
];
`;

writeFileSync(join(root, 'config', 'locations.php'), php);

const cityTotal = Object.values(citiesByCountryState).reduce((n, v) => n + v.length, 0);
console.log(
    JSON.stringify(
        {
            countries: countriesList.length,
            states: Object.values(statesByCountry).reduce((n, v) => n + v.length, 0),
            cityKeys: Object.keys(citiesByCountryState).length,
            cities: cityTotal,
        },
        null,
        2
    )
);