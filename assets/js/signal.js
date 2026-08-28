/*
 * Signature graphique « l'onde qui se régularise ».
 *
 * disorder = 1 : signal erratique (ouverture de page).
 * disorder = 0 : rythme parfaitement régulier (CTA final).
 *
 * Le bruit est tiré d'un générateur pseudo-aléatoire déterministe
 * (mulberry32) : à graine égale, le tracé est toujours identique, donc
 * stable entre deux rendus et entre deux visiteurs.
 */

const WIDTH = 1440;
const HEIGHT = 48;
const POINTS = 110;

function mulberry32(a) {
    return function () {
        a |= 0;
        a = (a + 0x6D2B79F5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * Construit l'attribut `d` du tracé, dans le repère 0 0 1440 48.
 *
 * @param {number} disorder Entre 0 (régulier) et 1 (erratique).
 * @param {number} seed     Graine du bruit.
 * @returns {string}
 */
export function signalPath(disorder = 1, seed = 7) {
    const mid = HEIGHT / 2;
    const random = mulberry32(seed * 97 + 13);
    const points = [];

    for (let i = 0; i <= POINTS; i++) {
        const x = (i / POINTS) * WIDTH;
        const regular = Math.sin((i / POINTS) * Math.PI * 2 * 7) * 7;
        const noise = ((random() * 2 - 1) * 16 + Math.sin(i * 2.63) * 7) * disorder;
        const y = mid + regular * (1 - 0.35 * disorder) + noise;

        points.push(`${x.toFixed(1)},${y.toFixed(1)}`);
    }

    return `M${points.join(' L')}`;
}

export const SIGNAL_VIEWBOX = `0 0 ${WIDTH} ${HEIGHT}`;
