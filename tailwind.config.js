/** @type {import('tailwindcss').Config} */

const fs = require('fs')
//const themePath = process.env.SRC_PATH + process.env.THEME_PATH + '/'
const themePath = './'

const themeJson = fs.readFileSync(themePath + 'theme.json')
const theme = JSON.parse(themeJson)
const Colour = require('color') // Ref: https://github.com/Qix-/color

const generateColorVarieties = (color, name) => {
  const lowerLevels = {
    100: 0,
    '090': 0.05,
    '080': 0.1,
    '070': 0.15,
    '060': 0.2,
    '050': 0.25,
    '040': 0.3,
    '030': 0.35,
    '020': 0.4,
    '010': 0.45,
    '000': 0.5,
  }

  const upperLevels = {
    200: 0.5,
    190: 0.45,
    180: 0.4,
    170: 0.35,
    160: 0.3,
    150: 0.25,
    140: 0.2,
    130: 0.15,
    120: 0.1,
    110: 0.05,
  }

  const lightLevels = {
    '000': 0,
    '010': 0.05,
    '020': 0.1,
    '030': 0.15,
    '040': 0.2,
    '050': 0.25,
    '060': 0.3,
    '070': 0.35,
    '080': 0.4,
    '090': 0.45,
    100: 0.5,
    110: 0.55,
    120: 0.6,
    130: 0.65,
    140: 0.7,
    150: 0.75,
    160: 0.8,
    170: 0.85,
    180: 0.9,
    190: 0.95,
    200: 1,
  }

  let returnObject = {}

  // Handle separately if this is the "light" colour
  if (name === 'light') {
    // Generate the -000 through -200 varieties (darker varients)
    for (var varietyCode in lightLevels) {
      let percentageChange = lightLevels[varietyCode]
      let colourObject = Colour(color)
      let newColour = colourObject.darken(percentageChange)
      returnObject[varietyCode] = newColour.hex()
    }

    return returnObject
  }

  // Handle separately if this is the "white" colour
  if (name === 'white') {
    returnObject['DEFAULT'] = color
    return returnObject
  }

  // Handle separately if this is the "black" colour
  if (name === 'black') {
    returnObject['DEFAULT'] = color
    return returnObject
  }

  // Generate the -000 through -100 varieties (lighter varients)
  for (var varietyCode in lowerLevels) {
    let percentageChange = lowerLevels[varietyCode]
    let colourObject = Colour(color)
    let newColour = colourObject.lighten(percentageChange)
    returnObject[varietyCode] = newColour.hex()

    // Ensure no adjustments are done to the original color
    if (varietyCode == 100) {
      returnObject[varietyCode] = color
    }
  }

  // Generate the -110 through -200 varieties (darker varients)
  for (var varietyCode in upperLevels) {
    let percentageChange = upperLevels[varietyCode]
    let colourObject = Colour(color)
    let newColour = colourObject.darken(percentageChange)
    returnObject[varietyCode] = newColour.hex()
  }

  return returnObject
}

// Reformat colors from theme.json to fit Tailwind config format
// Credit: https://gist.github.com/alexstandiford/c4fbd990676a7511418f2e669c5be592
const colors = theme.settings.color.palette.reduce((accumulator, item) => {
  accumulator[item.slug] = generateColorVarieties(item.color, item.slug)
  return accumulator
}, {})

// Reformat font families
const fontFamily = theme.settings.typography.fontFamilies.reduce(
  (accumulator, item) => {
    // Make the main sans and serif fonts easier to reference in Tailwind
    if (item.slug == 'system-sans-serif') {
      accumulator['sans'] = item.fontFamily.split(',')
    } else if (item.slug == 'system-serif') {
      accumulator['serif'] = item.fontFamily.split(',')
    } else {
      accumulator[item.slug] = item.fontFamily.split(',')
    }

    return accumulator
  },
  {}
)

// Reformat font sizes
const fontSize = theme.settings.typography.fontSizes.reduce(
  (accumulator, item) => {
    accumulator[item.slug] = item.size
    return accumulator
  },
  {}
)

const customThemeJsonSettings = theme.settings.custom
const borderRadius = customThemeJsonSettings['border-radius']
const boxShadow = customThemeJsonSettings['box-shadow']
const layout = customThemeJsonSettings.layout
const letterSpacing = customThemeJsonSettings['letter-spacing']
const lineHeight = customThemeJsonSettings['line-height']

/**
 * Theme.json values not (yet) used:
 * - theme.settings.spacing
 * - theme.settings.layout
 * - theme.settings.blocks
 * - theme.settings.styles
 * Reference for mapping theme.json to Tailwind config:
 * https://tailwindcss.com/docs/font-size#customizing-your-theme
 */

module.exports = {
  content: [
    './includes/components/**/*.php',
  ],
  theme: {
    extend: {
      colors: colors,
      boxShadow: boxShadow,
      width: layout,
      lineHeight: lineHeight,
      aspectRatio: {
        '3/2': '3 / 2',
      },
    },
    container: {
      center: true,
    },
    fontFamily: fontFamily,
    fontSize: fontSize,
    borderRadius: borderRadius,
    letterSpacing: letterSpacing,
  },
  plugins: [require('@tailwindcss/container-queries')],
  corePlugins: {
    preflight: false,
  },
  safelist: [
    'lg:grid-cols-6',
    'lg:grid-cols-5',
    'lg:grid-cols-4',
    'lg:grid-cols-3',
    'lg:grid-cols-2',
    'aspect-[3/2]',
    {pattern: /(grid-cols|grid-rows)-./},
    {pattern: /(opacity)-./},
  ],
}

// Uncomment to see config that gets generated:
console.log(
  'Tailwind is using these settings combined from theme.json and manual overrides:'
)
console.log(module.exports)

/**
 * Example of the final generated theme config:
 * {
    extend: {
      colors: {
        primary: 'var(--bs-primary)',
        secondary: 'var(--bs-secondary)',
        success: 'var(--bs-success)',
        info: 'var(--bs-info)',
        warning: 'var(--bs-warning)',
        danger: 'var(--bs-danger)',
        light: 'var(--bs-light)',
        dark: 'var(--bs-dark)'
      },
      boxShadow: {
        '1': '0px 2px 8px rgba(33, 33, 33, 0.12)',
        '2': '0px 3px 10px rgba(33, 33, 33, 0.25)'
      },
      width: {
        content: 'var(--bs-container-md)',
        wide: 'var(--bs-container-xl)',
        sidebar: 'var(--bs-container-sm)',
        page: 'var(--bs-container-lg)',
        padding: 'var(--bs-gutter-x)',
        'block-gap': 'var(--bs-gutter-x)',
        'block-gap-large': '40px'
      },
      lineHeight: { tiny: 1.1, small: 1.2, medium: 1.4, normal: 1.6 }
    },
    container: { center: true },
    fontFamily: {
      'sans': [
        '-apple-system',
        'BlinkMacSystemFont',
        '"Segoe UI"',
        'Roboto',
        'Oxygen-Sans',
        'Ubuntu',
        'Cantarell',
        '"Helvetica Neue"',
        'sans-serif'
      ],
      'serif': [
        '-apple-system-ui-serif',
        'ui-serif',
        'Noto Serif',
        'Iowan Old Style',
        'Apple Garamond',
        'Baskerville',
        'Times New Roman',
        'Droid Serif',
        'Times',
        'Source Serif Pro',
        'serif',
        'Apple Color Emoji',
        'Segoe UI Emoji',
        'Segoe UI Symbol'
      ]
    },
    fontSize: {
      gargantuan: 'clamp(2.75rem, 5.2vw, 3.25rem)',
      colossal: 'clamp(2.5rem, 4.8vw, 3rem)',
      gigantic: 'clamp(2.125rem, 4.4vw, 2.75rem)',
      jumbo: 'clamp(2rem, 4vw, 2.5rem)',
      huge: 'clamp(1.875rem, 3.6vw, 2.25rem)',
      big: 'clamp(1.75rem, 3.2vw, 2rem)',
      'x-large': 'clamp(1.5rem, 2.8vw, 1.75rem)',
      large: '1.25rem',
      medium: '1.125rem',
      small: '1rem',
      tiny: '0.875rem',
      min: '0.75rem'
    },
    borderRadius: {
      base: 'var(--bs-border-radius)',
      small: 'var(--bs-border-radius-sm)',
      large: 'var(--bs-border-radius-lg)',
      xl: 'var(--bs-border-radius-xl)',
      xxl: 'var(--bs-border-radius-xxl)',
      pill: 'var(--bs-border-radius-pill)'
    },
    borderWidth: { base: 'var(--bs-border-width)', medium: '2px', large: '3px' },
    letterSpacing: { none: 'normal', tight: '-.01em', loose: '.05em', looser: '.1em' }
  }
 */
