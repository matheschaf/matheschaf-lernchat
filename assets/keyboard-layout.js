/**
 * Matheschaf – Tastatur-Layout (reine Daten, wartungsfreundlich).
 *
 * Neue Taste = eine Datenzeile ergänzen. Kurzform: Zeichenkette = Taste
 * (fügt das Zeichen als Text bzw. als LaTeX ein). Objekt:
 *   { l: Label, latex?, action?, wide?, small?, cls? }
 * Aktionen: 'shift' | 'backspace' | 'space' | 'enter'.
 * '#?' ist der MathLive-Platzhalter für die Cursor-Position.
 */
window.KB_LAYOUT = {
    text: [
        ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
        ['q', 'w', 'e', 'r', 't', 'z', 'u', 'i', 'o', 'p', 'ü'],
        ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'ö', 'ä'],
        [{ l: '⇧', action: 'shift', wide: true, cls: 'aktion' }, 'y', 'x', 'c', 'v', 'b', 'n', 'm', 'ß', { l: '⌫', action: 'backspace', wide: true, cls: 'aktion' }],
        ['-', '+', '=', '/', '*', '!', '?', '(', ')', '@'],
        [',', { l: '␣', action: 'space', wide: true }, '.', { l: '↵', action: 'enter', wide: true, cls: 'aktion' }]
    ],

    math: [
        ['7', '8', '9', { l: '÷', latex: '\\div' }, { l: '·', latex: '\\cdot' }, { l: '⌫', action: 'backspace', cls: 'aktion' }],
        ['4', '5', '6', '+', '-', { l: '±', latex: '\\pm' }],
        ['1', '2', '3', '=', '(', ')'],
        [{ l: '/', latex: '\\frac{#@}{#?}' }, 'a', 'b', 'p', 'q', { l: 'π', latex: '\\pi' }],
        ['x', 'y', '0', ',', '.', { l: 'xₙ', latex: '_{#?}' }],
        ['c', 'd', 'e', 'f', { l: '√', latex: '\\sqrt{#?}', cls: 'wurzel' }, { l: 'x²', latex: '^{2}' }]
    ],

    erweitert: [
        [{ l: 'sin', latex: '\\sin(#?)' }, { l: 'cos', latex: '\\cos(#?)' }, { l: 'tan', latex: '\\tan(#?)' },
         { l: 'sin⁻¹', latex: '\\sin^{-1}(#?)', small: true }, { l: 'cos⁻¹', latex: '\\cos^{-1}(#?)', small: true }, { l: 'tan⁻¹', latex: '\\tan^{-1}(#?)', small: true }],
        [{ l: 'α', latex: '\\alpha' }, { l: 'β', latex: '\\beta' }, { l: 'γ', latex: '\\gamma' }, { l: 'δ', latex: '\\delta' }, { l: 'ε', latex: '\\varepsilon' }, { l: 'θ', latex: '\\theta' }],
        [{ l: 'φ', latex: '\\varphi' }, { l: 'λ', latex: '\\lambda' }, { l: 'μ', latex: '\\mu' }, { l: 'ω', latex: '\\omega' }, { l: 'π', latex: '\\pi' }, { l: 'Δ', latex: '\\Delta' }],
        [{ l: 'x²', latex: '^{2}' }, { l: 'x³', latex: '^{3}' }, { l: 'xⁿ', latex: '^{n}' }, { l: '√', latex: '\\sqrt{#?}' }, { l: '∛', latex: '\\sqrt[3]{#?}' }, { l: 'ⁿ√', latex: '\\sqrt[n]{#?}' }],
        [{ l: '∞', latex: '\\infty' }, { l: '≤', latex: '\\leq' }, { l: '≥', latex: '\\geq' }, { l: '≠', latex: '\\neq' }, { l: '≈', latex: '\\approx' }, { l: '±', latex: '\\pm' }],
        [{ l: 'log', latex: '\\log' }, { l: 'ln', latex: '\\ln' }, { l: 'eˣ', latex: 'e^{x}' }, { l: 'Σ', latex: '\\sum' }, { l: '∫', latex: '\\int' }, { l: 'lim', latex: '\\lim' }],
        [{ l: '°', latex: '^{\\circ}' }, { l: 'σ', latex: '\\sigma' }, { l: 'σ²', latex: '\\sigma^2' }, { l: 'x̄', latex: '\\bar{x}' }, { l: '′', latex: '\\prime' }, { l: '″', latex: '\\prime\\prime' }]
    ],

    strip: ['+', { l: '−', latex: '-' }, { l: '·', latex: '\\cdot' }, ':', '=', { l: '⌫', action: 'backspace' }]
};
