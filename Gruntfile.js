/*global module:false*/
/*global require:false*/
/*jshint -W097*/
"use strict";

module.exports = function(grunt) {

    // load all grunt tasks
    require('matchdep').filterDev('grunt-*').forEach(grunt.loadNpmTasks);

    grunt.initConfig({

        pkg: grunt.file.readJSON('package.json'),

        makepot: {
            target: {
                options: {
                    domainPath: '/languages/',    // Where to save the POT file.
                    exclude: ['build/.*'],
                    mainFile: 'charitable-paystack.php',    // Main project file.
                    potFilename: 'charitable-paystack.pot',    // Name of the POT file.
                    potHeaders: {
                        poedit: true,                 // Includes common Poedit headers.
                        'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
                    },
                    type: 'wp-plugin',    // Type of project (wp-plugin or wp-theme).
                    updateTimestamp: true,    // Whether the POT-Creation-Date should be updated without other changes.
                    processPot: function( pot, options ) {
                        pot.headers['report-msgid-bugs-to'] = 'https://www.wpcharitable.com/';
                        pot.headers['last-translator'] = 'WP-Translations (http://wp-translations.org/)';
                        pot.headers['language-team'] = 'WP-Translations <wpt@wp-translations.org>';
                        pot.headers['language'] = 'en_US';
                        var translation, // Exclude meta data from pot.
                            excluded_meta = [
                                'Plugin Name of the plugin/theme',
                                'Plugin URI of the plugin/theme',
                                'Author of the plugin/theme',
                                'Author URI of the plugin/theme'
                            ];

                        for ( translation in pot.translations[''] ) {
                            if ( 'undefined' !== typeof pot.translations[''][ translation ].comments.extracted ) {
                                if ( excluded_meta.indexOf( pot.translations[''][ translation ].comments.extracted ) >= 0 ) {
                                    console.log( 'Excluded meta: ' + pot.translations[''][ translation ].comments.extracted );
                                        delete pot.translations[''][ translation ];
                                }
                            }
                        }

                        return pot;
                    }
                }
            }
        },

        // Clean up build directory
        clean: {
            main: ['build/<%= pkg.name %>']
        },

        // Copy the theme into the build directory
        copy: {
            main: {
                src:  [
                    '**',
                    '!bin/**',
                    '!composer.json',
                    '!composer.lock',
                    '!phpunit.xml',
                    '!node_modules/**',
                    '!build/**',
                    '!.git/**',
                    '!Gruntfile.js',
                    '!package.json',
                    '!.gitignore',
                    '!tests/**',
                    '!**/Gruntfile.js',
                    '!**/package.json',
                    '!**/README.md',
                    '!**/*~',
                    '!assets/css/scss/**',
                    '!assets/css/*.map'
                ],
                dest: 'build/<%= pkg.name %>/'
            }
        },

        // Compress build directory into <name>.zip and <name>-<version>.zip
        compress: {
            main: {
                options: {
                    mode: 'zip',
                    archive: './build/<%= pkg.name %>-<%= pkg.version %>.zip'
                },
                expand: true,
                cwd: 'build/<%= pkg.name %>/',
                src: ['**/*'],
                dest: '<%= pkg.name %>/'
            }
        },

    });

    // Classmap
    grunt.registerTask( 'classmap', 'Generate class to file array" task.', function() {
        var map = "<?php\nreturn [\n";

        //loop through all files in includes directory
        grunt.file.recurse("includes", function (abspath, rootdir, subdir, filename) {
            var classname = filename.replace('.php', '');
            var filepath = typeof subdir === 'undefined' ? filename : subdir + '/' + filename;

            if( filename.includes('interface') ) {
                classname = classname.replace('interface-', '') + '_Interface';
            } else if ( filename.includes('abstract') ) {
                classname = classname.replace('abstract-class-', '');
            } else if ( filename.includes('deprecated-class-' ) ) {
                classname = classname.replace('deprecated-class-', '');
            } else if ( filename.includes('class') ) {
                classname = classname.replace('class-', '');
            } else {
                classname = '';
            }

            // Ignore function files.
            if( classname != '' ) {

                if ( grunt.file.read(abspath).includes('/* CLASSMAP: IGNORE */') ) {
                    return;
                }

                // Replace the hyphens - with underderscores and capitalize.
                classname = classname.replace(/-/g, '_' ).replace(/^\w|\_[a-z]/g, function(letter) {
                    return letter.toUpperCase();
                });

                // A few gotchas.
                classname = classname.replace( '_Api', '_API' );
                classname = classname.replace( '_Db', '_DB' );
                classname = classname.replace( '_I18n', '_i18n' );

                map = map.concat( "\t'" + classname + "' => '" + filepath + "',\n" );
            }
        });

        map = map.concat( "];\n" );

        grunt.file.write('includes/autoloader/charitable-paystack-class-map.php', map );
    } );

    // Default task. - grunt watch
    grunt.registerTask( 'default', 'build' );

    // Build task(s).
	grunt.registerTask( 'build', [ 'classmap', 'makepot', 'clean', 'copy', 'compress' ] );
};