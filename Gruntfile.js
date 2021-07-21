module.exports = function(grunt) {
  grunt.initConfig({
    less: {
      development: {
        options: {
          compress: false,
          yuicompress: false,
          optimization: 2,
          sourceMap: true,
          sourceMapFilename: 'lib/css/main.css.map',
          sourceMapBasepath: 'lib/less',
          sourceMapURL: 'main.css.map'
        },
        files: {
          // target.css file: source.less file
          'lib/css/main.css': 'lib/less/main.less'
        }
      },
      production: {
        options: {
          compress: true,
          yuicompress: true,
          optimization: 2
        },
        files: {
          // target.css file: source.less file
          'lib/css/main.css': 'lib/less/main.less'
        }
      },
    },
    watch: {
      styles: {
        files: ['lib/less/**/*.less','js/**/*.js'], // which files to watch
        tasks: ['less:development'],
        options: {
          nospawn: true,
          livereload: true
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-contrib-less');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.registerTask('builddev', ['less:development']);
  grunt.registerTask('build', ['less:production']);
  grunt.registerTask('default', ['watch']);
};