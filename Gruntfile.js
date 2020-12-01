module.exports = function( grunt ) {

	'use strict';
	// Project configuration
	grunt.initConfig( {

		pkg:    grunt.file.readJSON( 'package.json' ),

		wp_readme_to_markdown: {
			options: {
				screenshot_url: 'https://s.w.org/plugins/{plugin}/{screenshot}.png',
			},
			your_target: {
				files: {
					'README.md': 'readme.txt'
				}
			},
		},

	} );

	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.registerTask( 'readme', ['wp_readme_to_markdown'] );

	grunt.util.linefeed = '\n';

};
