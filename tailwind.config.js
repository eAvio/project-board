function generateColor(name) {
  return ({ opacityValue }) => {
    if (opacityValue === undefined) {
      return `rgba(var(--colors-${name}))`
    }
    return `rgba(var(--colors-${name}), ${opacityValue})`
  }
}

module.exports = {
  important: '.project-board-wrapper',
  // prefix: 'pb-', // Removed to use standard Nova classes
  corePlugins: {
    preflight: false, 
  },
  content: [
    './resources/**/*.vue',
    './resources/**/*.js',
  ],
  darkMode: 'class',
  theme: {
    extend: {
        colors: {
            primary: {
                50: 'rgba(var(--colors-primary-50), <alpha-value>)',
                100: 'rgba(var(--colors-primary-100), <alpha-value>)',
                200: 'rgba(var(--colors-primary-200), <alpha-value>)',
                300: 'rgba(var(--colors-primary-300), <alpha-value>)',
                400: 'rgba(var(--colors-primary-400), <alpha-value>)',
                500: 'rgba(var(--colors-primary-500), <alpha-value>)',
                600: 'rgba(var(--colors-primary-600), <alpha-value>)',
                700: 'rgba(var(--colors-primary-700), <alpha-value>)',
                800: 'rgba(var(--colors-primary-800), <alpha-value>)',
                900: 'rgba(var(--colors-primary-900), <alpha-value>)',
            },
            gray: {
                50: 'rgba(var(--colors-gray-50), <alpha-value>)',
                100: 'rgba(var(--colors-gray-100), <alpha-value>)',
                200: 'rgba(var(--colors-gray-200), <alpha-value>)',
                300: 'rgba(var(--colors-gray-300), <alpha-value>)',
                400: 'rgba(var(--colors-gray-400), <alpha-value>)',
                500: 'rgba(var(--colors-gray-500), <alpha-value>)',
                600: 'rgba(var(--colors-gray-600), <alpha-value>)',
                700: 'rgba(var(--colors-gray-700), <alpha-value>)',
                800: 'rgba(var(--colors-gray-800), <alpha-value>)',
                900: 'rgba(var(--colors-gray-900), <alpha-value>)',
            }
        }
    },
  },
  plugins: [
     require('@tailwindcss/typography'),
     require('@tailwindcss/forms')({
       strategy: 'class',
     }),
  ],
}
