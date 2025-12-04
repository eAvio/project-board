let mix = require('laravel-mix')
let NovaExtension = require('laravel-nova-devtool')

mix.extend('nova', new NovaExtension())

mix
  .setPublicPath('dist')
  .js('resources/js/tool.js', 'js')
  .vue({ version: 3 })
  .postCss('resources/css/tool.css', 'css', [
    require('tailwindcss'),
    require('autoprefixer'),
  ])
  .nova('eavio/project-board')
  .version()
