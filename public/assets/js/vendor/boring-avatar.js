/*
 * Beam avatar generator adapted for vanilla JavaScript from boring-avatars.
 * https://github.com/boringdesigners/boring-avatars
 *
 * MIT License
 * Copyright (c) 2021 boringdesigners
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

const SIZE = 36;
const DEFAULT_COLORS = ['#92A1C6', '#146A7C', '#F0AB3D', '#C271B4', '#C20D90'];

function hashCode(name) {
    let hash = 0;

    for (let index = 0; index < name.length; index++) {
        hash = ((hash << 5) - hash) + name.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
}

function getDigit(number, position) {
    return Math.floor((number / (10 ** position)) % 10);
}

function getUnit(number, range, position) {
    const value = number % range;

    if (position && getDigit(number, position) % 2 === 0) {
        return -value;
    }

    return value;
}

function getContrast(hexColor) {
    const color = hexColor.replace(/^#/, '');
    const red = Number.parseInt(color.slice(0, 2), 16);
    const green = Number.parseInt(color.slice(2, 4), 16);
    const blue = Number.parseInt(color.slice(4, 6), 16);
    const yiq = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return yiq >= 128 ? '#000000' : '#FFFFFF';
}

export function createBoringBeamAvatarSvg(name, colors = DEFAULT_COLORS) {
    const number = hashCode(name);
    const wrapperColor = colors[number % colors.length];
    const backgroundColor = colors[(number + 13) % colors.length];
    const preTranslateX = getUnit(number, 10, 1);
    const preTranslateY = getUnit(number, 10, 2);
    const wrapperTranslateX = preTranslateX < 5 ? preTranslateX + (SIZE / 9) : preTranslateX;
    const wrapperTranslateY = preTranslateY < 5 ? preTranslateY + (SIZE / 9) : preTranslateY;
    const wrapperRotate = getUnit(number, 360);
    const wrapperScale = 1 + (getUnit(number, SIZE / 12) / 10);
    const isMouthOpen = getDigit(number, 2) % 2 === 0;
    const isCircle = getDigit(number, 1) % 2 === 0;
    const eyeSpread = getUnit(number, 5);
    const mouthSpread = getUnit(number, 3);
    const faceRotate = getUnit(number, 10, 3);
    const faceTranslateX = wrapperTranslateX > (SIZE / 6)
        ? wrapperTranslateX / 2
        : getUnit(number, 8, 1);
    const faceTranslateY = wrapperTranslateY > (SIZE / 6)
        ? wrapperTranslateY / 2
        : getUnit(number, 7, 2);
    const faceColor = getContrast(wrapperColor);
    const mouth = isMouthOpen
        ? `<path d="M15 ${19 + mouthSpread} c2 1 4 1 6 0" stroke="${faceColor}" fill="none" stroke-linecap="round"/>`
        : `<path d="M13,${19 + mouthSpread} a1,0.75 0 0,0 10,0" fill="${faceColor}"/>`;

    return '<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">'
        + '<mask id="avatar-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="36" height="36">'
        + '<rect width="36" height="36" rx="72" fill="#FFFFFF"/></mask>'
        + `<g mask="url(#avatar-mask)"><rect width="36" height="36" fill="${backgroundColor}"/>`
        + `<rect width="36" height="36" transform="translate(${wrapperTranslateX} ${wrapperTranslateY}) rotate(${wrapperRotate} 18 18) scale(${wrapperScale})" fill="${wrapperColor}" rx="${isCircle ? SIZE : SIZE / 6}"/>`
        + `<g transform="translate(${faceTranslateX} ${faceTranslateY}) rotate(${faceRotate} 18 18)">${mouth}`
        + `<rect x="${14 - eyeSpread}" y="14" width="1.5" height="2" rx="1" fill="${faceColor}"/>`
        + `<rect x="${20 + eyeSpread}" y="14" width="1.5" height="2" rx="1" fill="${faceColor}"/>`
        + '</g></g></svg>';
}

export function createBoringBeamAvatarDataUrl(name, colors = DEFAULT_COLORS) {
    return 'data:image/svg+xml;charset=UTF-8,'
        + encodeURIComponent(createBoringBeamAvatarSvg(name, colors));
}
