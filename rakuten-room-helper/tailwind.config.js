/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        rakuten: {
          red: '#BF0000',
          dark: '#8B0000',
        }
      }
    },
  },
  plugins: [],
}
