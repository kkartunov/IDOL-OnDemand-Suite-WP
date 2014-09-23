var gulp = require('gulp'),
    less = require('gulp-less'),
    prefix = require('gulp-autoprefixer'),
    minCSS = require('gulp-minify-css'),
    uglify = require('gulp-uglify'),
    docco = require('gulp-docco'),
    zip = require('gulp-zip');

// Compile LESS.
gulp.task('less', function () {
    return gulp.src('src/OnDemandSuiteWP/Assets/less/*.less')
        .pipe(less())
        .pipe(prefix('last 2 versions'))
        .pipe(minCSS())
        .pipe(gulp.dest('src/OnDemandSuiteWP/Assets/less/dist/'));
});

// Build JS.
gulp.task('js', function () {
    return gulp.src('src/OnDemandSuiteWP/Assets/js/*.js')
        .pipe(uglify({
            mangle: false
        }))
        .pipe(gulp.dest('src/OnDemandSuiteWP/Assets/js/dist/'));
});

// Build JS source code annotations.
gulp.task('docs', function () {
    gulp.src('src/OnDemandSuiteWP/Assets/js/*.js')
        .pipe(docco())
        .pipe(gulp.dest('./docs/js/'));
});

// Make distribution.
gulp.task('dist', ['less', 'js'], function () {
    gulp.src([
        './src/**/*.*',
        './vendor/angular/angular.min.js',
        './vendor/angular/angular.min.js.map',
        './vendor/angular-animate/angular-animate.min.js',
        './vendor/angular-animate/angular-animate.min.js.map',
        './vendor/composer/**/*.*',
        './vendor/fontawesome/css/font-awesome.min.css',
        './vendor/fontawesome/fonts/**/*.*',
        './vendor/lodash/dist/lodash.compat.min.js',
        './vendor/ng-file-upload/angular-file-upload-shim.min.js',
        './vendor/ng-file-upload/angular-file-upload.min.js',
        './vendor/ngModal/dist/ng-modal.css',
        './vendor/ngModal/dist/ng-modal.min.js',
        './vendor/autoload.php',
        './LICENSE',
        './HP_IDOL_OnDemand_Suite_For_WP.php',
        './readme.txt'
    ], {
        base: './'
    })
        .pipe(zip('HP_IDOL_OnDemand_Suite_For_WP.zip'))
        .pipe(gulp.dest('./dist'));
});

// Dev mode.
gulp.task('dev', function () {
    // js
    gulp.watch('src/OnDemandSuiteWP/Assets/js/*.js', function (e) {
        gulp.src(e.path)
            .pipe(gulp.dest('src/OnDemandSuiteWP/Assets/js/dist/'));
    }).on('change', function (event) {
        console.log('Changed:' + event.path);
    });
    // less
    gulp.watch('src/OnDemandSuiteWP/Assets/less/*.less', function (e) {
        gulp.src(e.path)
            .pipe(less())
            .pipe(prefix('last 2 versions'))
            .pipe(gulp.dest('src/OnDemandSuiteWP/Assets/less/dist/'));
    }).on('change', function (event) {
        console.log('Changed:' + event.path);
    });
});
