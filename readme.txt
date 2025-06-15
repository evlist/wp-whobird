=== whoBIRD observations ===
Contributors:      Eric van der Vlist
Tags:              block, birds, observations, gutenberg, taxonomy
Requires at least: 6.0
Tested up to:      6.8.1
Stable tag:        1.0.0
License:           GPL-3.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-3.0.html
License File:      LICENSES/GPL-3.0-or-later.txt

Display your whoBIRD observations in WordPress posts and pages using a custom Gutenberg block.

== Description ==

This plugin, whoBIRD observations, lets you easily showcase your birdwatching records from the whoBIRD mobile app. Insert the whoBIRD block anywhere in your site to display your taxonomy-based bird data, with seamless integration into the WordPress block editor.

- **Not so easy to setup and update daily**: the current versions of both the whoBIRD app and this plugin do **not** provide any automatic mechanism to upload whoBIRD app SQLite exports and sound recordings and you'll need to take care of these uploads.
- **Easy to use (after you've managed the setup and update)**: Just add the whoBIRD block.
- **Modern**: Built for the latest versions of WordPress and Gutenberg and can be used in templates.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-whobird` directory, or install the plugin through the WordPress plugins screen directly.
2. Run `npm install && npm run build` if you’re installing from source (GitHub).
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Upload the SQLite whoBIRD export and (optional) the sound .wav recordings.
5. Go to the plugin admin page to set the paths for the SQLite exports and (optional) the directory with the sound recordings.
6. Use the whoBIRD block from the editor to embed your observations.

== Frequently Asked Questions ==

= Do I need the whoBIRD mobile app? =
Yes, the plugin is designed to display data exported from the whoBIRD app.

= Does this plugin work with classic editor? =
Right now, no, this plugin is intended for the block editor (Gutenberg) only.

If there was a need, it would be easy, though, to implement a shortcode to display whoBIRD observations when using the classic editor, feel free to ask if you need it!

= How do I export and upload the whoBIRD SQLite database? =

In the whoBIRD app, click on the "eye" icom to get the list of observations and then on the save (disket) icon to export your observations. This will save the database in a zip file. You'll need to unzip this file and upload the BirdDatabase.db somewhere on your WordPress server.

= How do I upload the whoBIRD sound recordings (optional)  =

whoBIRD has an option to save .wav files into the Music/whoBIRD directory. If you find a way to upload these files on your WordPress server, visitors will be able to hear these recordings when they select a bird.

= How do I select the period to display? =

When inserting the block you can choose if you want to display the observation for the day, week or month of the current post or page publish date.

= Can I select arbitrary date ranges? =

Right now, no, if you need it feel free to ask.

= Can I insert this block in templates? =

Yes ! This is the way I use the plugin on my blog.


== Screenshots ==

1. Example of a bird observation block in the editor.
2. Example of observations displayed on the frontend.

== Changelog ==

= 1.0.0 =
* Initial release.

== Credits ==

This plugin was created by Eric van der Vlist.  
Special thanks to:
* [The whoBIRD app](https://github.com/woheller69/whoBIRD) for their data and inspiration.
* [GitHub Copilot](https://github.com/features/copilot) for AI-powered coding assistance.

== License ==

This plugin is licensed under the GPL-3.0-or-later. See `LICENSES/GPL-3.0-or-later.txt` for full license text.

