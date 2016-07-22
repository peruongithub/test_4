'use strict';

var gulp = require('gulp');
var googlecdn = require('gulp-google-cdn');

gulp.task('googlecdn', function () {
    return gulp.src(['data/tpl/*.tpl.php'])
        .pipe(googlecdn(require('./bower.json')))
        .pipe(gulp.dest('dist'));
});
