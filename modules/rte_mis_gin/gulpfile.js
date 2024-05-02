var gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const rename = require('gulp-rename');
const glob = require('glob');

gulp.task('scss', function () {
	return gulp.src('./scss/**/*.scss')
		.pipe(sass().on('error', sass.logError))
		.pipe(rename(function (path) {
			path.basename = path.basename.replace(/\.(scss|sass)$/, '');
		}))
		.pipe(gulp.dest('./css'));
});

gulp.task('watch', function () {
	gulp.watch('./scss/**/*.scss', gulp.series('scss'));
});

gulp.task('default', gulp.series('scss', 'watch'));
