# Most read posts

_A developer-friendly plugin to count post reads and list most read content_

Plugin adds small _(332 bytes)_ javascript file, which calls the counter, to every singular post and introduces two new [functions](#functions) to get most read posts. Once plugin is activated, it starts to count the times post has been read.

Only all time most read posts will be counted by default and reads made by logged-in user or more than once per hour by same user in same post will be ignored. These defaults can be changed using [filters](#filters).

Handcrafted with love at [Digitoimisto Dude Oy](https://www.dude.fi), a Finnish boutique digital agency in the center of Jyväskylä.

## Table of contents

1. [Please note before using](#please-note-before-using)
2. [License](#license)
3. [Usage](#usage)
   1. [Parameters](#parameters)
4. [Filters](#filters)
5. [Composer](#composer)
6. [Contributing](#contributing)

### Please note before using

This plugin is not meant to be "plugin for everyone", it needs at least some basic knowledge about php. Plugin is in development for now, so it may update very often and things might change.

### License

Most read posts is released under the GNU GPL 2 or later.

### Usage

This plugin does not have settings page or provide anything visible on front-end. Settings can be changed with [filters](#filters) listed below.

**Plugin introduces two new [functions](#functions) to get most read posts.**

`get_most_popular_posts( $period, $query_args, $custom_start, $custom_end )` returns default WP_Query object containing five posts, and can be used same way as normal `new WP_Query` excluding [parameters](#parameters). Behaviour can be altered with [parameters](#parameters).

`get_most_popular_posts_ids( $period, $only_ids, $custom_start, $custom_end )` returns array containing ids of five posts, behaviour can be altered with [parameters](#parameters).

#### Parameters

Both functions accepts three basic parameters.

`$period` _(string) (optional)_ Which period of most read posts to receive. Possible values are year, month, week and custom. Default value is null, which equals to all-time. If period is custom, provide start and end date to period.

`$custom_start` _(string) (optional)_ If period is custom, tell from where to start counting.

`$custom_end` _(string) (optional)_ If period is custom, tell from where to end counting.

`$query_args` _(array) (optional)_ Can be passed as a second arg for `get_most_popular_posts` function. Array is merged to arguments set by plugin and forwarded to WP_Query. With this you can set post type or result count with `post_per_page` for example. Default value is empty array.

`$only_ids` _(boolean) (optional)_ Can be passed as a second arg for `get_most_popular_posts_ids` function. Tell if you want to get the read counts also. If true post ids will be alues, if false post ids will be keys and read count is value for those. Default value is true.

### Filters

Plugin functionality can be changed with hooks.

`dmrp_dont_count_logged_in_users` when set to false, count also reads made by logged-in users. It's recommended to use [`__return_false`](https://codex.wordpress.org/Function_Reference/_return_false).

`dmrp_count_for_post_types` by default reads are counted only for posts. Change it by passing array of wanted post types for this filter.

`dmrp_cookie_timeout` by default reads more than once per hour by same user in same post will be ignored. Change the time by passing wanted cookie timeout in milliseconds or disable this functionality by passing `0`.

### Composer

To use this plugin with composer, run command `composer require digitoimistodude/dude-most-read-posts` in your project directory or add `"digitoimistodude/dude-most-read-posts":"dev-master"` to your composer.json require.

### Contributing

If you have ideas about the plugin or spot an issue, please let us know. Before contributing ideas or reporting an issue about "missing" features or things regarding to the nature of that matter, please read [Please note section](#please-note-before-using). Thank you very much.
