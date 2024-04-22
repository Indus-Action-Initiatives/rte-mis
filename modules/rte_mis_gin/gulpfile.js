var gulp = require('gulp');
 const sass = require('gulp-sass')(require('sass')); 
// var uglifycss = require('gulp-uglifycss');
gulp.task('scss', function(){ 
	return gulp.src('./scss/*.scss') 
.pipe(sass().on('error', sass.logError)) .pipe(gulp.dest('./css')); 
	});
gulp.task('css', function(){ 
	gulp.src('./css/*.css') 
.pipe(uglifycss({ "uglyComments": true })) .pipe(gulp.dest('./dist')); 
	});
	 
gulp.task('run', gulp.series('scss', 'css'));
gulp.task('watch', function(){ 
	gulp.watch('./scss/.scss', ['scss']); 
	gulp.watch('./css/.css', ['css']); 
});
gulp.task('default', gulp.series('run', 'watch'));
