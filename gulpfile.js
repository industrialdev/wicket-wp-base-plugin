const // Package Variables
  //dotenv = require('dotenv').config({ path: '.env' }),
  gulp = require('gulp'),
  sass = require('gulp-dart-sass'),
  sourcemaps = require('gulp-sourcemaps'),
  uglify = require('gulp-uglify'),
  autoprefixer = require('gulp-autoprefixer'),
  concat = require('gulp-concat'),
  rename = require('gulp-rename'),
  plumber = require('gulp-plumber'),
  notify = require('gulp-notify'),
  imagemin = require('gulp-imagemin'),
  wrap = require('gulp-wrap'),
  insert = require('gulp-insert'),
  gulpIgnore = require('gulp-ignore'),
  postcss = require('gulp-postcss'),
  tailwindcss = require('tailwindcss'),
  webpack = require('webpack-stream'),
  // Former Environment Variables
  srcPath = '.',
  assetPath = '/assets',
  componentsPath = '/includes/components',
  baseStyleName = 'wicket',
  tailwindStyleName = 'wicket-tailwind',
  alpineScriptName = 'wicket-alpine';

// Compiles both unminified and minified CSS files
function sassTask() {
  return gulp.src([
    srcPath + assetPath + '/css/' + baseStyleName + '.scss',
  ])
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'expanded',
      includePaths: ['node_modules'],
    }))
    .on('error', onError)
    .pipe(autoprefixer({
      browsers: ['last 100 versions'],
      cascade: false,
    }))
    .on('error', function (err) {
      console.log(err.message);
    })
    .pipe(sourcemaps.write('../../maps'))
    .pipe(gulp.dest(srcPath + assetPath + '/css/'));
}

function tailwindTask() {
  return gulp.src([
    srcPath + assetPath + '/css/' + tailwindStyleName + '.scss',
  ])
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'expanded',
      includePaths: ['node_modules'],
    }))
    .on('error', onError)
    .pipe(postcss([tailwindcss]))
    .pipe(autoprefixer({
      browsers: ['last 100 versions'],
      cascade: false,
    }))
    .on('error', function (err) {
      console.log(err.message);
    })
    .pipe(sourcemaps.write('../../maps'))
    .pipe(gulp.dest(srcPath + assetPath + '/css/'));
}

// Note: Updated this to minify the .css files that were just compiled from scss. Notify Coulter if this was targetting
// the original .scss files for any reason
function minSass() {
  return gulp.src([
    srcPath + assetPath + '/css/' + baseStyleName + '.css',
  ])
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'compressed',
    }))
    .on('error', onError)
    .pipe(autoprefixer({
      browsers: ['last 100 versions'],
      cascade: false,
    }))
    .on('error', function (err) {
      console.log(err.message);
    })
    .pipe(rename({ suffix: '.min' }))
    .pipe(sourcemaps.write('../../maps'))
    .pipe(gulp.dest(srcPath + assetPath + '/css/min'));
}

function minTailwindSass() {
  return gulp.src([
    srcPath + assetPath + '/css/' + tailwindStyleName + '.css',
  ])
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'compressed',
    }))
    .on('error', onError)
    .pipe(autoprefixer({
      browsers: ['last 100 versions'],
      cascade: false,
    }))
    .on('error', function (err) {
      console.log(err.message);
    })
    .pipe(rename({ suffix: '.min' }))
    .pipe(sourcemaps.write('../../maps'))
    .pipe(gulp.dest(srcPath + assetPath + '/css/min'));
}

// Compiles both unminified and minified JS files
function scriptsAlpineTask() {
  return gulp.src(srcPath + assetPath + '/js/' + 'alpine.js')
    .pipe(plumber())
    .pipe(webpack({}))
    .pipe(concat(alpineScriptName + '.js'))
    .pipe(insert.wrap('(function($){\n\n', '\n\n})(jQuery);'))
    .on('error', onError)
    .pipe(gulp.dest(srcPath + assetPath + '/js/mingul'));
}

// Targets the newly compiled script and minifies it
function minScripts() {
  return gulp.src(srcPath + assetPath + `/js/mingul/${alpineScriptName}.js`)
    .pipe(plumber())
    .pipe(concat(alpineScriptName + '.js'))
    //.pipe(insert.wrap('(function($){\n\n', '\n\n})(jQuery);'))
    .on('error', onError)
    .pipe(rename(alpineScriptName + '.min.js'))
    .pipe(uglify())
    .pipe(gulp.dest(srcPath + assetPath + '/js/min'));
}

// Watches files for changes and compiles on the fly
function watchTask() {
  let scssLocations = [
    srcPath + assetPath + '/css/' + '**/*.scss',
    //srcPath + '/**/*.js',
  ];
  let tailwindLocations = [
    srcPath + componentsPath + '/**/*.php',
  ];

  //gulp.watch(srcPath + assetPath + '/scripts/' + '*.js', gulp.series(scriptsTask, minScripts));
  gulp.watch(scssLocations, gulp.series(sassTask, minSass));
  gulp.watch(tailwindLocations, gulp.series(tailwindTask, minTailwindSass));
}

// error notifications
var onError = function (err) {
  notify({
    title: 'Gulp Task Error',
    message: 'Error: <%= error.message %>',
  }).write(err);

  this.emit('end');
};

module.exports = {
  sass: gulp.series(
    sassTask, minSass, tailwindTask, minTailwindSass,
  ),
  scripts: gulp.series(
    scriptsAlpineTask, minScripts,
  ),
  watch: gulp.series(watchTask),
  default: gulp.series(
    sassTask, minSass, tailwindTask, minTailwindSass, scriptsAlpineTask, minScripts, watchTask, // scriptsTask, minScripts, scriptsTaskAdmin, minScriptsAdmin, fontsTask,
  ),
  serve: gulp.parallel(
    watchTask,
  ),
  build: gulp.series(
    sassTask, minSass, tailwindTask, minTailwindSass, scriptsAlpineTask, minScripts,//scriptsTask, minScripts, scriptsTaskAdmin, minScriptsAdmin, imagesTask, iconsTask, fontsTask
  ),
};
